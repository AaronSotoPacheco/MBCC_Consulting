<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bitácora de auditoría: quién hizo qué y cuándo (solo se agrega, nunca se edita). */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable(); // users.id es INT UNSIGNED: el tipo debe coincidir en MySQL
            $table->string('user_name', 150)->nullable();   // copia del nombre: sigue legible aunque cambie o se borre el usuario
            $table->string('action', 60)->index();           // p. ej. instruccion.aprobar, orden.estado, qr.generar
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('summary', 255);
            $table->json('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
