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
import 'chartjs-adapter-date-fns'
import { Bar, Line, Doughnut } from 'vue-chartjs'
import Card from 'primevue/card'
import SelectButton from 'primevue/selectbutton'
import StreamGraph from './StreamGraph.vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'
import { format } from 'date-fns'
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
    Filler
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

const {
    chartType,
    chartData,
    summaryData,
    loading,
    isCompareMode,
    activeSection,
    isShowingTotals,
    periods
} = useAnalyticsState()

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
            display: chartData.value?.mode === 'aggregate-compare',
            position: 'top'
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const value = context.raw
                    return `${context.dataset.label}: ${value}`
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
            display: true,
            position: 'top'
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            position: 'customOffset',
            padding: 12,
            titleFont: {
                size: 14,
                weight: 'bold'
            },
            bodyFont: {
                size: 13
            },
            callbacks: {
                title: (tooltipItems) => {
                    if (!tooltipItems.length) return ''
                    const index = tooltipItems[0].dataIndex
                    // Use rawLabels if available for full date
                    if (chartData.value?.rawLabels?.[index]) {
                        return chartData.value.rawLabels[index]
                    }
                    return tooltipItems[0].label
                },
                label: (context) => {
                    const value = context.raw
                    return `${context.dataset.label}: ${value}`
                }
            }
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
            position: 'right'
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const value = context.raw
                    const total = context.dataset.data.reduce((a, b) => a + b, 0)
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0
                    return `${context.label}: ${value} (${percentage}%)`
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
            display: true,
            position: 'top'
        },
        tooltip: {
            mode: 'index',
            intersect: false
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

                    <!-- Right: Chart type selector -->
                    <div class="header-right">
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

                <div v-else-if="chartReady" class="chart-container">
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
                        <Doughnut
                            v-if="canShowPie && pieChartData"
                            :key="'pie-' + chartKey"
                            :data="pieChartData"
                            :options="pieChartOptions"
                        />
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
    margin-bottom: 130px;
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
.chart-container {
    height: 400px;
    position: relative;
    padding: 0 2rem 2rem;
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
