import { useMemo } from 'react'
import {
  axisLabelStyle,
  categoryLabelStyle,
  chartColors,
  chartFont,
  DIMMED_OPACITY,
  gridLine,
  intl,
  tooltipBase,
  tooltipHtml,
} from '../../../components/charts/chartTheme'
import { EChart } from '../../../components/charts/EChart'

export function YearChart({
  years,
  color,
  onSelect,
}: {
  years: { year: number; beneficiaries: number; selected: boolean }[]
  color: string
  onSelect: (year: string) => void
}) {
  const hasSelection = years.some((row) => row.selected)
  const option = useMemo(
    () => ({
      grid: { top: 28, bottom: 4, left: 8, right: 48, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { row: { year: number; beneficiaries: number } } }) =>
          tooltipHtml(String(p.data.row.year), [{ color, label: 'المستفيدون', value: intl.format(p.data.row.beneficiaries), strong: true }]),
      },
      xAxis: {
        type: 'category',
        inverse: true,
        data: years.map((row) => String(row.year)),
        axisTick: { show: false },
        axisLine: { lineStyle: { color: chartColors.axis } },
        axisLabel: { ...axisLabelStyle, interval: 0 },
      },
      yAxis: { type: 'value', position: 'right', splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (value: number) => intl.format(value) } },
      series: [
        {
          id: 'years',
          type: 'bar',
          barMaxWidth: 36,
          cursor: 'pointer',
          itemStyle: { borderRadius: [5, 5, 0, 0] },
          label: {
            show: years.length <= 8,
            position: 'top',
            fontFamily: chartFont.family,
            fontWeight: 700,
            color: chartColors.ink,
            formatter: (p: { value: number }) => intl.format(p.value),
          },
          data: years.map((row) => ({
            key: String(row.year),
            row,
            value: row.beneficiaries,
            itemStyle: { color, opacity: hasSelection && !row.selected ? DIMMED_OPACITY : 1 },
          })),
        },
      ],
    }),
    [years, color, hasSelection],
  )

  const summary = years.map((row) => `${row.year} ${intl.format(row.beneficiaries)}`).join('، ')
  return <EChart option={option} ariaLabel={`المستفيدون حسب السنة: ${summary}`} className="h-72" onSelect={onSelect} />
}

export function RankChart({
  items,
  color,
  ariaLabel,
  onSelect,
}: {
  items: { key: string; name: string; value: number; selected?: boolean }[]
  color: string
  ariaLabel: string
  onSelect?: (key: string) => void
}) {
  const hasSelection = items.some((item) => item.selected)
  const height = Math.max(160, items.length * 52 + 24)
  const option = useMemo(
    () => ({
      grid: { top: 8, bottom: 4, left: 56, right: 4, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { item: { name: string; value: number } } }) =>
          tooltipHtml(p.data.item.name, [{ color, label: 'المستفيدون', value: intl.format(p.data.item.value), strong: true }]),
      },
      xAxis: { type: 'value', inverse: true, splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (value: number) => intl.format(value) } },
      yAxis: {
        type: 'category',
        inverse: true,
        position: 'right',
        data: items.map((item) => item.name),
        axisTick: { show: false },
        axisLine: { show: false },
        axisLabel: { ...categoryLabelStyle, inside: true, align: 'right', verticalAlign: 'bottom', margin: 0, padding: [0, 2, 12, 0] },
      },
      series: [
        {
          id: 'rank',
          type: 'bar',
          barWidth: 14,
          cursor: onSelect ? 'pointer' : 'default',
          itemStyle: { borderRadius: [4, 0, 0, 4] },
          label: {
            show: true,
            position: 'left',
            distance: 8,
            fontFamily: chartFont.family,
            fontWeight: 700,
            color: chartColors.ink,
            formatter: (p: { value: number }) => intl.format(p.value),
          },
          data: items.map((item) => ({
            key: item.key,
            item,
            value: item.value,
            itemStyle: { color, opacity: hasSelection && !item.selected ? DIMMED_OPACITY : 1 },
          })),
        },
      ],
    }),
    [items, color, hasSelection, onSelect],
  )

  return <EChart option={option} ariaLabel={ariaLabel} style={{ height }} onSelect={onSelect} />
}
