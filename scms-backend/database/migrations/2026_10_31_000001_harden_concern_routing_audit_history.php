<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes performed_by on concern_routing from cascadeOnDelete() to nullOnDelete()
     * and adds performed_by_name snapshot column so historical audit records are
     * preserved even if the user account is deleted.
     */
    public function up(): void
    {
        Schema::table('concern_routing', function (Blueprint $table) {
            if (!Schema::hasColumn('concern_routing', 'performed_by_name')) {
                $table->string('performed_by_name')->nullable()->after('performed_by');
            }
            $table->dropForeign(['performed_by']);
        });

        Schema::table('concern_routing', function (Blueprint $table) {
            $table->unsignedBigInteger('performed_by')->nullable()->change();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Safely backfill existing records with the historical actor's name
        $records = DB::table('concern_routing')
            ->whereNull('performed_by_name')
            ->whereNotNull('performed_by')
            ->get(['id', 'performed_by']);

        foreach ($records as $record) {
            $name = DB::table('users')->where('id', $record->performed_by)->value('name');
            if ($name) {
                DB::table('concern_routing')->where('id', $record->id)->update([
                    'performed_by_name' => $name,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('concern_routing', function (Blueprint $table) {
            $table->dropForeign(['performed_by']);
        });

        Schema::table('concern_routing', function (Blueprint $table) {
            $table->unsignedBigInteger('performed_by')->nullable(false)->change();
            $table->foreign('performed_by')->references('id')->on('users')->cascadeOnDelete();
            if (Schema::hasColumn('concern_routing', 'performed_by_name')) {
                $table->dropColumn('performed_by_name');
            }
        });
    }
};
