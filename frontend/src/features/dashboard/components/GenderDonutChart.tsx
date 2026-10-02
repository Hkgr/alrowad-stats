import { useMemo } from 'react'
import { chartColors, chartFont, intl, tooltipBase, tooltipHtml } from '../../../components/charts/chartTheme'
import { ChartLegend } from '../../../components/charts/ChartLegend'
import { EChart } from '../../../components/charts/EChart'
import { formatNumber, formatPercent } from '../../../lib/format'
import type { Summary } from '../../../types/api'

/**
 * Male / female split of the filtered scope, with the exact counts and shares in the legend.
 * The part whose gender the source does not report is its own grey slice, never split by guess.
 */
export function GenderDonutChart({ summary }: { summary: Summary }) {
  const { male, female, total, male_share, female_share } = summary
  const unreported = summary.gender_unreported ?? 0
  const unreportedShare = total ? Math.round((unreported / total) * 1000) / 10 : null

  const option = useMemo(
    () => ({
      tooltip: {
        ...tooltipBase,
        trigger: 'item',
        formatter: (p: { name: string; value: number; percent: number; color: string }) =>
          tooltipHtml(p.name, [
            { color: p.color, label: 'العدد', value: intl.format(p.value) },
            { label: 'النسبة', value: formatPercent(p.percent) },
          ]),
      },
      series: [
        {
          id: 'gender',
          type: 'pie',
          radius: ['58%', '82%'],
          center: ['50%', '50%'],
          startAngle: 90,
          avoidLabelOverlap: true,
          padAngle: 2,
          itemStyle: { borderRadius: 6, borderColor: chartColors.surface, borderWidth: 2 },
          label: {
            show: true,
            position: 'center',
            formatter: () => `{value|${intl.format(total ?? 0)}}\n{caption|الإجمالي}`,
            rich: {
              value: { fontFamily: chartFont.family, fontSize: 30, fontWeight: 800, color: chartColors.ink, lineHeight: 40 },
              caption: { fontFamily: chartFont.family, fontSize: 13, color: chartColors.inkMuted, lineHeight: 20 },
            },
          },
          emphasis: { scaleSize: 6, label: { show: true } },
          labelLine: { show: false },
          data: [
            { name: 'ذكور', value: male ?? 0, itemStyle: { color: chartColors.male } },
            { name: 'إناث', value: female ?? 0, itemStyle: { color: chartColors.female } },
            ...(unreported > 0 ? [{ name: 'الجنس غير مذكور', value: unreported, itemStyle: { color: chartColors.unreported } }] : []),
          ],
        },
      ],
    }),
    [male, female, total, unreported],
  )

  return (
    <div>
      <EChart
        option={option}
        ariaLabel={`توزيع الذكور والإناث: ذكور ${formatNumber(male)} (${formatPercent(male_share)})، إناث ${formatNumber(female)} (${formatPercent(female_share)})`}
        className="h-64"
      />
      <ChartLegend
        className="mt-3 justify-center"
        items={[
          { key: 'male', label: 'ذكور', color: chartColors.male, detail: `${formatNumber(male)} · ${formatPercent(male_share)}` },
          { key: 'female', label: 'إناث', color: chartColors.female, detail: `${formatNumber(female)} · ${formatPercent(female_share)}` },
          ...(unreported > 0
            ? [{ key: 'unreported', label: 'الجنس غير مذكور', color: chartColors.unreported, detail: `${formatNumber(unreported)} · ${formatPercent(unreportedShare)}` }]
            : []),
        ]}
      />
    </div>
  )
}
