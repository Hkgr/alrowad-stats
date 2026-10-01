<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = ['slug', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function beneficiaryRecords(): HasMany
    {
        return $this->hasMany(BeneficiaryRecord::class);
    }
}
