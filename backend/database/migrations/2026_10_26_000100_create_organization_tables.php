<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organization's annual figures (2017–2024) are a different grain from the institution's
 * monthly activity rows, so they live in their own tables and never mix into those totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('code', 8);
            $table->string('name', 190);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('org_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('org_programs')->cascadeOnDelete();
            $table->string('slug', 96)->unique();
            $table->string('name', 190);
            $table->unsignedInteger('disabled_count')->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['program_id', 'id']);
        });

        Schema::create('org_project_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('org_projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('beneficiaries');
            $table->unique(['project_id', 'year']);
        });

        Schema::create('org_program_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('org_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('projects_count')->nullable();
            $table->unique(['program_id', 'year']);
        });

        Schema::create('org_regions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 190);
            $table->unsignedInteger('disabled_count')->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedInteger('offices_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('org_region_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('org_regions')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('beneficiaries');
            $table->unsignedInteger('projects_count')->nullable();
            $table->unique(['region_id', 'year']);
        });

        Schema::create('org_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('org_regions')->nullOnDelete();
            $table->string('slug', 96)->unique();
            $table->string('name', 190);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('org_center_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('org_projects')->cascadeOnDelete();
            $table->foreignId('center_id')->constrained('org_centers')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('beneficiaries');
            $table->unique(['project_id', 'center_id', 'year']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_center_years');
        Schema::dropIfExists('org_centers');
        Schema::dropIfExists('org_region_years');
        Schema::dropIfExists('org_regions');
        Schema::dropIfExists('org_program_years');
        Schema::dropIfExists('org_project_years');
        Schema::dropIfExists('org_projects');
        Schema::dropIfExists('org_programs');
    }
};
