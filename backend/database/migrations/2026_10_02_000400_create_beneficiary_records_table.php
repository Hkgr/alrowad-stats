<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row = one measure, for one project, in one office, in one month, split by gender.
 * Counts are registered participations (a person attending twice is counted twice), so they
 * can be summed across offices and months but are NOT unique beneficiaries.
 *
 * The total is derived (male + female) by the dashboard service and is not stored.
 * A missing row means "no data", never zero.
 */
return new class extends Migration
{
    public function up(): void
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
            $table->index(['institution_id', 'measure_id', 'period_id'], 'beneficiary_records_scope_idx');
            $table->index(['institution_id', 'project_id']);
            $table->index(['institution_id', 'office_id']);
            $table->index(['institution_id', 'period_id']);
            $table->index(['institution_id', 'data_source_id']);

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'project_id'])
                ->references(['institution_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['institution_id', 'office_id'])
                ->references(['institution_id', 'id'])->on('offices')->restrictOnDelete();
            $table->foreign(['institution_id', 'period_id'])
                ->references(['institution_id', 'id'])->on('periods')->restrictOnDelete();
            $table->foreign(['institution_id', 'data_source_id'])
                ->references(['institution_id', 'id'])->on('data_sources')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_records');
    }
};
