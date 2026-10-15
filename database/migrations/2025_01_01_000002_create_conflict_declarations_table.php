<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel deklarasi potensi benturan kepentingan. Menyimpan snapshot data
     * karyawan agar PDF historis tidak berubah (Requirement 4.12), status
     * deklarasi, dan metadata submit/PDF. FK + index sesuai Requirement 9.4.
     */
    public function up(): void
    {
        Schema::create('conflict_declarations', function (Blueprint $table) {
            $table->id();
            // Pemilik deklarasi; cascade saat user dihapus. Diindeks untuk query riwayat.
            $table->unsignedBigInteger('user_id');
            // Snapshot identitas karyawan saat deklarasi dibuat (Requirement 4.12).
            $table->string('employee_name');
            // NIK snapshot; VARCHAR(16) agar leading zero terjaga.
            $table->string('employee_nip');
            $table->string('employee_nik');
            $table->string('employee_position');
            $table->string('employee_work_unit');
            // Status deklarasi: none | has_conflict (Requirement 4.2 / 4.11).
            $table->string('declaration_status')->default('none');
            // Label penanda sistem setelah submit (mis. "APPROVED").
            $table->string('approved_label')->nullable();
            // Waktu submit (zona Asia/Jakarta diterapkan di aplikasi).
            $table->timestamp('submitted_at')->nullable();
            // Path file PDF privat; diisi setelah PDF berhasil dibuat.
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index('user_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        // Check constraint agar status hanya bernilai none | has_conflict (PostgreSQL).
        DB::statement(
            "ALTER TABLE conflict_declarations ADD CONSTRAINT conflict_declarations_declaration_status_check CHECK (declaration_status IN ('none', 'has_conflict'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conflict_declarations');
    }
};
