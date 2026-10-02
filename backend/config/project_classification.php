<?php

/*
 * Settings for `php artisan rowad:import-classification`.
 *
 * Only "project → track" is read from the reference workbook. Statuses, beneficiary and staff
 * counts, monthly figures, office statistics and summary totals are deliberately ignored.
 */
return [

    // Primary sheet: one column group per track (number | name | status), codes on the next row.
    'primary_sheet' => 'قائمة المشاريع',

    // Secondary sheets used only to cross-check the track of each project.
    'cross_check_sheets' => [
        // sheet => [project name column, track name column, first data row]
        'مشاريع حسب الزمن' => ['name' => 'D', 'track' => 'B', 'from_row' => 5],
        'مشاريع حسب المكتب' => ['name' => 'B', 'track' => 'C', 'from_row' => 5],
    ],

    /*
     * Explicit links: project name as written in the reference file => slug of an existing project.
     * Use this when an existing project must be linked although its name is written differently.
     */
    'aliases' => [
        'مشروع لعبة وفرحة' => 'loba-wa-farha',
    ],

    /*
     * Known spelling variants in the secondary sheets => the name as written in the primary sheet.
     * Used for cross-checking only; they never create or merge projects.
     */
    'cross_check_aliases' => [
        'مشروع المعهد الرياضي' => 'مشروع معهد الرياضي',
        'مشروع جائزة الدكتور عبد القادر السنكري للثقافة والتراث والتنمية' => 'مشروع جائزة الدكتور عبد القادر سنكري للثقافة و التراث و التنمية',
        'مشروع معهد الرواد للعلوم التنقية' => 'مشروع معهد الرواد للعلوم التقنية',
        'مشروع معهد الرواد للثقافة والتراث الفنون' => 'مشروع معهد الرواد للثقافة والتراث والفنون',
        'مشروع إعادة تأهيل وتجهيز مخابر كلية الكهرباء في جامعة حلب (قسم الميكاترونيكس)' => 'مشروع إعادة تأهيل وتجهيز مخابر كلية الكهرباء في جامعة حلب (قسم الميكاترونيكس- التحكم والأتمتة)',
    ],
];
