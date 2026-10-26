<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    /** Month names as they appear in the institution's source files. */
    public const MONTHS_AR = [
        1 => 'كانون الثاني',
        2 => 'شباط',
        3 => 'آذار',
        4 => 'نيسان',
        5 => 'أيار',
        6 => 'حزيران',
        7 => 'تموز',
        8 => 'آب',
        9 => 'أيلول',
        10 => 'تشرين الأول',
        11 => 'تشرين الثاني',
        12 => 'كانون الأول',
    ];

    protected $fillable = ['institution_id', 'year', 'month'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }

    /** A whole-year report (sheet «كل الأشهر»), stored as month 0 rather than a calendar month. */
    public function isAnnual(): bool
    {
        return $this->month === 0;
    }

    /** Stable identifier used in URLs and the API, e.g. "2026-05". A year report is "2025-00". */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function label(): string
    {
        return $this->isAnnual() ? 'تقرير السنة '.$this->year : self::MONTHS_AR[$this->month].' '.$this->year;
    }

    /** @return array{0: int, 1: int}|null [year, month]; month 0 is a year report */
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^(\d{4})-(0[0-9]|1[0-2])$/', $key, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }
}
