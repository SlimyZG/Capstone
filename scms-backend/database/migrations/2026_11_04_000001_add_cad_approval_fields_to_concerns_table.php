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
        Schema::table('concerns', function (Blueprint $table) {
            $table->string('cad_status', 50)->nullable()->after('forwarded_to_cad_at');
            $table->timestamp('cad_document_generated_at')->nullable()->after('cad_status');
            $table->text('cad_department_notes')->nullable()->after('cad_document_generated_at');
            $table->text('cad_submission_notes')->nullable()->after('cad_department_notes');
            $table->timestamp('cad_submitted_at')->nullable()->after('cad_submission_notes');
            $table->foreignId('cad_submitted_by')->nullable()->constrained('users')->nullOnDelete()->after('cad_submitted_at');
            $table->string('cad_decision', 30)->nullable()->after('cad_submitted_by');
            $table->timestamp('cad_decision_at')->nullable()->after('cad_decision');
            $table->foreignId('cad_decision_by')->nullable()->constrained('users')->nullOnDelete()->after('cad_decision_at');
            $table->text('cad_decision_notes')->nullable()->after('cad_decision_by');
            $table->string('cad_signed_document_path', 500)->nullable()->after('cad_decision_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->dropForeign(['cad_submitted_by']);
            $table->dropForeign(['cad_decision_by']);
            $table->dropColumn([
                'cad_status',
                'cad_document_generated_at',
                'cad_department_notes',
                'cad_submission_notes',
                'cad_submitted_at',
                'cad_submitted_by',
                'cad_decision',
                'cad_decision_at',
                'cad_decision_by',
                'cad_decision_notes',
                'cad_signed_document_path',
            ]);
        });
    }
};

