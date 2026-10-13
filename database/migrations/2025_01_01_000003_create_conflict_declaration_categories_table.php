<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kategori benturan kepentingan terpilih untuk sebuah deklarasi (Bagian B).
     * Satu baris per kategori yang dicentang. FK + index serta unique komposit
     * (declaration_id, category_key) sesuai Requirement 9.4 agar tidak ada
     * duplikasi kategori pada deklarasi yang sama.
     */
    public function up(): void
    {
        Schema::create('conflict_declaration_categories', function (Blueprint $table) {
            $table->id();
            // Relasi ke deklarasi induk; cascade saat deklarasi dihapus.
            $table->unsignedBigInteger('declaration_id');
            // Salah satu dari: family_relationship, multiple_positions, hr_process,
            // procurement_finance, financial_interest (daftar divalidasi di aplikasi).
            $table->string('category_key');

            $table->index('declaration_id');
            // Mencegah kategori ganda pada satu deklarasi.
            $table->unique(['declaration_id', 'category_key']);

            $table->foreign('declaration_id')
                ->references('id')
                ->on('conflict_declarations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conflict_declaration_categories');
    }
};
