import { BookOpen, Calculator, Layers, Users } from 'lucide-react'
import { Fact } from '../../../components/ui/Fact'
import { formatNumber, formatPercent } from '../../../lib/format'
import type { Analytics } from '../../../types/api'

type Courses = NonNullable<Analytics['courses']>

const cell = 'border-b border-line px-3 py-2 text-center'
const head = 'whitespace-nowrap border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-xs font-bold text-ink-soft'

/** Decimal with one place, Latin digits like the rest of the dashboard. */
const decimal = (value: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(value)

/**
 * Courses of the scope. A course is one project + course number (the same number runs in several
 * offices and months). The unit is always "registered benefits": a person who attends in several
 * months is registered in each, so these are not unique people per course.
 */
export function CoursesPanel({ courses, onOpenProject }: { courses: Courses; onOpenProject: (slug: string) => void }) {
  const { sections } = courses

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Fact icon={BookOpen} label="دورات" value={formatNumber(courses.courses)} caption="مشروع + رقم الدورة" />
        <Fact icon={Users} label="استفادات الدورات" value={formatNumber(courses.total)} caption={`${formatPercent(courses.coverage)} من استفادات النطاق`} />
        <Fact icon={Calculator} label="متوسط الاستفادات للدورة" value={formatNumber(courses.average_per_course)} caption="استفادة مسجلة لكل دورة" />
        {sections ? (
          <Fact
            icon={Layers}
            label="الشعب"
            value={`${decimal(sections.benefits_per_section)} استفادة/شعبة`}
            caption={`متوسط ${decimal(sections.average_per_record)} شعبة لكل مكتب في الشهر`}
          />
        ) : (
          <Fact icon={Layers} label="الشعب" value="—" caption="عدد الشعب غير مذكور في المصدر" />
        )}
      </div>

      <div className="overflow-x-auto rounded-xl border border-line">
        {courses.list.length > 0 ? (
          <table className="w-full border-separate border-spacing-0 text-sm">
            <caption className="sr-only">دورات المشروع</caption>
            <thead>
              <tr>
                <th scope="col" className={`${head} text-start`}>رقم الدورة</th>
                <th scope="col" className={head}>المكاتب</th>
                <th scope="col" className={head}>الأشهر</th>
                <th scope="col" className={head}>الاستفادات المسجلة</th>
              </tr>
            </thead>
            <tbody>
              {courses.list.map((c) => (
                <tr key={c.number}>
                  <th scope="row" className="border-b border-line px-3 py-2 text-start font-semibold text-ink">دورة <span className="num">{c.number}</span></th>
                  <td className={cell}><span className="num">{formatNumber(c.offices)}</span></td>
                  <td className={cell}><span className="num">{formatNumber(c.months)}</span></td>
                  <td className={`${cell} font-bold text-ink`}><span className="num">{formatNumber(c.total)}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <table className="w-full border-separate border-spacing-0 text-sm">
            <caption className="sr-only">الدورات حسب المشروع</caption>
            <thead>
              <tr>
                <th scope="col" className={`${head} text-start`}>المشروع</th>
                <th scope="col" className={head}>الدورات</th>
                <th scope="col" className={head}>الاستفادات المسجلة</th>
                <th scope="col" className={head}>المتوسط للدورة</th>
              </tr>
            </thead>
            <tbody>
              {courses.by_project.map((p) => (
                <tr key={p.slug}>
                  <th scope="row" className="border-b border-line px-3 py-2 text-start font-semibold text-ink">
                    <button type="button" onClick={() => onOpenProject(p.slug)} className="cursor-pointer text-start hover:underline">
                      {p.name}
                    </button>
                  </th>
                  <td className={cell}><span className="num">{formatNumber(p.courses)}</span></td>
                  <td className={`${cell} font-bold text-ink`}><span className="num">{formatNumber(p.total)}</span></td>
                  <td className={cell}><span className="num">{formatNumber(p.average_per_course)}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <p className="text-sm text-ink-muted">
        الأرقام استفادات مسجلة وليست أشخاصًا فريدين: من يحضر في أكثر من شهر يُسجَّل في كل شهر. تدخل هنا السجلات التي ذُكر رقم دورتها فقط، والشعب
        تُحسب من السجلات التي ذُكر عدد شعبها.
      </p>
    </div>
  )
}
