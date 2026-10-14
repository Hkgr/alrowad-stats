<?php

/*
 * Regions (المناطق) and the offices (المكاتب) that belong to each one.
 *
 * `offices` lists office slugs (the slugs of the offices table). A region with `has_offices => false`
 * has no office of its own: the single office row listed for it only stands for the region, because
 * the source files record those figures under the region's name. The filter shows such a region as one
 * choice with the note «لا يوجد مكاتب», and it is not counted as an office.
 *
 * `php artisan rowad:sync-regions` (and the migration / seeder) apply this file; it is safe to re-run.
 * An office that is not listed here appears in the filter under «بدون منطقة».
 */
return [
    // Names shown in the filter for an office whose own name would read like its region («حلب» inside the region «حلب»).
    'office_labels' => [
        'aleppo' => 'حلب المدينة',
        'idlib' => 'إدلب المدينة',
    ],

    'regions' => [
        ['slug' => 'aleppo', 'name' => 'حلب', 'offices' => ['aleppo', 'jinderes', 'afrin', 'marea', 'atarib', 'jarabulus', 'al-bab', 'sosian', 'azaz']],
        ['slug' => 'idlib', 'name' => 'إدلب', 'offices' => ['idlib', 'salqin']],
        ['slug' => 'damascus', 'name' => 'دمشق', 'offices' => ['damascus'], 'has_offices' => false],
        ['slug' => 'raqqa', 'name' => 'الرقة', 'offices' => ['raqqa'], 'has_offices' => false],
        ['slug' => 'deir-ez-zor', 'name' => 'دير الزور', 'offices' => ['deir-ez-zor'], 'has_offices' => false],
        ['slug' => 'hama', 'name' => 'حماة', 'offices' => ['hama'], 'has_offices' => false],
        ['slug' => 'daraa', 'name' => 'درعا', 'offices' => ['daraa'], 'has_offices' => false],
    ],
];
