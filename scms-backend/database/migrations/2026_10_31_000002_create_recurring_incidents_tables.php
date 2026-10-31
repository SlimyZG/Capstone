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
        // 1. Recurring Incidents table
        Schema::create('recurring_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->enum('workflow_type', ['academic', 'non_academic'])->default('academic');
            $table->foreignId('academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete();
            $table->foreignId('dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['open', 'monitoring', 'investigating', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('recurrence_tier', ['normal', 'high', 'critical', 'major_critical'])->default('normal');
            $table->boolean('is_priority_overridden')->default(false);
            $table->text('priority_override_reason')->nullable();
            $table->foreignId('priority_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('priority_overridden_at')->nullable();
            $table->unsignedInteger('unique_students_count')->default(1);
            $table->unsignedInteger('total_reports_count')->default(1);
            $table->string('cluster_signature')->nullable()->index();
            $table->timestamp('first_reported_at')->nullable();
            $table->timestamp('latest_reported_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 2. Recurring Incident History (Audit Log)
        Schema::create('recurring_incident_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_incident_id')->constrained('recurring_incidents')->cascadeOnDelete();
            $table->string('action'); // incident_created, concern_linked, concern_unlinked, tier_escalated, priority_overridden, status_changed
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('unique_students_snapshot')->default(1);
            $table->timestamp('created_at')->nullable();
        });

        // 3. Add recurring_incident_id and critical_notified_at to concerns table
        Schema::table('concerns', function (Blueprint $table) {
            $table->foreignId('recurring_incident_id')->nullable()->after('dept_id')
                ->constrained('recurring_incidents')->nullOnDelete();
            $table->timestamp('critical_notified_at')->nullable()->after('priority_overridden_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            if (Schema::hasColumn('concerns', 'recurring_incident_id')) {
                $table->dropForeign(['recurring_incident_id']);
                $table->dropColumn('recurring_incident_id');
            }
            if (Schema::hasColumn('concerns', 'critical_notified_at')) {
                $table->dropColumn('critical_notified_at');
            }
        });

        Schema::dropIfExists('recurring_incident_history');
        Schema::dropIfExists('recurring_incidents');
    }
};
