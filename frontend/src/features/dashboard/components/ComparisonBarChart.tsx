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

export interface ComparisonItem {
  key: string
  name: string
  value: number
  male: number
  female: number
  color?: string
  selected?: boolean
}

interface Props {
  items: ComparisonItem[]
  ariaLabel: string
  onSelect?: (key: string) => void
}

/**
 * Horizontal ranking of registered benefits (sectors, projects or periods). Only rendered by
 * callers when at least two items have data, so it always compares something real.
 */
export function ComparisonBarChart({ items, ariaLabel, onSelect }: Props) {
  const hasSelection = items.some((item) => item.selected)
  const height = Math.max(150, items.length * 58 + 36)

  const option = useMemo(
    () => ({
      grid: { top: 8, bottom: 4, left: 56, right: 4, containLabel: true },
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { data: { item: ComparisonItem } }) => {
          const it = p.data.item
          return tooltipHtml(it.name, [
            { color: chartColors.male, label: 'ذكور', value: intl.format(it.male) },
            { color: chartColors.female, label: 'إناث', value: intl.format(it.female) },
            { label: 'الإجمالي', value: intl.format(it.value), strong: true },
          ])
        },
      },
      xAxis: { type: 'value', inverse: true, splitLine: gridLine, axisLabel: { ...axisLabelStyle, formatter: (v: number) => intl.format(v) } },
      yAxis: {
        type: 'category',
        inverse: true,
        position: 'right',
        data: items.map((item) => item.name),
        axisTick: { show: false },
        axisLine: { show: false },
        // Full names sit above each bar (right-aligned), so long Arabic names are never cut,
        // wrapped awkwardly, or allowed to squeeze the bars on narrow screens.
        axisLabel: { ...categoryLabelStyle, inside: true, align: 'right', verticalAlign: 'bottom', margin: 0, padding: [0, 2, 13, 0] },
      },
      series: [
        {
          id: 'value',
          type: 'bar',
          barWidth: 16,
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
            itemStyle: { color: item.color ?? chartColors.total, opacity: hasSelection && !item.selected ? DIMMED_OPACITY : 1 },
          })),
        },
      ],
    }),
    [items, hasSelection, onSelect],
  )

  return <EChart option={option} ariaLabel={ariaLabel} style={{ height }} onSelect={onSelect} />
}
