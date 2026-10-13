<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            // Relasi 1-1 ke users; unik + cascade on delete.
            $table->unsignedBigInteger('user_id')->unique();
            // Diisi saat profil dilengkapi (Setting Akun).
            $table->string('full_name')->nullable();
            $table->string('position')->nullable();
            $table->string('work_unit')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
