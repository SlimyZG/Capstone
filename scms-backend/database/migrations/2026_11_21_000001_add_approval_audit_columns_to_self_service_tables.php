<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: records the authenticated SAO user who recorded an approval, and preserves
     * the approval metadata of prior versions inside revision snapshots.
     */
    public function up(): void
    {
        Schema::table('self_service_kb_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('self_service_kb_entries', 'approval_recorded_by')) {
                $table->foreignId('approval_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('self_service_kb_revisions', function (Blueprint $table) {
            if (!Schema::hasColumn('self_service_kb_revisions', 'validated_by')) {
                $table->string('validated_by')->nullable();
                $table->string('validated_role')->nullable();
                $table->string('validated_office')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->string('supporting_reference')->nullable();
                $table->foreignId('approval_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('self_service_kb_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('self_service_kb_revisions', 'approval_recorded_by')) {
                $table->dropConstrainedForeignId('approval_recorded_by');
            }
            foreach (['validated_by', 'validated_role', 'validated_office', 'validated_at', 'supporting_reference'] as $col) {
                if (Schema::hasColumn('self_service_kb_revisions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('self_service_kb_entries', function (Blueprint $table) {
            if (Schema::hasColumn('self_service_kb_entries', 'approval_recorded_by')) {
                $table->dropConstrainedForeignId('approval_recorded_by');
            }
        });
    }
};
