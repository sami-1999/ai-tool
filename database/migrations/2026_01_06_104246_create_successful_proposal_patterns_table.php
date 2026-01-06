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
        Schema::create('successful_proposal_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_type'); // e.g., 'web development', 'mobile development'
            $table->string('tone')->nullable(); // e.g., 'professional', 'friendly', 'formal'
            $table->text('structure_notes')->nullable(); // Key patterns that led to success
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('successful_proposal_patterns');
    }
};
