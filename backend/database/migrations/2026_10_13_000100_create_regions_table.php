<?php

use App\Models\Institution;
use App\Services\Regions\RegionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regions (المناطق) group the offices: "حلب" holds Jarabulus, Afrin, …; "إدلب" holds Idlib and Salqin;
 * a region such as Damascus has no office of its own. offices.region_id is nullable, so an office
 * the catalog does not mention is still counted (it is listed under "بدون منطقة").
 *
 * The mapping lives in config/regions.php and is applied here for the offices that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('slug', 96);
            $table->string('name', 190);
            // false = the region has no office; its figures are recorded under one office row that stands for it.
            $table->boolean('has_offices')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['institution_id', 'slug']);
            $table->unique(['institution_id', 'id']);
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('name');
            $table->index(['institution_id', 'region_id']);
            $table->foreign(['institution_id', 'region_id'])->references(['institution_id', 'id'])->on('regions')->restrictOnDelete();
        });

        foreach (Institution::all() as $institution) {
            app(RegionCatalog::class)->sync($institution);
        }
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropForeign(['institution_id', 'region_id']);
            $table->dropIndex(['institution_id', 'region_id']);
            $table->dropColumn('region_id');
        });
        Schema::dropIfExists('regions');
    }
};
