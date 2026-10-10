import { useMemo } from 'react'
import { axisLabelStyle, categoryLabelStyle, chartColors, chartFont, gridLine, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { ChartLegend } from '../../../components/charts/ChartLegend'
import { EChart } from '../../../components/charts/EChart'
import { formatNumber, formatPercent } from '../../../lib/format'
import type { Analytics } from '../../../types/api'

type Pyramid = NonNullable<Analytics['pyramid']>

const GROUPS = [
  { key: 'adult', label: '18 سنة فأكثر' },
  { key: 'under_18', label: 'أقل من 18 سنة' },
] as const

/**
 * Age group × gender, as two bars back to back. Males extend to one side and females to the other
 * from a common centre line. The part whose age (or gender) the source does not report is not
 * drawn and not guessed: it is stated under the chart with its share of the total.
 */
export function PyramidChart({ pyramid }: { pyramid: Pyramid }) {
  const both = pyramid.genders.length === 2
  const showMale = pyramid.genders.includes('male')
  const showFemale = pyramid.genders.includes('female')
  const unreportedShare = pyramid.total > 0 ? Math.round((pyramid.unreported / pyramid.total) * 1000) / 10 : null

  const option = useMemo(() => {
    const side = (gender: 'male' | 'female', sign: 1 | -1, color: string, label: string, textColor: string) => ({
      id: gender,
      name: label,
      type: 'bar',
      stack: both ? 'pyramid' : undefined,
      barWidth: 34,
      itemStyle: { color, borderRadius: sign === 1 ? [0, 6, 6, 0] : [6, 0, 0, 6] },
      label: {
        show: true,
        position: 'inside',
        fontFamily: chartFont.family,
        fontWeight: 700,
        color: textColor,
        formatter: (p: { value: number }) => (p.value === 0 ? '' : intl.format(Math.abs(p.value))),
      },
      data: GROUPS.map((g) => sign * pyramid[g.key][gender]),
    })

    return {
      grid: { top: 8, bottom: 4, left: 8, right: 8, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { dataIndex: number }) => {
          const g = GROUPS[p.dataIndex]
          return tooltipHtml(g.label, [
            ...(showMale ? [{ color: chartColors.male, label: 'ذكور', value: intl.format(pyramid[g.key].male) }] : []),
            ...(showFemale ? [{ color: chartColors.female, label: 'إناث', value: intl.format(pyramid[g.key].female) }] : []),
          ])
        },
      },
      xAxis: { type: 'value', splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(Math.abs(v)) } },
      yAxis: { type: 'category', data: GROUPS.map((g) => g.label), axisTick: { show: false }, axisLine: { show: false }, axisLabel: categoryLabelStyle },
      series: [
        ...(showMale ? [side('male', both ? -1 : 1, chartColors.male, 'ذكور', chartColors.onMale)] : []),
        ...(showFemale ? [side('female', 1, chartColors.female, 'إناث', chartColors.onFemale)] : []),
      ],
    }
  }, [pyramid, both, showMale, showFemale])

  const total = (g: (typeof GROUPS)[number]['key']) => pyramid[g].male + pyramid[g].female
  const summary = GROUPS.map((g) => `${g.label}: ذكور ${intl.format(pyramid[g.key].male)}، إناث ${intl.format(pyramid[g.key].female)}`).join('؛ ')

  return (
    <div>
      <EChart option={option} ariaLabel={`الفئات العمرية حسب الجنس. ${summary}`} className="h-40" />
      <ChartLegend
        className="mt-3 justify-center"
        items={[
          ...(showMale ? [{ key: 'male', label: 'ذكور', color: chartColors.male }] : []),
          ...(showFemale ? [{ key: 'female', label: 'إناث', color: chartColors.female }] : []),
        ]}
      />
      <p className="mt-3 text-sm text-ink-muted">
        {GROUPS.map((g) => `${g.label}: ${formatNumber(total(g.key))}`).join(' · ')}
      </p>
      {pyramid.unreported > 0 && (
        <p className="mt-1 text-sm text-ink-muted">
          عمر {formatNumber(pyramid.unreported)} ({formatPercent(unreportedShare)} من الإجمالي) غير مذكور في المصدر، فلا يظهر في الرسم.
        </p>
      )}
    </div>
  )
}
