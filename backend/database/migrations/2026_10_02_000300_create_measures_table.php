<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of what a number means. A measure fixes the unit, the grain of a record and the
 * aggregation rule, so figures with different units are never summed together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measures', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 190);
            // What one counted unit is: a person's participation, a household, a delivered service...
            $table->string('unit', 64);
            $table->string('unit_label', 120);
            // Grain of one stored row, e.g. project + office + month.
            $table->string('record_level', 64);
            $table->string('aggregation', 16)->default('sum');
            // Breakdown carried by each row (here: male / female).
            $table->string('breakdown', 32);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measures');
    }
};
