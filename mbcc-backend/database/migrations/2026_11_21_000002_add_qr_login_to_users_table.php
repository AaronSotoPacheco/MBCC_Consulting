<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Código QR único por empleado: solo se guarda su hash (SHA-256); el código en claro se muestra una vez al generarlo. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_token_hash', 64)->nullable()->unique();
            $table->timestamp('qr_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['qr_token_hash']);
            $table->dropColumn(['qr_token_hash', 'qr_generated_at']);
        });
    }
};
