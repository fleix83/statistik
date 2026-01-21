<script setup>
import { onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import AnalyticsSidebar from '../components/analytics/AnalyticsSidebar.vue'
import ChartCanvas from '../components/analytics/ChartCanvas.vue'
import { useAnalyticsState } from '../composables/useAnalyticsState'
import { analytics } from '../services/api'
import { format } from 'date-fns'

const toast = useToast()

const {
    loadFilters,
    fetchData,
    periods,
    activeSection,
    activeValues
} = useAnalyticsState()

onMounted(async () => {
    await loadFilters()
    // Initial data load
    await fetchData()
})

async function handleExport() {
    try {
        const period = periods.value[0]
        const params = {
            section: activeSection.value,
            start_date: format(period.start, 'yyyy-MM-dd'),
            end_date: format(period.end, 'yyyy-MM-dd')
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
    <div class="analytics-dashboard">
        <AnalyticsSidebar @export="handleExport" />
        <ChartCanvas />
    </div>
</template>

<style scoped>
.analytics-dashboard {
    display: flex;
    height: calc(100vh - 60px); /* Adjust based on your header height */
    overflow: hidden;
}
</style>
