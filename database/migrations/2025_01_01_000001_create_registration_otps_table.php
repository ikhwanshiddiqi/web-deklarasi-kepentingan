<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel OTP registrasi email. Menyimpan HANYA hash OTP (tidak pernah plaintext)
     * sesuai Requirement 1.2 & 1.8. Mendukung verifikasi (1.5), rate limit/resend
     * (1.3, 1.4), dan pembersihan data kedaluwarsa (1.10).
     */
    public function up(): void
    {
        Schema::create('registration_otps', function (Blueprint $table) {
            $table->id();
            // Email tujuan OTP (dinormalkan: trim + lowercase di aplikasi).
            $table->string('email');
            // Hanya hash OTP yang disimpan (mis. sha256/Hash::make), bukan plaintext.
            $table->string('code_hash');
            // Masa berlaku kode (5 menit sejak dibuat).
            $table->timestamp('expires_at');
            // Jumlah percobaan verifikasi salah; cap 5.
            $table->unsignedTinyInteger('attempt_count')->default(0);
            // Diisi saat OTP berhasil diverifikasi (sekali pakai).
            $table->timestamp('consumed_at')->nullable();
            // Waktu kirim terakhir; dasar jeda minimal 60 detik untuk resend.
            $table->timestamp('last_sent_at')->nullable();
            // Alamat IP pemohon (IPv4/IPv6); panjang 45 mencukupi IPv6.
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Index untuk pencarian berdasarkan email.
            $table->index('email');
            // Index komposit untuk pencarian kode aktif (email + expires_at).
            $table->index(['email', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_otps');
    }
};
