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
            $table->string('client_name')->nullable()->after('detected_job_type');
            $table->decimal('client_rating', 3, 2)->nullable()->after('client_name');
            $table->string('client_spending')->nullable()->after('client_rating');
            $table->string('posted_job_type')->nullable()->after('client_spending');
            $table->string('budget')->nullable()->after('posted_job_type');
            $table->string('risk_level')->nullable()->after('budget');
            $table->boolean('should_apply')->default(true)->after('risk_level');
            $table->text('risk_reasoning')->nullable()->after('should_apply');
            $table->integer('risk_score')->default(0)->after('risk_reasoning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'client_name',
                'client_rating',
                'client_spending',
                'posted_job_type',
                'budget',
                'risk_level',
                'should_apply',
                'risk_reasoning',
                'risk_score',
            ]);
        });
    }
};
