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
        Schema::create('sentiment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concern_id');
            $table->text('raw_text_analyzed');
            $table->decimal('sentiment_score', 4, 3)->nullable();
            $table->string('sentiment_label')->nullable();
            $table->string('priority_suggestion')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('analyzed_at')->useCurrent();
            $table->integer('api_tokens_used')->default(0);
            $table->timestamps();

            $table->foreign('concern_id')->references('concern_id')->on('concerns')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sentiment_logs');
    }
};
