<?php

declare(strict_types=1);

namespace App\Models\Vbmapp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPage extends Model
{
    use HasFactory;

    protected $table = 'vbmapp_material_pages';

    protected $fillable = ['level', 'area_id', 'item_position', 'image_path', 'page_number', 'source_file'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'item_position' => 'integer',
            'page_number' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
