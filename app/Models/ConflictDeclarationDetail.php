<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictDeclarationDetail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conflict_declaration_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'declaration_id',
        'category_key',
        'related_party_name',
        'situation_explanation',
    ];

    /**
     * Deklarasi induk.
     */
    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ConflictDeclaration::class, 'declaration_id');
    }
}
