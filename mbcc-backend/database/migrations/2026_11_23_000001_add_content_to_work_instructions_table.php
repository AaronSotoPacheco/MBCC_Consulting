<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Contenido que el inspector ve en la Estación: puntos clave (uno por línea) y especificaciones (torque, tornillería…). */
    public function up(): void
    {
        Schema::table('work_instructions', function (Blueprint $table) {
            $table->text('key_points')->nullable();
            $table->text('specifications')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_instructions', function (Blueprint $table) {
            $table->dropColumn(['key_points', 'specifications']);
        });
    }
};
