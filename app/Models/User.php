<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\AuditLog;
use App\Models\ConflictDeclaration;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'nik',
        'nip',
        'password',
        'role',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Profil karyawan (relasi 1-1).
     */
    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * Deklarasi benturan kepentingan milik user.
     */
    public function declarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }

    /**
     * Ambil deklarasi terbaru user dengan kolom minimal untuk pengecekan status.
     */
    public function latestDeclaration(): ?ConflictDeclaration
    {
        return $this->declarations()
            ->latest('submitted_at')
            ->select(['id', 'user_id', 'submitted_at', 'created_at', 'updated_at'])
            ->first();
    }

    /**
     * Catatan audit yang dilakukan user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Apakah user memiliki role admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Apakah profil user sudah lengkap (nama, jabatan, unit kerja, dan NIK terisi).
     */
    public function hasCompleteProfile(): bool
    {
        $profile = $this->profile;

        if ($profile === null) {
            return false;
        }

        return filled($profile->full_name)
            && filled($profile->position)
            && filled($profile->work_unit)
            && filled($this->nik);
    }
}
