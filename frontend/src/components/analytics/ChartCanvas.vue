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
import { usePdfExport } from '../../composables/usePdfExport'
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
const { exportToPdf } = usePdfExport()
const pdfExportArea = ref(null)
const isExporting = ref(false)

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
    },
    {
        separator: true
    },
    {
        label: 'PDF exportieren',
        icon: 'pi pi-file-pdf',
        command: () => handleExportPdf()
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

async function handleExportPdf() {
    if (isExporting.value || !pdfExportArea.value) return
    isExporting.value = true

    const element = pdfExportArea.value

    // Add class to hide UI controls and apply export styling
    element.classList.add('exporting')

    // Wait for DOM to update
    await nextTick()

    try {
        const filename = `${chartTitle.value}-${periods.value[0]?.label || ''}-${format(new Date(), 'yyyy-MM-dd')}.pdf`
        const marginMm = 10

        // Use high scale for print-quality resolution (3x = ~288 DPI effective)
        await exportToPdf(element, {
            filename,
            margin: [marginMm, marginMm, marginMm, marginMm],
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
            html2canvas: {
                scale: 3,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }
        })

        toast.add({
            severity: 'success',
            summary: 'PDF Export',
            detail: 'Diagramm exportiert',
            life: 3000
        })
    } catch (error) {
        console.error('PDF export error:', error)
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'PDF Export fehlgeschlagen',
            life: 3000
        })
    } finally {
        element.classList.remove('exporting')
        isExporting.value = false
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

// Format periods as date ranges with their counts
const formattedPeriods = computed(() => {
    return periods.value.map((p, index) => {
        const startDate = format(p.start, 'dd.MM.yyyy', { locale: de })
        const endDate = format(p.end, 'dd.MM.yyyy', { locale: de })
        // Get the count for this period from summaryData
        const periodSummary = summaryData.value.periods?.[index]
        const count = periodSummary?.total ?? 0
        return {
            ...p,
            dateRange: `${startDate} – ${endDate}`,
            count: count
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

// Utility: Lighten a hex color by a percentage (0-100)
// Higher percentage = lighter color
function lightenColor(hex, percent) {
    // Remove # if present
    hex = hex.replace(/^#/, '')

    // Parse hex to RGB
    let r = parseInt(hex.substring(0, 2), 16)
    let g = parseInt(hex.substring(2, 4), 16)
    let b = parseInt(hex.substring(4, 6), 16)

    // Lighten by moving towards white (255)
    r = Math.round(r + (255 - r) * (percent / 100))
    g = Math.round(g + (255 - g) * (percent / 100))
    b = Math.round(b + (255 - b) * (percent / 100))

    // Clamp values
    r = Math.min(255, Math.max(0, r))
    g = Math.min(255, Math.max(0, g))
    b = Math.min(255, Math.max(0, b))

    // Convert back to hex
    return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('')
}

// Get color for a specific period index (0 = primary, 1+ = comparison)
// Returns progressively lighter shades for comparison periods
function getColorForPeriod(baseColor, periodIndex, totalPeriods = 2) {
    if (periodIndex === 0) return baseColor
    // Lighten by 35% for each subsequent period (capped at 70%)
    const lightenPercent = Math.min(35 * periodIndex, 70)
    return lightenColor(baseColor, lightenPercent)
}

// Generate colors for all periods based on a base color
function getPeriodColors(baseColor, numPeriods) {
    return Array.from({ length: numPeriods }, (_, i) =>
        getColorForPeriod(baseColor, i, numPeriods)
    )
}

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

    // Single period aggregate mode - different color per parameter
    if (chartData.value.mode === 'aggregate') {
        return {
            labels: chartData.value.items.map(d => d.label),
            datasets: [{
                label: 'Anzahl',
                data: chartData.value.items.map(d => d.count),
                backgroundColor: chartData.value.items.map((_, i) => colors.value[i % colors.value.length]),
                borderRadius: 4
            }]
        }
    }

    // Multiple periods comparison mode - grouped bars
    // Each parameter gets its own color, with lighter shades for comparison periods
    if (chartData.value.mode === 'aggregate-compare') {
        const numPeriods = chartData.value.datasets.length
        const labels = chartData.value.labels // Parameter labels (e.g., Besuch, Telefon)

        return {
            labels: labels,
            datasets: chartData.value.datasets.map((ds, periodIndex) => ({
                label: ds.label, // Period label (e.g., 2025, 2024)
                data: ds.data,
                // Each bar gets its parameter's color, lightened for comparison periods
                backgroundColor: labels.map((_, valueIndex) => {
                    const baseColor = colors.value[valueIndex % colors.value.length]
                    return getColorForPeriod(baseColor, periodIndex, numPeriods)
                }),
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

    // Totals mode - year comparison with lighter shades for comparison periods
    if (chartData.value.mode === 'totals') {
        // Handle empty datasets
        if (!chartData.value.datasets || chartData.value.datasets.length === 0) {
            return null
        }
        const numPeriods = chartData.value.datasets.length
        const periodColors = getPeriodColors(primaryColor.value, numPeriods)
        return {
            labels: chartData.value.labels || [],
            datasets: chartData.value.datasets.map((ds, i) => {
                const color = periodColors[i]
                const isComparison = i > 0
                return {
                    label: `${ds.label} (${(ds.total || 0).toLocaleString('de-CH')})`,
                    data: ds.data || [],
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: lineFill.value && !isComparison, // Only fill the primary line when enabled
                    tension: 0.3,
                    pointRadius: isComparison ? 2 : 4,
                    pointHoverRadius: isComparison ? 4 : 6,
                    borderWidth: isComparison ? 2 : 3,
                    borderDash: isComparison ? [5, 5] : [], // Dashed line for comparison
                    order: isComparison ? 1 : 0 // Primary line on top
                }
            })
        }
    }

    // Timeseries mode - specific values over time (with multi-period support)
    if (chartData.value.mode === 'timeseries') {
        if (!chartData.value.datasets || chartData.value.datasets.length === 0) {
            return null
        }
        const numPeriods = chartData.value.numPeriods || 1
        return {
            labels: chartData.value.labels || [],
            datasets: chartData.value.datasets.map((ds) => {
                // Get base color from value index
                const valueIndex = ds.valueIndex ?? 0
                const periodIndex = ds.periodIndex ?? 0
                const baseColor = colors.value[valueIndex % colors.value.length]
                // Apply lighter shade for comparison periods
                const color = getColorForPeriod(baseColor, periodIndex, numPeriods)
                const isComparison = periodIndex > 0

                return {
                    label: ds.label,
                    data: ds.data || [],
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: lineFill.value && !isComparison,
                    tension: 0.3,
                    pointRadius: isComparison ? 2 : 3,
                    pointHoverRadius: isComparison ? 4 : 5,
                    borderWidth: isComparison ? 2 : 3,
                    borderDash: isComparison ? [5, 5] : [],
                    order: isComparison ? 1 : 0
                }
            })
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

// Pie/Doughnut chart data - supports multiple periods
const pieChartsData = computed(() => {
    if (!chartData.value) return []

    // Single period mode
    if (chartData.value.mode === 'aggregate') {
        const items = chartData.value.items.slice(0, 10)
        return [{
            periodLabel: periods.value[0]?.label || '',
            data: {
                labels: items.map(d => d.label),
                datasets: [{
                    data: items.map(d => d.count),
                    backgroundColor: colors.value.slice(0, items.length)
                }]
            }
        }]
    }

    // Multiple periods mode
    if (chartData.value.mode === 'aggregate-compare') {
        const labels = chartData.value.labels
        return chartData.value.datasets.map((ds, i) => {
            const items = labels.map((label, j) => ({
                label: label,
                count: ds.data[j]
            })).slice(0, 10)

            return {
                periodLabel: ds.label,
                data: {
                    labels: items.map(d => d.label),
                    datasets: [{
                        data: items.map(d => d.count),
                        backgroundColor: colors.value.slice(0, items.length)
                    }]
                }
            }
        })
    }

    return []
})

// Legacy single pie chart data (for backwards compatibility)
const pieChartData = computed(() => {
    return pieChartsData.value[0]?.data || null
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

    // For totals mode (period comparison) - use period-based colors
    if (mode === 'totals' && datasets?.length > 0) {
        const numPeriods = datasets.length
        const periodColors = getPeriodColors(primaryColor.value, numPeriods)
        return datasets.map((ds, i) => ({
            label: ds.label,
            color: periodColors[i]
        }))
    }

    // For aggregate-compare mode (bar chart with periods) - show parameter colors
    // Each parameter gets its own color; lighter shades indicate comparison periods
    if (mode === 'aggregate-compare' && chartData.value.labels?.length > 0) {
        const labels = chartData.value.labels // Parameter labels
        return labels.map((label, i) => ({
            label: label,
            color: colors.value[i % colors.value.length]
        }))
    }

    // For timeseries - use value colors with period shading
    if (mode === 'timeseries' && datasets?.length > 0) {
        const numPeriods = chartData.value.numPeriods || 1
        return datasets.map((ds) => {
            const valueIndex = ds.valueIndex ?? 0
            const periodIndex = ds.periodIndex ?? 0
            const baseColor = colors.value[valueIndex % colors.value.length]
            const color = getColorForPeriod(baseColor, periodIndex, numPeriods)
            return {
                label: ds.label,
                color: color
            }
        })
    }

    // For stacked - use distinct colors for each value
    if (mode === 'stacked' && datasets?.length > 0) {
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
    const mode = chartData.value?.mode
    if (mode === 'aggregate' && chartData.value?.items?.length > 0) return true
    if (mode === 'aggregate-compare' && chartData.value?.datasets?.length > 0) return true
    return false
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

// Data table rows derived from chartData
const tableData = computed(() => {
    if (!chartData.value) return []

    const mode = chartData.value.mode

    // Single period aggregate (bar, pie)
    if (mode === 'aggregate' && chartData.value.items?.length > 0) {
        const items = chartData.value.items
        const total = items.reduce((s, i) => s + i.count, 0)
        return [{
            periodLabel: null,
            total,
            rows: items.map(i => ({
                label: i.label,
                count: i.count,
                percent: total > 0 ? ((i.count / total) * 100).toFixed(1) : '0.0'
            }))
        }]
    }

    // Multi-period aggregate comparison (bar, pie)
    if (mode === 'aggregate-compare' && chartData.value.datasets?.length > 0) {
        const labels = chartData.value.labels
        return chartData.value.datasets.map(ds => {
            const total = ds.data.reduce((s, v) => s + v, 0)
            return {
                periodLabel: ds.label,
                total,
                rows: labels.map((label, j) => ({
                    label,
                    count: ds.data[j],
                    percent: total > 0 ? ((ds.data[j] / total) * 100).toFixed(1) : '0.0'
                }))
            }
        })
    }

    // Totals mode (line chart period comparison)
    if (mode === 'totals' && chartData.value.datasets?.length > 0) {
        const datasets = chartData.value.datasets
        const grandTotal = datasets.reduce((s, ds) => s + (ds.total || ds.data.reduce((a, b) => a + b, 0)), 0)
        return [{
            periodLabel: null,
            total: grandTotal,
            rows: datasets.map(ds => {
                const dsTotal = ds.total || ds.data.reduce((a, b) => a + b, 0)
                return {
                    label: ds.label,
                    count: dsTotal,
                    percent: grandTotal > 0 ? ((dsTotal / grandTotal) * 100).toFixed(1) : '0.0'
                }
            })
        }]
    }

    // Timeseries mode (specific values over time)
    if (mode === 'timeseries' && chartData.value.datasets?.length > 0) {
        // Group by period
        const numPeriods = chartData.value.numPeriods || 1
        if (numPeriods <= 1) {
            const datasets = chartData.value.datasets
            const totals = datasets.map(ds => ds.data.reduce((a, b) => a + b, 0))
            const grandTotal = totals.reduce((a, b) => a + b, 0)
            return [{
                periodLabel: null,
                total: grandTotal,
                rows: datasets.map((ds, i) => ({
                    label: ds.label,
                    count: totals[i],
                    percent: grandTotal > 0 ? ((totals[i] / grandTotal) * 100).toFixed(1) : '0.0'
                }))
            }]
        }
        // Multi-period: group datasets by periodIndex
        const periodGroups = {}
        chartData.value.datasets.forEach(ds => {
            const pi = ds.periodIndex ?? 0
            if (!periodGroups[pi]) periodGroups[pi] = []
            periodGroups[pi].push(ds)
        })
        return Object.entries(periodGroups).map(([pi, datasets]) => {
            const totals = datasets.map(ds => ds.data.reduce((a, b) => a + b, 0))
            const grandTotal = totals.reduce((a, b) => a + b, 0)
            const periodLabel = periods.value[parseInt(pi)]?.label || `Periode ${parseInt(pi) + 1}`
            return {
                periodLabel,
                total: grandTotal,
                rows: datasets.map((ds, i) => ({
                    label: ds.label.replace(` (${periodLabel})`, ''),
                    count: totals[i],
                    percent: grandTotal > 0 ? ((totals[i] / grandTotal) * 100).toFixed(1) : '0.0'
                }))
            }
        })
    }

    // Stacked mode
    if (mode === 'stacked' && chartData.value.datasets?.length > 0) {
        const datasets = chartData.value.datasets
        const totals = datasets.map(ds => ds.data.reduce((a, b) => a + b, 0))
        const grandTotal = totals.reduce((a, b) => a + b, 0)
        return [{
            periodLabel: null,
            total: grandTotal,
            rows: datasets.map((ds, i) => ({
                label: ds.label,
                count: totals[i],
                percent: grandTotal > 0 ? ((totals[i] / grandTotal) * 100).toFixed(1) : '0.0'
            }))
        }]
    }

    return []
})
</script>

<template>
    <div class="chart-canvas">
        <!-- Export menu - positioned at top edge -->
        <div class="export-menu-top">
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

        <div ref="pdfExportArea" class="pdf-export-area">
        <Card class="chart-card">
            <template #content>
                <!-- KPI Header -->
                <div class="kpi-header">
                    <!-- Left: Time periods with their counts -->
                    <div class="header-left">
                        <div class="periods-group">
                            <div
                                v-for="period in formattedPeriods"
                                :key="period.id"
                                class="period-item"
                                :class="{ 'comparison': period.isComparison }"
                            >
                                <div class="period-main">
                                    <span class="period-label">{{ period.label }}</span>
                                    <span class="period-count">{{ period.count.toLocaleString('de-CH') }}</span>
                                    <span class="period-count-label">Anfragen</span>
                                </div>
                                <span class="period-dates">{{ period.dateRange }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Chart type selector + PDF logo -->
                    <div class="header-right">
                        <div class="header-right-controls">
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
                        <!-- Logo branding for PDF export (hidden in UI, shown during export) -->
                        <div class="pdf-branding">
                            <svg class="pdf-branding-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 452.68 201.61">
                                <path d="M49.31,148.61C22.12,148.61,0,126.49,0,99.31s22.12-49.31,49.31-49.31c14.11,0,26.13,4.37,34.78,12.64,1.55,1.49,2.45,3.5,2.49,5.66s-.75,4.21-2.24,5.76c-1.53,1.59-3.59,2.48-5.81,2.48-2.08,0-4.07-.79-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.31,0-32.49,15.39-32.49,32.95s14.88,32.95,32.49,32.95c15.05,0,25.99-8.62,29.43-23.12h-17.29c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.62c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.06-12.21,34.89-8.5,8.9-20.47,13.59-34.61,13.59h0Z"/>
                                <path d="M137.13,201.61c-32.13,0-57.19-30.89-47.02-64.54,6.15-20.37,25.25-33.93,46.53-34.08,14.32-.1,26.52,4.27,35.27,12.64,1.56,1.49,2.45,3.5,2.49,5.66.05,2.16-.75,4.2-2.24,5.76-1.52,1.59-3.59,2.48-5.8,2.48-2.08,0-4.07-.79-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.32,0-32.49,15.39-32.49,32.95s14.88,32.96,32.49,32.96c15.04,0,25.98-8.62,29.43-23.12h-17.3c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.61c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.06-12.21,34.89-8.49,8.9-20.46,13.59-34.6,13.59h0Z"/>
                                <path d="M137.13,98.61c-27.19,0-49.32-22.12-49.32-49.31S109.93,0,137.13,0c14.11,0,26.13,4.37,34.78,12.64,1.56,1.49,2.45,3.5,2.49,5.66.05,2.16-.75,4.2-2.24,5.76-1.52,1.59-3.59,2.48-5.8,2.48-2.08,0-4.07-.78-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.32,0-32.49,15.39-32.49,32.95s14.88,32.95,32.49,32.95c15.04,0,25.98-8.62,29.43-23.12h-17.3c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.61c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.05-12.21,34.89-8.49,8.9-20.46,13.59-34.6,13.59h0Z"/>
                                <g>
                                    <path d="M241.45,144.99l-7.59-26.86h-.08l-7.34,25.58c-.54,1.84-1.3,2.59-2.59,2.59s-2.1-.75-2.59-2.59l-7.99-31.25c-.11-.43-.17-.86-.17-1.13,0-1.24.86-2.27,2.32-2.27,1.13,0,1.99.64,2.32,2.05l6.31,26.01h.12l7.35-26.34c.44-1.6,1.27-2.38,2.54-2.38s2.04.77,2.49,2.38l7.51,27.34h.1l6.4-27.37c.34-1.48,1.25-2.15,2.44-2.15,1.53,0,2.44,1.08,2.44,2.38,0,.29-.06.74-.17,1.19l-8.39,32.81c-.51,1.93-1.36,2.72-2.72,2.72-1.36.02-2.15-.78-2.73-2.71Z"/>
                                    <path d="M256.67,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.42,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM273.14,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.09-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                                    <path d="M298.64,122.14v-.67c0-1.57,1.07-2.52,2.5-2.52s2.5.95,2.5,2.52v23.16c0,7.98-4.53,12-12.45,12-3.75,0-7.33-1.45-9.05-3.18-.47-.44-.71-1-.71-1.51,0-1.23.95-2.18,2.26-2.18.71,0,1.37.28,2.03.73,1.61,1.11,3.58,1.79,5.66,1.79,4.58,0,7.26-2.12,7.26-7.25v-2.9h-.05c-1.43,2.23-3.81,3.52-7.97,3.52-4.76,0-8.16-2.45-9.59-6.65-.66-1.9-.95-3.91-.95-6.87s.3-4.97.95-6.87c1.43-4.18,4.83-6.65,9.59-6.65,4.22,0,6.61,1.51,7.97,3.52h.05ZM298.49,137.53c.44-1.27.61-2.76.61-4.97s-.17-3.65-.61-4.92c-.94-2.76-2.76-4.14-5.64-4.14s-4.7,1.38-5.63,4.14c-.44,1.27-.61,2.76-.61,4.92s.17,3.7.61,4.97c.94,2.76,2.76,4.14,5.63,4.14s4.7-1.38,5.64-4.14Z"/>
                                    <path d="M331.81,143.94l-5.6-17.81h-.11l-5.66,17.81c-.56,1.7-1.47,2.44-2.83,2.44s-2.26-.74-2.83-2.44l-7.19-21.95c-.11-.34-.17-.74-.17-1.02,0-1.3.9-2.26,2.32-2.26,1.19,0,1.92.68,2.32,2.04l5.71,18.38h.11l5.66-18.1c.51-1.64,1.3-2.32,2.6-2.32s2.09.68,2.6,2.32l5.77,18.1h.11l5.6-18.38c.4-1.36,1.14-2.04,2.32-2.04,1.36,0,2.26.96,2.26,2.26,0,.29-.05.68-.16,1.02l-7.19,21.95c-.56,1.7-1.47,2.44-2.77,2.44-1.43,0-2.38-.74-2.9-2.44Z"/>
                                    <path d="M347.73,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.42,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM364.19,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.09-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                                    <path d="M373.23,111.45c0-1.71,1.38-3.04,3.04-3.04s3.04,1.33,3.04,3.04-1.38,3.04-3.04,3.04-3.04-1.38-3.04-3.04ZM373.64,121.15c0-1.67,1.07-2.68,2.5-2.68s2.5,1.01,2.5,2.68v23.76c0,1.67-1.07,2.68-2.5,2.68s-2.5-1.01-2.5-2.68v-23.76Z"/>
                                    <path d="M383.99,143.94c-.72-.5-1.1-1.16-1.1-1.93,0-1.1.88-2.1,2.15-2.1.44,0,.88.11,1.55.5,2.15,1.27,4.42,2.32,7.4,2.32,3.81,0,5.8-1.66,5.8-3.98s-.99-3.31-5.19-3.76l-2.76-.28c-5.2-.55-7.9-3.2-7.9-7.46,0-5.03,3.59-8.07,9.78-8.07,3.1,0,5.86.77,7.9,1.93.94.5,1.38,1.11,1.38,1.93,0,1.11-.88,1.99-1.99,1.99-.44,0-.94-.11-1.66-.44-1.77-.83-3.76-1.38-5.86-1.38-3.31,0-5.03,1.49-5.03,3.65s1.21,3.21,5.19,3.59l2.76.28c5.52.61,7.96,3.2,7.96,7.51,0,5.19-3.76,8.62-10.77,8.62-4.03.01-7.4-1.37-9.61-2.92Z"/>
                                    <path d="M408.76,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.41,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM425.22,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.1-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                                    <path d="M434.64,143.96v-22.05c0-1.55,1.07-2.49,2.5-2.49s2.5.94,2.5,2.49v1.22h.06c1.19-2.38,3.81-4.03,7.32-4.03,1.96,0,3.28.44,4.22.94,1.01.5,1.43,1.21,1.43,1.99,0,1.33-1.01,2.32-2.5,2.32-.36,0-.83-.11-1.25-.28-1.07-.39-2.08-.66-3.28-.66-4.29,0-6.01,3.32-6.01,7.74v12.82c0,1.55-1.07,2.49-2.5,2.49s-2.5-.95-2.5-2.5Z"/>
                                </g>
                            </svg>
                            <h1 class="pdf-branding-title">STATISTIK</h1>
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

                <!-- Base Label Heading (stacked chart in subset mode only) -->
                <div v-if="chartType === 'stacked' && chartData?.subsetMode && chartData?.baseLabel" class="stacked-base-heading">
                    {{ chartData.baseLabel }}
                </div>

                <!-- Custom HTML Legend -->
                <div v-if="showCustomLegend && chartReady" class="custom-legend" :class="{ 'pie-legend': chartType === 'pie', 'two-columns': legendItems.length > 8 }">
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
                        <div v-if="canShowPie && pieChartsData.length > 0" class="pie-charts-container">
                            <div
                                v-for="(pieChart, index) in pieChartsData"
                                :key="'pie-container-' + index"
                                class="pie-chart-item"
                            >
                                <h4 class="pie-chart-title">{{ pieChart.periodLabel }}</h4>
                                <div class="pie-chart-wrapper">
                                    <Doughnut
                                        :key="'pie-' + chartKey + '-' + index"
                                        :data="pieChart.data"
                                        :options="pieChartOptions"
                                    />
                                </div>
                            </div>
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

                <!-- Data Table -->
                <div v-if="tableData.length > 0 && !loading" class="data-table-section">
                    <div
                        v-for="(table, tIndex) in tableData"
                        :key="'table-' + tIndex"
                        class="data-table-wrapper"
                    >
                        <h4 v-if="table.periodLabel" class="data-table-period">{{ table.periodLabel }}</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-label">Feld</th>
                                    <th class="col-count">Anzahl</th>
                                    <th class="col-percent">Anteil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rIndex) in table.rows" :key="rIndex">
                                    <td class="col-label">{{ row.label }}</td>
                                    <td class="col-count">{{ row.count.toLocaleString('de-CH') }}</td>
                                    <td class="col-percent">{{ row.percent }}%</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="col-label">Total</td>
                                    <td class="col-count">{{ table.total.toLocaleString('de-CH') }}</td>
                                    <td class="col-percent">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </template>
        </Card>
        </div>

    </div>
</template>

<style scoped>
.chart-canvas {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* Export menu at top edge */
.export-menu-top {
    position: absolute;
    top: 0;
    right: 1.5rem;
    z-index: 10;
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
    gap: 0.125rem;
}

.period-item.comparison {
    opacity: 0.6;
}

.period-main {
    display: flex;
    align-items: flex-start;
}

.period-label {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-color);
    min-width: 120px;
    margin-right: 2.75rem;
}

.period-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-right: 0.75rem;
}

.period-count-label {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    line-height: 1.5rem;
}

.period-dates {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
    margin-left: 0;
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
/* Stacked Base Heading (subset mode) */
.stacked-base-heading {
    text-align: center;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-color);
    margin-top: 70px;
    margin-bottom: 0.5rem;
}

/* Custom HTML Legend */
.custom-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 2rem;
    margin-top: 70px;
    margin-bottom: 1rem;
}

.custom-legend.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    justify-items: start;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    gap: 0.5rem 3rem;
}

/* When stacked base heading is present, reduce legend margin */
.stacked-base-heading + .custom-legend {
    margin-top: 0.5rem;
}

.custom-legend.pie-legend {
    margin-left: 0;
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

.pie-charts-container {
    display: flex;
    justify-content: center;
    gap: 2rem;
    height: 100%;
    width: 100%;
}

.pie-chart-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    max-width: 400px;
}

.pie-chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
    margin: 0 0 0.5rem 0;
    text-align: center;
}

.pie-chart-wrapper {
    height: 100%;
    width: 100%;
    position: relative;
}

/* Single pie chart offset (legacy) */
.pie-charts-container:has(.pie-chart-item:only-child) .pie-chart-wrapper {
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

/* PDF Export branding (hidden in UI, shown during export) */
.pdf-branding {
    display: none;
}

.pdf-branding-logo {
    height: 3rem;
    fill: #000000;
}

.pdf-branding-title {
    font-family: 'Din Next Rounded', sans-serif;
    font-size: 2.0rem;
    font-weight: 400;
    margin: -0.7rem 0 0;
    margin-left: 157px;
    color: #000000;
    letter-spacing: 0.10em;
}

/* PDF Export - hide controls during capture, show branding */
.pdf-export-area.exporting {
    background: #ffffff;
}

.pdf-export-area.exporting .header-right-controls {
    display: none !important;
}

.pdf-export-area.exporting .pdf-branding {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-left: auto;
}

/* Data Table */
.data-table-section {
    padding: 0 2rem 2rem;
    margin-top: 2rem;
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.data-table-wrapper {
    flex: 1;
    min-width: 280px;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 12px;
    overflow: hidden;
}

.data-table-period {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color);
    margin: 0;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 1.175rem;
}

.data-table thead th {
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.data-table tbody tr {
    transition: background 0.1s ease;
}

.data-table tbody tr:hover {
    background: #fafafa;
}

.data-table tbody td {
    padding: 0.625rem 1.25rem;
    color: var(--text-color);
    border-bottom: 1px solid rgba(0, 0, 0, 0.03);
}

.data-table tfoot td {
    padding: 0.75rem 1.25rem;
    font-weight: 600;
    color: var(--text-color);
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.data-table .col-count,
.data-table .col-percent {
    text-align: right;
}

.data-table .col-count {
    font-variant-numeric: tabular-nums;
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
