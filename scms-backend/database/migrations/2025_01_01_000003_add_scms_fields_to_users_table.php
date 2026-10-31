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
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id_number', 8)->unique()->nullable()->after('name');
            $table->enum('role', ['student', 'admin', 'dean', 'dept_staff', 'sao'])->default('student')->after('email');
            $table->foreignId('academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete()->after('role');
            $table->foreignId('dept_id')->nullable()->constrained('departments')->nullOnDelete()->after('academic_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['academic_unit_id']);
            $table->dropForeign(['dept_id']);
            $table->dropColumn(['student_id_number', 'role', 'academic_unit_id', 'dept_id']);
        });
    }
};
