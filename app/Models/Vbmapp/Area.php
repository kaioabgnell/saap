<?php

declare(strict_types=1);

namespace App\Models\Vbmapp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;

    protected $table = 'vbmapp_areas';

    protected $fillable = ['code', 'name', 'short_name', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'area_id');
    }
}
