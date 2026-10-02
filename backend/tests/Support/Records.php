<?php

namespace Tests\Support;

use App\Models\ActivityRecord;

/** Creates activity records in tests with the defaults the importer would set. */
final class Records
{
    public static function make(array $attributes): ActivityRecord
    {
        $male = $attributes['male_count'] ?? null;
        $female = $attributes['female_count'] ?? null;

        return ActivityRecord::create($attributes + [
            'detail_key' => sha1(uniqid('', true)),
            'total_count' => (int) $male + (int) $female,
            'is_active' => true,
        ]);
    }
}
