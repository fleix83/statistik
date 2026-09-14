// Chart.js inline plugin for pie charts: writes each slice's share ("51%") just
// outside the pie, level with the middle of its arc. Slices below `minPercent`
// get no label, and a label that would overlap one already drawn is skipped.
//
// Usage: <Pie :plugins="[piePercentLabels]" />
// Options (optional) go in options.plugins.piePercentLabels.

const DEFAULTS = {
    minPercent: 2,
    offset: 10,
    color: '#1f2937',
    font: "600 13px 'Din Next Rounded', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
    lineHeight: 14
}

function overlaps(a, b, gap = 2) {
    return a.left < b.right + gap && a.right > b.left - gap && a.top < b.bottom + gap && a.bottom > b.top - gap
}

export const piePercentLabels = {
    id: 'piePercentLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const opts = { ...DEFAULTS, ...(pluginOptions || {}) }
        const meta = chart.getDatasetMeta(0)
        if (!meta || meta.hidden || !meta.data?.length) return

        const values = chart.data.datasets[0]?.data || []
        const total = values.reduce((sum, v, i) => sum + (chart.getDataVisibility(i) ? (Number(v) || 0) : 0), 0)
        if (total <= 0) return

        const ctx = chart.ctx
        ctx.save()
        ctx.font = opts.font
        ctx.fillStyle = opts.color
        ctx.textBaseline = 'middle'

        const drawn = []
        meta.data.forEach((arc, i) => {
            if (!chart.getDataVisibility(i)) return
            const percent = (Number(values[i]) || 0) / total * 100
            if (percent < opts.minPercent) return

            // Current (animated) geometry, so labels follow the slices while they grow
            const { x, y, startAngle, endAngle, outerRadius } = arc.getProps(['x', 'y', 'startAngle', 'endAngle', 'outerRadius'])
            if (!(endAngle > startAngle)) return
            const angle = (startAngle + endAngle) / 2
            const cos = Math.cos(angle)
            const sin = Math.sin(angle)
            const px = x + cos * (outerRadius + opts.offset)
            const py = y + sin * (outerRadius + opts.offset)

            // Anchor the text on the side facing away from the pie
            const align = Math.abs(cos) < 0.2 ? 'center' : (cos > 0 ? 'left' : 'right')
            const label = `${Math.round(percent)}%`
            const width = ctx.measureText(label).width
            const left = align === 'center' ? px - width / 2 : (align === 'left' ? px : px - width)
            const box = { left, right: left + width, top: py - opts.lineHeight / 2, bottom: py + opts.lineHeight / 2 }
            if (drawn.some(b => overlaps(box, b))) return
            drawn.push(box)

            ctx.textAlign = align
            ctx.fillText(label, px, py)
        })

        ctx.restore()
    }
}

export default piePercentLabels
