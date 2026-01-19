<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement } from 'chart.js'
import { Bar, Doughnut } from 'vue-chartjs'
import Card from 'primevue/card'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { analytics, options } from '../services/api'

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Title, Tooltip, Legend, ArcElement)

const toast = useToast()

// Filter state
const dateRange = ref(null)
const selectedSection = ref(null)
const loading = ref(false)

// Data
const aggregatedData = ref([])
const totalEntries = ref(0)

const sections = [
    { label: 'Kontaktart', value: 'kontaktart' },
    { label: 'Person', value: 'person' },
    { label: 'Thema', value: 'thema' },
    { label: 'Zeitfenster', value: 'zeitfenster' },
    { label: 'Tageszeit', value: 'tageszeit' },
    { label: 'Dauer', value: 'dauer' },
    { label: 'Referenz', value: 'referenz' }
]

// Chart data computed
const barChartData = computed(() => ({
    labels: aggregatedData.value.map(d => d.label),
    datasets: [{
        label: 'Anzahl',
        data: aggregatedData.value.map(d => d.count),
        backgroundColor: '#6366f1',
        borderRadius: 4
    }]
}))

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false }
    },
    scales: {
        x: { beginAtZero: true }
    }
}

const doughnutChartData = computed(() => ({
    labels: aggregatedData.value.slice(0, 8).map(d => d.label),
    datasets: [{
        data: aggregatedData.value.slice(0, 8).map(d => d.count),
        backgroundColor: [
            '#6366f1', '#22c55e', '#f59e0b', '#ef4444',
            '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'
        ]
    }]
}))

const doughnutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' }
    }
}

onMounted(async () => {
    // Set default date range (last 30 days)
    const end = new Date()
    const start = new Date()
    start.setDate(start.getDate() - 30)
    dateRange.value = [start, end]

    selectedSection.value = 'thema'
    await loadData()
})

async function loadData() {
    if (!selectedSection.value) return

    loading.value = true
    try {
        const params = {
            section: selectedSection.value
        }

        if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
            params.start_date = formatDate(dateRange.value[0])
            params.end_date = formatDate(dateRange.value[1])
        }

        const response = await analytics.aggregate(params)
        aggregatedData.value = response.data.items || []
        totalEntries.value = response.data.total || 0
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Daten konnten nicht geladen werden',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

function formatDate(date) {
    return date.toISOString().split('T')[0]
}

async function exportData() {
    try {
        const params = {
            section: selectedSection.value
        }

        if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
            params.start_date = formatDate(dateRange.value[0])
            params.end_date = formatDate(dateRange.value[1])
        }

        const response = await analytics.export(params)

        // Create download
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `statistik-${selectedSection.value}-${formatDate(new Date())}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()

        toast.add({
            severity: 'success',
            summary: 'Export',
            detail: 'Daten wurden exportiert',
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
</script>

<template>
    <Toast />
    <div class="analytics">
        <!-- Filters -->
        <Card class="mb-3">
            <template #content>
                <div class="filter-bar">
                    <div class="field">
                        <label>Zeitraum</label>
                        <DatePicker
                            v-model="dateRange"
                            selectionMode="range"
                            dateFormat="dd.mm.yy"
                            placeholder="Zeitraum wählen"
                            showIcon
                            class="w-full"
                        />
                    </div>
                    <div class="field">
                        <label>Kategorie</label>
                        <Select
                            v-model="selectedSection"
                            :options="sections"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Kategorie wählen"
                            class="w-full"
                        />
                    </div>
                    <div class="field button-field">
                        <Button
                            label="Anzeigen"
                            icon="pi pi-search"
                            :loading="loading"
                            @click="loadData"
                        />
                        <Button
                            label="Export"
                            icon="pi pi-download"
                            severity="secondary"
                            @click="exportData"
                        />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Summary -->
        <div class="summary-cards mb-3">
            <Card>
                <template #content>
                    <div class="summary-stat">
                        <span class="stat-label">Einträge gesamt</span>
                        <span class="stat-value">{{ totalEntries }}</span>
                    </div>
                </template>
            </Card>
            <Card>
                <template #content>
                    <div class="summary-stat">
                        <span class="stat-label">Verschiedene Werte</span>
                        <span class="stat-value">{{ aggregatedData.length }}</span>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <Card>
                <template #title>Verteilung (Balken)</template>
                <template #content>
                    <div class="chart-container" style="height: 400px">
                        <Bar
                            v-if="aggregatedData.length"
                            :data="barChartData"
                            :options="barChartOptions"
                        />
                        <p v-else class="text-center text-color-secondary">Keine Daten vorhanden</p>
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>Verteilung (Kreis)</template>
                <template #content>
                    <div class="chart-container" style="height: 400px">
                        <Doughnut
                            v-if="aggregatedData.length"
                            :data="doughnutChartData"
                            :options="doughnutChartOptions"
                        />
                        <p v-else class="text-center text-color-secondary">Keine Daten vorhanden</p>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Data Table -->
        <Card class="mt-3">
            <template #title>Detailansicht</template>
            <template #content>
                <DataTable
                    :value="aggregatedData"
                    :loading="loading"
                    paginator
                    :rows="10"
                >
                    <Column field="label" header="Wert" sortable />
                    <Column field="count" header="Anzahl" sortable />
                    <Column header="Anteil">
                        <template #body="{ data }">
                            {{ totalEntries > 0 ? ((data.count / totalEntries) * 100).toFixed(1) : 0 }}%
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
    </div>
</template>

<style scoped>
.analytics {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.filter-bar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-bar .field {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-bar .button-field {
    flex: 0;
    flex-direction: row;
    min-width: auto;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.summary-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.stat-label {
    color: var(--text-color-secondary);
    font-size: 0.875rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 600;
    color: var(--primary-color);
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1rem;
}

.chart-container {
    position: relative;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>
