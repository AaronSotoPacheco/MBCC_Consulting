<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->unique();
            $table->string('description')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150)->unique();
            $table->string('contact_email', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('part_numbers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->string('part_number', 100);
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['client_id', 'part_number'], 'uk_client_part');
            $table->foreign('client_id')->references('id')->on('clients')->onUpdate('cascade');
        });

        Schema::create('work_instructions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('part_number_id');
            $table->string('code', 50);
            $table->string('revision', 10);
            $table->string('title', 200);
            $table->string('file_path');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'OBSOLETE'])->default('DRAFT');
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['code', 'revision'], 'uk_wi_revision');
            $table->foreign('part_number_id')->references('id')->on('part_numbers')->onUpdate('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete()->onUpdate('cascade');
        });

        Schema::create('sort_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_number', 50)->unique();
            $table->unsignedInteger('part_number_id');
            $table->unsignedInteger('work_instruction_id');
            $table->unsignedInteger('target_quantity');
            $table->enum('status', ['OPEN', 'PAUSED', 'CLOSED'])->default('OPEN');
            $table->string('shift_hours', 50)->default('8:00 AM - 4:00 PM');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('part_number_id')->references('id')->on('part_numbers')->onUpdate('cascade');
            $table->foreign('work_instruction_id')->references('id')->on('work_instructions')->onUpdate('cascade');
        });

        Schema::create('defect_catalog', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->enum('category', ['COSMETIC', 'MECHANICAL', 'FUNCTIONAL', 'PACKAGING']);
            $table->string('description');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('serial_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order_id');
            $table->string('serial_number', 100);
            $table->enum('status', ['PASSED', 'FAILED', 'REWORK_REQUIRED', 'SCRAP']);
            $table->unsignedInteger('inspected_by');
            $table->string('station_identifier', 50)->default('Station-01');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['sort_order_id', 'serial_number'], 'uk_order_serial');
            $table->index(['sort_order_id', 'status'], 'idx_order_status');
            $table->index('status', 'idx_serial_status');
            $table->foreign('sort_order_id')->references('id')->on('sort_orders')->onUpdate('cascade');
            $table->foreign('inspected_by')->references('id')->on('users')->onUpdate('cascade');
        });

        Schema::create('serial_defect_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serial_record_id');
            $table->unsignedInteger('defect_id');
            $table->string('zone', 10)->default('A');
            $table->string('photo_evidence_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('serial_record_id', 'idx_serial_defect');
            $table->index('defect_id', 'idx_defect');
            $table->foreign('serial_record_id')->references('id')->on('serial_records')->cascadeOnDelete()->onUpdate('cascade');
            $table->foreign('defect_id')->references('id')->on('defect_catalog')->onUpdate('cascade');
        });

        Schema::create('rework_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('serial_record_id');
            $table->string('rework_action');
            $table->unsignedInteger('reworked_by');
            $table->unsignedInteger('reinspected_by')->nullable();
            $table->enum('final_status', ['PASSED', 'SCRAP'])->default('PASSED');
            $table->timestamp('completed_at')->useCurrent();
            $table->index('serial_record_id', 'idx_rework_serial');
            $table->index('final_status', 'idx_rework_status');
            $table->foreign('serial_record_id')->references('id')->on('serial_records')->onUpdate('cascade');
            $table->foreign('reworked_by')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('reinspected_by')->references('id')->on('users')->nullOnDelete()->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rework_logs');
        Schema::dropIfExists('serial_defect_logs');
        Schema::dropIfExists('serial_records');
        Schema::dropIfExists('defect_catalog');
        Schema::dropIfExists('sort_orders');
        Schema::dropIfExists('work_instructions');
        Schema::dropIfExists('part_numbers');
        Schema::dropIfExists('clients');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists('roles');
    }
};
