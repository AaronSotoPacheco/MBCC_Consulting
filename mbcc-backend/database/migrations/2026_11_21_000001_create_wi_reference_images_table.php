<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fotos de referencia (OK / NG) por instrucción de trabajo, usadas por la comparación con IA. */
    public function up(): void
    {
        Schema::create('wi_reference_images', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('work_instruction_id');
            $table->enum('kind', ['OK', 'NG']);
            $table->string('label', 120)->nullable(); // NG: nombre del defecto que muestra
            $table->string('file_path');
            $table->string('mime', 50);
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['work_instruction_id', 'kind']);
            $table->foreign('work_instruction_id')->references('id')->on('work_instructions')->cascadeOnDelete()->onUpdate('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete()->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wi_reference_images');
    }
};
