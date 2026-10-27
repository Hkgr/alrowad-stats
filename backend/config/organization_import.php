<?php

/*
 * Settings for `php artisan rowad:import-organization`.
 *
 * The workbook sits next to the 2025 and 2026 folders. Its center sheet is the record for
 * beneficiaries; the program, project and region sheets are the rollups and the 2024 snapshots
 * (special needs, staff, offices, project counts).
 */
return [

    'root' => '../data/raw',

    // Theme codes, keyed by the program name on the programs sheet.
    'program_codes' => [
        'التعليم' => 'EDU',
        'الحماية' => 'PRT',
        'التعافي المبكر' => 'EAR',
        'الأمن الغذائي' => 'FOD',
        'الإصحاح' => 'WSH',
    ],

    // Program label on the project and center sheets => name on the programs sheet.
    'program_aliases' => [
        'الأمن الغذائي وسبل العيش' => 'الأمن الغذائي',
        'المياه والاصحاح والنظافة' => 'الإصحاح',
    ],

    // Project label on the center sheet => name on the project sheet.
    'project_aliases' => [
        'المعهد المتوسط لاعداد المدرسين' => 'المعهد المتوسط لاعداد المدرسين - الرواد',
        'الاستقرار وبناء السلام فرحة وطن' => 'الاستقرار وبناء السلام - فرحة وطن',
        'نقل المياه' => 'سقيا الخير',
    ],

    // Center name => region name on the region sheet. A center absent here uses its own name.
    'center_regions' => [
        'غازي عنتاب' => 'غازي عينتاب',
        'نيزب - حافظ باشا' => 'نيزب',
        'نيزب - الفاتح' => 'نيزب',
        'سوسيان' => 'الباب',
        'الكمونة' => 'الأتارب',
    ],

    // A repeated header / subtotal, not a place.
    'skip_centers' => ['Year'],

];
