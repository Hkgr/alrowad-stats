# frontend

واجهة «إحصاءات الرواد»: React 19 + TypeScript + Vite + Tailwind CSS 4 + TanStack Query + ECharts.
أوامر التشغيل في [../README.md](../README.md).

```
src/
  App.tsx                    المزودات والمسارات
  layout/                    AppShell، الشريط الجانبي، الرأس
  components/ui/             أزرار وبطاقات وحالات (تحميل/فراغ/خطأ)
  components/charts/         غلاف ECharts وثيم الشارتات الموحد ووسيلة الإيضاح
  features/dashboard/        الصفحة وحالة الفلاتر (URL) والاستعلامات ومكونات اللوحة
  services/api/              عميل API
  types/api.ts               أنواع العقود (انظر ../api/README.md)
  lib/, hooks/               تنسيق الأرقام والبحث العربي وملء الشاشة وتقليل الحركة
```

لا توجد أسماء مشاريع أو مكاتب أو أرقام ثابتة في الكود؛ كل شيء من `/api/v1`.
