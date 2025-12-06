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
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('cms_type')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedTinyInteger('lighthouse_performance')->nullable();
            $table->unsignedTinyInteger('lighthouse_accessibility')->nullable();
            $table->unsignedTinyInteger('lighthouse_seo')->nullable();
            $table->unsignedTinyInteger('cta_score')->nullable();
            $table->json('cta_details')->nullable();
            $table->unsignedTinyInteger('form_friction_score')->nullable();
            $table->json('form_details')->nullable();
            $table->json('trust_signals')->nullable();
            $table->json('mobile_issues')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->json('image_issues')->nullable();
            $table->json('schema_detected')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
