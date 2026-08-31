<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Vbmapp\Stimulus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'response_id', 'position', 'stimulus_id',
        'list_key', 'column_key', 'text_value', 'is_checked',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_checked' => 'boolean',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class, 'stimulus_id');
    }
}
