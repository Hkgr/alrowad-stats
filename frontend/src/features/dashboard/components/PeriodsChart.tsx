import { useMemo } from 'react'
import { axisLabelStyle, chartColors, chartFont, DIMMED_OPACITY, gridLine, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { EChart } from '../../../components/charts/EChart'
import type { PeriodRow } from '../../../types/api'

interface Props {
  periods: PeriodRow[]
  color: string
  outline?: string
  onSelect: (key: string) => void
}

/**
 * Registered benefits per month, in calendar order. Only months with records are shown (a missing
 * month is absent, not zero). No growth rates or trend lines are derived.
 */
export function PeriodsChart({ periods, color, outline, onSelect }: Props) {
  const hasSelection = periods.some((p) => p.selected)

  const option = useMemo(
    () => ({
      grid: { top: 24, bottom: 4, left: 8, right: 48, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { row: PeriodRow } }) => {
          const r = p.data.row
          return tooltipHtml(r.label, [
            { color: chartColors.male, label: 'ذكور', value: intl.format(r.male ?? 0) },
            { color: chartColors.female, label: 'إناث', value: intl.format(r.female ?? 0) },
            ...(r.gender_unreported ? [{ color: chartColors.unreported, label: 'الجنس غير مذكور', value: intl.format(r.gender_unreported) }] : []),
            { label: 'الإجمالي', value: intl.format(r.total ?? 0), strong: true },
          ])
        },
      },
      xAxis: {
        type: 'category',
        inverse: true,
        data: periods.map((p) => p.label),
        axisTick: { show: false },
        axisLine: { lineStyle: { color: chartColors.axis } },
        axisLabel: { ...axisLabelStyle, interval: 0, rotate: periods.length > 8 ? 35 : 0 },
      },
      yAxis: { type: 'value', position: 'right', splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(v) } },
      series: [
        {
          id: 'periods',
          type: 'bar',
          barMaxWidth: 34,
          cursor: 'pointer',
          itemStyle: { borderRadius: [5, 5, 0, 0] },
          label: {
            show: periods.length <= 8,
            position: 'top',
            fontFamily: chartFont.family,
            fontWeight: 700,
            color: chartColors.ink,
            formatter: (p: { value: number }) => intl.format(p.value),
          },
          data: periods.map((p) => ({
            key: p.key,
            row: p,
            value: p.total ?? 0,
            itemStyle: { color, borderColor: outline, borderWidth: outline ? 1.5 : 0, opacity: hasSelection && !p.selected ? DIMMED_OPACITY : 1 },
          })),
        },
      ],
    }),
    [periods, color, outline, hasSelection],
  )

  const summary = periods.map((p) => `${p.label} ${intl.format(p.total ?? 0)}`).join('، ')

  return (
    <div className="overflow-x-auto">
      <div style={{ minWidth: `${Math.max(20, periods.length * 3.2)}rem` }}>
        <EChart option={option} ariaLabel={`الاستفادات المسجلة حسب الشهر: ${summary}`} className="h-72" onSelect={onSelect} />
      </div>
    </div>
  )
}
