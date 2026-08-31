<?php

declare(strict_types=1);

namespace App\Models\Vbmapp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stimulus extends Model
{
    use HasFactory;

    protected $table = 'vbmapp_stimuli';

    protected $fillable = ['item_id', 'label', 'image_path', 'source_page', 'position'];

    protected function casts(): array
    {
        return ['source_page' => 'integer', 'position' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
