import { BarChart, PieChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import * as echarts from 'echarts/core'
import type { EChartsCoreOption } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { useEffect, useRef, type CSSProperties } from 'react'
import { useReducedMotion } from '../../hooks/useReducedMotion'

echarts.use([BarChart, PieChart, GridComponent, TooltipComponent, CanvasRenderer])

interface EChartProps {
  option: EChartsCoreOption
  /** Text alternative for the canvas; the tables carry the exact figures. */
  ariaLabel: string
  className?: string
  style?: CSSProperties
  /** Called with the clicked data item's key (data.key) for cross-filtering. */
  onSelect?: (key: string) => void
}

export function EChart({ option, ariaLabel, className = 'h-80', style, onSelect }: EChartProps) {
  const host = useRef<HTMLDivElement>(null)
  const chart = useRef<echarts.ECharts | null>(null)
  const reduced = useReducedMotion()
  const selectRef = useRef(onSelect)

  useEffect(() => {
    selectRef.current = onSelect
  }, [onSelect])

  useEffect(() => {
    const element = host.current
    if (!element) return

    const instance = echarts.init(element, undefined, { renderer: 'canvas' })
    chart.current = instance
    instance.on('click', (params) => {
      const key = (params.data as { key?: string } | undefined)?.key
      if (key) selectRef.current?.(key)
    })

    const observer = new ResizeObserver(() => instance.resize())
    observer.observe(element)

    return () => {
      observer.disconnect()
      instance.dispose()
      chart.current = null
    }
  }, [])

  useEffect(() => {
    chart.current?.setOption(
      { animation: !reduced, animationDuration: 550, animationDurationUpdate: 400, ...option },
      { replaceMerge: ['series', 'xAxis', 'yAxis'] },
    )
  }, [option, reduced])

  return <div ref={host} role="img" aria-label={ariaLabel} className={`w-full ${style ? '' : className}`} style={style} />
}
