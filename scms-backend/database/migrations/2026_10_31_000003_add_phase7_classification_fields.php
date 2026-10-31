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
            $table->string('issue_category')->nullable()->after('category');
            $table->string('issue_subcategory')->nullable()->after('issue_category');
            $table->text('classification_explanation')->nullable()->after('issue_subcategory');
            $table->decimal('classification_confidence', 4, 3)->nullable()->after('classification_explanation');
            $table->text('ai_priority_explanation')->nullable()->after('ai_priority');
        });

        Schema::table('recurring_incidents', function (Blueprint $table) {
            $table->string('issue_category')->nullable()->after('workflow_type');
            $table->string('issue_subcategory')->nullable()->after('issue_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concerns', function (Blueprint $table) {
            $table->dropColumn([
                'issue_category',
                'issue_subcategory',
                'classification_explanation',
                'classification_confidence',
                'ai_priority_explanation',
            ]);
        });

        Schema::table('recurring_incidents', function (Blueprint $table) {
            $table->dropColumn([
                'issue_category',
                'issue_subcategory',
            ]);
        });
    }
};
