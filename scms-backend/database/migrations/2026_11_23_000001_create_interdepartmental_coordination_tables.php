<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Interdepartmental Concern Coordination:
     * 1. concern_transfer_requests (Unlimited Handshake Transfers)
     * 2. concern_collaborations (Multi-Department Collaboration)
     * 3. pco_approval_requests (PCO Department Work Authorization Requests)
     * 4. pco_approval_events (Immutable PCO Approval Decision & Clarification History)
     * 5. concern_internal_messages (Physically Separated Department-Only Communication Channel)
     *
     * Also non-destructively ensures the 'PCO' department exists via firstOrCreate.
     */
    public function up(): void
    {
        // 1. Handshake Transfer Requests & Immutable Transfer History
        Schema::create('concern_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concern_id');
            $table->foreign('concern_id')
                ->references('concern_id')
                ->on('concerns')
                ->cascadeOnDelete();

            $table->foreignId('from_dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete();
            $table->string('from_office_name', 255);

            $table->foreignId('to_dept_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->foreignId('to_academic_unit_id')->nullable()->constrained('academic_units')->cascadeOnDelete();
            $table->string('to_office_name', 255);

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name', 255);

            $table->string('transfer_reason', 500);
            $table->text('handoff_summary');

            $table->unsignedInteger('transfer_sequence')->default(1);
            $table->boolean('is_reversal')->default(false);
            $table->string('reversal_warning', 500)->nullable();

            // Allowed status: 'pending', 'accepted', 'declined', 'cancelled'
            $table->string('status', 32)->default('pending')->index();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name', 255)->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['concern_id', 'status']);
            $table->index(['to_dept_id', 'status']);
            $table->index(['from_dept_id', 'status']);
        });

        // 2. Multi-Department Collaboration Requests & Participants
        Schema::create('concern_collaborations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concern_id');
            $table->foreign('concern_id')
                ->references('concern_id')
                ->on('concerns')
                ->cascadeOnDelete();

            $table->foreignId('primary_dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('primary_academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete();
            $table->string('primary_office_name', 255);

            $table->foreignId('collaborator_dept_id')->constrained('departments')->cascadeOnDelete();
            $table->string('collaborator_office_name', 255);

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name', 255);

            $table->string('collaboration_reason', 500);
            $table->text('requested_assistance');

            // Allowed status: 'pending', 'accepted', 'declined', 'completed', 'revoked'
            $table->string('status', 32)->default('pending')->index();

            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responded_by_name', 255)->nullable();
            $table->text('response_notes')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['concern_id', 'collaborator_dept_id', 'status'], 'cc_concern_collab_status_idx');
            $table->index(['collaborator_dept_id', 'status']);
        });

        // 3. PCO Department Approval Requests
        Schema::create('pco_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concern_id');
            $table->foreign('concern_id')
                ->references('concern_id')
                ->on('concerns')
                ->cascadeOnDelete();

            $table->foreignId('supersedes_request_id')
                ->nullable()
                ->constrained('pco_approval_requests')
                ->nullOnDelete();

            $table->foreignId('requesting_dept_id')->constrained('departments')->cascadeOnDelete();
            $table->string('requesting_office_name', 255)->default('PCO');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name', 255);

            $table->foreignId('approving_dept_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->foreignId('approving_academic_unit_id')->nullable()->constrained('academic_units')->cascadeOnDelete();
            $table->string('approving_office_name', 255);

            $table->string('approval_reason', 500);
            $table->text('proposed_work_description');
            $table->text('work_scope');
            $table->text('supporting_notes')->nullable();
            $table->json('attachments')->nullable();

            // Allowed status: 'pending', 'approved', 'rejected', 'clarification_requested', 'cancelled', 'superseded'
            $table->string('status', 32)->default('pending')->index();
            $table->boolean('is_work_authorized')->default(false)->index();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name', 255)->nullable();
            $table->string('decided_by_role', 64)->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['concern_id', 'status']);
            $table->index(['approving_dept_id', 'status']);
            $table->index(['approving_academic_unit_id', 'status']);
        });

        // 4. Immutable PCO Approval Event Audit Trail
        Schema::create('pco_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pco_approval_request_id')
                ->constrained('pco_approval_requests')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('concern_id');
            $table->foreign('concern_id')
                ->references('concern_id')
                ->on('concerns')
                ->cascadeOnDelete();

            // Allowed event_type: 'submitted', 'clarification_requested', 'clarification_provided', 'approved', 'rejected', 'cancelled', 'scope_amended'
            $table->string('event_type', 48)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255);
            $table->string('actor_role', 64)->nullable();
            $table->string('actor_office_name', 255);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pco_approval_request_id', 'created_at']);
            $table->index(['concern_id', 'created_at']);
        });

        // 5. Physically Separated Internal Department Communication Channel
        Schema::create('concern_internal_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concern_id');
            $table->foreign('concern_id')
                ->references('concern_id')
                ->on('concerns')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name', 255);
            $table->string('sender_role', 64);
            $table->foreignId('sender_dept_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('sender_academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete();
            $table->string('sender_department_name', 255);

            // Allowed message_type: 'internal_note', 'transfer_event', 'collaboration_event', 'pco_approval_event'
            $table->string('message_type', 32)->default('internal_note')->index();
            $table->text('body');
            $table->timestamps();

            $table->index(['concern_id', 'created_at']);
        });

        // Non-destructively ensure the 'PCO' department exists in existing databases
        if (Schema::hasTable('departments')) {
            try {
                if (Department::count() > 0) {
                    Department::firstOrCreate(['name' => 'PCO']);
                }
            } catch (\Throwable $e) {
                // Safe no-op during empty test schema initialization
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concern_internal_messages');
        Schema::dropIfExists('pco_approval_events');
        Schema::dropIfExists('pco_approval_requests');
        Schema::dropIfExists('concern_collaborations');
        Schema::dropIfExists('concern_transfer_requests');
    }
};
