<?php

/*
 * Settings for `php artisan rowad:import-statistics`.
 *
 * Every workbook under data/raw must be listed here explicitly: an unlisted file is reported and
 * not imported. `project` is the project name as written in the 2026 project list (matched after
 * Arabic normalization, never fuzzily). The file name, the Total sheet's «اسم المشروع» and the
 * reason for each non-obvious link are noted so the mapping can be reviewed.
 */
return [

    // Relative to the backend directory.
    'root' => '../data/raw',

    'files' => [
        // ---- 2025 (September–December 2025) ----------------------------------------------------
        '2025/temp.xlsx' => [
            'skip' => 'نسخة قالب من أوراق «أثر» (أيلول–كانون الأول) بلا أي قيمة مدخلة؛ ليست مصدر أرقام.',
            'expect_empty' => true,
        ],
        '2025/أثر.xlsx' => ['project' => 'أثر', 'year' => 2025],
        '2025/اصرار.xlsx' => ['project' => 'إصرار', 'year' => 2025],
        '2025/الرعاية المجتمعية.xlsx' => ['project' => 'الرعاية المجتمعية', 'year' => 2025],
        '2025/الرواد الصغار.xlsx' => ['project' => 'الرواد الصغار', 'year' => 2025],
        '2025/رواد العلم_.xlsx' => ['project' => 'رواد العلم', 'year' => 2025],
        '2025/قدرات.xlsx' => ['project' => 'قدرات', 'year' => 2025],
        // Total sheet names the project «مهني».
        '2025/معهد الرواد للتدريب والتأهيل المهني.xlsx' => ['project' => 'معهد الرواد للتدريب والتأهيل المهني', 'year' => 2025],
        // File says «الابتكاري», the list says «الابتكار»; Total sheet names it «إداري».
        '2025/معهد الرواد للتطوير الاداري والابتكاري.xlsx' => ['project' => 'معهد الرواد للتطوير الإداري والابتكار', 'year' => 2025],
        '2025/معهد الرواد للثقافة والتراث والفنون.xlsx' => ['project' => 'معهد الرواد للثقافة والتراث والفنون', 'year' => 2025],
        '2025/معهد الرواد للعلوم التقنية_.xlsx' => ['project' => 'معهد الرواد للعلوم التقنية', 'year' => 2025],

        // ---- 2026 ------------------------------------------------------------------------------
        '2026/SEGMA 2026.xlsx' => ['project' => 'الجمعية الطبية السورية - الألمانية (SEGMA)', 'year' => 2026],
        '2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026],
        // Counts families (and sacrifices), not people: a separate measure, never added to persons.
        '2026/أضحيتي 2026.xlsx' => ['project' => 'أضحيتي', 'year' => 2026, 'measure' => 'households_served'],
        '2026/أهل القران 2026.xlsx' => ['project' => 'أهل القرآن', 'year' => 2026],
        '2026/اصرار 2026.xlsx' => ['project' => 'إصرار', 'year' => 2026],
        '2026/الرعاية المجتمعية 2026.xlsx' => ['project' => 'الرعاية المجتمعية', 'year' => 2026],
        '2026/الرواد الصغار 2026.xlsx' => ['project' => 'الرواد الصغار', 'year' => 2026],
        // The device project (health track), not «ترميم غرفة جهاز الحصيات» (development track).
        '2026/تفتيت الحصيات ابن رشد 2026.xlsx' => ['project' => 'جهاز تفتيت الحصيات ضمن مشفى ابن رشد', 'year' => 2026],
        '2026/جامعة الرواد.xlsx' => ['project' => 'جامعة الرواد', 'year' => 2026],
        '2026/رمضان الخير 2026.xlsx' => ['project' => 'رمضان الخير', 'year' => 2026],
        '2026/رواد العلم 2026.xlsx' => ['project' => 'رواد العلم', 'year' => 2026],
        '2026/رواد المعرفة 2026.xlsx' => ['project' => 'رواد المعرفة', 'year' => 2026],
        '2026/قدرات 2026.xlsx' => ['project' => 'قدرات', 'year' => 2026],
        '2026/لعبة و فرحة 2026.xlsx' => ['project' => 'لعبة وفرحة', 'year' => 2026],
        '2026/مشروع العلاج الفيزيائي 2026.xlsx' => ['project' => 'مركز العلاج الفيزيائي', 'year' => 2026],
        '2026/معهد اعداد المدرسين.xlsx' => ['project' => 'معهد اعداد المدرسين', 'year' => 2026],
        // File name says «التدريب الاداري والتأهيل المهني»; sheets and Total say «المعهد المهني».
        '2026/معهد الرواد للتدريب الاداري والتأهيل المهني 2026.xlsx' => ['project' => 'معهد الرواد للتدريب والتأهيل المهني', 'year' => 2026],
        '2026/معهد الرواد للتطوير الاداري والابتكار 2026.xlsx' => ['project' => 'معهد الرواد للتطوير الإداري والابتكار', 'year' => 2026],
        '2026/معهد الرواد للثقافة والتراث والفنون 2026.xlsx' => ['project' => 'معهد الرواد للثقافة والتراث والفنون', 'year' => 2026],
        '2026/معهد الرواد للعلوم التقنية 2026.xlsx' => ['project' => 'معهد الرواد للعلوم التقنية', 'year' => 2026],
        // The list writes «مشروع معهد الرياضي».
        '2026/معهد رياضي.xlsx' => ['project' => 'معهد الرياضي', 'year' => 2026],
        '2026/نادي الرواد الرياضي.xlsx' => ['project' => 'نادي الرواد الرياضي', 'year' => 2026],
        '2026/نادي النهضة 2026.xlsx' => ['project' => 'نادي النهضة', 'year' => 2026],
    ],

    // Stable URL slugs for offices that are not in the phase 1 sample.
    'office_slugs' => [
        'جنديرس' => 'jinderes', 'حلب' => 'aleppo', 'إدلب' => 'idlib', 'اعزاز' => 'azaz', 'إعزاز' => 'azaz',
        'الرقة' => 'raqqa', 'حماة' => 'hama', 'درعا' => 'daraa', 'دمشق' => 'damascus', 'دير الزور' => 'deir-ez-zor',
    ],
];
