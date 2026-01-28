<script setup>
import { ref, computed, onMounted } from 'vue'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

const {
    periods,
    addPeriod,
    removePeriod,
    updatePeriod,
    setYearPeriod,
    savedPeriodsConfigs,
    activeSavedPeriodIds,
    loadSavedPeriods,
    togglePeriodConfig,
    updateSavedPeriodName,
    deleteSavedPeriod,
    savePeriodConfig
} = useAnalyticsState()

const currentYear = new Date().getFullYear()
const yearButtons = computed(() => {
    return [currentYear - 2, currentYear - 1, currentYear]
})

// Check if a specific period is already saved (by matching label)
function isPeriodSaved(periodLabel) {
    return savedPeriodsConfigs.value.some(c => c.name === periodLabel)
}

// Saved periods section state
const showSavedPeriods = ref(false)
const editingNameId = ref(null)
const editingNameValue = ref('')

onMounted(() => {
    loadSavedPeriods()
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

function toggleSavedPeriods() {
    showSavedPeriods.value = !showSavedPeriods.value
}

function handleToggle(config) {
    togglePeriodConfig(config.id)
}

function isActive(configId) {
    return activeSavedPeriodIds.value.includes(configId)
}

async function handleDelete(config) {
    await deleteSavedPeriod(config.id)
}

function startEditName(config) {
    editingNameId.value = config.id
    editingNameValue.value = config.name
}

async function finishEditName(config) {
    if (editingNameValue.value.trim() && editingNameValue.value !== config.name) {
        await updateSavedPeriodName(config.id, editingNameValue.value.trim())
    }
    editingNameId.value = null
    editingNameValue.value = ''
}

function cancelEditName() {
    editingNameId.value = null
    editingNameValue.value = ''
}

const savingPeriodId = ref(null)

async function handleSavePeriod(period) {
    savingPeriodId.value = period.id
    const success = await savePeriodConfig(period)
    savingPeriodId.value = null
    if (!success) {
        alert('Fehler beim Speichern')
    }
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
                <div class="period-date-row">
                    <DatePicker
                        :modelValue="getDateRange(period)"
                        @update:modelValue="onDateRangeChange(period.id, $event)"
                        selectionMode="range"
                        dateFormat="dd.mm.yy"
                        placeholder="Zeitraum wählen"
                        showIcon
                        hideOnRangeSelection
                        class="flex-1 period-date-picker"
                    />
                    <Button
                        :icon="isPeriodSaved(period.label) ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"
                        text
                        size="small"
                        class="bookmark-btn"
                        :class="{ 'is-saved': isPeriodSaved(period.label) }"
                        :loading="savingPeriodId === period.id"
                        @click="handleSavePeriod(period)"
                        v-tooltip.top="isPeriodSaved(period.label) ? 'Gespeichert' : 'Periode speichern'"
                    />
                </div>
            </div>
        </div>

        <!-- Bookmark Icon and Add Period Button -->
        <div class="period-actions">
            <Button
                :icon="showSavedPeriods ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"
                :outlined="!showSavedPeriods"
                size="small"
                class="icon-btn saved-periods-btn"
                @click="toggleSavedPeriods"
                v-tooltip.top="'Gespeicherte Perioden'"
            />
            <Button
                label="Periode hinzufügen"
                icon="pi pi-plus"
                severity="secondary"
                outlined
                size="small"
                class="flex-1 add-period-btn"
                @click="addPeriod"
            />
        </div>

        <!-- Saved Periods Section (Collapsible) -->
        <div v-if="showSavedPeriods" class="saved-periods-section">
            <div class="saved-periods-header">
                <span class="saved-periods-title">Gespeicherte Perioden</span>
            </div>

            <div v-if="savedPeriodsConfigs.length === 0" class="no-saved-periods">
                Keine gespeicherten Perioden
            </div>

            <div v-else class="saved-periods-list">
                <div
                    v-for="config in savedPeriodsConfigs"
                    :key="config.id"
                    class="saved-period-item"
                    :class="{ 'is-active': isActive(config.id) }"
                >
                    <!-- Name (editable) -->
                    <div class="saved-period-name">
                        <InputText
                            v-if="editingNameId === config.id"
                            v-model="editingNameValue"
                            size="small"
                            class="name-edit-input"
                            @blur="finishEditName(config)"
                            @keyup.enter="finishEditName(config)"
                            @keyup.escape="cancelEditName"
                            autofocus
                        />
                        <span
                            v-else
                            class="name-text"
                            @click="startEditName(config)"
                            :title="'Klicken zum Bearbeiten'"
                        >
                            {{ config.name }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="saved-period-actions">
                        <span
                            class="toggle-btn"
                            :class="{ 'is-active': isActive(config.id) }"
                            @click="handleToggle(config)"
                        >
                            {{ isActive(config.id) ? 'Aktiv' : 'Laden' }}
                        </span>
                        <Button
                            icon="pi pi-times"
                            size="small"
                            severity="danger"
                            text
                            @click="handleDelete(config)"
                        />
                    </div>
                </div>
            </div>
        </div>
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

.period-date-row {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

/* Bookmark button */
.bookmark-btn.p-button {
    color: #94a3b8 !important;
}

.bookmark-btn.p-button:hover {
    background: rgba(148, 163, 184, 0.1) !important;
}

.bookmark-btn.is-saved.p-button {
    color: #3b82f6 !important;
}

.bookmark-btn.is-saved.p-button:hover {
    background: rgba(59, 130, 246, 0.1) !important;
}

.period-date-picker :deep(.p-inputtext) {
    font-size: 0.875rem;
}


.period-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-top: 0.25rem;
}

.add-period-btn {
    flex: 1;
}

:deep(.add-period-btn.p-button) {
    color: #3b82f6 !important;
    border: none !important;
}

:deep(.add-period-btn.p-button:hover) {
    background: rgba(59, 130, 246, 0.1) !important;
}

/* Saved Periods Section */
.saved-periods-section {
    background: #f8fafc;
    border-radius: 8px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.saved-periods-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.saved-periods-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.no-saved-periods {
    font-size: 0.875rem;
    color: #94a3b8;
    text-align: center;
    padding: 0.5rem 0;
}

.saved-periods-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.saved-period-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.375rem 0.5rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}


.saved-period-name {
    flex: 1;
    min-width: 0;
}

.name-text {
    font-size: 0.875rem;
    color: #334155;
    cursor: pointer;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.name-text:hover {
    color: #3b82f6;
}

.name-edit-input {
    width: 100%;
}

.name-edit-input :deep(.p-inputtext) {
    padding: 0.25rem 0.375rem;
    font-size: 0.875rem;
}

.saved-period-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.toggle-btn {
    font-size: 0.875rem;
    color: #64748b;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background 0.15s, color 0.15s;
}

.toggle-btn:hover {
    background: rgba(100, 116, 139, 0.1);
}

.toggle-btn.is-active {
    color: #3b82f6;
}

.toggle-btn.is-active:hover {
    background: rgba(59, 130, 246, 0.1);
}

/* Icon buttons (clock, bookmark) - blue */
.icon-btn.p-button {
    color: #3b82f6 !important;
    border-color: #3b82f6 !important;
}

.icon-btn.p-button:hover {
    background: rgba(59, 130, 246, 0.1) !important;
}

/* Filled state (not outlined, not text) */
.icon-btn.p-button:not(.p-button-outlined):not(.p-button-text) {
    background: #3b82f6 !important;
    color: white !important;
    margin-left: 15px;
}

.icon-btn.p-button:not(.p-button-outlined):not(.p-button-text):hover {
    background: #2563eb !important;
}

/* Saved periods button icon offset */
.saved-periods-btn.p-button :deep(.p-button-icon) {
    margin-left: 10px;
}
</style>
