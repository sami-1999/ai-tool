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
        Schema::table('proposal_requests', function (Blueprint $table) {
            $table->timestamp('job_posted_at')->nullable()->after('risk_score');
            $table->unsignedInteger('proposals_count')->nullable()->after('job_posted_at');
            $table->boolean('has_payment_verified')->nullable()->after('proposals_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'job_posted_at',
                'proposals_count',
                'has_payment_verified',
            ]);
        });
    }
};
