<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A group of offices (حلب, إدلب, …). See config/regions.php. */
class Region extends Model
{
    protected $fillable = ['institution_id', 'slug', 'name', 'has_offices', 'sort_order'];

    protected function casts(): array
    {
        return ['has_offices' => 'boolean'];
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }
}
