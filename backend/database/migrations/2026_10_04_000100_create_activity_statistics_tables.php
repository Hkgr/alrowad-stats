<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statistics at the finest documented level: project → Level 1 (internal category) →
 * Level 2 "الأنشطة الرئيسية" → Level 3 "الأنشطة الفرعية", per office and month.
 *
 * - activity_records replaces beneficiary_records. Its key carries the activity path, the
 *   course number and an occurrence index, so several detail rows in the same office and month
 *   are never collapsed by an upsert.
 * - Every record keeps its source (file + SHA-256, sheet, row, import run); every change made by
 *   an import is written to activity_record_revisions.
 * - Existing beneficiary_records rows are copied into activity_records (same figures, same
 *   sample data source) before the old table is dropped. down() restores it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measures', function (Blueprint $table) {
            // Label of an additional, non-person count carried by some measures (e.g. sacrifices).
            $table->string('items_label', 120)->nullable()->after('breakdown');
        });

        Schema::create('project_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->timestamps();

            $table->unique(['project_id', 'name_key'], 'project_categories_project_name');
            $table->unique(['institution_id', 'id']);
            $table->index(['institution_id', 'project_id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'project_id'])->references(['institution_id', 'id'])->on('projects')->restrictOnDelete();
        });

        Schema::create('main_activities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            // Level 1 context when the source has one; 0 in category_key means "no Level 1".
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('category_key')->default(0);
            $table->string('slug', 120);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->timestamps();

            $table->unique(['project_id', 'category_key', 'name_key'], 'main_activities_identity');
            $table->unique(['project_id', 'slug']);
            $table->unique(['institution_id', 'id']);
            $table->index(['institution_id', 'project_id']);
            $table->index(['institution_id', 'category_id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'project_id'])->references(['institution_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['institution_id', 'category_id'])->references(['institution_id', 'id'])->on('project_categories')->restrictOnDelete();
        });

        Schema::create('sub_activities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('main_activity_id');
            $table->string('slug', 120);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->timestamps();

            $table->unique(['main_activity_id', 'name_key'], 'sub_activities_identity');
            $table->unique(['main_activity_id', 'slug']);
            $table->unique(['institution_id', 'id']);
            $table->index(['institution_id', 'main_activity_id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'main_activity_id'])->references(['institution_id', 'id'])->on('main_activities')->restrictOnDelete();
        });

        Schema::create('source_files', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->string('path', 400);
            $table->string('file_name', 255);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['institution_id', 'path']);
            $table->unique(['institution_id', 'id']);
            $table->index('sha256');
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->string('kind', 32);
            $table->boolean('dry_run')->default(false);
            $table->string('status', 16)->default('running');
            $table->json('summary')->nullable();
            $table->string('report_path', 400)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
        });

        Schema::create('activity_records', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('office_id');
            $table->foreignId('measure_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('main_activity_id')->nullable();
            $table->unsignedBigInteger('sub_activity_id')->nullable();
            // Hash of the detail path (Level 1/2/3 names, course, attributes, occurrence).
            $table->char('detail_key', 40);
            $table->string('course_number', 40)->nullable();
            $table->unsignedInteger('sections_count')->nullable();
            $table->unsignedInteger('male_under_18')->nullable();
            $table->unsignedInteger('female_under_18')->nullable();
            $table->unsignedInteger('male_adult')->nullable();
            $table->unsignedInteger('female_adult')->nullable();
            // Null = not reported (or not reliable) in the source, never "zero".
            $table->unsignedInteger('male_count')->nullable();
            $table->unsignedInteger('female_count')->nullable();
            // Part of total_count, never added on top of it.
            $table->unsignedInteger('disabled_count')->nullable();
            $table->unsignedInteger('total_count');
            // Non-person count of some measures (e.g. sacrifices), labelled by measures.items_label.
            $table->unsignedInteger('items_count')->nullable();
            $table->json('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('data_source_id')->nullable();
            $table->unsignedBigInteger('source_file_id')->nullable();
            $table->string('source_sheet', 190)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->unsignedBigInteger('import_run_id')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'period_id', 'office_id', 'measure_id', 'detail_key'], 'activity_records_natural_key');
            $table->index(['institution_id', 'measure_id', 'is_active', 'period_id'], 'activity_records_scope_idx');
            $table->index(['institution_id', 'project_id']);
            $table->index(['institution_id', 'period_id']);
            $table->index(['institution_id', 'office_id']);
            $table->index(['institution_id', 'category_id']);
            $table->index(['institution_id', 'main_activity_id']);
            $table->index(['institution_id', 'sub_activity_id']);
            $table->index(['institution_id', 'data_source_id']);
            $table->index(['institution_id', 'source_file_id']);
            $table->index(['institution_id', 'import_run_id']);

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            foreach ([
                'project_id' => 'projects', 'period_id' => 'periods', 'office_id' => 'offices',
                'category_id' => 'project_categories', 'main_activity_id' => 'main_activities',
                'sub_activity_id' => 'sub_activities', 'data_source_id' => 'data_sources',
                'source_file_id' => 'source_files', 'import_run_id' => 'import_runs',
            ] as $column => $parent) {
                $table->foreign(['institution_id', $column])->references(['institution_id', 'id'])->on($parent)->restrictOnDelete();
            }
        });

        Schema::create('activity_record_revisions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('activity_record_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('import_run_id')->nullable();
            // created | updated | deactivated | reactivated | superseded | migrated
            $table->string('change', 16);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('import_run_id');
            $table->foreign('import_run_id')->references('id')->on('import_runs')->nullOnDelete();
        });

        Schema::create('import_issues', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_file_id')->nullable();
            $table->string('source_sheet', 190)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            // excluded (not imported) | conflict (imported with a field withheld) | resolved | info
            $table->string('severity', 16);
            $table->string('code', 64);
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['import_run_id', 'severity']);
            $table->foreign('source_file_id')->references('id')->on('source_files')->nullOnDelete();
        });

        $this->copyLegacyRecords();
        Schema::dropIfExists('beneficiary_records');
    }

    /** Moves the phase 1 sample rows across unchanged (no activity path, occurrence 1). */
    private function copyLegacyRecords(): void
    {
        if (! Schema::hasTable('beneficiary_records')) {
            return;
        }

        $now = now();
        $detailKey = sha1(json_encode(['', '', '', '', '', 1]));

        foreach (DB::table('beneficiary_records')->orderBy('id')->get() as $old) {
            $id = DB::table('activity_records')->insertGetId([
                'institution_id' => $old->institution_id,
                'project_id' => $old->project_id,
                'period_id' => $old->period_id,
                'office_id' => $old->office_id,
                'measure_id' => $old->measure_id,
                'detail_key' => $detailKey,
                'male_count' => $old->male_count,
                'female_count' => $old->female_count,
                'total_count' => $old->male_count + $old->female_count,
                'is_active' => true,
                'data_source_id' => $old->data_source_id,
                'created_at' => $old->created_at ?? $now,
                'updated_at' => $now,
            ]);
            DB::table('activity_record_revisions')->insert([
                'activity_record_id' => $id,
                'change' => 'migrated',
                'after' => json_encode(['from' => 'beneficiary_records', 'id' => $old->id]),
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::create('beneficiary_records', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('office_id');
            $table->unsignedBigInteger('period_id');
            $table->foreignId('measure_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('data_source_id')->nullable();
            $table->unsignedInteger('male_count');
            $table->unsignedInteger('female_count');
            $table->timestamps();
            $table->unique(['project_id', 'office_id', 'period_id', 'measure_id'], 'beneficiary_records_natural_key');
        });

        // Only rows that map 1:1 back to the old grain (no activity path) can be restored.
        foreach (DB::table('activity_records')->whereNull('main_activity_id')->whereNull('category_id')
            ->whereNotNull('male_count')->whereNotNull('female_count')->get() as $r) {
            DB::table('beneficiary_records')->insertOrIgnore([
                'institution_id' => $r->institution_id, 'project_id' => $r->project_id, 'office_id' => $r->office_id,
                'period_id' => $r->period_id, 'measure_id' => $r->measure_id, 'data_source_id' => $r->data_source_id,
                'male_count' => $r->male_count, 'female_count' => $r->female_count,
                'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
            ]);
        }

        foreach (['import_issues', 'activity_record_revisions', 'activity_records', 'import_runs', 'source_files', 'sub_activities', 'main_activities', 'project_categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('measures', fn (Blueprint $table) => $table->dropColumn('items_label'));
    }
};
