<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportIssue extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['import_run_id', 'source_file_id', 'source_sheet', 'source_row', 'severity', 'code', 'message', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
