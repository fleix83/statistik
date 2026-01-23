<script setup>
import { computed } from 'vue'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

const {
    periods,
    addPeriod,
    removePeriod,
    updatePeriod,
    setYearPeriod
} = useAnalyticsState()

const currentYear = new Date().getFullYear()
const yearButtons = computed(() => {
    return [currentYear - 2, currentYear - 1, currentYear]
})

function onDateRangeChange(periodId, dates) {
    if (dates && dates[0] && dates[1]) {
        updatePeriod(periodId, {
            start: dates[0],
            end: dates[1]
        })
    }
}

function getDateRange(period) {
    return [period.start, period.end]
}

function isYearSelected(year) {
    return periods.value[0]?.label === String(year)
}
</script>

<template>
    <div class="period-selector">
        <!-- Year Quick Select -->
        <div class="year-buttons">
            <Button
                v-for="year in yearButtons"
                :key="year"
                :label="String(year)"
                size="small"
                :class="{ 'year-selected': isYearSelected(year) }"
                @click="setYearPeriod(year)"
            />
        </div>

        <!-- Period List -->
        <div class="periods-list">
            <div
                v-for="period in periods"
                :key="period.id"
                class="period-item"
            >
                <div class="period-header">
                    <InputText
                        v-model="period.label"
                        size="small"
                        class="period-label-input"
                        placeholder="Label"
                    />
                    <Button
                        v-if="periods.length > 1"
                        icon="pi pi-times"
                        severity="danger"
                        text
                        size="small"
                        @click="removePeriod(period.id)"
                    />
                </div>
                <DatePicker
                    :modelValue="getDateRange(period)"
                    @update:modelValue="onDateRangeChange(period.id, $event)"
                    selectionMode="range"
                    dateFormat="dd.mm.yy"
                    placeholder="Zeitraum wählen"
                    showIcon
                    hideOnRangeSelection
                    class="w-full period-date-picker"
                />
            </div>
        </div>

        <!-- Add Period Button -->
        <Button
            label="Periode hinzufügen"
            icon="pi pi-plus"
            severity="secondary"
            outlined
            size="small"
            class="w-full add-period-btn"
            @click="addPeriod"
        />
    </div>
</template>

<style scoped>
.period-selector {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.year-buttons {
    display: flex;
    gap: 0;
    background: #f1f5f9;
    border-radius: 20px;
    padding: 3px;
}

.year-buttons :deep(.p-button) {
    flex: 1;
    border: none !important;
    border-radius: 18px !important;
    font-weight: 500;
    padding: 0.5rem 1rem;
    background: transparent !important;
    color: #64748b !important;
}

.year-buttons :deep(.p-button:hover) {
    background: rgba(59, 130, 246, 0.1) !important;
}

/* Selected year - blue background */
.year-buttons :deep(.year-selected.p-button) {
    background: #3b82f6 !important;
    color: white !important;
}

.year-buttons :deep(.year-selected.p-button:hover) {
    background: #2563eb !important;
}

.periods-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.period-item {
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.period-header {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.period-label-input {
    flex: 1;
}

.period-label-input :deep(.p-inputtext) {
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
}

.period-date-picker :deep(.p-inputtext) {
    font-size: 0.875rem;
}

.add-period-btn {
    margin-top: 0.25rem;
}

:deep(.add-period-btn.p-button) {
    color: #3b82f6 !important;
    border: none !important;
}

:deep(.add-period-btn.p-button:hover) {
    background: rgba(59, 130, 246, 0.1) !important;
}
</style>
