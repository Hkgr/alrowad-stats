<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A statistics workbook as imported: relative path under data/raw and the SHA-256 of its content. */
class SourceFile extends Model
{
    protected $fillable = ['institution_id', 'path', 'file_name', 'sha256', 'size'];
}
