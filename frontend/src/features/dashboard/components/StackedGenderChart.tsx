import { useMemo } from 'react'
import { axisLabelStyle, chartColors, chartFont, DIMMED_OPACITY, gridLine, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { ChartLegend } from '../../../components/charts/ChartLegend'
import { EChart } from '../../../components/charts/EChart'
import type { ComparisonRow } from '../../../types/api'

interface Props {
  rows: ComparisonRow[]
  onSelectOffice: (slug: string) => void
}

type Gender = 'male' | 'female'

/** Stacked columns: male + female per office. Clicking a column toggles the office filter. */
export function StackedGenderChart({ rows, onSelectOffice }: Props) {
  const hasSelection = rows.some((row) => row.selected)

  const option = useMemo(() => {
    const series = (gender: Gender, name: string, color: string, labelColor: string, isTop: boolean) => ({
      id: gender,
      name,
      type: 'bar' as const,
      stack: 'total',
      barMaxWidth: 54,
      cursor: 'pointer',
      // 2px surface gap between stacked segments; the data end of the stack is rounded.
      itemStyle: { borderColor: chartColors.surface, borderWidth: 2, borderRadius: isTop ? [6, 6, 0, 0] : 0 },
      label: {
        show: true,
        position: 'inside' as const,
        fontFamily: chartFont.family,
        fontWeight: 700,
        fontSize: 12.5,
        color: labelColor,
        formatter: (p: { value: number }) => intl.format(p.value),
      },
      emphasis: { focus: 'self' as const },
      data: rows.map((row) => ({
        key: row.slug,
        row,
        gender,
        value: row[gender],
        itemStyle: { color, opacity: hasSelection && !row.selected ? DIMMED_OPACITY : 1 },
        label: { opacity: hasSelection && !row.selected ? 0.5 : 1 },
      })),
    })

    return {
      grid: { top: 16, bottom: 8, left: 8, right: 56, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { row: ComparisonRow } }) => {
          const r = p.data.row
          return tooltipHtml(r.name, [
            { color: chartColors.male, label: 'ذكور', value: intl.format(r.male) },
            { color: chartColors.female, label: 'إناث', value: intl.format(r.female) },
            { label: 'الإجمالي', value: intl.format(r.total), strong: true },
          ])
        },
      },
      // RTL: first office on the right, value axis on the right.
      xAxis: {
        type: 'category',
        inverse: true,
        data: rows.map((row) => row.name),
        axisTick: { show: false },
        axisLine: { lineStyle: { color: chartColors.axis } },
        axisLabel: { ...axisLabelStyle, color: chartColors.ink, fontWeight: 600, fontSize: 13.5, interval: 0, margin: 12 },
      },
      yAxis: {
        type: 'value',
        position: 'right',
        splitLine: gridLine,
        axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(v) },
      },
      series: [
        series('male', 'ذكور', chartColors.male, chartColors.onMale, false),
        series('female', 'إناث', chartColors.female, chartColors.onFemale, true),
      ],
    }
  }, [rows, hasSelection])

  const summary = rows.map((r) => `${r.name}: ذكور ${intl.format(r.male)} وإناث ${intl.format(r.female)}`).join('، ')

  return (
    <div>
      <ChartLegend
        className="mb-2"
        items={[
          { key: 'male', label: 'ذكور', color: chartColors.male },
          { key: 'female', label: 'إناث', color: chartColors.female },
        ]}
      />
      {/* Seven columns need room: on narrow screens the chart scrolls sideways instead of overlapping labels. */}
      <div className="overflow-x-auto">
        <div className="min-w-[34rem]">
          <EChart
            option={option}
            ariaLabel={`أعمدة مكدسة تقارن الذكور والإناث بين المكاتب. ${summary}`}
            className="h-[22rem]"
            onSelect={onSelectOffice}
          />
        </div>
      </div>
    </div>
  )
}
