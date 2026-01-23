<script setup>
import { computed } from 'vue'
import SelectButton from 'primevue/selectbutton'
import { useAnalyticsState } from '../../composables/useAnalyticsState'
import { format } from 'date-fns'
import { de } from 'date-fns/locale'

const {
    periods,
    summaryData,
    chartType
} = useAnalyticsState()

const chartTypes = [
    { value: 'bar', icon: 'pi pi-chart-bar', title: 'Balkendiagramm' },
    { value: 'stacked', icon: 'pi pi-objects-column', title: 'Gestapeltes Balkendiagramm' },
    { value: 'line', icon: 'pi pi-chart-line', title: 'Liniendiagramm' },
    { value: 'pie', icon: 'pi pi-chart-pie', title: 'Kreisdiagramm' },
    { value: 'stream', icon: 'pi pi-wave-pulse', title: 'Streamgraph' }
]

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
</script>

<template>
    <div class="analytics-header">
        <!-- Left: Time periods -->
        <div class="header-left">
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

        <!-- Center: Total count -->
        <div class="header-center">
            <div class="total-count">{{ primaryTotal.toLocaleString('de-CH') }}</div>
            <div class="total-label">Anfragen total<br>Selektion</div>
        </div>

        <!-- Right: Chart type selector -->
        <div class="header-right">
            <SelectButton
                v-model="chartType"
                :options="chartTypes"
                optionValue="value"
                class="chart-type-selector"
                :pt="{
                    button: { class: 'chart-btn' }
                }"
            >
                <template #option="{ option }">
                    <i :class="option.icon" :title="option.title"></i>
                </template>
            </SelectButton>
        </div>
    </div>
</template>

<style scoped>
.analytics-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border-radius: 30px;
    padding: 1.25rem 2rem;
    margin-bottom: 1rem;
    min-height: 80px;
    box-shadow: none;
}

/* Left section: Periods */
.header-left {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
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

/* Center section: Total */
.header-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 1;
}

.total-count {
    font-size: 3rem;
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

/* Right section: Chart type */
.header-right {
    display: flex;
    justify-content: flex-end;
    flex: 1;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .analytics-header {
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
