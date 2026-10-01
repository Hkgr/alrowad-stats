# عقود API — إحصاءات الرواد (v1)

هذا المجلد **توثيق فقط** (عقود وأمثلة طلبات)، وليس تطبيقًا. التنفيذ في `backend/`:
المسارات في [`backend/routes/api.php`](../backend/routes/api.php)، والحسابات في
`backend/app/Services/Dashboard/`. أمثلة جاهزة للتجربة في [`examples.http`](examples.http).

- الأساس: `/api/v1`
- الصيغة: JSON، وكل استجابة ناجحة داخل مفتاح `data`.
- المرحلة 1 للقراءة فقط وبلا مصادقة؛ المرحلة 2 تضعها خلف `auth:sanctum`.
- الأعداد أعداد صحيحة كاملة (بلا اختصار). النسب `share` بنسبة مئوية بخانة عشرية واحدة.
- **غياب البيانات ليس صفرًا**: عند عدم وجود سجلات للفلتر تكون القيم `null`، و`has_data: false`.

## الفلاتر (Query String)

| المعامل | مطلوب | الصيغة | ملاحظات |
|---|---|---|---|
| `institution` | نعم | slug المؤسسة، مثل `rowad` | يجب أن تكون المؤسسة موجودة وفعالة |
| `project` | لا | slug المشروع | يجب أن ينتمي لنفس المؤسسة |
| `period` | لا | `YYYY-MM` مثل `2026-05` | يجب أن توجد الفترة للمؤسسة |
| `office` | لا | slug المكتب | يجب أن ينتمي لنفس المؤسسة |

كل المعاملات الاختيارية تضيّق النتائج داخل المؤسسة المحددة فقط. أي قيمة لا تنتمي للمؤسسة تُرفض بـ `422`.
المعامل الفارغ يُعامل كغير موجود.

## الأخطاء

التحقق الفاشل `422` بصيغة Laravel القياسية، ورسائله بالعربية:

```json
{
  "message": "المكتب المحدد لا ينتمي إلى هذه المؤسسة.",
  "errors": { "office": ["المكتب المحدد لا ينتمي إلى هذه المؤسسة."] }
}
```

مسارات `/api/*` تُرجع JSON دائمًا حتى بدون ترويسة `Accept`.

---

## `GET /api/v1/health`

فحص التشغيل والاتصال بقاعدة البيانات.

```json
{ "status": "ok", "application": "Rowad Insights", "database": "connected" }
```

## `GET /api/v1/institutions`

المؤسسات الفعالة. الواجهة تختار منها المؤسسة الافتراضية ولا تثبّتها في الكود.

```json
{ "data": [ { "slug": "rowad", "name": "مؤسسة الرواد" } ] }
```

## `GET /api/v1/filters?institution=rowad[&project=…][&period=…]`

الخيارات الموجودة **فعليًا** في البيانات، وهي متتالية:

- `projects`: المشاريع التي لها سجلات.
- `periods`: الفترات التي لها سجلات (للمشروع المحدد إن وُجد).
- `offices`: المكاتب التي لها سجلات (للمشروع والفترة المحددين إن وُجدا).

```json
{
  "data": {
    "institution": { "slug": "rowad", "name": "مؤسسة الرواد" },
    "projects": [ { "slug": "loba-wa-farha", "name": "لعبة وفرحة" } ],
    "periods":  [ { "key": "2026-05", "label": "أيار 2026", "year": 2026, "month": 5 } ],
    "offices":  [ { "slug": "jarabulus", "name": "جرابلس" } ]
  }
}
```

## `GET /api/v1/dashboard?institution=rowad[&project=…][&period=…][&office=…]`

كل ما تعرضه لوحة العرض، محسوبًا في الـbackend من نفس الصفوف:

```json
{
  "data": {
    "institution": { "slug": "rowad", "name": "مؤسسة الرواد" },
    "filters": {
      "project": null,
      "period": null,
      "office": { "slug": "jarabulus", "name": "جرابلس" }
    },
    "measure": {
      "code": "registered_benefits",
      "name": "الاستفادات المسجلة",
      "unit": "person_participation",
      "unit_label": "استفادة مسجلة",
      "record_level": "project_office_month",
      "record_level_label": "مشروع ومكتب وشهر",
      "aggregation": "sum",
      "aggregation_label": "مجموع",
      "description": "…"
    },
    "has_data": true,
    "summary": {
      "total": 250, "male": 120, "female": 130,
      "male_share": 48.0, "female_share": 52.0,
      "offices_count": 1
    },
    "offices": [
      { "slug": "jarabulus", "name": "جرابلس", "male": 120, "female": 130, "total": 250, "share": 100.0 }
    ],
    "comparison": [
      { "slug": "al-bab", "name": "الباب", "male": 128, "female": 122, "total": 250, "selected": false },
      { "slug": "jarabulus", "name": "جرابلس", "male": 120, "female": 130, "total": 250, "selected": true }
    ],
    "scope": {
      "projects": [ { "slug": "loba-wa-farha", "name": "لعبة وفرحة",
                      "sector": "مسار الثقافة والرياضة والتسلية والفنون", "source_category": "نشاط ترفيهي" } ],
      "periods":  [ { "key": "2026-05", "label": "أيار 2026" } ],
      "sources":  [ { "label": "لعبة وفرحة 2026 — عينة أيار", "file_name": "لعبة و فرحة 2026.xlsx",
                      "reference_url": "https://docs.google.com/…", "coverage": "sample", "notes": "…" } ]
    }
  }
}
```

### معاني الحقول

| الحقل | المعنى |
|---|---|
| `summary` | مجاميع النطاق **بعد** كل الفلاتر. `total = male + female` دائمًا. كل قيمه `null` عند `has_data=false`. |
| `offices` | صفوف المكاتب بعد الفلاتر مرتبة بالإجمالي تنازليًا؛ تغذي البطاقات والجدول وتوزيع الجنسين. `share` نسبة المكتب من `summary.total`. |
| `comparison` | نفس النطاق لكن **بدون فلتر المكتب**، والمكتب المحدد `selected: true`. تستعمله الشارتات لإبراز المكتب المختار مع إبقاء سياق بقية المكاتب. |
| `scope` | المشاريع والفترات والمصادر التي تقف خلف الأرقام. `sector` تكون `null` عندما لا يوجد مسار موثّق للمشروع. `coverage`: `sample` (عينة من المصدر) أو `full` (استيراد كامل). |
| `measure` | تعريف الرقم: الوحدة ومستوى السجل وطريقة التجميع. |

لا يوجد حقل خط زمني أو نمو في هذه النسخة عمدًا: لا توجد فترات قابلة للمقارنة.

### حالات الغياب

`GET /dashboard?institution=rowad&office=jarabulus&period=2026-06` (فترة موجودة بلا سجلات) تُرجع `200`:

```json
{ "data": { "has_data": false,
            "summary": { "total": null, "male": null, "female": null,
                         "male_share": null, "female_share": null, "offices_count": null },
            "offices": [], "comparison": [], "…": "…" } }
```
