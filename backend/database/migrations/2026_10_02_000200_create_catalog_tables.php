<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-institution catalogs: sectors (tracks), projects, offices, periods and data sources.
 *
 * Every table carries institution_id and a unique (institution_id, id) key. Child tables
 * reference that pair, so the database itself rejects a row that points at a parent
 * belonging to a different institution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('slug', 64);
            $table->string('name', 190);
            $table->timestamps();

            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            // Nullable on purpose: a project stays "unclassified" until its sector is documented.
            $table->unsignedBigInteger('sector_id')->nullable();
            $table->string('slug', 96);
            $table->string('name', 190);
            // Classification exactly as written in the source file (e.g. the "level 1" column).
            $table->string('source_category', 190)->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'id']);
            $table->index(['institution_id', 'sector_id']);
            $table->foreign(['institution_id', 'sector_id'])
                ->references(['institution_id', 'id'])->on('sectors')->restrictOnDelete();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('slug', 96);
            $table->string('name', 190);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'id']);
        });

        // Monthly granularity. Month names follow the source files (see App\Models\Period).
        Schema::create('periods', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->unique(['institution_id', 'year', 'month']);
            $table->unique(['institution_id', 'id']);
        });

        Schema::create('data_sources', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('slug', 96);
            $table->string('label', 190);
            $table->string('file_name', 255)->nullable();
            $table->string('reference_url', 500)->nullable();
            // 'sample' = a vetted extract of the source; 'full' = a complete import.
            $table->string('coverage', 16)->default('sample');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
        Schema::dropIfExists('periods');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('sectors');
    }
};
