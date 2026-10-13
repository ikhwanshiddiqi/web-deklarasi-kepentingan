<?php

namespace App\Models;

use App\Models\ConflictDeclarationCategory;
use App\Models\ConflictDeclarationDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConflictDeclaration extends Model
{
    use HasFactory;

    /**
     * Status deklarasi: tidak memiliki benturan kepentingan.
     */
    public const STATUS_NONE = 'none';

    /**
     * Status deklarasi: memiliki benturan kepentingan.
     */
    public const STATUS_HAS_CONFLICT = 'has_conflict';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'employee_name',
        'employee_nik',
        'employee_position',
        'employee_work_unit',
        'declaration_status',
        'approved_label',
        'submitted_at',
        'pdf_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Pemilik deklarasi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kategori benturan kepentingan terpilih (Bagian B).
     */
    public function categories(): HasMany
    {
        return $this->hasMany(ConflictDeclarationCategory::class, 'declaration_id');
    }

    /**
     * Rincian situasi benturan kepentingan (Bagian C).
     */
    public function details(): HasMany
    {
        return $this->hasMany(ConflictDeclarationDetail::class, 'declaration_id');
    }
}
