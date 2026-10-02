<?php

namespace App\Services\Import;

/**
 * Turns the figures of one source row into record values, or explains why it cannot.
 *
 * Rules (documented in docs/data-model.md):
 *  - A row with no value typed by a person (only blanks, "-" or formulas over blanks) is MISSING,
 *    not zero. A typed 0 is an explicit zero.
 *  - Male = «ذكور -18» + «ذكور +18» when the age split is entered, else the typed «عدد الذكور»
 *    (same for female). A gender formula based on a percentage of the total is an estimate and is
 *    not stored as a count.
 *  - Total = male + female (disabled people are part of it, never added on top). A stored formula
 *    result that disagrees with its components is replaced by the recomputed value and logged.
 *  - Typed aggregates that contradict the age split: when both give the same total, the total is
 *    kept and the gender split withheld; otherwise the row is isolated.
 *  - Disabled > total is impossible: the disabled figure is withheld.
 */
class RowValueResolver
{
    /**
     * @param  array<string, array{value: ?float, typed: bool, formula: bool, f: ?string}>  $v
     * @return array{status: 'missing'|'ok'|'excluded', values?: array, issues: list<array{severity: string, code: string, message: string}>}
     */
    public function resolve(array $v, string $measure): array
    {
        $issues = [];
        $typedInputs = ['mu18', 'fu18', 'm18p', 'f18p', 'male', 'female', 'disabled', 'total', 'families', 'sacrifices'];
        if (! array_filter($typedInputs, fn ($f) => $v[$f]['typed'] ?? false)) {
            return ['status' => 'missing', 'issues' => []];
        }

        foreach ($typedInputs as $field) {
            $value = $v[$field]['value'] ?? null;
            if (($v[$field]['typed'] ?? false) && $value !== null && ($value < 0 || floor($value) !== $value)) {
                return ['status' => 'excluded', 'issues' => [['severity' => 'excluded', 'code' => 'invalid_number', 'message' => "قيمة غير صالحة كعدد ({$value}) في عمود {$field}."]]];
            }
        }

        $int = fn (?float $x) => $x === null ? null : (int) round($x);
        $sections = ($v['sections']['typed'] ?? false) ? $int($v['sections']['value']) : null;

        if ($measure === 'households_served') {
            $families = ($v['families']['typed'] ?? false) ? $v['families']['value'] : (($v['total']['typed'] ?? false) ? $v['total']['value'] : null);
            if ($families === null) {
                return ['status' => 'excluded', 'issues' => [['severity' => 'excluded', 'code' => 'no_count', 'message' => 'لا يوجد عدد عوائل مدخل في الصف.']]];
            }

            return ['status' => 'ok', 'issues' => [], 'values' => [
                'total_count' => $int($families),
                'items_count' => ($v['sacrifices']['typed'] ?? false) ? $int($v['sacrifices']['value']) : null,
                'sections_count' => $sections,
            ]];
        }

        $age = fn (string $a, string $b) => ($v[$a]['typed'] ?? false) || ($v[$b]['typed'] ?? false)
            ? ($v[$a]['value'] ?? 0) + ($v[$b]['value'] ?? 0) : null;
        $estimate = fn (string $f) => ($v[$f]['formula'] ?? false) && str_contains((string) ($v[$f]['f'] ?? ''), '%');

        $male = $age('mu18', 'm18p');
        $female = $age('fu18', 'f18p');
        $typedMale = ($v['male']['typed'] ?? false) ? $v['male']['value'] : null;
        $typedFemale = ($v['female']['typed'] ?? false) ? $v['female']['value'] : null;
        $genderWithheld = false;

        foreach ([['male', $male, $typedMale], ['female', $female, $typedFemale]] as [$field, $fromAge, $typed]) {
            if ($fromAge !== null && $typed !== null && abs($fromAge - $typed) > 1e-9) {
                $genderWithheld = true;
            }
        }

        if ($genderWithheld) {
            $ageTotal = ($male ?? 0) + ($female ?? 0);
            $typedTotal = ($typedMale ?? $male ?? 0) + ($typedFemale ?? $female ?? 0);
            if (abs($ageTotal - $typedTotal) > 1e-9) {
                return ['status' => 'excluded', 'issues' => [[
                    'severity' => 'excluded', 'code' => 'conflicting_counts',
                    'message' => "عدد الذكور/الإناث المكتوب يخالف مجموع الفئات العمرية ويغيّر الإجمالي ({$typedTotal} مقابل {$ageTotal})؛ عُزل الصف.",
                ]]];
            }
            $issues[] = ['severity' => 'conflict', 'code' => 'gender_split_conflict', 'message' => "توزيع الجنس المكتوب يخالف الفئات العمرية والإجمالي متطابق ({$ageTotal})؛ حُفظ الإجمالي دون توزيع الجنس."];
            $total = $ageTotal;
            $male = $female = null;
        } else {
            $male ??= $typedMale;
            $female ??= $typedFemale;
            foreach (['male', 'female'] as $field) {
                if (${$field} === null && $estimate($field)) {
                    $issues[] = ['severity' => 'conflict', 'code' => 'gender_estimated', 'message' => 'عدد '.($field === 'male' ? 'الذكور' : 'الإناث').' في المصدر نسبة تقديرية من الإجمالي (صيغة %)؛ لم يُحفظ كعدد فعلي.'];
                }
            }

            $sourceTotal = $v['total']['value'] ?? null;
            if ($male !== null && $female !== null) {
                $total = $male + $female;
                if ($sourceTotal !== null && abs($sourceTotal - $total) > 1e-9) {
                    if ($v['total']['typed']) {
                        return ['status' => 'excluded', 'issues' => [['severity' => 'excluded', 'code' => 'typed_total_mismatch', 'message' => "الإجمالي المكتوب ({$sourceTotal}) لا يساوي الذكور + الإناث ({$total})؛ عُزل الصف."]]];
                    }
                    $issues[] = ['severity' => 'resolved', 'code' => 'formula_recomputed', 'message' => "نتيجة صيغة «العدد الكامل» المخزنة ({$sourceTotal}) تخالف مكوناتها؛ اعتُمد الذكور + الإناث ({$total}). الصيغة: ".($v['total']['f'] ?? '—')];
                }
            } elseif ($male !== null || $female !== null) {
                // Only one gender entered. The total is kept when it is typed, or when the sheet's own
                // total formula equals that gender exactly (the other cell is blank: "not reported").
                $known = $male ?? $female;
                if ($v['total']['typed'] ?? false) {
                    $total = $sourceTotal;
                } elseif ($sourceTotal !== null && abs($sourceTotal - $known) < 1e-9) {
                    $total = $known;
                    $issues[] = ['severity' => 'resolved', 'code' => 'one_gender_reported', 'message' => 'أُدخل عدد '.($male !== null ? 'الذكور' : 'الإناث').' فقط وخلية الجنس الآخر فارغة؛ حُفظ الإجمالي كما تحسبه الورقة ('.$known.')، والجنس الآخر «غير مذكور» لا صفر.'];
                } else {
                    return ['status' => 'excluded', 'issues' => [['severity' => 'excluded', 'code' => 'incomplete_counts', 'message' => 'عدد أحد الجنسين فقط مدخل والإجمالي لا يطابقه؛ عُزل الصف.']]];
                }
            } else {
                if (! ($v['total']['typed'] ?? false)) {
                    return ['status' => 'excluded', 'issues' => [['severity' => 'excluded', 'code' => 'no_count', 'message' => 'لا يوجد عدد أشخاص مدخل في الصف (قيم أخرى فقط).']]];
                }
                $total = $sourceTotal;
            }
        }

        $disabled = ($v['disabled']['typed'] ?? false) ? $v['disabled']['value'] : null;
        if ($disabled !== null && $disabled > $total) {
            $issues[] = ['severity' => 'conflict', 'code' => 'disabled_exceeds_total', 'message' => "عدد ذوي الاحتياجات الخاصة ({$disabled}) أكبر من الإجمالي ({$total})؛ لم يُحفظ."];
            $disabled = null;
        }

        $keepAge = ! $genderWithheld;

        return ['status' => 'ok', 'issues' => $issues, 'values' => [
            'male_under_18' => $keepAge && ($v['mu18']['typed'] ?? false) ? $int($v['mu18']['value']) : null,
            'female_under_18' => $keepAge && ($v['fu18']['typed'] ?? false) ? $int($v['fu18']['value']) : null,
            'male_adult' => $keepAge && ($v['m18p']['typed'] ?? false) ? $int($v['m18p']['value']) : null,
            'female_adult' => $keepAge && ($v['f18p']['typed'] ?? false) ? $int($v['f18p']['value']) : null,
            'male_count' => $int($male),
            'female_count' => $int($female),
            'disabled_count' => $int($disabled),
            'total_count' => $int($total),
            'items_count' => null,
            'sections_count' => $sections,
        ]];
    }
}
