<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Identificador que genera el dispositivo al capturar: permite reenviar sin duplicar (cola sin conexión). */
    public function up(): void
    {
        Schema::table('serial_records', function (Blueprint $table) {
            $table->string('client_uuid', 64)->nullable()->after('serial_number');
            $table->unique(['sort_order_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('serial_records', function (Blueprint $table) {
            $table->dropUnique(['sort_order_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
