<?php

namespace App\Services\Classification;

/** Renders the importer report as Markdown (kept in storage/app/reports, not in Git). */
class ClassificationReportWriter
{
    /** @param array<string, mixed> $r */
    public function markdown(array $r): string
    {
        $lines = [
            "# تقرير استيراد تصنيف المشاريع {$r['reference_year']}",
            '',
            "- الملف: `{$r['file']}`",
            "- المؤسسة: `{$r['institution']}`",
            '- التشغيل: '.($r['dry_run'] ? 'تجريبي (لم يُحفظ شيء)' : 'فعلي'),
            '- المقروء: المشروع ← المسار فقط. الحالات والأعداد والإحصاءات لم تُستورد.',
            '',
            '## المسارات',
            '',
            '| # | الرمز | المسار | المشاريع | الحالة |',
            '|---|---|---|---|---|',
        ];
        foreach ($r['sectors'] as $s) {
            $lines[] = "| {$s['order']} | {$s['code']} | {$s['name']} | {$s['projects']} | {$s['status']} |";
        }

        $p = $r['projects'];
        $a = $r['assignments'];
        $lines = [...$lines, '', '## المشاريع والروابط', '',
            '- مشاريع جديدة: '.count($p['created']),
            '- مطابقة لمشاريع موجودة (اسم مطابق بعد التوحيد): '.count($p['matched']),
            '- مطابقة صريحة (alias): '.count($p['aliased']),
            "- روابط جديدة: {$a['created']} · دون تغيير: {$a['unchanged']} · تغيّر مسارها: ".count($a['changed']),
        ];
        foreach (['matched' => 'المطابقة', 'aliased' => 'الصريحة', 'created' => 'الجديدة'] as $key => $label) {
            if ($p[$key] !== []) {
                $lines[] = '';
                $lines[] = "### {$label}";
                foreach ($p[$key] as $name) {
                    $lines[] = "- {$name}";
                }
            }
        }
        foreach ($a['changed'] as $change) {
            $lines[] = "- تغيّر: {$change}";
        }

        $lines = [...$lines, '', '## التعارضات غير المحسومة', ''];
        if ($r['conflicts'] === []) {
            $lines[] = 'لا يوجد.';
        }
        foreach ($r['conflicts'] as $c) {
            $lines[] = "- {$c['name']} — {$c['reason']}";
        }

        $lines = [...$lines, '', '## أسماء متشابهة أُبقيت منفصلة', ''];
        if ($r['kept_separate'] === []) {
            $lines[] = 'لا يوجد.';
        }
        foreach ($r['kept_separate'] as $pair) {
            $lines[] = "- {$pair}";
        }

        $lines = [...$lines, '', '## المطابقة مع الأوراق الأخرى', ''];
        foreach ($r['cross_check'] as $sheet => $c) {
            if (! empty($c['missing_sheet'])) {
                $lines[] = "- «{$sheet}»: الورقة غير موجودة.";

                continue;
            }
            $lines[] = "### {$sheet}";
            $lines[] = "- مسار مطابق: {$c['agree']} (منها {$c['aliased']} عبر اختلاف كتابي معروف)";
            $lines[] = '- مسار مختلف: '.count($c['mismatch']);
            foreach ($c['mismatch'] as $m) {
                $lines[] = "  - {$m}";
            }
            $lines[] = '- أسماء غير معروفة في هذه الورقة: '.count($c['unknown']);
            foreach ($c['unknown'] as $u) {
                $lines[] = "  - {$u}";
            }
            $lines[] = '- مشاريع القائمة غير المذكورة في هذه الورقة: '.count($c['not_listed']);
            foreach ($c['not_listed'] as $n) {
                $lines[] = "  - {$n}";
            }
            $lines[] = '';
        }

        if ($r['notes'] !== []) {
            $lines = [...$lines, '## ملاحظات', ''];
            foreach ($r['notes'] as $note) {
                $lines[] = "- {$note}";
            }
        }

        return implode("\n", $lines)."\n";
    }
}
