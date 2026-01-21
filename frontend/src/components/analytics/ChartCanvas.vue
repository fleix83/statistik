<script setup>
import { computed, ref, watch } from 'vue'
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
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AnalyticsHeader from './AnalyticsHeader.vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

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

const {
    chartType,
    chartData,
    summaryData,
    loading,
    isCompareMode,
    activeSection,
    isShowingTotals
} = useAnalyticsState()

// Key for forcing chart re-render
const chartKey = ref(0)
watch(chartData, () => {
    chartKey.value++
})

// Color palette
const primaryColor = '#6366f1'
const comparisonColor = '#94a3b8' // Muted gray for comparison
const colors = [
    '#6366f1', '#22c55e', '#f59e0b', '#ef4444',
    '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16',
    '#f97316', '#14b8a6', '#a855f7', '#0ea5e9'
]

const colorsBg = colors.map(c => c + '20')

// Bar chart data
const barChartData = computed(() => {
    if (!chartData.value) return null

    if (chartData.value.mode === 'aggregate') {
        return {
            labels: chartData.value.items.map(d => d.label),
            datasets: [{
                label: 'Anzahl',
                data: chartData.value.items.map(d => d.count),
                backgroundColor: primaryColor,
                borderRadius: 4
            }]
        }
    }

    return null
})

const barChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
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
                text: 'Besuche'
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
                borderColor: ds.isComparison ? comparisonColor : primaryColor,
                backgroundColor: ds.isComparison ? comparisonColor + '10' : primaryColor + '20',
                fill: !ds.isComparison, // Only fill the primary line
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
                borderColor: colors[i % colors.length],
                backgroundColor: colorsBg[i % colorsBg.length],
                fill: true,
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
                borderColor: primaryColor,
                backgroundColor: primaryColor + '20',
                fill: true,
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
            intersect: false
        }
    },
    scales: {
        x: {
            type: 'category',
            title: {
                display: true,
                text: xAxisLabel.value
            }
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: 'Besuche'
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
                backgroundColor: colors.slice(0, items.length)
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

// Table data
const tableData = computed(() => {
    if (!chartData.value) return []

    if (chartData.value.mode === 'aggregate') {
        return chartData.value.items.map(item => ({
            label: item.label,
            count: item.count,
            percentage: chartData.value.total > 0
                ? ((item.count / chartData.value.total) * 100).toFixed(1)
                : 0
        }))
    }

    if (chartData.value.mode === 'totals') {
        // Monthly breakdown for totals
        return chartData.value.labels.map((label, i) => {
            const row = { label }
            chartData.value.datasets.forEach(ds => {
                row[ds.label] = ds.data[i]
            })
            return row
        })
    }

    return []
})

// Chart title
const chartTitle = computed(() => {
    if (chartData.value?.mode === 'totals') {
        return 'Besuche'
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
</script>

<template>
    <div class="chart-canvas">
        <!-- KPI Header -->
        <AnalyticsHeader />

        <!-- Chart Area -->
        <Card class="chart-card">
            <template #title>
                {{ chartTitle }}
                <span v-if="chartSubtitle" class="chart-subtitle">{{ chartSubtitle }}</span>
            </template>
            <template #content>
                <div v-if="loading" class="chart-loading">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem;"></i>
                    <span>Daten werden geladen...</span>
                </div>

                <div v-else-if="!chartData" class="chart-empty">
                    <i class="pi pi-chart-bar" style="font-size: 3rem; color: var(--text-color-secondary);"></i>
                    <p>Wählen Sie Filter aus und klicken Sie auf "Anzeigen"</p>
                </div>

                <div v-else class="chart-container">
                    <!-- Bar Chart -->
                    <Bar
                        v-if="chartType === 'bar' && barChartData"
                        :key="'bar-' + chartKey"
                        :data="barChartData"
                        :options="barChartOptions"
                    />

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
                </div>
            </template>
        </Card>

        <!-- Data Table -->
        <Card v-if="tableData.length > 0" class="table-card">
            <template #title>Detailansicht</template>
            <template #content>
                <DataTable
                    :value="tableData"
                    :loading="loading"
                    paginator
                    :rows="10"
                    class="data-table"
                >
                    <Column field="label" :header="chartData?.mode === 'totals' ? 'Monat' : 'Wert'" sortable />

                    <!-- Single period columns (aggregate) -->
                    <template v-if="chartData?.mode === 'aggregate'">
                        <Column field="count" header="Anzahl" sortable />
                        <Column field="percentage" header="Anteil" sortable>
                            <template #body="{ data }">
                                {{ data.percentage }}%
                            </template>
                        </Column>
                    </template>

                    <!-- Totals mode columns -->
                    <template v-else-if="chartData?.mode === 'totals'">
                        <Column
                            v-for="ds in chartData.datasets"
                            :key="ds.label"
                            :field="ds.label"
                            :header="ds.label"
                            sortable
                        />
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>

<style scoped>
.chart-canvas {
    flex: 1;
    padding: 1rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chart-card {
    flex: 1;
    min-height: 400px;
}

.chart-subtitle {
    font-size: 0.875rem;
    font-weight: normal;
    color: var(--text-color-secondary);
}

.chart-container {
    height: 400px;
    position: relative;
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
}

.table-card {
    margin-top: auto;
}

.data-table :deep(.p-datatable-thead > tr > th) {
    background: var(--surface-50);
}
</style>
