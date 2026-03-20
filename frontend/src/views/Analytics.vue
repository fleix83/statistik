<script setup>
import { onMounted } from 'vue'
import Toast from 'primevue/toast'
import AnalyticsSidebar from '../components/analytics/AnalyticsSidebar.vue'
import ChartCanvas from '../components/analytics/ChartCanvas.vue'
import { useAnalyticsState } from '../composables/useAnalyticsState'

const {
    loadFilters,
    fetchData
} = useAnalyticsState()

onMounted(async () => {
    await loadFilters()
    // Initial data load
    await fetchData()
})
</script>

<template>
    <Toast />
    <div class="analytics-dashboard">
        <AnalyticsSidebar />
        <ChartCanvas />
    </div>
</template>

<style scoped>
.analytics-dashboard {
    display: flex;
    height: calc(100vh - 60px); /* Adjust based on your header height */
    overflow: hidden;
    position: relative;
}

.analytics-dashboard::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 120px;
    background: linear-gradient(180deg, #c8deff, transparent);
    pointer-events: none;
    z-index: 0;
}
</style>
