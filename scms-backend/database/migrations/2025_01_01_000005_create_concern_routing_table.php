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
        Schema::create('concern_routing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concern_id')->constrained('concerns', 'concern_id')->cascadeOnDelete();
            $table->enum('action', [
                'routed_to_dept',
                'escalated_to_dean',
                'approved',
                'rejected',
                'resolved',
                'closed'
            ]);
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concern_routing');
    }
};
