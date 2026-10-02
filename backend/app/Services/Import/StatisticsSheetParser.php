<?php

namespace App\Services\Import;

use App\Models\Period;
use App\Support\ArabicName;
use App\Support\XlsxReader;

/**
 * Reads one statistics workbook into neutral source rows (no database access).
 *
 * Layouts handled (all found in data/raw):
 *  - monthly sheets ("<project> - Jan", "… - أيلول", "… - 1") with merged office/Level cells and a
 *    final «الإجمالي» row, optionally a flat «Total» sheet;
 *  - a single sheet with a «الشهر» column.
 *
 * Monthly/single sheets are the record source. The «Total» sheet is a flattened copy: it is used
 * only to cross-check rows and to recover a classification label that a merge failed to cover,
 * never as extra records (it also writes empty months as zeros).
 */
class StatisticsSheetParser
{
    /** Normalized header (spaces removed) => field. */
    private const HEADERS = [
        'المكتب' => 'office', 'level1' => 'l1', 'level2' => 'l2', 'level3' => 'l3',
        'عددالشعب' => 'sections', 'رقمالدوره' => 'course',
        'ذكور-18' => 'mu18', 'ذكور018' => 'mu18', 'اناث-18' => 'fu18', 'اناث018' => 'fu18',
        'ذكور+18' => 'm18p', 'اناث+18' => 'f18p',
        'عددالذكور' => 'male', 'عددالاناث' => 'female', 'عددذويالاحتياجاتالخاصه' => 'disabled',
        'العددالكامل' => 'total', 'الشهر' => 'month', 'رقمالشهر' => 'month_no', 'اسمالمشروع' => 'project',
        'السنه' => 'year', 'الاختصاص' => 'specialty', 'الجامعه/المعهد' => 'university',
        'عددالاضاحي' => 'sacrifices', 'عددالعوايل' => 'families',
    ];

    public const NUMERIC = ['sections', 'mu18', 'fu18', 'm18p', 'f18p', 'male', 'female', 'disabled', 'total', 'sacrifices', 'families'];

    private const TEXT = ['office', 'l1', 'l2', 'l3', 'course', 'month', 'month_no', 'project', 'year', 'specialty', 'university'];

    private const EN_MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** @var list<array{severity: string, code: string, message: string, sheet: ?string, row: ?int, payload: array}> */
    private array $issues = [];

    /**
     * @return array{rows: list<array>, issues: list<array>, sheets: list<string>, summaries: list<array>}
     */
    public function parse(string $path, int $year): array
    {
        $this->issues = [];
        $reader = new XlsxReader($path);
        $rows = [];
        $summaries = [];
        $totalRows = [];
        $lastMonth = 0;
        $wrapped = false;

        foreach ($reader->sheetNames() as $sheet) {
            $data = $reader->sheet($sheet);
            $header = $this->headers($data['cells'][1] ?? [], $sheet);

            if (ArabicName::normalize($sheet) === 'total') {
                $totalRows = $this->readRows($data, $header, $sheet, flat: true);

                continue;
            }

            $sheetMonth = in_array('month', $header, true) ? null : $this->monthFromSheetName($sheet);
            if ($sheetMonth === null && ! in_array('month', $header, true)) {
                $withValues = array_filter($this->readRows($data, $header, $sheet, flat: false), fn ($r) => isset($r['values']) && ($r['values']['total']['value'] ?? 0) > 0);
                $sum = array_sum(array_map(fn ($r) => $r['values']['total']['value'], $withValues));
                $this->issue('excluded', 'sheet_without_month', "الورقة «{$sheet}» بلا شهر في اسمها ولا عمود «الشهر» ولا تاريخ (".count($withValues)." صفًا بقيم، مجموع «العدد الكامل» {$sum})؛ الفترة غير محسومة فلم تُستورد.", $sheet, null, ['rows' => count($withValues), 'total' => $sum]);

                continue;
            }

            // Calendar wrap inside one workbook (e.g. كانون الثاني after كانون الأول): the year is not
            // stated by the sheet itself, so it is resolved later against the Total sheet.
            $sheetWrapped = false;
            if ($sheetMonth !== null) {
                if ($sheetMonth < $lastMonth) {
                    $wrapped = true;
                }
                $sheetWrapped = $wrapped;
                $lastMonth = max($lastMonth, $sheetMonth);
            }

            foreach ($this->readRows($data, $header, $sheet, flat: false) as $row) {
                if (isset($row['summary_total'])) {
                    $summaries[] = $row;

                    continue;
                }
                $row['month'] = $sheetMonth ?? $this->monthOf($row['text']['month']);
                if ($sheetMonth !== null && $row['text']['month'] !== null && $this->monthOf($row['text']['month']) !== $sheetMonth) {
                    $this->issue('info', 'month_column_differs', 'الشهر في الصف يخالف اسم الورقة؛ اعتُمد عمود «الشهر».', $sheet, $row['row']);
                    $row['month'] = $this->monthOf($row['text']['month']);
                }
                $row['year'] = $year;
                $row['year_unresolved'] = $sheetWrapped;
                $row['from_monthly_sheet'] = $sheetMonth !== null;
                $rows[] = $row;
            }
        }

        $this->pairWithTotal($rows, $totalRows, $summaries);
        $this->checkSummaries($rows, $summaries);

        return ['rows' => $rows, 'issues' => $this->issues, 'sheets' => $reader->sheetNames(), 'summaries' => $summaries, 'total_rows' => count($totalRows)];
    }

    /** @return array<string, string> column => field */
    private function headers(array $cells, string $sheet): array
    {
        $map = [];
        foreach ($cells as $column => $cell) {
            $text = trim((string) ($cell['v'] ?? ''));
            if ($text === '') {
                continue;
            }
            $key = str_replace(' ', '', ArabicName::normalize($text));
            if (isset(self::HEADERS[$key])) {
                $map[$column] = self::HEADERS[$key];
            } else {
                $map[$column] = '?'.$text;
            }
        }

        // A garbled first header ("5555…") where the layout expects «المكتب».
        if (isset($map['A']) && str_starts_with($map['A'], '?') && ! in_array('office', $map, true) && ($map['B'] ?? null) === 'l1') {
            $this->issue('resolved', 'garbled_header', 'عنوان العمود A غير مقروء («'.mb_substr(substr($map['A'], 1), 0, 12).'…»)؛ عومل كعمود «المكتب» كما في بقية أوراق الملف.', $sheet, 1);
            $map['A'] = 'office';
        }

        return array_filter($map, fn ($field) => ! str_starts_with($field, '?'));
    }

    /** @return list<array> */
    private function readRows(array $data, array $header, string $sheet, bool $flat): array
    {
        $columns = array_flip($header);
        $mergeTop = $this->mergeMap($data['merges']);
        $out = [];

        foreach ($data['cells'] as $number => $cells) {
            if ($number === 1) {
                continue;
            }

            $text = [];
            foreach (self::TEXT as $field) {
                $column = $columns[$field] ?? null;
                $value = $column ? $this->string($cells[$column]['v'] ?? null) : null;
                // Merged cells: the value lives in the top-left cell of the same merged range only.
                if ($value === null && $column && isset($mergeTop["{$column}{$number}"])) {
                    [$topColumn, $topRow] = $mergeTop["{$column}{$number}"];
                    $value = $this->string($data['cells'][$topRow][$topColumn]['v'] ?? null);
                }
                $text[$field] = $value;
            }

            $values = [];
            $hasAny = false;
            foreach (self::NUMERIC as $field) {
                $column = $columns[$field] ?? null;
                $cell = $column ? ($cells[$column] ?? null) : null;
                $values[$field] = $this->numeric($cell);
                $hasAny = $hasAny || $values[$field]['value'] !== null;
            }

            $office = $text['office'] !== null ? ArabicName::normalize($text['office']) : '';
            // A sheet total row: labelled «الإجمالي», or (label lost) only a column-range SUM in «العدد الكامل».
            $rangeSum = $values['total']['formula'] && preg_match('/^SUM\([A-Z]+\d+:[A-Z]+\d+\)$/i', (string) $values['total']['f'])
                && $text['l1'] === null && $text['l2'] === null && $text['l3'] === null;
            if (in_array($office, ['الاجمالي', 'المجموع', 'الاجمالي الكلي'], true) || ($office === '' && $rangeSum)) {
                $out[] = ['summary_total' => $values['total']['value'], 'sheet' => $sheet, 'row' => $number];

                continue;
            }
            if (! $hasAny && array_filter($text) === []) {
                continue;
            }

            $out[] = ['sheet' => $sheet, 'row' => $number, 'text' => $text, 'values' => $values, 'flat' => $flat];
        }

        return $out;
    }

    /**
     * @return array{value: ?float, typed: bool, formula: bool, f: ?string, raw: ?string}
     *                                                                                    typed = entered by a person (not a formula); blank and "-" are missing, not zero.
     */
    private function numeric(?array $cell): array
    {
        $raw = $cell['v'] ?? null;
        $formula = (bool) ($cell['formula'] ?? false);
        $trimmed = $raw === null ? '' : trim($raw);
        if ($trimmed === '' || in_array($trimmed, ['-', '—', '–'], true) || ! is_numeric($trimmed)) {
            return ['value' => null, 'typed' => false, 'formula' => $formula, 'f' => $cell['f'] ?? null, 'raw' => $raw];
        }

        return ['value' => (float) $trimmed, 'typed' => ! $formula, 'formula' => $formula, 'f' => $cell['f'] ?? null, 'raw' => $raw];
    }

    /**
     * Pairs monthly rows with the Total sheet row at the same position of the same month.
     * Fills a classification label missing from a merge only when every figure matches.
     */
    private function pairWithTotal(array &$rows, array $totalRows, array $summaries): void
    {
        if ($totalRows === []) {
            return;
        }

        // Sheet totals per month, to spot a «الإجمالي» row that was copied into Total as data.
        $summaryByMonth = [];
        foreach ($summaries as $summary) {
            $month = $this->monthFromSheetName($summary['sheet']);
            if ($month !== null && $summary['summary_total'] !== null) {
                $summaryByMonth[$month][] = $summary['summary_total'];
            }
        }

        $byMonth = [];
        foreach ($totalRows as $t) {
            $month = $this->monthOf($t['text']['month']) ?? ($t['text']['month_no'] !== null ? (int) (float) $t['text']['month_no'] : null);
            if ($month !== null) {
                $byMonth[$month][] = $t;
            }
        }
        // The last row of a month block that carries only the sheet's «الإجمالي» figure is that
        // summary row copied into Total, not a record.
        foreach ($byMonth as $month => $list) {
            $last = end($list);
            $onlyTotal = array_filter(['mu18', 'fu18', 'm18p', 'f18p', 'male', 'female', 'disabled'], fn ($f) => $last['values'][$f]['value'] !== null) === [];
            $value = $last['values']['total']['value'];
            if ($onlyTotal && $value > 0 && in_array($value, $summaryByMonth[$month] ?? [], true)) {
                array_pop($byMonth[$month]);
                $this->issue('info', 'total_sheet_has_summary_row', "ورقة Total تحتوي صف «الإجمالي» لشهر {$month} ({$value}) كأنه سجل؛ تُجوهل في المطابقة ولا يُستورد.", 'Total', $last['row']);
            }
        }

        $monthly = [];
        foreach ($rows as $i => $row) {
            if ($row['from_monthly_sheet']) {
                $monthly[$row['month']][] = $i;
            }
        }

        $differences = 0;
        foreach ($monthly as $month => $indexes) {
            $totals = $byMonth[$month] ?? [];
            if (count($totals) !== count($indexes)) {
                $this->issue('info', 'total_sheet_row_count', "شهر {$month}: عدد صفوف Total (".count($totals).') يختلف عن الورقة الشهرية ('.count($indexes).')؛ لم تُستخدم للمطابقة.', null);

                continue;
            }

            foreach ($indexes as $position => $i) {
                $t = $totals[$position];
                $row = &$rows[$i];
                $row['total_year'] = $t['text']['year'] !== null ? (int) (float) $t['text']['year'] : null;
                $same = $this->sameFigures($row['values'], $t['values']);

                foreach (['office', 'l1', 'l2', 'l3'] as $field) {
                    if ($row['text'][$field] === null && $t['text'][$field] !== null) {
                        if ($same) {
                            $row['text'][$field] = $t['text'][$field];
                            $this->issue('resolved', 'label_from_total', "خلية «{$field}» فارغة خارج الدمج؛ أُخذت القيمة «{$t['text'][$field]}» من الصف المقابل في ورقة Total (الأرقام متطابقة).", $row['sheet'], $row['row']);
                        }
                    }
                }
                if (! $same) {
                    $differences++;
                }
                unset($row);
            }
        }

        if ($differences > 0) {
            $this->issue('info', 'total_sheet_differs', "{$differences} صفًا تختلف أرقامها بين الورقة الشهرية وورقة Total؛ اعتُمدت الورقة الشهرية.", null);
        }
    }

    private function sameFigures(array $a, array $b): bool
    {
        foreach (['mu18', 'fu18', 'm18p', 'f18p', 'male', 'female', 'disabled', 'total'] as $field) {
            if (abs(($a[$field]['value'] ?? 0) - ($b[$field]['value'] ?? 0)) > 1e-9) {
                return false;
            }
        }

        return true;
    }

    /** Compares the «الإجمالي» cell of each monthly sheet with the sum of its rows (as stored). */
    private function checkSummaries(array $rows, array $summaries): void
    {
        foreach ($summaries as $summary) {
            if ($summary['summary_total'] === null) {
                continue;
            }
            $sum = 0.0;
            foreach ($rows as $row) {
                if ($row['sheet'] === $summary['sheet']) {
                    $sum += $row['values']['total']['value'] ?? 0;
                }
            }
            if (abs($sum - $summary['summary_total']) > 1e-9) {
                $this->issue('info', 'summary_row_differs', "صف «الإجمالي» ({$summary['summary_total']}) لا يساوي مجموع العمود ({$sum}) في الورقة.", $summary['sheet'], $summary['row']);
            }
        }
    }

    /** @return array<string, array{0: string, 1: int}> "C12" => ["C", 10] */
    private function mergeMap(array $merges): array
    {
        $map = [];
        foreach ($merges as $range) {
            if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $m)) {
                continue;
            }
            for ($r = (int) $m[2]; $r <= (int) $m[4]; $r++) {
                for ($c = XlsxReader::columnIndex($m[1]); $c <= XlsxReader::columnIndex($m[3]); $c++) {
                    $map[XlsxReader::columnLetter($c).$r] = [$m[1], (int) $m[2]];
                }
            }
        }

        return $map;
    }

    public function monthFromSheetName(string $sheet): ?int
    {
        $name = ArabicName::normalize($sheet);
        if (preg_match('/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s*$/', $name, $m)) {
            return self::EN_MONTHS[$m[1]];
        }
        $months = $this->arabicMonths();
        uksort($months, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($months as $label => $number) {
            if (str_ends_with($name, $label)) {
                return $number;
            }
        }
        if (preg_match('/-\s*(\d{1,2})\s*$/', $name, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return (int) $m[1];
        }

        return null;
    }

    public function monthOf(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        return $this->arabicMonths()[ArabicName::normalize($text)] ?? null;
    }

    /** @return array<string, int> normalized Levantine month name => month number */
    private function arabicMonths(): array
    {
        $out = [];
        foreach (Period::MONTHS_AR as $number => $label) {
            $out[ArabicName::normalize($label)] = $number;
        }
        // A sheet name truncated to Excel's 31 characters ("… تشرين الثان").
        $out['تشرين الثان'] = 11;

        return $out;
    }

    private function string(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value === '' ? null : $value;
    }

    private function issue(string $severity, string $code, string $message, ?string $sheet, ?int $row = null, array $payload = []): void
    {
        $this->issues[] = compact('severity', 'code', 'message', 'sheet', 'row', 'payload');
    }
}
