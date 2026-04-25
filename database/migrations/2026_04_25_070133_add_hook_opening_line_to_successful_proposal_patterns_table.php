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
        Schema::table('successful_proposal_patterns', function (Blueprint $table) {
            $table->string('hook_opening_line', 200)->nullable()->after('structure_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('successful_proposal_patterns', function (Blueprint $table) {
            $table->dropColumn('hook_opening_line');
        });
    }
};
