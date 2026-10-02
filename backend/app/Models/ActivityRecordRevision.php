<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What an import changed on a record (before/after), so corrections of a source stay traceable. */
class ActivityRecordRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['activity_record_id', 'import_run_id', 'change', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
