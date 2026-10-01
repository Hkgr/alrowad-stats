import { useMemo } from 'react'
import { axisLabelStyle, chartColors, chartFont, DIMMED_OPACITY, gridLine, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { EChart } from '../../../components/charts/EChart'
import { formatPercent } from '../../../lib/format'
import type { ComparisonRow } from '../../../types/api'

interface Props {
  rows: ComparisonRow[]
  onSelectOffice: (slug: string) => void
}

/** Horizontal ranking of offices by total. Clicking a bar toggles the office filter. */
export function OfficeRankingChart({ rows, onSelectOffice }: Props) {
  const hasSelection = rows.some((row) => row.selected)

  const option = useMemo(() => {
    const grandTotal = rows.reduce((sum, row) => sum + row.total, 0)

    return {
      aria: { enabled: false },
      // RTL layout: the category axis sits on the right and bars grow leftwards.
      grid: { top: 8, bottom: 8, left: 56, right: 8, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { row: ComparisonRow } }) => {
          const r = p.data.row
          return tooltipHtml(r.name, [
            { color: chartColors.male, label: 'ذكور', value: intl.format(r.male) },
            { color: chartColors.female, label: 'إناث', value: intl.format(r.female) },
            { label: 'الإجمالي', value: intl.format(r.total), strong: true },
            { label: 'من إجمالي النطاق', value: formatPercent(grandTotal > 0 ? (r.total / grandTotal) * 100 : null) },
          ])
        },
      },
      xAxis: {
        type: 'value',
        inverse: true,
        splitLine: gridLine,
        axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(v) },
      },
      yAxis: {
        type: 'category',
        inverse: true,
        position: 'right',
        data: rows.map((row) => row.name),
        axisTick: { show: false },
        axisLine: { lineStyle: { color: chartColors.axis } },
        axisLabel: { ...axisLabelStyle, color: chartColors.ink, fontWeight: 600, fontSize: 14, margin: 12 },
      },
      series: [
        {
          id: 'total',
          type: 'bar',
          barWidth: 22,
          cursor: 'pointer',
          itemStyle: { borderRadius: [4, 0, 0, 4] },
          label: {
            show: true,
            position: 'left',
            distance: 8,
            fontFamily: chartFont.family,
            fontWeight: 700,
            fontSize: 13,
            color: chartColors.ink,
            formatter: (p: { value: number }) => intl.format(p.value),
          },
          emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(20,39,78,.25)' } },
          data: rows.map((row) => ({
            key: row.slug,
            row,
            value: row.total,
            itemStyle: { color: chartColors.total, opacity: hasSelection && !row.selected ? DIMMED_OPACITY : 1 },
            label: { opacity: hasSelection && !row.selected ? 0.45 : 1 },
          })),
        },
      ],
    }
  }, [rows, hasSelection])

  const summary = rows.map((r) => `${r.name} ${intl.format(r.total)}`).join('، ')

  return (
    <EChart
      option={option}
      ariaLabel={`رسم أعمدة أفقي يرتب المكاتب حسب إجمالي الاستفادات المسجلة: ${summary}`}
      className="h-[22rem]"
      onSelect={onSelectOffice}
    />
  )
}
