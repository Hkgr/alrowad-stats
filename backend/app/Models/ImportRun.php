<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = ['institution_id', 'kind', 'dry_run', 'status', 'summary', 'report_path', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'dry_run' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ImportIssue::class);
    }
}
