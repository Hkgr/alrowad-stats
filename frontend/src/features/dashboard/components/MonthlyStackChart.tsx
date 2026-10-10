import { useMemo } from 'react'
import { axisLabelStyle, chartColors, DIMMED_OPACITY, gridLine, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { ChartLegend } from '../../../components/charts/ChartLegend'
import { EChart } from '../../../components/charts/EChart'
import { formatNumber } from '../../../lib/format'
import { themeFor } from '../../../lib/themes'
import type { Analytics } from '../../../types/api'

type Stack = NonNullable<Analytics['monthly_stack']>

/** Distinguishable neutrals for projects (tracks use their own theme colour). */
const PROJECT_COLORS = ['#2f6f8f', '#7a5195', '#bc5090', '#ef5675', '#4f7f52', '#8a6d3b', '#3d6fb0', '#a3563a', '#6b7280']

interface Props {
  stack: Stack
  selectedPeriod: string | null
  onSelectPeriod: (key: string) => void
}

/**
 * Registered benefits per month, split by track (overview) or by project (inside a track). Bars are
 * stacked so each month's height is the real total; a month in which a series has no record simply
 * has no segment for it (absent, not zero). No growth rates are derived.
 */
export function MonthlyStackChart({ stack, selectedPeriod, onSelectPeriod }: Props) {
  const series = useMemo(
    () =>
      stack.series.map((s, index) => {
        const theme = stack.group === 'sector' && s.code ? themeFor(s.code) : null
        const color = s.key === 'unclassified' ? '#a3a8b0' : (theme?.primary ?? PROJECT_COLORS[index % PROJECT_COLORS.length])
        return { ...s, color, outline: theme?.outline }
      }),
    [stack],
  )
  const monthTotals = useMemo(() => stack.periods.map((_, i) => series.reduce((sum, s) => sum + (s.values[i] ?? 0), 0)), [stack, series])

  const option = useMemo(
    () => ({
      grid: { top: 24, bottom: 4, left: 8, right: 48, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: (params: { dataIndex: number }[]) => {
          const i = params[0]?.dataIndex ?? 0
          return tooltipHtml(stack.periods[i].label, [
            ...series.filter((s) => s.values[i] != null).map((s) => ({ color: s.color, label: s.name, value: intl.format(s.values[i] ?? 0) })),
            { label: 'الإجمالي', value: intl.format(monthTotals[i]), strong: true },
          ])
        },
      },
      xAxis: {
        type: 'category',
        inverse: true,
        data: stack.periods.map((p) => p.label),
        axisTick: { show: false },
        axisLine: { lineStyle: { color: chartColors.axis } },
        axisLabel: { ...axisLabelStyle, interval: 0, rotate: stack.periods.length > 8 ? 35 : 0 },
      },
      yAxis: { type: 'value', position: 'right', splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(v) } },
      series: series.map((s) => ({
        id: s.key,
        name: s.name,
        type: 'bar',
        stack: 'months',
        barMaxWidth: 38,
        cursor: 'pointer',
        data: s.values.map((value, i) => ({
          key: stack.periods[i].key,
          value,
          itemStyle: {
            color: s.color,
            borderColor: s.outline ?? '#ffffff',
            borderWidth: s.outline ? 1.5 : 1,
            opacity: selectedPeriod && selectedPeriod !== stack.periods[i].key ? DIMMED_OPACITY : 1,
          },
        })),
      })),
    }),
    [stack, series, monthTotals, selectedPeriod],
  )

  const summary = stack.periods.map((p, i) => `${p.label} ${intl.format(monthTotals[i])}`).join('، ')

  return (
    <div>
      <ChartLegend
        className="mb-2"
        items={series.map((s) => ({ key: s.key, label: s.name, color: s.color, detail: formatNumber(s.total) }))}
      />
      <div className="overflow-x-auto">
        <div style={{ minWidth: `${Math.max(20, stack.periods.length * 3.4)}rem` }}>
          <EChart
            option={option}
            ariaLabel={`الاستفادات المسجلة حسب الشهر موزعة على ${stack.group === 'sector' ? 'المسارات' : 'المشاريع'}: ${summary}`}
            className="h-80"
            onSelect={onSelectPeriod}
          />
        </div>
      </div>
    </div>
  )
}
