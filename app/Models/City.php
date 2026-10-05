<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'country_code', 'slug'])]
/**
 * Stadt als Archiv-Gruppierung. Der Slug entsteht aus Name und Land.
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (City $city): void {
            if (blank($city->slug)) {
                $city->slug = Str::slug($city->name.'-'.$city->country_code);
            }
        });
    }

    /** @return HasMany<Museum, $this> */
    public function museums(): HasMany
    {
        return $this->hasMany(Museum::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
