<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for the Student Self-Service Assistance module ("Need Help?").
     */
    public function up(): void
    {
        // 1. Knowledge Base Entries Table
        Schema::create('self_service_kb_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 32)->unique(); // e.g., A1..G4, S1..S7
            $table->string('title');
            $table->string('category', 64)->index(); // One of the 12 discovery categories
            $table->string('manual_category', 160)->nullable();
            $table->json('common_questions')->nullable();
            $table->text('approved_answer');
            $table->json('steps')->nullable(); // Array of structured step objects
            $table->json('requirements')->nullable(); // Array of requirement strings
            $table->string('responsible_office', 500);
            $table->string('primary_review_office', 255)->nullable();
            $table->json('secondary_review_offices')->nullable();
            $table->string('source_reference', 255)->default('University of Cebu Student Manual (June 2019 Edition)');
            $table->string('manual_page_number', 120);
            $table->boolean('is_sensitive')->default(false)->index();
            $table->string('sensitive_pathway_type', 64)->nullable(); // sexual_harassment, violence_bullying, hazing, disciplinary_complaint, medical_emergency, pregnancy_protection, confidential_drug_testing
            $table->json('validation_needed')->nullable();

            // Content Lifecycle & Separate Approval vs. Publication States
            $table->string('lifecycle_stage', 64)->default('Draft Extracted')->index();
            // Allowed lifecycle_stage: 'Draft Extracted', 'Under Office Review', 'Approved', 'Published', 'Needs Revision', 'Archived / Outdated'
            $table->string('approval_status', 64)->default('Pending UCLM Validation')->index();
            // Allowed approval_status: 'Pending UCLM Validation', 'Verbal Confirmation Pending Formal Approval', 'Approved', 'Needs Revision', 'Rejected'
            $table->string('publication_status', 64)->default('Unpublished')->index();
            // Allowed publication_status: 'Unpublished', 'Published', 'Archived'

            // Validation & Publication Audit Trail
            $table->string('validated_by')->nullable();
            $table->string('validated_role')->nullable();
            $table->string('validated_office')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();
            $table->string('supporting_reference')->nullable();

            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            // Versioning for student task persistence & outdated guidance detection
            $table->unsignedInteger('version')->default(1);
            $table->text('revision_summary')->nullable();
            $table->timestamps();
        });

        // 2. Knowledge Base Revision Snapshots Table
        Schema::create('self_service_kb_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_entry_id')->constrained('self_service_kb_entries')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('approved_answer');
            $table->json('steps')->nullable();
            $table->json('requirements')->nullable();
            $table->string('responsible_office', 500);
            $table->string('manual_page_number', 120);
            $table->string('lifecycle_stage', 64);
            $table->string('approval_status', 64);
            $table->string('publication_status', 64);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_notes')->nullable();
            $table->timestamps();

            $table->index(['kb_entry_id', 'version']);
        });

        // 3. Student Guided Self-Service Tasks Table
        Schema::create('self_service_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kb_entry_id')->constrained('self_service_kb_entries')->cascadeOnDelete();
            $table->string('entry_code', 32)->index();
            $table->unsignedInteger('kb_version')->default(1);

            // Snapshot of approved guidance at task creation time so edits to KB never silently rewrite saved student tasks
            $table->string('task_title');
            $table->string('category', 64);
            $table->string('responsible_office', 500);
            $table->string('source_reference', 255);
            $table->string('manual_page_number', 120);
            $table->json('steps_snapshot');
            $table->json('requirements_snapshot')->nullable();

            // Progress & Self-Reported Status
            $table->json('completed_step_ids')->nullable(); // e.g., [1, 2]
            $table->json('blocked_steps')->nullable(); // Array of {step_number, reason, reported_at}
            $table->unsignedInteger('total_steps')->default(0);
            $table->unsignedInteger('completed_steps_count')->default(0);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('status', 32)->default('in_progress')->index(); // in_progress, paused, blocked, completed, restarted
            $table->text('student_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'kb_entry_id']);
        });

        // 4. Student Helpfulness Feedback Table (Separate from formal concerns)
        Schema::create('self_service_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kb_entry_id')->constrained('self_service_kb_entries')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('self_service_tasks')->nullOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['kb_entry_id', 'is_helpful']);
        });

        // 5. Self-Service Search & Discovery Log (Aggregated analytics for SAO)
        Schema::create('self_service_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('query', 500)->nullable();
            $table->string('selected_category', 64)->nullable()->index();
            $table->unsignedInteger('matched_entries_count')->default(0);
            $table->boolean('has_published_match')->default(false)->index();
            $table->boolean('triggered_sensitive_guardrail')->default(false)->index();
            $table->boolean('escalated_to_formal_concern')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_service_search_logs');
        Schema::dropIfExists('self_service_feedbacks');
        Schema::dropIfExists('self_service_tasks');
        Schema::dropIfExists('self_service_kb_revisions');
        Schema::dropIfExists('self_service_kb_entries');
    }
};
