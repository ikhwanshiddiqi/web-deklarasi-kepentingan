<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel audit log untuk mencatat aktivitas penting (Requirement 7.1/7.2):
     * registrasi, perubahan profil, pengiriman deklarasi, pembuatan ulang PDF,
     * dan unduhan dokumen.
     *
     * Requirement 7.2: menyimpan user_id, event, target_type, target_id, created_at.
     * Requirement 7.3: audit log TIDAK PERNAH menyimpan password, token sesi, atau
     * OTP. Ini adalah catatan tingkat skema — tidak ada kolom untuk data sensitif.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Pelaku aktivitas; nullable karena sebagian event bisa terjadi tanpa
            // user terautentikasi. Diset NULL saat user dihapus agar jejak tetap ada.
            $table->unsignedBigInteger('user_id')->nullable();
            // Jenis event, mis. registered, profile_updated, declaration_submitted,
            // pdf_generated, pdf_downloaded, account_status_changed.
            $table->string('event');
            // Entitas sasaran event (opsional), mis. ConflictDeclaration, User.
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            // Tabel ini hanya butuh created_at sesuai desain (bagian 3).
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('event');
            $table->index(['target_type', 'target_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
