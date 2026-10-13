# عقود API — إحصاءات الرواد (v1)

هذا المجلد **توثيق فقط** (عقود وأمثلة طلبات)، وليس تطبيقًا. التنفيذ في `backend/`:
المسارات في [`backend/routes/api.php`](../backend/routes/api.php)، والحسابات في
`backend/app/Services/Dashboard/`. أمثلة جاهزة للتجربة في [`examples.http`](examples.http).

- الأساس: `/api/v1`
- الصيغة: JSON، وكل استجابة ناجحة داخل مفتاح `data`.
- للقراءة فقط وبلا مصادقة حاليًا؛ المرحلة اللاحقة تضعها خلف `auth:sanctum`.
- الأعداد صحيحة كاملة (بلا اختصار). النسب `*_share`/`share` نسبة مئوية بخانة عشرية واحدة.
- **غياب البيانات ليس صفرًا**: عند عدم وجود سجلات تكون القيم `null` و`has_data: false`.
- القياس الرئيسي: «الاستفادات المسجلة» (`registered_benefits`)؛ والأسر (`households_served`) في `other_measures`. لا تُجمع قياسات مختلفة الوحدة.

## الفلاتر (Query String)

| المعامل | مطلوب | الصيغة | ملاحظات |
|---|---|---|---|
| `institution` | نعم | slug المؤسسة، مثل `rowad` | يجب أن تكون موجودة وفعالة |
| `sector` | لا | slug المسار، مثل `cul`، أو `unclassified` | يجب أن ينتمي للمؤسسة؛ `unclassified` = سجلات بلا تصنيف لسنة فترتها |
| `project` | لا | slug المشروع | يجب أن ينتمي للمؤسسة، ولـ`sector` إن حُدد |
| `main_activity` | لا | slug النشاط الرئيسي (Level 2) | يتطلب `project` ويجب أن يتبعه |
| `sub_activity` | لا | slug النشاط الفرعي (Level 3) | يتطلب `main_activity` ويجب أن يتبعه |
| `period` | لا | `YYYY-MM` مثل `2026-05` | يجب أن توجد الفترة للمؤسسة |
| `office` | لا | slug المكتب | يجب أن ينتمي للمؤسسة |
| `year` | لا | سنة، مثل `2026` | يجب أن توجد للمؤسسة؛ مع `period` يجب أن تكون الفترة ضمنها (وإلا `422`) |
| `from` / `to` | لا | `YYYY-MM` | مجال أشهر **شاملٌ للطرفين**، وكل طرف اختياري (طرف مفتوح = من أول شهر / إلى آخر شهر). `from` بعد `to` أو الجمع مع `period` (شهر مفرد) أو خروجهما عن `year` ← `422`. يطبَّق على كل الأرقام والرسوم والتحليلات والتصدير؛ وخيارات الأشهر في `/filters` لا تتأثر به ليبقى قابلًا للتغيير |
| `gender` | لا | `male` أو `female` | يعدّ ذلك الجنس فقط |
| `age` | لا | `under_18` أو `adult` | يعدّ تلك الفئة العمرية فقط |
| `disabled` | لا | `1` | يعدّ ذوي الاحتياجات الخاصة المسجَّلين فقط؛ **لا يُجمع مع `gender` أو `age`** (`422`) لأن المصدر لا يقسّمهم |

- المعامل الفارغ يُعامل كغير موجود.
- **فلاتر الفئة** (`gender`, `age`, `disabled`): تصبح الأرقام (`summary` والمكاتب والمشاريع والأشهر وغيرها) مجموع خلايا تلك الفئة، ولا يدخل
  إلا السجل الذي يذكر مصدره إحدى خلاياها (الخلية الفارغة «غير مذكور» وليست صفرًا)؛ لذلك قد يقل المجموع عن إجمالي المشروع.
  `demographic: true` في الرد، و`other_measures` فارغة (الأسر لا تُقسَّم)، و`insights.disabled` فارغ، و`insights.ages` فارغ مع `age`/`disabled`.
  مع `gender` وحده يبقى `insights.ages` لذلك الجنس. مع `disabled` يكون الجنس «غير مذكور» لكل المجموع.
- `/filters` يعيد أيضًا `years` (السنوات التي لها سجلات).
- **سنة التصنيف** (`classification_year`): سنة `period` إن حُدد، ثم `year`، ثم سنة المجال إن وقع طرفاه `from`/`to` في سنة واحدة، وإلا أحدث سنة لها تصنيف. تحدد قوائم المشاريع في كل مسار.
  أما الأرقام فتُنسب للمسار وفق تصنيف **سنة فترة كل سجل** (سجل 2025 لا يأخذ تصنيف 2026).
- أي قيمة لا تنتمي للمؤسسة، أو مشروع لا يتبع المسار المحدد في سنة التصنيف، تُرفض بـ `422`.

## الأخطاء

التحقق الفاشل `422` بصيغة Laravel القياسية، ورسائله بالعربية:

```json
{
  "message": "المشروع المحدد لا يتبع هذا المسار.",
  "errors": { "project": ["المشروع المحدد لا يتبع هذا المسار."] }
}
```

مسارات `/api/*` تُرجع JSON دائمًا حتى بدون ترويسة `Accept`.

---

## `GET /api/v1/health`

```json
{ "status": "ok", "application": "Rowad Insights", "database": "connected" }
```

## `GET /api/v1/institutions`

المؤسسات الفعالة. الواجهة تختار منها المؤسسة الافتراضية ولا تثبّتها في الكود.

```json
{ "data": [ { "slug": "rowad", "name": "مؤسسة الرواد" } ] }
```

## `GET /api/v1/filters?institution=rowad[&sector=…][&project=…][&main_activity=…][&sub_activity=…][&period=…]`

إضافة لما يلي: `main_activities` (أنشطة المشروع المحدد: `slug`, `name`, `category`, `has_data`) و`sub_activities` (أنشطة النشاط الرئيسي المحدد).

- `sectors`: كل مسارات المؤسسة بترتيب الملف المرجعي، مع عدد المشاريع المصنفة في سنة التصنيف.
- `projects`: **كتالوج البحث** — كل مشروع مصنف في سنة التصنيف (مع أو بلا بيانات)، وأي مشروع غير مصنف له سجلات.
  `has_data` يعني وجود سجلات ضمن `period` (أو كل الفترات).
- `periods`: الفترات التي لها سجلات ضمن المسار/المشروع المحدد.
- `offices`: المكاتب التي لها سجلات ضمن المسار/المشروع/الفترة.

```json
{
  "data": {
    "institution": { "slug": "rowad", "name": "مؤسسة الرواد" },
    "classification_year": 2026,
    "sectors": [ { "slug": "cul", "code": "CUL", "name": "مسار الثقافة والرياضة والتسلية والفنون", "classified_projects": 11 } ],
    "projects": [ { "slug": "loba-wa-farha", "name": "لعبة وفرحة",
                    "sector": { "slug": "cul", "name": "مسار الثقافة والرياضة والتسلية والفنون" }, "has_data": true } ],
    "periods":  [ { "key": "2026-05", "label": "أيار 2026", "year": 2026, "month": 5 } ],
    "offices":  [ { "slug": "jarabulus", "name": "جرابلس" } ]
  }
}
```

## `GET /api/v1/dashboard?institution=rowad[&sector=…][&project=…][&main_activity=…][&sub_activity=…][&period=…][&office=…]`

الأرقام كلها من `activity_records` الفعالة لقياس «الاستفادات المسجلة»؛ من مستوى المشروع فما دون يكون المسار سياقًا للتصفح فقط
(أرقام المشروع تشمل كل سنواته). مثال مختصر (الحقول الجديدة في هذه المرحلة: `activities`، `other_measures`، `gender_unreported`، `periods[].selected`):

كل ما تعرضه الواجهة، محسوبًا في الـbackend (`BeneficiaryDashboardService`) من نفس السجلات:

```json
{
  "data": {
    "institution": { "slug": "rowad", "name": "مؤسسة الرواد" },
    "level": "project",
    "classification_year": 2026,
    "filters": {
      "sector": null,
      "project": { "slug": "loba-wa-farha", "name": "لعبة وفرحة" },
      "period": null,
      "office": { "slug": "jarabulus", "name": "جرابلس" }
    },
    "active_sector": { "slug": "cul", "code": "CUL", "name": "مسار الثقافة والرياضة والتسلية والفنون" },
    "measure": { "code": "registered_benefits", "name": "الاستفادات المسجلة", "unit_label": "استفادة مسجلة", "…": "…" },
    "has_data": true,
    "summary": {
      "total": 250, "male": 120, "female": 130, "male_share": 48.0, "female_share": 52.0,
      "offices_count": 1, "projects_with_data": 1, "classified_projects": 11
    },
    "sectors": [
      { "slug": "edu", "code": "EDU", "name": "مسار التعليم والتمكين", "selected": false, "classified_projects": 15,
        "has_data": false, "total": null, "male": null, "female": null, "projects_with_data": null, "offices_count": null },
      { "slug": "cul", "code": "CUL", "name": "مسار الثقافة والرياضة والتسلية والفنون", "selected": true, "classified_projects": 11,
        "has_data": true, "total": 250, "male": 120, "female": 130, "projects_with_data": 1, "offices_count": 1 }
    ],
    "unclassified": null,
    "projects": [
      { "slug": "loba-wa-farha", "name": "لعبة وفرحة", "sector": { "slug": "cul", "name": "…" }, "selected": true,
        "has_data": true, "total": 250, "male": 120, "female": 130, "projects_with_data": 1, "offices_count": 1 }
    ],
    "periods": [ { "key": "2026-05", "label": "أيار 2026", "has_data": true, "total": 250, "…": "…" } ],
    "offices": [ { "slug": "jarabulus", "name": "جرابلس", "male": 120, "female": 130, "total": 250, "share": 100.0 } ],
    "comparison": [ { "slug": "al-bab", "name": "الباب", "male": 128, "female": 122, "total": 250, "selected": false } ],
    "sources": [ { "label": "لعبة وفرحة 2026 — عينة أيار", "file_name": "لعبة و فرحة 2026.xlsx", "coverage": "sample" } ]
  }
}
```

### معاني الحقول

| الحقل | المعنى |
|---|---|
| `level` | `overview`، `sector`، `project`، `main_activity`، أو `sub_activity`. |
| `active_sector` | المسار المحدد، أو مسار المشروع المحدد في سنة التصنيف. |
| `summary` | مجاميع النطاق بعد **كل** الفلاتر. `total` مجموع إجماليات السجلات؛ `male`/`female` مجموع المذكور فقط و`gender_unreported = total − male − female` (جنس غير مذكور في المصدر، لا يُقسَّم). `disabled` جزء من `total`. القيم `null` عند `has_data=false`. `offices_count` و`projects_with_data` تُحسب بـ`DISTINCT`. `classified_projects`: المشاريع المصنفة في المسار النشط (أو كلها) لسنة التصنيف. |
| `sectors` | كل المسارات لبطاقات المدخل. أرقامها تحترم `period` و`office` ولا تتأثر بـ`sector`/`project` حتى تبقى قابلة للمقارنة. |
| `unclassified` | سجلات لا يملك مشروعها تصنيفًا لسنة فترتها؛ `null` إن لم توجد. |
| `projects` | مشاريع المسار النشط (أو كل المشاريع المصنفة + غير المصنفة ذات السجلات). المشروع بلا سجلات يظهر بـ`has_data=false` وقيم `null`. |
| `periods` | كل الفترات التي لها سجلات في النطاق (فلتر الفترة لا يُطبَّق هنا، والمحددة `selected: true`) مع أرقامها. لا يوجد حقل نمو أو اتجاه. |
| `activities` | `main_available`/`sub_available` (هل يوجد Level 2/3 في المصدر؛ `null` خارج مستواه)، `main[]` (عند المشروع: `slug`, `name`, `category`, `sub_activities_count`, `selected` + الأرقام)، `without_main` (سجلات بلا نشاط رئيسي)، `sub[]` و`without_sub` (عند النشاط الرئيسي)، و`categories[]` (Level 1 لملف المشروع — تصنيف المصدر وليس نشاطًا). مجموع `sub[]` + `without_sub` = إجمالي النشاط الرئيسي. |
| `other_measures` | قياسات بوحدة أخرى في النطاق نفسه، مثل `households_served`: `total` (أسر)، `items` و`items_label` (الأضاحي). لا تُضاف إلى `summary`. |
| `insights` | مؤشرات تحليلية من نفس الأرقام: `ages` (`under_18`, `adult`, `unreported` + نسبها من **الإجمالي الكامل**، و`coverage` = نسبة الإجمالي التي ذُكر عمرها؛ `null` إن لم يذكر أي سجل عمرًا — الجزء غير المذكور لا يُوزَّع ولا يُقدَّر)، `disabled` (`count`, `share` من الإجمالي — المسجَّل فقط)، `months_with_data`، `monthly_average`، `peak_month` (من أشهر النطاق دون فلتر الفترة)، و`top_office` (من `comparison` دون فلتر المكتب). كلها `null` عند غياب البيانات. |
| `unclassified` | كبطاقة مسار (`slug: "unclassified"`, `name: "غير مصنف"`) مع أرقامها و`selected`. |
| `offices` | صفوف المكاتب بعد كل الفلاتر مرتبة تنازليًا؛ `share` نسبة المكتب من `summary.total`. |
| `comparison` | نفس النطاق دون فلتر المكتب، والمكتب المحدد `selected: true` (لإبراز الاختيار في الرسوم). |
| `sources` | للتتبع فقط؛ الواجهة لا تعرضه. |

### حالات الغياب

مسار مصنف بلا سجلات (`sector=hlt`) يُرجع `200` مع `has_data: false`، وكل أرقام `summary` `null`،
و`classified_projects: 8`، وقائمة `projects` بمشاريعه الثمانية وكل منها `has_data: false`.

## `GET /api/v1/analytics?institution=rowad[…كل فلاتر dashboard…]`

قراءات أعمق للنطاق نفسه (تُحمَّل بعد الأرقام الرئيسية). تتبع كل الفلاتر بما فيها `gender` و`age` و`disabled` و`year`. كل جزء `null` عند غياب بياناته.

| الحقل | المحتوى |
|---|---|
| `heatmap` | (مستوى النظرة العامة والمسار فقط) `offices[]` و`rows[]`: لكل مشروع `total` و`cells{مكتب: {value, share}}`، و`share` حصة المكتب من إجمالي **المشروع نفسه**. الخلية بلا سجل **غائبة** (لا صفر)؛ والصفر الصريح المكتوب في المصدر يظهر `value: 0`. فلتر المكتب لا يُطبَّق هنا. |
| `monthly_stack` | (النظرة العامة والمسار فقط) `group`: `sector` (النظرة العامة) أو `project` (داخل مسار؛ أكبر 8 مشاريع + «مشاريع أخرى»)، `periods[]`، و`series[]` لكل منها `values[]` بترتيب الأشهر (`null` = لا سجل لهذه السلسلة في ذلك الشهر). فلتر الفترة لا يُطبَّق. |
| `pyramid` | `under_18` و`adult` لكل منهما `male`/`female` من خلايا الأعمار المذكورة فقط، و`unreported` (من الإجمالي، غير موزَّع)، و`coverage`، و`genders`. `null` مع `age` أو `disabled` أو بلا أعمار. |
| `courses` | الدورة = (مشروع، رقم الدورة): الرقم سلسلة داخل المشروع تتكرر في مكاتب وأشهر، فتُعدّ مرة واحدة. `courses`, `total`, `average_per_course`, `coverage` (حصة استفادات النطاق التي لها رقم دورة)، و`sections` (`benefits_per_section` و`average_per_record` من السجلات التي ذُكرت شعبها فقط)، و`by_project[]` (فوق المشروع) أو `list[]` (داخله). وحدتها «استفادة مسجلة» لا «أشخاص». |
| `knowledge` | توزيع `specialties[]` (أكبر 12 + `other_specialties`) و`universities[]` من `details` (رواد المعرفة)، و`*_unreported` لما لا يذكر القيمة. |

## `GET /api/v1/data-quality?institution=rowad`

ما لم يستطع آخر استيراد فعلي اعتماده كما هو، ومواضع النقص. لا يتأثر بأي فلتر غير `institution`، ولا يغيّر أي رقم.

| الحقل | المحتوى |
|---|---|
| `run` | آخر تشغيل استيراد فعلي مكتمل (`id`, `finished_at`)، أو `null` إن لم يحدث. |
| `issues` | `severities[]` (مستبعد / تعارض / حُلّ تلقائيًا / معلومة مع أعدادها)، و`groups[]` لكل `code`: `label` عربي و`count` وأمثلة (ملف › ورقة › صف)؛ كل الصفوف المستبعدة (حتى 40) وخمسة أمثلة لغيرها. |
| `gaps[]` | مشاريع تنقصها أشهر **داخل مدتها** (بين أول شهر وآخر شهر لها) مقارنةً بالأشهر التي فيها أرقام لأي مشروع. المشروع الذي يبدأ متأخرًا أو ينتهي مبكرًا ليس ناقصًا. |
| `coverage` | المشاريع المصنفة (لأحدث سنة تصنيف) مقابل التي لها أرقام، وأسماء التي بلا أرقام. |

## `GET /api/v1/export?institution=rowad[…كل فلاتر dashboard…][&format=xlsx|csv]`

تنزيل الأرقام حسب الفلاتر الحالية (الافتراضي `xlsx`). المصدر نفسه الذي تجمعه اللوحة، فكل مجموع في الملف يساوي الرقم على الشاشة، وفلاتر `gender`/`age`/`disabled` تضيّقه بالطريقة نفسها.

- **`csv`**: جدول التفاصيل فقط، UTF-8 مع BOM (يقرأه Excel بالعربية). النص الذي يبدأ بـ `= + - @` يُسبق بـ `'` فلا يُفسَّر صيغةً عند الفتح.
- **`xlsx`** (أوراق من اليمين لليسار): `الملخص` (المؤسسة، الفلاتر المطبّقة، الإجماليات، الوحدات الأخرى كالأسر، والملاحظات)، `التفاصيل`، ثم مجاميع `حسب المسار` و`حسب المشروع` و`حسب المكتب` و`حسب الشهر`.
- **التفاصيل**: صف لكل (شهر، مشروع، مكتب، نشاط رئيسي، نشاط فرعي): `السنة، الشهر، المسار، المشروع، المكتب، النشاط الرئيسي، النشاط الفرعي، ذكور، إناث، الجنس غير مذكور، الإجمالي`، وفيه ذكور + إناث + غير مذكور = الإجمالي. المسار حسب تصنيف سنة السجل، و«غير مصنف» لما لا تصنيف له.
- نطاق بلا سجلات ينتج ملفًا فيه الملخص يقول ذلك وجدول تفاصيل بلا صفوف. `format` غير ذلك أو فلتر غير صالح: `422`.
