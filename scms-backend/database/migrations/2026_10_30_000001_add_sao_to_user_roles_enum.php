<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures 'sao' is included in the users.role allowed values.
     * On MySQL: alters the ENUM column directly.
     * On SQLite (used by tests): the base migration already includes 'sao',
     *   so no action is needed here.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql' || (Schema::getConnection()->getDriverName() === 'mysql')) {
            Schema::getConnection()->statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('student', 'admin', 'dean', 'dept_staff', 'sao') NOT NULL DEFAULT 'student'"
            );
        }
        // SQLite: base migration (add_scms_fields) already declares 'sao' in enum — no action needed.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'mysql' || (Schema::getConnection()->getDriverName() === 'mysql')) {
            Schema::getConnection()->statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('student', 'admin', 'dean', 'dept_staff') NOT NULL DEFAULT 'student'"
            );
        }
    }
};
