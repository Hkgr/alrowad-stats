# إحصاءات الرواد

منصة لعرض وتحليل بيانات مشاريع مؤسسة الرواد لعامي 2025 و2026، مع تفاعل بين الفلاتر والشارتات يشبه Power BI.
الحالة: **المرحلة 1** — تأسيس النموذج وعرض أول مقطع (مشروع «لعبة وفرحة»، أيار 2026) من قاعدة البيانات إلى الشاشة.
التفاصيل في [docs/phase-1.md](docs/phase-1.md).

## البنية

| المجلد | المحتوى |
|---|---|
| `frontend/` | React + TypeScript + Vite + Tailwind CSS + TanStack Query + ECharts (واجهة عربية RTL، خط Cairo محلي) |
| `backend/` | Laravel 13 + MySQL/MariaDB (InnoDB) + Sanctum: النموذج والخدمات ومسارات API |
| `api/` | عقود API وأمثلة الطلبات (توثيق فقط) |
| `docs/` | وثائق المراحل ونموذج البيانات |
| `data/raw/2025`, `data/raw/2026` | ملفات المصدر الأصلية (غير مرفوعة إلى Git) |

## المتطلبات

PHP 8.3+ مع `pdo_mysql`، وComposer، وNode.js 20+، وخادم MySQL/MariaDB يعمل محليًا.

## التشغيل المحلي (Windows PowerShell)

أول مرة فقط:

```powershell
# 1) الواجهة الخلفية
cd "D:\Website\alrowad stats\backend"
composer install
# تأكد أن ملف .env يحوي إعدادات DB_* الصحيحة وأن قاعدة rowad_insights موجودة (لا تُنشأ تلقائيًا)
php artisan migrate            # آمن: يضيف الجداول الناقصة فقط
php artisan db:seed            # عينة «لعبة وفرحة / أيار 2026»؛ آمن لإعادة التشغيل دون تكرار

# 2) الواجهة الأمامية
cd "D:\Website\alrowad stats\frontend"
npm install
```

كل مرة (طرفيتان):

```powershell
# الطرفية 1: API على http://127.0.0.1:8000
cd "D:\Website\alrowad stats\backend"
php artisan serve --host=127.0.0.1 --port=8000

# الطرفية 2: الواجهة على http://127.0.0.1:5173
cd "D:\Website\alrowad stats\frontend"
npm run dev
```

افتح **http://127.0.0.1:5173/** — الواجهة تمرّر `/api` إلى `127.0.0.1:8000` عبر Vite proxy.
فحص الاتصال: `Invoke-RestMethod http://127.0.0.1:8000/api/v1/health`

## الاختبار والبناء

```powershell
cd "D:\Website\alrowad stats\backend"
php artisan test                 # يستخدم SQLite في الذاكرة، ولا يلمس قاعدة MySQL
php vendor\bin\pint --test       # تنسيق PHP

cd "D:\Website\alrowad stats\frontend"
npx tsc -b                       # فحص TypeScript
npm run lint
npm run build
```

## اختصارات لوحة المفاتيح

`F` وضع العرض (ملء الشاشة) · `/` الانتقال إلى بحث الجدول · `Esc` إغلاق القائمة/الخروج من ملء الشاشة.

## ملاحظات

- لا تستخدم `migrate:fresh` على قاعدة البيانات المحلية.
- الأرقام «استفادات مسجلة» وليست «مستفيدين فريدين». راجع [docs/data-model.md](docs/data-model.md).
