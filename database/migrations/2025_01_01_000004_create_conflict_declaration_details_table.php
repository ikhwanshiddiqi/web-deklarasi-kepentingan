<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rincian situasi benturan kepentingan (Bagian C). Setiap baris mengacu ke
     * salah satu kategori yang dicentang pada deklarasi. FK + index sesuai
     * Requirement 9.4; konsistensi kategori terhadap Bagian B divalidasi di
     * aplikasi (Requirement 4.9).
     */
    public function up(): void
    {
        Schema::create('conflict_declaration_details', function (Blueprint $table) {
            $table->id();
            // Relasi ke deklarasi induk; cascade saat deklarasi dihapus.
            $table->unsignedBigInteger('declaration_id');
            // Kategori rincian; harus salah satu kategori terpilih di Bagian B.
            $table->string('category_key');
            // Nama pihak terkait pada situasi benturan kepentingan.
            $table->string('related_party_name');
            // Penjelasan situasi (teks panjang).
            $table->text('situation_explanation');
            $table->timestamps();

            $table->index('declaration_id');

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
        Schema::dropIfExists('conflict_declaration_details');
    }
};
