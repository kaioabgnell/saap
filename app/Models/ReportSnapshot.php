<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Vbmapp\Report\ReportPayload;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['assessment_id', 'payload', 'pdf_path', 'content_hash', 'generated_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function reportPayload(): ReportPayload
    {
        return new ReportPayload($this->payload);
    }

    /** O hash confere com o payload guardado? Detecta adulteração. */
    public function hashIsValid(): bool
    {
        return $this->reportPayload()->contentHash() === $this->content_hash;
    }
}
