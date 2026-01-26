<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import * as d3 from 'd3'

const props = defineProps({
    data: {
        type: Object,
        default: null
    }
})

const containerRef = ref(null)
const svgRef = ref(null)

// Color palette - read from global CSS variables (colors.css)
const getCssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()

const colors = computed(() => [
    getCssVar('--chart-color-1'),
    getCssVar('--chart-color-2'),
    getCssVar('--chart-color-3'),
    getCssVar('--chart-color-4'),
    getCssVar('--chart-color-5'),
    getCssVar('--chart-color-6'),
    getCssVar('--chart-color-7'),
    getCssVar('--chart-color-8'),
    getCssVar('--chart-color-9'),
    getCssVar('--chart-color-10'),
    getCssVar('--chart-color-11'),
    getCssVar('--chart-color-12')
])

// Transform chart data to D3 format
const transformedData = computed(() => {
    if (!props.data) return null

    // Handle timeseries mode (selected values over time)
    if (props.data.mode === 'timeseries' && props.data.datasets?.length > 0) {
        const labels = props.data.labels || []
        const datasets = props.data.datasets || []

        // Create array of objects: [{date, value1, value2, ...}, ...]
        return labels.map((label, i) => {
            const row = { date: label }
            datasets.forEach(ds => {
                row[ds.label] = ds.data[i] || 0
            })
            return row
        })
    }

    // Handle totals mode (year comparison)
    if (props.data.mode === 'totals' && props.data.datasets?.length > 0) {
        const labels = props.data.labels || []
        const datasets = props.data.datasets || []

        return labels.map((label, i) => {
            const row = { date: label }
            datasets.forEach(ds => {
                row[ds.label] = ds.data[i] || 0
            })
            return row
        })
    }

    return null
})

const seriesKeys = computed(() => {
    if (!props.data?.datasets) return []
    return props.data.datasets.map(ds => ds.label)
})

function renderChart() {
    if (!containerRef.value || !transformedData.value || seriesKeys.value.length === 0) return

    // Clear previous chart
    d3.select(svgRef.value).selectAll('*').remove()

    const container = containerRef.value
    const width = container.clientWidth
    const height = 400
    const margin = { top: 20, right: 120, bottom: 40, left: 50 }
    const innerWidth = width - margin.left - margin.right
    const innerHeight = height - margin.top - margin.bottom

    const svg = d3.select(svgRef.value)
        .attr('width', width)
        .attr('height', height)

    const g = svg.append('g')
        .attr('transform', `translate(${margin.left},${margin.top})`)

    const data = transformedData.value
    const keys = seriesKeys.value

    // Create stack generator - use stackOffsetNone for no negative values
    const stack = d3.stack()
        .keys(keys)
        .offset(d3.stackOffsetNone)
        .order(d3.stackOrderNone)

    const series = stack(data)

    // X scale
    const x = d3.scalePoint()
        .domain(data.map(d => d.date))
        .range([0, innerWidth])

    // Y scale
    const yMax = d3.max(series, layer => d3.max(layer, d => d[1]))
    const y = d3.scaleLinear()
        .domain([0, yMax * 1.1])
        .range([innerHeight, 0])

    // Color scale
    const color = d3.scaleOrdinal()
        .domain(keys)
        .range(colors.value.slice(0, keys.length))

    // Area generator with smooth curves
    const area = d3.area()
        .x(d => x(d.data.date))
        .y0(d => y(d[0]))
        .y1(d => y(d[1]))
        .curve(d3.curveBasis)

    // Draw areas
    g.selectAll('.layer')
        .data(series)
        .join('path')
        .attr('class', 'layer')
        .attr('d', area)
        .attr('fill', d => color(d.key))
        .attr('opacity', 0.85)
        .on('mouseover', function(event, d) {
            d3.select(this).attr('opacity', 1)
            tooltip.style('display', 'block')
        })
        .on('mousemove', function(event, d) {
            const [mx, my] = d3.pointer(event, container)
            tooltip
                .style('left', (mx + 10) + 'px')
                .style('top', (my - 10) + 'px')
                .html(`<strong>${d.key}</strong>`)
        })
        .on('mouseout', function() {
            d3.select(this).attr('opacity', 0.85)
            tooltip.style('display', 'none')
        })

    // X axis
    g.append('g')
        .attr('transform', `translate(0,${innerHeight})`)
        .call(d3.axisBottom(x))
        .selectAll('text')
        .style('font-size', '11px')

    // Y axis
    g.append('g')
        .call(d3.axisLeft(y).ticks(6))
        .selectAll('text')
        .style('font-size', '11px')

    // Y axis label
    g.append('text')
        .attr('transform', 'rotate(-90)')
        .attr('y', -40)
        .attr('x', -innerHeight / 2)
        .attr('text-anchor', 'middle')
        .style('font-size', '12px')
        .style('fill', 'var(--text-color-secondary)')
        .text('Besuche')

    // Legend - horizontal, centered at top
    const legendItemWidth = 120
    const totalLegendWidth = keys.length * legendItemWidth
    const legendStartX = (width - totalLegendWidth) / 2

    const legend = svg.append('g')
        .attr('transform', `translate(${legendStartX}, 20)`)

    keys.forEach((key, i) => {
        const legendItem = legend.append('g')
            .attr('transform', `translate(${i * legendItemWidth}, 0)`)

        legendItem.append('rect')
            .attr('width', 30)
            .attr('height', 14)
            .attr('rx', 3)
            .attr('fill', color(key))

        legendItem.append('text')
            .attr('x', 38)
            .attr('y', 11)
            .style('font-size', '14px')
            .style('fill', 'var(--text-color)')
            .text(key)
    })

    // Tooltip
    const tooltip = d3.select(container)
        .append('div')
        .attr('class', 'stream-tooltip')
        .style('display', 'none')
}

// Watch for data changes
watch(() => props.data, () => {
    renderChart()
}, { deep: true })

// Handle resize
let resizeObserver = null

onMounted(() => {
    renderChart()

    resizeObserver = new ResizeObserver(() => {
        renderChart()
    })
    if (containerRef.value) {
        resizeObserver.observe(containerRef.value)
    }
})

onUnmounted(() => {
    if (resizeObserver) {
        resizeObserver.disconnect()
    }
})
</script>

<template>
    <div ref="containerRef" class="stream-container">
        <svg ref="svgRef"></svg>
    </div>
</template>

<style scoped>
.stream-container {
    width: 100%;
    height: 400px;
    position: relative;
}

.stream-container :deep(.stream-tooltip) {
    position: absolute;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    z-index: 10;
}

.stream-container :deep(svg) {
    overflow: visible;
}

.stream-container :deep(.layer) {
    transition: opacity 0.2s;
    cursor: pointer;
}
</style>
