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
        Schema::table('user_profiles', function (Blueprint $table) {
            // Personal Information
            $table->date('birthday')->nullable()->after('writing_style_notes');
            $table->text('bio')->nullable()->after('birthday');
            
            // Address Information
            $table->string('country')->nullable()->after('bio');
            $table->string('city')->nullable()->after('country');
            $table->text('address')->nullable()->after('city');
            
            // Professional Links
            $table->string('portfolio_site_link')->nullable()->after('address');
            $table->string('github_link')->nullable()->after('portfolio_site_link');
            $table->string('linkedin_link')->nullable()->after('github_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'birthday',
                'bio',
                'country',
                'city',
                'address',
                'portfolio_site_link',
                'github_link',
                'linkedin_link'
            ]);
        });
    }
};
