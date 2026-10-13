<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictDeclarationCategory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conflict_declaration_categories';

    /**
     * Tabel ini tidak memiliki kolom timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'declaration_id',
        'category_key',
    ];

    /**
     * Deklarasi induk.
     */
    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ConflictDeclaration::class, 'declaration_id');
    }
}
