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
            $table->enum('ai_status', ['pending', 'complete', 'failed'])->default('pending')->after('status');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->nullable()->after('ai_status');
            $table->decimal('sentiment_score', 4, 3)->nullable()->after('priority');
            $table->enum('sentiment_label', ['positive', 'neutral', 'negative', 'urgent'])->nullable()->after('sentiment_score');
            $table->text('ai_summary')->nullable()->after('sentiment_label');
            $table->text('resolution_summary')->nullable()->after('ai_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->dropColumn([
                'ai_status',
                'priority',
                'sentiment_score',
                'sentiment_label',
                'ai_summary',
                'resolution_summary'
            ]);
        });
    }
};
