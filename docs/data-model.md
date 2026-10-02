# نموذج البيانات

## الفكرة

كل رقم في المنصة له **تعريف** (`measures`) يحدد ثلاثة أشياء، حتى لا تختلط أنواع القياس:

| ما يحدده | الحقل | الاستفادات المسجلة | الأسر المستفيدة |
|---|---|---|---|
| ماذا نعدّ (وحدة القياس) | `unit` / `unit_label` | `person_participation` — «استفادة مسجلة» | `household` — «أسرة» (+ `items_label`: الأضاحي) |
| ما معنى الصف الواحد | `record_level` | `activity_office_month` — نشاط ومكتب وشهر | نفسه |
| كيف تُجمع الصفوف | `aggregation` | `sum` | `sum` |

«الاستفادات المسجلة» تعدّ **مرات استفادة** مسجلة. الشخص الذي يستفيد مرتين يُحتسب مرتين، لذلك لا نسميها «مستفيدين فريدين» في أي مكان.
القياسان لا يُجمعان أبدًا: الأسر تظهر في بطاقة منفصلة.

## الجداول

```
institutions ─┬─ sectors (المسارات: code, sort_order)
              ├─ projects ─┬─ project_categories (Level 1: تصنيف ملف المشروع)
              │            └─ main_activities (Level 2 «الأنشطة الرئيسية»؛ category_id اختياري)
              │                  └─ sub_activities (Level 3 «الأنشطة الفرعية»)
              ├─ project_sector_assignments (project, sector, reference_year)  ← تصنيف بحسب السنة
              ├─ offices
              ├─ periods (شهري: year + month)
              ├─ data_sources (sample | full | reference) · source_files (المسار + SHA-256)
              ├─ import_runs ── import_issues (excluded | conflict | resolved | info)
              └─ activity_records ── measure_id → measures
                       └─ activity_record_revisions (created | updated | deactivated | reactivated | superseded | migrated)
```

### activity_records

صف واحد = **أدق مستوى موثق في المصدر**: مشروع، شهر، مكتب، قياس، و(إن وُجدت) Level 1 والنشاط الرئيسي والنشاط الفرعي،
مع «رقم الدورة» وخصائص الصف (الاختصاص/الجامعة، أو Level 3 بلا أصل في `details.level_3`).

- المفتاح الطبيعي: `(project_id, period_id, office_id, measure_id, detail_key)` حيث `detail_key` بصمة لمسار النشاط
  (أسماء Level 1/2/3 الموحَّدة، رقم الدورة، الخصائص) **وترتيب التكرار** داخل نفس المكتب والشهر — فلا يمحو upsert صفين متشابهين.
- الحقول: الأعمار الأربعة، `male_count`, `female_count` (null = غير مذكور في المصدر)، `disabled_count` (جزء من الإجمالي)،
  `total_count`، `items_count` (للأسر: الأضاحي)، `sections_count`، `course_number` (نص لا يُجمع).
- المصدر: `source_file_id` (وبصمته)، `source_sheet`, `source_row`, `import_run_id`, `data_source_id`؛ و`is_active`.
- لا يُخزَّن أي إجمالي أب: النشاط الفرعي ← الرئيسي ← المشروع ← المسار ← المؤسسة كلها مجاميع للسجلات نفسها.
- غياب الصف يعني «لا بيانات»، لا صفرًا. السجلات لا تُحذف: ما يختفي من ملف عند إعادة استيراده يُعطَّل مع revision.

### هوية الأنشطة

- النشاط الرئيسي يتبع مشروعه وسياقه في Level 1: الهوية `(project_id, category_key, name_key)`؛ نفس الاسم في مشروعين
  أو تحت Level 1 مختلفين = نشاطان مختلفان. `slug` فريد داخل المشروع.
- النشاط الفرعي يتبع نشاطه الرئيسي: `(main_activity_id, name_key)`، و`slug` فريد داخله.
- Level 1 لا يتحول إلى مسار ولا إلى نشاط رئيسي. إن لم يوجد Level 2 تبقى السجلات على مستوى المشروع بتصنيفها الأصلي.
- Level 3 بلا Level 2 واضح (بعد فحص الدمج وورقة Total) يُحفظ في `details.level_3` ويُسجَّل تعارضًا، دون اختراع نشاط رئيسي.

## العزل بين المؤسسات

كل جدول يحمل `institution_id` ومفتاحًا فريدًا `(institution_id, id)`. الجداول الفرعية تشير إلى الزوج كله
بمفاتيح أجنبية مركّبة، فقاعدة البيانات نفسها ترفض سجلًا يربط مشروعًا أو مكتبًا أو فترة من مؤسسة أخرى
(مغطى باختبار، ومُتحقَّق منه على MySQL الفعلي). وعلى مستوى التطبيق:

- `InstitutionFiltersRequest` يتحقق أن المسار والمشروع والمكتب والفترة تنتمي للمؤسسة المحددة، وأن المشروع يتبع المسار المختار،
  وأن النشاط الرئيسي يتبع المشروع والنشاط الفرعي يتبع النشاط الرئيسي.
- كل استعلام في `BeneficiaryDashboardService` و`FilterOptionsService` مقيّد بـ`institution_id` و`measure_id`.
- لا يوجد `institution_id` ثابت في أي خدمة؛ المؤسسة تأتي من الطلب.

## كيف نضيف نوع قياس آخر دون خلط

القاعدة: **لا نجمع أبدًا قياسين مختلفي الوحدة**، ولا نبني جدولًا مرنًا عامًا (EAV).

1. **قياس بنفس الشكل** (أشخاص موزعون بين ذكور وإناث، بمستوى نشاط/مكتب/شهر) مثل «المتدربون المسجلون»:
   أضف صفًا في `measures` بوحدة ورمز جديدين (في `MeasureSeeder`)، وسجّل صفوفه في `activity_records` بذلك `measure_id`.
   الخدمة تُمرَّر رمز القياس بدل الثابت `Measure::REGISTERED_BENEFITS`، ويبقى كل استعلام مقيدًا بقياس واحد.
2. **قياس بوحدة مختلفة** (أسر، خدمات مقدمة، مبالغ، مخزون…): قياس جديد في `measures` بوحدته، وصفوفه في
   `activity_records` تستخدم `total_count` (وحدة القياس) و`items_count` (كمية ثانوية مسماة بـ`items_label`) فقط، ولا تملأ
   `male_count`/`female_count`. مثال مطبّق: «أضحيتي» (`households_served`). الخدمة تقرأ كل قياس على حدة ولا تجمعها.
3. **مستوى سجل أدق أو أعلى** (يومي، أو مستوى المشروع كاملًا دون مكتب): قياس جديد بقيمة `record_level` جديدة،
   وتُضاف تسميتها في `Measure::RECORD_LEVEL_LABELS`. لا تُجمع صفوف بمستويين مختلفين في استعلام واحد.
4. لكل قياس جديد: نقطة API وخدمة تحسب منه، وتعريف الوحدة ومستوى السجل والتجميع يبقى في `measures` وفي هذه الوثيقة. الواجهة تعرض اسم المؤشر ووحدته باختصار فقط.

## المسار (القطاع) — تصنيف بحسب سنة المرجع

قائمة المشاريع لدى المؤسسة (`data/Projects List_2026_09-29.xlsx`) مرجع **2026**. لو خُزّن المسار في عمود واحد على
المشروع لأعاد تصنيف سجلات 2025 ضمنيًا. لذلك (migration `2026_10_03_000100`):

- الربط في `project_sector_assignments` بمفتاح فريد `(project_id, reference_year)`، مع `source_name` (الاسم كما ورد في الملف)
  و`data_source_id` (الملف المرجعي). مفاتيح أجنبية مركّبة تمنع ربط مشروع بمسار من مؤسسة أخرى.
- السجل يتبع المسار عبر ربط مشروعه **لسنة فترته نفسها** (`psa.reference_year = periods.year`).
  سجل 2025 لمشروع مصنف في 2026 فقط يظهر «غير مصنف» (`unclassified` في الـAPI) ولا يُنسب لأي مسار.
- قوائم المشاريع داخل المسار تستخدم «سنة التصنيف»: سنة الفترة المختارة، وإلا أحدث سنة لها تصنيف.
- نُقل الربط السابق (`projects.sector_id` للمشروع «لعبة وفرحة») إلى الجدول الجديد كربط 2026 ثم حُذف العمود.
  الـmigration قابل للعكس (`down()` يعيد العمود من أحدث ربط).

### الاستيراد

```powershell
php artisan rowad:import-classification "..\data\Projects List_2026_09-29.xlsx" --year=2026 [--dry-run]
```

- يقرأ ورقة «قائمة المشاريع» فقط للتصنيف: رأس فيه أسماء المسارات، وتحته رموزها (EDU, CUL, DEV, HLT, CHR)، ثم المشاريع.
- **لا يقرأ** الحالات ولا أعداد المستفيدين أو الكادر ولا الأرقام الشهرية ولا إحصاءات المكاتب ولا الملخص التنفيذي.
- المطابقة: (1) mapping صريح في `config/project_classification.php` (`aliases`)، ثم (2) تطابق تام بعد التوحيد
  (`App\Support\ArabicName::normalize`: حذف التشكيل والتطويل وتوحيد الألف والياء والتاء المربوطة، وحذف «مشروع» في البداية،
  وتوحيد المسافات و«و »). لا مطابقة تقريبية؛ اسمان مختلفان بعد التوحيد مشروعان مختلفان.
- الاسم المعروض يُحفظ في `projects.name` (دون كلمة «مشروع» في بدايته)، والأصل كما ورد في `project_sector_assignments.source_name`.
- ورقتا «مشاريع حسب الزمن» و«مشاريع حسب المكتب» للمطابقة فقط؛ الاختلافات الكتابية المعروفة فيهما في `cross_check_aliases`.
  عند التعارض تُعتمد «قائمة المشاريع» ويُسجَّل التعارض في التقرير.
- التقرير: `backend/storage/app/reports/project-classification-2026.md` (والتجريبي `.dry-run.md`)، وهو خارج Git.

عمود `level 1` في ملف «لعبة وفرحة» (نشاط ترفيهي) يُحفظ كما هو في `projects.source_category` وليس مسارًا.

## استيراد الإحصاءات

```powershell
php artisan rowad:import-statistics --dry-run     # كل شيء في معاملة تُلغى + تقرير .dry-run.md
php artisan rowad:import-statistics               # فعلي، قابل لإعادة التشغيل
php artisan rowad:import-statistics --only="أثر"  # ملفات يحتوي مسارها النص
```

- الإعداد: `backend/config/statistics_import.php` (ربط كل ملف بمشروعه وسنته وقياسه؛ ملف غير مدرج لا يُستورد).
- القواعد والنتائج والتعارضات: [statistics-activities-themes.md](statistics-activities-themes.md).
- التقرير: `backend/storage/app/reports/statistics-import.md`، والسجل الكامل في `import_runs` و`import_issues`.
- سجلات العينة القديمة (`coverage = sample`) لمشروع ما تُعطَّل تلقائيًا (superseded) عند استيراد مصدره الحقيقي، و`db:seed` لا يعيدها.
