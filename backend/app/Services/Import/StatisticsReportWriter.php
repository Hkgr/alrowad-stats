<?php

namespace App\Services\Import;

/** Renders the statistics import report as Markdown (kept in storage/app/reports, outside Git). */
class StatisticsReportWriter
{
    private const STATUS = [
        'imported' => 'مستورد', 'skipped' => 'مستثنى', 'unmapped' => 'غير مدرج', 'duplicate' => 'مكرر',
        'excluded' => 'معزول', 'failed' => 'فشل', 'missing' => 'غير موجود',
    ];

    private const SEVERITY = [
        'excluded' => 'لم يُستورد (معزول)', 'conflict' => 'استُورد مع حجب حقل', 'resolved' => 'حُسم وفق قاعدة موثقة', 'info' => 'ملاحظة',
    ];

    public function markdown(array $r, array $extra = []): string
    {
        $l = [
            '# تقرير استيراد الإحصاءات',
            '',
            "- التشغيل: #{$r['run_id']} ".($r['dry_run'] ? '(تجريبي — لم يُحفظ شيء)' : '(فعلي)'),
            "- المجلد: `{$r['root']}`",
            "- البدء: {$r['started_at']} · الانتهاء: ".($r['finished_at'] ?? '—'),
            '- المصدر الوحيد للأرقام: ملفات Excel في data/raw. التصنيف على المسارات من قائمة المشاريع فقط.',
            '',
            '## الملفات',
            '',
            '| الملف | الحالة | المشروع | السنة | أوراق | صفوف مقروءة | فارغة | معزولة | جديد | محدَّث | دون تغيير | أُعيد تفعيله | عُطّل | الإجمالي المستورد | الفترات |',
            '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|',
        ];
        foreach ($r['files'] as $file => $f) {
            $periods = $f['periods'] ?? [];
            $range = $periods ? (count($periods) === 1 ? $periods[0] : $periods[0].' → '.end($periods).' ('.count($periods).')') : '—';
            $l[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                $file, self::STATUS[$f['status']] ?? $f['status'], $f['project'] ?? '—', $f['year'] ?? '—', $f['sheets'] ?? '—',
                $f['rows_read'] ?? '—', $f['missing'] ?? '—', $f['excluded'] ?? '—', $f['created'] ?? '—', $f['updated'] ?? '—',
                $f['unchanged'] ?? '—', $f['reactivated'] ?? '—', $f['deactivated'] ?? '—',
                isset($f['total']) ? number_format($f['total']).(($f['measure'] ?? '') === 'households_served' ? ' أسرة' : '') : '—', $range,
            );
        }
        $l[] = '';
        $l[] = "سجلات العينة السابقة التي استُبدلت بمصدرها الحقيقي: {$r['superseded']}.";

        foreach ($extra as $title => $lines) {
            $l = [...$l, '', "## {$title}", '', ...$lines];
        }

        $l = [...$l, '', '## مقارنة الإجماليات قبل الاستيراد وبعده (السجلات الفعالة)', '', '| المشروع | قبل: استفادات | بعد: استفادات | قبل: أسر | بعد: أسر | سجلات بعد |', '|---|---|---|---|---|---|'];
        $projects = array_unique([...array_keys($r['before']), ...array_keys($r['after'] ?? [])]);
        sort($projects);
        foreach ($projects as $p) {
            $b = $r['before'][$p] ?? ['persons' => 0, 'households' => 0, 'records' => 0];
            $a = $r['after'][$p] ?? ['persons' => 0, 'households' => 0, 'records' => 0];
            $l[] = sprintf('| %s | %s | %s | %s | %s | %s |', $p, number_format($b['persons']), number_format($a['persons']), number_format($b['households']), number_format($a['households']), number_format($a['records']));
        }

        $groups = [];
        foreach ($r['issues'] as $issue) {
            $groups[$issue['severity']][$issue['code']][] = $issue;
        }
        $l = [...$l, '', '## التعارضات والاستثناءات', ''];
        if ($groups === []) {
            $l[] = 'لا يوجد.';
        }
        foreach (['excluded', 'conflict', 'resolved', 'info'] as $severity) {
            if (! isset($groups[$severity])) {
                continue;
            }
            $l[] = '### '.self::SEVERITY[$severity];
            $l[] = '';
            foreach ($groups[$severity] as $code => $issues) {
                $l[] = "**{$code}** — ".count($issues);
                foreach (array_slice($issues, 0, 40) as $i) {
                    $where = trim(($i['file'] ?? '').($i['sheet'] ? " › {$i['sheet']}" : '').($i['row'] ? " › صف {$i['row']}" : ''));
                    $l[] = "- {$where}: {$i['message']}";
                }
                if (count($issues) > 40) {
                    $l[] = '- … و'.(count($issues) - 40).' غيرها (كاملة في جدول import_issues).';
                }
                $l[] = '';
            }
        }

        return implode("\n", $l)."\n";
    }
}
