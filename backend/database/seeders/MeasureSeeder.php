<?php

namespace Database\Seeders;

use App\Models\Measure;
use Illuminate\Database\Seeder;

/** Reference data: the measure definitions the dashboard knows how to aggregate. Idempotent. */
class MeasureSeeder extends Seeder
{
    public function run(): void
    {
        self::ensure();
    }

    /** Creates or refreshes the measure definitions (also called by the statistics importer). */
    public static function ensure(): void
    {
        Measure::updateOrCreate(
            ['code' => Measure::REGISTERED_BENEFITS],
            [
                'name' => 'الاستفادات المسجلة',
                'unit' => 'person_participation',
                'unit_label' => 'استفادة مسجلة',
                'record_level' => 'activity_office_month',
                'aggregation' => 'sum',
                'breakdown' => 'gender',
                'items_label' => null,
                'description' => 'عدد مرات استفادة الأشخاص المسجلة لكل مشروع ونشاط ومكتب وشهر، موزعة بين ذكور وإناث. '
                    .'الشخص الذي يستفيد أكثر من مرة يُحتسب أكثر من مرة، لذلك لا تمثل الأرقام مستفيدين فريدين. '
                    .'ذوو الاحتياجات الخاصة جزء من الإجمالي.',
            ],
        );

        Measure::updateOrCreate(
            ['code' => Measure::HOUSEHOLDS_SERVED],
            [
                'name' => 'الأسر المستفيدة',
                'unit' => 'household',
                'unit_label' => 'أسرة',
                'record_level' => 'activity_office_month',
                'aggregation' => 'sum',
                'breakdown' => 'none',
                'items_label' => 'الأضاحي',
                'description' => 'عدد العوائل المستفيدة كما ورد في المصدر (مثل «أضحيتي»)، مع عدد الأضاحي. '
                    .'وحدة مختلفة عن الأشخاص فلا تُجمع معها.',
            ],
        );
    }
}
