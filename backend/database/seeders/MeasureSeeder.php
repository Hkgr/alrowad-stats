<?php

namespace Database\Seeders;

use App\Models\Measure;
use Illuminate\Database\Seeder;

/** Reference data: the measure definitions the dashboard knows how to aggregate. Idempotent. */
class MeasureSeeder extends Seeder
{
    public function run(): void
    {
        Measure::updateOrCreate(
            ['code' => Measure::REGISTERED_BENEFITS],
            [
                'name' => 'الاستفادات المسجلة',
                'unit' => 'person_participation',
                'unit_label' => 'استفادة مسجلة',
                'record_level' => 'project_office_month',
                'aggregation' => 'sum',
                'breakdown' => 'gender',
                'description' => 'عدد مرات استفادة الأشخاص المسجلة في مشروع ومكتب وشهر محدد، موزعة بين ذكور وإناث. '
                    .'الشخص الذي يستفيد أكثر من مرة يُحتسب أكثر من مرة، لذلك لا تمثل الأرقام مستفيدين فريدين.',
            ],
        );
    }
}
