<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    TimeScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js'
import annotationPlugin from 'chartjs-plugin-annotation'
import 'chartjs-adapter-date-fns'
import { Bar, Line, Doughnut } from 'vue-chartjs'
import Card from 'primevue/card'
import SelectButton from 'primevue/selectbutton'
import Menu from 'primevue/menu'
import { useToast } from 'primevue/usetoast'
import StreamGraph from './StreamGraph.vue'
import SelectionHierarchy from './SelectionHierarchy.vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'
import { analytics } from '../../services/api'
import { format, parseISO, isWithinInterval } from 'date-fns'
import { de } from 'date-fns/locale'

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    TimeScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    annotationPlugin
)

// Register custom tooltip positioner - dynamically offset based on position
Tooltip.positioners.customOffset = function(elements, eventPosition) {
    if (!elements.length) {
        return false
    }
    const pos = Tooltip.positioners.average.call(this, elements, eventPosition)
    const chart = this.chart
    const chartCenter = (chart.chartArea.left + chart.chartArea.right) / 2

    // If point is on left side, show tooltip to the right; otherwise to the left
    const xOffset = pos.x < chartCenter ? 30 : -30

    return {
        x: pos.x + xOffset,
        y: pos.y - 100
    }
}

const toast = useToast()

const {
    chartType,
    chartData,
    summaryData,
    loading,
    isCompareMode,
    activeSection,
    activeValues,
    isShowingTotals,
    periods,
    markers
} = useAnalyticsState()

// Export menu
const exportMenu = ref()
const exportMenuItems = ref([
    {
        label: 'Aktuelle Ansicht',
        icon: 'pi pi-filter',
        command: () => handleExportCurrentView()
    },
    {
        label: 'Gesamte Datenbank',
        icon: 'pi pi-database',
        command: () => handleExportFullDatabase()
    }
])

function toggleExportMenu(event) {
    exportMenu.value.toggle(event)
}

async function handleExportCurrentView() {
    try {
        const allPeriods = periods.value.map(p => ({
            start: format(p.start, 'yyyy-MM-dd'),
            end: format(p.end, 'yyyy-MM-dd'),
            label: p.label
        }))

        const params = {
            section: activeSection.value,
            start_date: allPeriods[0].start,
            end_date: allPeriods[0].end,
            periods: JSON.stringify(allPeriods)
        }

        if (activeValues.value.length > 0) {
            params.values = activeValues.value.join(',')
        }

        const response = await analytics.export(params)

        // Create download
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `statistik-${activeSection.value}-${format(new Date(), 'yyyy-MM-dd')}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        toast.add({
            severity: 'success',
            summary: 'Export',
            detail: 'Aktuelle Ansicht wurde exportiert',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Export fehlgeschlagen',
            life: 3000
        })
    }
}

async function handleExportFullDatabase() {
    try {
        const response = await analytics.exportFull()

        // Create download
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `statistik-vollstaendig-${format(new Date(), 'yyyy-MM-dd')}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        toast.add({
            severity: 'success',
            summary: 'Export',
            detail: 'Gesamte Datenbank wurde exportiert',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Export fehlgeschlagen',
            life: 3000
        })
    }
}

// Chart type options for selector
const chartTypes = [
    { value: 'bar', icon: 'pi pi-chart-bar', title: 'Balkendiagramm' },
    { value: 'stacked', icon: 'pi pi-objects-column', title: 'Gestapeltes Balkendiagramm' },
    { value: 'line', icon: 'pi pi-chart-line', title: 'Liniendiagramm' },
    { value: 'pie', icon: 'pi pi-chart-pie', title: 'Kreisdiagramm' },
    { value: 'stream', icon: 'pi pi-wave-pulse', title: 'Streamgraph' }
]

// Line chart fill toggle
const lineFill = ref(true)

// Format periods as date ranges
const formattedPeriods = computed(() => {
    return periods.value.map(p => {
        const startDate = format(p.start, 'dd.MM.yyyy', { locale: de })
        const endDate = format(p.end, 'dd.MM.yyyy', { locale: de })
        return {
            ...p,
            dateRange: `${startDate} – ${endDate}`
        }
    })
})

// Get the primary total (non-comparison period)
const primaryTotal = computed(() => {
    const primary = summaryData.value.periods.find(p => !p.isComparison)
    return primary?.total ?? summaryData.value.total ?? 0
})

// Key for forcing chart re-render - use timestamp for guaranteed uniqueness
const chartKey = ref(Date.now())
const chartReady = ref(true)

// Force complete remount by toggling chartReady
async function forceChartRemount() {
    chartReady.value = false
    await nextTick()
    chartKey.value = Date.now()
    chartReady.value = true
}

watch(chartData, () => {
    forceChartRemount()
}, { deep: true })

// Also watch chartType to force re-render when switching chart types
watch(chartType, () => {
    forceChartRemount()
})

// Watch lineFill to re-render when toggled
watch(lineFill, () => {
    forceChartRemount()
})

// Watch markers to re-render when markers change
watch(markers, () => {
    forceChartRemount()
}, { deep: true })

// Color palette - read from global CSS variables (colors.css)
const getCssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()

const primaryColor = computed(() => getCssVar('--chart-primary'))
const comparisonColor = computed(() => getCssVar('--chart-comparison'))

const colors = computed(() => [
    getCssVar('--chart-color-1'),  // blue
    getCssVar('--chart-color-2'),  // red
    getCssVar('--chart-color-3'),  // green
    getCssVar('--chart-color-4'),  // earth
    getCssVar('--chart-color-5'),  // yellow
    getCssVar('--chart-color-6'),  // indigo
    getCssVar('--chart-color-7'),  // amber
    getCssVar('--chart-color-8'),  // pink
    getCssVar('--chart-color-9'),  // cyan
    getCssVar('--chart-color-10'), // violet
    getCssVar('--chart-color-11'), // teal
    getCssVar('--chart-color-12')  // orange
])

const colorsBg = computed(() => colors.value.map(c => c + '20'))

// Shared tooltip styling
const baseTooltipStyle = {
    padding: {
        top: 14,
        right: 18,
        bottom: 14,
        left: 18
    },
    cornerRadius: 8,
    titleFont: {
        size: 15,
        weight: '600'
    },
    titleMarginBottom: 10,
    bodyFont: {
        size: 14
    },
    bodySpacing: 8,
    boxWidth: 14,
    boxHeight: 14,
    boxPadding: 8,
    usePointStyle: false,
    backgroundColor: 'rgba(30, 41, 59, 0.95)',
    titleColor: '#fff',
    bodyColor: 'rgba(255, 255, 255, 0.85)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderWidth: 1,
    callbacks: {
        labelColor: (context) => ({
            borderColor: 'transparent',
            backgroundColor: context.dataset.borderColor || context.dataset.backgroundColor,
            borderWidth: 0,
            borderRadius: 2
        })
    }
}

// Convert markers to Chart.js annotations
const chartAnnotations = computed(() => {
    if (!markers.value || markers.value.length === 0) return {}
    if (!chartData.value?.labels) return {}
    if (!periods.value || periods.value.length === 0) return {}

    const labels = chartData.value.labels
    const annotations = {}

    // Get the primary period's date range for filtering
    const primaryPeriod = periods.value.find(p => !p.isComparison) || periods.value[0]
    const periodStart = primaryPeriod.start
    const periodEnd = primaryPeriod.end

    // Filter to only active markers
    const activeMarkers = markers.value.filter(m => m.is_active !== false)

    activeMarkers.forEach((marker) => {
        const startDate = parseISO(marker.start_date)
        const endDate = marker.end_date ? parseISO(marker.end_date) : null

        // Check if marker overlaps with the current period
        const markerEnd = endDate || startDate
        if (markerEnd < periodStart || startDate > periodEnd) {
            return // Marker is outside the visible period
        }

        // Find the label index that matches or is closest to the marker date
        let startIndex = findLabelIndex(startDate, labels, periodStart)
        let endIndex = endDate ? findLabelIndex(endDate, labels, periodStart) : startIndex

        // Clamp indices to valid range
        if (startIndex === -1) startIndex = 0
        if (endIndex === -1) endIndex = labels.length - 1
        startIndex = Math.max(0, Math.min(startIndex, labels.length - 1))
        endIndex = Math.max(0, Math.min(endIndex, labels.length - 1))

        const color = marker.color || '#f59e0b'

        if (endDate && endIndex !== startIndex) {
            // Range marker - draw a box
            annotations[`marker-${marker.id}`] = {
                type: 'box',
                xMin: startIndex - 0.5,
                xMax: endIndex + 0.5,
                backgroundColor: color + '20',
                borderColor: color,
                borderWidth: 1,
                borderDash: [4, 4],
                label: {
                    display: true,
                    content: marker.name,
                    position: 'start',
                    color: color,
                    font: {
                        size: 11,
                        weight: '500'
                    },
                    padding: 4,
                    yAdjust: -20
                }
            }
        } else {
            // Single date marker - draw a line
            annotations[`marker-${marker.id}`] = {
                type: 'line',
                xMin: startIndex,
                xMax: startIndex,
                borderColor: color,
                borderWidth: 2,
                borderDash: [6, 4],
                label: {
                    display: true,
                    content: marker.name,
                    position: 'start',
                    backgroundColor: color,
                    color: '#fff',
                    font: {
                        size: 11,
                        weight: '500'
                    },
                    padding: { x: 6, y: 3 },
                    borderRadius: 4,
                    yAdjust: -10
                }
            }
        }
    })

    return annotations
})

// Find the label index for a given date
function findLabelIndex(date, labels, periodStart) {
    if (!labels || !date) return -1

    const granularity = chartData.value?.granularity || 'month'
    const dateMonth = date.getMonth()
    const dateYear = date.getFullYear()
    const dateWeek = getWeekNumber(date)
    const dateDay = date.getDate()

    // For year comparisons, we need to normalize to the period's context
    const periodYear = periodStart?.getFullYear() || dateYear

    for (let i = 0; i < labels.length; i++) {
        const label = labels[i]

        if (granularity === 'month') {
            // Labels are like 'Jan', 'Feb', etc. - match by month index
            const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
            // Only match if the marker is in the same year as the period (or we're doing year comparison)
            if (monthNames[dateMonth] === label && dateYear === periodYear) return i
        } else if (granularity === 'week') {
            // Labels are like 'KW01', 'KW02', etc.
            const weekNum = parseInt(label.replace('KW', ''))
            if (weekNum === dateWeek && dateYear === periodYear) return i
        } else if (granularity === 'day') {
            // Labels are like '15.01.', '16.01.', etc.
            const expectedLabel = `${String(dateDay).padStart(2, '0')}.${String(dateMonth + 1).padStart(2, '0')}.`
            if (label === expectedLabel) return i
        }
    }

    return -1
}

// Get ISO week number
function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
    const dayNum = d.getUTCDay() || 7
    d.setUTCDate(d.getUTCDate() + 4 - dayNum)
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
}

// Bar chart data
const barChartData = computed(() => {
    if (!chartData.value) return null

    // Single period aggregate mode
    if (chartData.value.mode === 'aggregate') {
        return {
            labels: chartData.value.items.map(d => d.label),
            datasets: [{
                label: 'Anzahl',
                data: chartData.value.items.map(d => d.count),
                backgroundColor: primaryColor.value,
                borderRadius: 4
            }]
        }
    }

    // Multiple periods comparison mode - grouped bars
    if (chartData.value.mode === 'aggregate-compare') {
        return {
            labels: chartData.value.labels,
            datasets: chartData.value.datasets.map((ds, i) => ({
                label: ds.label,
                data: ds.data,
                backgroundColor: ds.isComparison ? comparisonColor.value : primaryColor.value,
                borderRadius: 4
            }))
        }
    }

    return null
})

const barChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            ...baseTooltipStyle,
            callbacks: {
                label: (context) => {
                    const value = context.raw
                    return ` ${context.dataset.label}:  ${value}`
                }
            }
        }
    },
    scales: {
        x: {
            title: {
                display: false
            }
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: 'Anfragen'
            }
        }
    }
}))

// Line chart data
const lineChartData = computed(() => {
    if (!chartData.value) return null

    // Totals mode - year comparison with shaded comparison line
    if (chartData.value.mode === 'totals') {
        // Handle empty datasets
        if (!chartData.value.datasets || chartData.value.datasets.length === 0) {
            return null
        }
        return {
            labels: chartData.value.labels || [],
            datasets: chartData.value.datasets.map((ds, i) => ({
                label: `${ds.label} (${(ds.total || 0).toLocaleString('de-CH')})`,
                data: ds.data || [],
                borderColor: ds.isComparison ? comparisonColor.value : primaryColor.value,
                backgroundColor: ds.isComparison ? comparisonColor.value + '10' : primaryColor.value + '20',
                fill: lineFill.value && !ds.isComparison, // Only fill the primary line when enabled
                tension: 0.3,
                pointRadius: ds.isComparison ? 2 : 4,
                pointHoverRadius: ds.isComparison ? 4 : 6,
                borderWidth: ds.isComparison ? 2 : 3,
                borderDash: ds.isComparison ? [5, 5] : [], // Dashed line for comparison
                order: ds.isComparison ? 1 : 0 // Primary line on top
            }))
        }
    }

    // Timeseries mode - specific values over time
    if (chartData.value.mode === 'timeseries') {
        if (!chartData.value.datasets || chartData.value.datasets.length === 0) {
            return null
        }
        return {
            labels: chartData.value.labels || [],
            datasets: chartData.value.datasets.map((ds, i) => ({
                label: ds.label,
                data: ds.data || [],
                borderColor: colors.value[i % colors.value.length],
                backgroundColor: colorsBg.value[i % colorsBg.value.length],
                fill: lineFill.value,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5
            }))
        }
    }

    // Aggregate mode - show as line chart (fallback when switching from bar/pie)
    if (chartData.value.mode === 'aggregate' && chartData.value.items) {
        return {
            labels: chartData.value.items.map(d => d.label),
            datasets: [{
                label: `Total (${(chartData.value.total || 0).toLocaleString('de-CH')})`,
                data: chartData.value.items.map(d => d.count),
                borderColor: primaryColor.value,
                backgroundColor: primaryColor.value + '20',
                fill: lineFill.value,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3
            }]
        }
    }

    return null
})

// X-axis label based on granularity
const xAxisLabel = computed(() => {
    if (chartData.value?.mode !== 'totals') return 'Datum'
    const gran = chartData.value?.granularity
    if (gran === 'day') return 'Tag'
    if (gran === 'week') return 'Woche'
    return 'Monat'
})

const lineChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            ...baseTooltipStyle,
            mode: 'index',
            intersect: false,
            position: 'customOffset',
            callbacks: {
                title: (tooltipItems) => {
                    if (!tooltipItems.length) return ''
                    const index = tooltipItems[0].dataIndex
                    // Use rawLabels for full date display (e.g., "15. Januar 2025")
                    if (chartData.value?.rawLabels?.[index]) {
                        return chartData.value.rawLabels[index]
                    }
                    return tooltipItems[0].label
                },
                label: (context) => {
                    const value = context.raw
                    return ` ${context.dataset.label}:  ${value}`
                }
            }
        },
        annotation: {
            annotations: chartAnnotations.value
        }
    },
    scales: {
        x: {
            type: 'category',
            title: {
                display: true,
                text: xAxisLabel.value
            },
            grid: {
                color: (context) => {
                    // Fade grid lines towards the top
                    const chart = context.chart
                    const { ctx, chartArea } = chart
                    if (!chartArea) return 'rgba(0, 0, 0, 0.1)'
                    // Vertical lines fade from bottom to top
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top)
                    gradient.addColorStop(0, 'rgba(0, 0, 0, 0.1)')
                    gradient.addColorStop(1, 'rgba(0, 0, 0, 0)')
                    return gradient
                }
            }
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: 'Anfragen'
            },
            grid: {
                color: (context) => {
                    // Fade horizontal grid lines towards the top
                    const chart = context.chart
                    const { chartArea } = chart
                    if (!chartArea) return 'rgba(0, 0, 0, 0.1)'
                    const totalTicks = context.scale.ticks.length
                    const tickIndex = context.index
                    // Calculate opacity: 0.1 at bottom, 0 at top
                    const opacity = 0.1 * (1 - tickIndex / (totalTicks - 1))
                    return `rgba(0, 0, 0, ${opacity})`
                }
            }
        }
    },
    interaction: {
        mode: 'index',
        intersect: false
    }
}))

// Pie/Doughnut chart data
const pieChartData = computed(() => {
    if (!chartData.value) return null

    if (chartData.value.mode === 'aggregate') {
        const items = chartData.value.items.slice(0, 10)
        return {
            labels: items.map(d => d.label),
            datasets: [{
                data: items.map(d => d.count),
                backgroundColor: colors.value.slice(0, items.length)
            }]
        }
    }

    return null
})

const pieChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            ...baseTooltipStyle,
            callbacks: {
                label: (context) => {
                    const value = context.raw
                    const total = context.dataset.data.reduce((a, b) => a + b, 0)
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0
                    return ` ${context.label}:  ${value} (${percentage}%)`
                }
            }
        }
    }
}))

// Stacked bar chart data
const stackedChartData = computed(() => {
    if (!chartData.value || chartData.value.mode !== 'stacked') return null
    return {
        labels: chartData.value.labels,
        datasets: chartData.value.datasets.map((ds, i) => ({
            label: ds.label,
            data: ds.data,
            backgroundColor: colors.value[i % colors.value.length],
            borderRadius: 4
        }))
    }
})

const stackedChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            ...baseTooltipStyle,
            mode: 'index',
            intersect: false,
            callbacks: {
                label: (context) => {
                    const value = context.raw
                    return ` ${context.dataset.label}:  ${value}`
                }
            }
        }
    },
    scales: {
        x: {
            stacked: true,
            title: {
                display: true,
                text: 'Periode'
            }
        },
        y: {
            stacked: true,
            beginAtZero: true,
            title: {
                display: true,
                text: 'Anfragen'
            }
        }
    }
}))

// Chart title
const chartTitle = computed(() => {
    if (chartData.value?.mode === 'totals') {
        return 'Anfragen'
    }

    const labels = {
        kontaktart: 'Kontaktart',
        person: 'Person',
        dauer: 'Dauer',
        thema: 'Thema',
        zeitfenster: 'Zeitfenster',
        tageszeit: 'Tageszeit',
        referenz: 'Referenz'
    }
    return labels[activeSection.value] || activeSection.value
})

// Chart subtitle
const chartSubtitle = computed(() => {
    if (chartData.value?.mode === 'totals') {
        return '- Jahresvergleich'
    }
    if (chartData.value?.mode === 'timeseries') {
        const gran = chartData.value.granularity
        return `- Zeitverlauf (${gran === 'month' ? 'monatlich' : gran === 'week' ? 'wöchentlich' : 'täglich'})`
    }
    return ''
})

// Custom legend items for HTML legend (outside canvas)
const legendItems = computed(() => {
    if (!chartData.value) return []

    const mode = chartData.value.mode
    const datasets = chartData.value.datasets

    // For timeseries, totals, stacked - use dataset labels
    if ((mode === 'timeseries' || mode === 'totals' || mode === 'stacked') && datasets?.length > 0) {
        return datasets.map((ds, i) => ({
            label: ds.label,
            color: colors.value[i % colors.value.length]
        }))
    }

    // For aggregate-compare mode
    if (mode === 'aggregate-compare' && datasets?.length > 0) {
        return datasets.map((ds, i) => ({
            label: ds.label,
            color: colors.value[i % colors.value.length]
        }))
    }

    // For aggregate mode (pie chart)
    if (mode === 'aggregate' && chartData.value.items?.length > 0) {
        const items = chartData.value.items.slice(0, 10)
        return items.map((item, i) => ({
            label: item.label,
            color: colors.value[i % colors.value.length]
        }))
    }

    return []
})

// Check if custom legend should be shown
const showCustomLegend = computed(() => {
    const type = chartType.value
    return legendItems.value.length > 0 &&
           (type === 'line' || type === 'bar' || type === 'stacked' || type === 'pie')
})

// Check if pie chart is applicable
const canShowPie = computed(() => {
    return chartData.value?.mode === 'aggregate' && chartData.value?.items?.length > 0
})

// Check if stacked bar chart is applicable
const canShowStacked = computed(() => {
    return chartData.value?.mode === 'stacked' &&
           chartData.value?.datasets?.length > 0
})

// Check if stream graph is applicable (needs timeseries or totals with datasets)
const canShowStream = computed(() => {
    const mode = chartData.value?.mode
    const hasDatasets = chartData.value?.datasets?.length > 0
    return (mode === 'timeseries' || mode === 'totals') && hasDatasets
})
</script>

<template>
    <div class="chart-canvas">
        <Card class="chart-card">
            <template #content>
                <!-- KPI Header -->
                <div class="kpi-header">
                    <!-- Left: Time periods + Total count -->
                    <div class="header-left">
                        <div class="periods-group">
                            <div
                                v-for="period in formattedPeriods"
                                :key="period.id"
                                class="period-item"
                                :class="{ 'comparison': period.isComparison }"
                            >
                                <span class="period-label">{{ period.label }}</span>
                                <span class="period-dates">{{ period.dateRange }}</span>
                            </div>
                        </div>
                        <div class="total-group">
                            <div class="total-count">{{ primaryTotal.toLocaleString('de-CH') }}</div>
                            <div class="total-label">Anfragen in Selektion</div>
                        </div>
                    </div>

                    <!-- Right: Export menu + Chart type selector -->
                    <div class="header-right">
                        <div class="header-right-controls">
                            <!-- Export menu -->
                            <div class="export-menu-wrapper">
                                <button
                                    class="export-btn"
                                    @click="toggleExportMenu"
                                    title="Daten exportieren"
                                >
                                    <i class="pi pi-file-export"></i>
                                    <span>Export</span>
                                    <i class="pi pi-chevron-down chevron"></i>
                                </button>
                                <Menu
                                    ref="exportMenu"
                                    :model="exportMenuItems"
                                    :popup="true"
                                    class="export-dropdown"
                                />
                            </div>

                            <!-- Chart type selector -->
                            <div class="chart-type-wrapper">
                                <SelectButton
                                    v-model="chartType"
                                    :options="chartTypes"
                                    optionValue="value"
                                    class="chart-type-selector"
                                >
                                    <template #option="{ option }">
                                        <i :class="option.icon" :title="option.title"></i>
                                    </template>
                                </SelectButton>
                                <!-- Line fill toggle (positioned below line chart icon) -->
                                <button
                                    v-if="chartType === 'line'"
                                    class="fill-toggle"
                                    :class="{ active: lineFill }"
                                    @click="lineFill = !lineFill"
                                    :title="lineFill ? 'Füllung ausblenden' : 'Füllung einblenden'"
                                >
                                    <i :class="lineFill ? 'pi pi-circle' : 'pi pi-circle-fill'"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selection Hierarchy -->
                <div class="hierarchy-container">
                    <SelectionHierarchy />
                </div>

                <!-- Chart Title -->
                <div class="chart-title-row">
                    <h3 class="chart-title">
                        {{ chartTitle }}
                        <span v-if="chartSubtitle" class="chart-subtitle">{{ chartSubtitle }}</span>
                    </h3>
                </div>

                <!-- Chart Content -->
                <div v-if="loading" class="chart-loading">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem;"></i>
                    <span>Daten werden geladen...</span>
                </div>

                <div v-else-if="!chartData" class="chart-empty">
                    <i class="pi pi-chart-bar" style="font-size: 3rem; color: var(--text-color-secondary);"></i>
                    <p>Wählen Sie Filter aus und klicken Sie auf "Anzeigen"</p>
                </div>

                <!-- Custom HTML Legend -->
                <div v-if="showCustomLegend && chartReady" class="custom-legend" :class="{ 'pie-legend': chartType === 'pie' }">
                    <div
                        v-for="(item, index) in legendItems"
                        :key="index"
                        class="legend-item"
                    >
                        <span class="legend-color" :style="{ backgroundColor: item.color }"></span>
                        <span class="legend-label">{{ item.label }}</span>
                    </div>
                </div>

                <div v-if="chartReady" class="chart-container">
                    <!-- Bar Chart -->
                    <Bar
                        v-if="chartType === 'bar' && barChartData"
                        :key="'bar-' + chartKey"
                        :data="barChartData"
                        :options="barChartOptions"
                    />

                    <!-- Stacked Bar Chart -->
                    <template v-else-if="chartType === 'stacked'">
                        <Bar
                            v-if="canShowStacked && stackedChartData"
                            :key="'stacked-' + chartKey"
                            :data="stackedChartData"
                            :options="stackedChartOptions"
                        />
                        <div v-else class="chart-notice">
                            <i class="pi pi-info-circle"></i>
                            <p>Gestapeltes Balkendiagramm: Klicken Sie "Anzeigen" um die Daten zu laden</p>
                        </div>
                    </template>

                    <!-- Line Chart -->
                    <Line
                        v-else-if="chartType === 'line' && lineChartData"
                        :key="'line-' + chartKey"
                        :data="lineChartData"
                        :options="lineChartOptions"
                    />

                    <!-- Pie Chart -->
                    <template v-else-if="chartType === 'pie'">
                        <div v-if="canShowPie && pieChartData" class="pie-chart-wrapper">
                            <Doughnut
                                :key="'pie-' + chartKey"
                                :data="pieChartData"
                                :options="pieChartOptions"
                            />
                        </div>
                        <div v-else class="chart-notice">
                            <i class="pi pi-info-circle"></i>
                            <p>Kreisdiagramm: Bitte wählen Sie Balkendiagramm und klicken Sie "Anzeigen"</p>
                        </div>
                    </template>

                    <!-- Stream Graph -->
                    <template v-else-if="chartType === 'stream'">
                        <StreamGraph
                            v-if="canShowStream"
                            :key="'stream-' + chartKey"
                            :data="chartData"
                        />
                        <div v-else class="chart-notice">
                            <i class="pi pi-info-circle"></i>
                            <p>Streamgraph: Wählen Sie mehrere Werte aus einer Kategorie für die Zeitverlauf-Darstellung</p>
                        </div>
                    </template>
                </div>

            </template>
        </Card>

    </div>
</template>

<style scoped>
.chart-canvas {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.chart-card {
    flex: 1;
    min-height: 500px;
    margin-bottom: 50px;
}

.chart-card :deep(.p-card) {
    background: transparent !important;
    border-radius: 30px;
    box-shadow: none !important;
    border: none;
}

.chart-card :deep(.p-card-body) {
    padding: 0;
}

.chart-card :deep(.p-card-content) {
    padding: 0;
}

/* KPI Header */
.kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
}

.header-left {
    display: flex;
    align-items: flex-start;
    gap: 3rem;
    flex: 1;
}

.periods-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.period-item {
    display: flex;
    flex-direction: column;
}

.period-item.comparison {
    opacity: 0.6;
}

.period-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-color);
}

.period-dates {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.total-group {
    display: flex;
    flex-direction: column;
}

.total-count {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.total-label {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.25rem;
    line-height: 1.3;
}

.header-right {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    flex: 1;
}

.header-right-controls {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.75rem;
}

.export-menu-wrapper {
    position: relative;
}

.export-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f5f3ef;
    border: none;
    border-radius: 8px;
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.export-btn:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.export-btn i:first-child {
    font-size: 1rem;
}

.export-btn .chevron {
    font-size: 0.75rem;
    margin-left: 0.25rem;
}

.chart-type-wrapper {
    position: relative;
}

.fill-toggle {
    position: absolute;
    top: 100%;
    /* Position below the 3rd button (line chart) */
    left: calc(4px + 70px * 2);
    margin-top: 9px;
    padding: 0.5rem;
    min-width: 70px;
    border: none;
    background: #f5f3ef;
    color: #64748b;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.fill-toggle:hover {
    background: #e2e8f0;
}

.fill-toggle.active {
    background: white;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fill-toggle i {
    font-size: 1rem;
}

.chart-type-selector {
    display: flex;
    background: #f5f3ef;
    border-radius: 12px;
    padding: 4px;
}

.chart-type-selector :deep(.p-togglebutton) {
    padding: 0.625rem 0.875rem;
    min-width: 44px;
    border: none !important;
    background: transparent !important;
    color: #64748b !important;
    border-radius: 8px !important;
}

.chart-type-selector :deep(.p-togglebutton.p-togglebutton-checked) {
    background: white !important;
    color: #1e293b !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chart-type-selector :deep(.p-togglebutton i) {
    font-size: 1.125rem;
}

.chart-type-selector :deep(.p-togglebutton:hover:not(.p-togglebutton-checked)) {
    background: rgba(255,255,255,0.5) !important;
}

/* Export dropdown menu styling */
:deep(.export-dropdown) {
    min-width: 180px;
    margin-top: 0.5rem;
}

:deep(.export-dropdown .p-menuitem-link) {
    padding: 0.75rem 1rem;
}

:deep(.export-dropdown .p-menuitem-icon) {
    margin-right: 0.75rem;
    color: #64748b;
}

:deep(.export-dropdown .p-menuitem-text) {
    font-size: 0.875rem;
}

/* Hierarchy Container */
.hierarchy-container {
    padding: 0 2rem 1rem;
    margin-bottom: 100px;
}

/* Chart Title */
.chart-title-row {
    padding: 1rem 2rem 0.5rem;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-color);
}

.chart-subtitle {
    font-size: 0.875rem;
    font-weight: normal;
    color: var(--text-color-secondary);
}

/* Chart Content */
/* Custom HTML Legend */
.custom-legend {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 70px;
    margin-bottom: 1rem;
}

.custom-legend.pie-legend {
    margin-left: -50px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-color {
    width: 30px;
    height: 14px;
    border-radius: 3px;
}

.legend-label {
    font-size: 14px;
    color: var(--text-color);
}

.chart-container {
    height: 400px;
    position: relative;
    padding: 0 2rem 2rem;
    margin-top: 30px;
}

.pie-chart-wrapper {
    height: 100%;
    width: 100%;
    position: relative;
    left: -50px;
}

.chart-loading,
.chart-empty,
.chart-notice {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--text-color-secondary);
    gap: 1rem;
}

.chart-notice {
    background: var(--surface-50);
    border-radius: 8px;
    padding: 2rem;
    margin: 0 2rem 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .kpi-header {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }

    .header-left,
    .header-center,
    .header-right {
        width: 100%;
        justify-content: center;
    }

    .header-left {
        flex-direction: row;
        gap: 1rem;
        flex-wrap: wrap;
    }
}
</style>
