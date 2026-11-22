<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Qué sugirió la IA al inspeccionar la pieza (para compararlo con la decisión del inspector en la trazabilidad). */
    public function up(): void
    {
        Schema::table('serial_records', function (Blueprint $table) {
            $table->string('ai_verdict', 2)->nullable();
            $table->string('ai_defect')->nullable();
            $table->string('ai_confidence', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('serial_records', function (Blueprint $table) {
            $table->dropColumn(['ai_verdict', 'ai_defect', 'ai_confidence']);
        });
    }
};
