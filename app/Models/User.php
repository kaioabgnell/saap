<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\ImageUploader;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'whatsapp',
        'photo_path',
        'council_id',
        'clinic_name',
        'clinic_phone',
        'clinic_email',
        'clinic_address',
        'clinic_city',
        'clinic_state',
        'clinic_zip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function learners(): HasMany
    {
        return $this->hasMany(Learner::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** Foto de perfil no disco público — menos sensível que a de aprendiz. */
    public function photoUrl(string $tamanho = 'padrao'): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        $path = $tamanho === 'miniatura'
            ? ImageUploader::thumbnailPathFor($this->photo_path)
            : $this->photo_path;

        return Storage::disk('public')->url($path);
    }
}
