<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acréscimo datado a um registro já fechado.
 *
 * Somente-acréscimo: a tabela não tem `updated_at` e o model não expõe
 * alteração. Um aditamento editável reabriria a porta que o check-out fecha.
 */
class AppointmentAddendum extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * O pluralizador do Laravel produz 'appointment_addendums'. O plural
     * latino é o que a tabela usa — e é o que o texto do prontuário fala.
     */
    protected $table = 'appointment_addenda';

    protected $fillable = ['appointment_id', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
