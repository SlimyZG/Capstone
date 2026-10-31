<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds workflow_type and forwarded_to_cad_at to the concerns table.
 *
 * workflow_type distinguishes:
 *   - 'academic'     : goes to Chairperson → Dean workflow
 *   - 'non_academic' : goes to Dept Staff → CAD (Campus Director) workflow
 *
 * forwarded_to_cad_at records when a non-academic concern was forwarded to CAD.
 *
 * Existing records default to 'non_academic' so no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->string('workflow_type')->default('non_academic')->after('category');
            $table->timestamp('forwarded_to_cad_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->dropColumn(['workflow_type', 'forwarded_to_cad_at']);
        });
    }
};
