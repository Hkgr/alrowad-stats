<?php

namespace Tests\Feature;

use App\Services\Organization\OrganizationImporter;
use App\Support\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $path = storage_path('app/organization-fixture.xlsx');
        (new XlsxWriter)
            ->addSheet('احصائيات البرامج', [
                ['البرنامج', 2017, 2018],
                ['التعليم', 10, 5, null, null, null, null, null, null, 15, 0, 1, 1],
            ])
            ->addSheet('احصائيات المشاريع - توتال', [
                ['البرنامج', 'المشروع', 2017, 2018],
                ['التعليم', 'مدرسة', 10, 5, 0, 0, 0, 0, 0, 0, 15, 4, 7],
            ])
            ->addSheet('احصائيات منطقة', [
                ['المنطقة', 2017, 2018],
                ['غازي عينتاب', 10, 5, 0, 0, 0, 0, 0, 0, 15, 2, 3, 1, 1, null, null, null, null, null, null, 9],
            ])
            ->addSheet('احصائيات مشاريع - مراكز', [
                ['البرنامج', 'المشروع', 'المركز', 2024, 2023, 2022, 2021, 2020, 2019, 2018, 2017],
                ['التعليم', 'مدرسة', 'غازي عينتاب', null, null, null, null, null, null, 5, 10],
            ])
            ->save($path);

        app(OrganizationImporter::class)->import($path);
    }

    public function test_center_rows_are_the_beneficiary_record(): void
    {
        $this->getJson('/api/v1/organization')
            ->assertOk()
            ->assertJsonPath('data.summary.beneficiaries', 15)
            ->assertJsonPath('data.summary.programs', 1)
            ->assertJsonPath('data.summary.projects', 1)
            ->assertJsonPath('data.summary.regions', 1)
            ->assertJsonPath('data.summary.centers', 1)
            ->assertJsonPath('data.summary.span', '2017 – 2018')
            ->assertJsonPath('data.snapshot.basis', 'overview')
            ->assertJsonPath('data.snapshot.disabled', 4)
            ->assertJsonPath('data.snapshot.staff', 7)
            ->assertJsonPath('data.snapshot.offices', 3)
            ->assertJsonPath('data.by_year.0.beneficiaries', 10)
            ->assertJsonPath('data.by_year.1.beneficiaries', 5);
    }

    public function test_year_and_place_filters_narrow_the_same_record(): void
    {
        $this->getJson('/api/v1/organization?year=2017')
            ->assertOk()
            ->assertJsonPath('data.summary.beneficiaries', 10)
            ->assertJsonPath('data.snapshot.disabled', 4);

        $this->getJson('/api/v1/organization?from=2018&to=2018')
            ->assertOk()
            ->assertJsonPath('data.summary.beneficiaries', 5);

        $region = $this->getJson('/api/v1/organization')->json('data.options.regions.0.slug');
        $this->getJson('/api/v1/organization?region='.$region)
            ->assertOk()
            ->assertJsonPath('data.summary.beneficiaries', 15)
            ->assertJsonPath('data.snapshot.basis', 'region')
            ->assertJsonPath('data.snapshot.disabled', 2)
            ->assertJsonPath('data.snapshot.offices', 3);

        $program = $this->getJson('/api/v1/organization')->json('data.options.programs.0.slug');
        $this->getJson('/api/v1/organization?program='.$program.'&region='.$region)
            ->assertOk()
            ->assertJsonPath('data.summary.beneficiaries', 15)
            ->assertJsonPath('data.snapshot', null);
    }

    public function test_a_year_and_a_range_together_are_rejected(): void
    {
        $this->getJson('/api/v1/organization?year=2017&from=2018')->assertStatus(422);
        $this->getJson('/api/v1/organization?from=2018&to=2017')->assertStatus(422);
        $this->getJson('/api/v1/organization?program=missing')->assertStatus(422);
    }
}
