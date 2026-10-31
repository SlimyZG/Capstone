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
            $table->enum('ai_priority', ['low', 'medium', 'high', 'critical'])->nullable()->after('priority');
            $table->boolean('is_priority_overridden')->default(false)->after('ai_priority');
            $table->text('priority_override_reason')->nullable()->after('is_priority_overridden');
            $table->foreignId('priority_overridden_by')->nullable()->constrained('users')->nullOnDelete()->after('priority_override_reason');
            $table->timestamp('priority_overridden_at')->nullable()->after('priority_overridden_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->dropForeign(['priority_overridden_by']);
            $table->dropColumn([
                'ai_priority',
                'is_priority_overridden',
                'priority_override_reason',
                'priority_overridden_by',
                'priority_override_at',
            ]);
        });
    }
};
