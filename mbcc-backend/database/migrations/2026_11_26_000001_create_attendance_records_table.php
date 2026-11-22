<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Asistencia: un registro por trabajador y día de trabajo (la fecha de la entrada; un turno nocturno cierra al día siguiente). */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');                 // users.id es INT UNSIGNED
            $table->date('work_date');
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('source', 10)->default('self');       // self = lo registró la persona; manual = corrección de un supervisor
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('edited_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date'], 'uk_attendance_user_day');
            $table->index('work_date');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('edited_by')->references('id')->on('users')->nullOnDelete()->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
