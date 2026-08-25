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
        Schema::create('concerns', function (Blueprint $table) {
            $table->id('concern_id');
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_unit_id')->constrained('academic_units')->cascadeOnDelete();
            $table->enum('target_type', ['department', 'dean']);
            $table->foreignId('dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['academic_units', 'operational_departments'])->default('operational_departments');
            $table->enum('status', [
                'pending',
                'in_progress',
                'escalated',
                'approved',
                'rejected',
                'resolved',
                'closed'
            ])->default('pending');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('last_updated')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_anonymous')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concerns');
    }
};
