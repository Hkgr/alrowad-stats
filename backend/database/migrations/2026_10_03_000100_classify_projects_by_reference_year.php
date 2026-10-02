<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project → sector (track) classification becomes year-scoped.
 *
 * The institution's project list is a 2026 reference. Storing the link on projects.sector_id
 * would silently re-classify 2025 records too, so the link moves to project_sector_assignments
 * keyed by (project, reference_year). Records are classified by the assignment of their own
 * period's year; a year without an assignment stays unclassified.
 *
 * Existing links are copied as 2026 assignments (the only one came from the 2026 list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            // Code and order exactly as listed in the reference file (EDU, CUL, ...).
            $table->string('code', 16)->nullable()->after('slug');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
        });

        Schema::create('project_sector_assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('sector_id');
            $table->unsignedSmallInteger('reference_year');
            // The project name exactly as written in the reference file.
            $table->string('source_name', 255)->nullable();
            $table->unsignedBigInteger('data_source_id')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'reference_year'], 'project_sector_assignments_project_year');
            $table->index(['institution_id', 'reference_year', 'sector_id'], 'project_sector_assignments_scope_idx');
            $table->index(['institution_id', 'project_id']);
            $table->index(['institution_id', 'sector_id']);
            $table->index(['institution_id', 'data_source_id']);

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'project_id'])
                ->references(['institution_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['institution_id', 'sector_id'])
                ->references(['institution_id', 'id'])->on('sectors')->restrictOnDelete();
            $table->foreign(['institution_id', 'data_source_id'])
                ->references(['institution_id', 'id'])->on('data_sources')->restrictOnDelete();
        });

        $now = now();
        DB::table('projects')->whereNotNull('sector_id')->orderBy('id')->get()->each(function ($project) use ($now) {
            DB::table('project_sector_assignments')->insert([
                'institution_id' => $project->institution_id,
                'project_id' => $project->id,
                'sector_id' => $project->sector_id,
                'reference_year' => 2026,
                'source_name' => $project->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['institution_id', 'sector_id']);
            $table->dropIndex(['institution_id', 'sector_id']);
            $table->dropColumn('sector_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('institution_id');
            $table->index(['institution_id', 'sector_id']);
            $table->foreign(['institution_id', 'sector_id'])
                ->references(['institution_id', 'id'])->on('sectors')->restrictOnDelete();
        });

        // Restore the most recent classification of each project.
        DB::table('project_sector_assignments')->orderBy('reference_year')->get()->each(function ($row) {
            DB::table('projects')->where('id', $row->project_id)->update(['sector_id' => $row->sector_id]);
        });

        Schema::dropIfExists('project_sector_assignments');

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn(['code', 'sort_order']);
        });
    }
};
