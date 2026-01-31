<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import ColorPicker from 'primevue/colorpicker'
import ToggleSwitch from 'primevue/toggleswitch'
import { useAnalyticsState } from '../../composables/useAnalyticsState'
import { format, parseISO } from 'date-fns'
import { de } from 'date-fns/locale'

const {
    markers,
    createMarker,
    updateMarker,
    deleteMarker
} = useAnalyticsState()

// Form state
const showForm = ref(false)
const editingId = ref(null)
const formData = ref({
    name: '',
    dateRange: null, // [startDate, endDate] or [singleDate]
    color: 'f59e0b'
})
const saving = ref(false)

// Format date for display
function formatDate(dateStr) {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return format(date, 'dd.MM.yyyy', { locale: de })
}

// Format marker display
function formatMarkerDates(marker) {
    if (marker.end_date) {
        return `${formatDate(marker.start_date)} – ${formatDate(marker.end_date)}`
    }
    return formatDate(marker.start_date)
}

// Check if marker is a range
function isRange(marker) {
    return !!marker.end_date
}

// Open form for new marker
function openNewForm() {
    editingId.value = null
    formData.value = {
        name: '',
        dateRange: null,
        color: 'f59e0b'
    }
    showForm.value = true
}

// Open form for editing
function editMarker(marker) {
    editingId.value = marker.id
    const startDate = parseISO(marker.start_date)
    const endDate = marker.end_date ? parseISO(marker.end_date) : null
    formData.value = {
        name: marker.name,
        dateRange: endDate ? [startDate, endDate] : [startDate],
        color: marker.color.replace('#', '')
    }
    showForm.value = true
}

// Close form
function closeForm() {
    showForm.value = false
    editingId.value = null
    formData.value = {
        name: '',
        dateRange: null,
        color: 'f59e0b'
    }
}

// Save marker (create or update)
async function saveMarker() {
    if (!formData.value.name || !formData.value.dateRange?.length) return

    saving.value = true
    try {
        const startDate = formData.value.dateRange[0]
        const endDate = formData.value.dateRange[1] || null

        const data = {
            name: formData.value.name,
            start_date: format(startDate, 'yyyy-MM-dd'),
            end_date: endDate ? format(endDate, 'yyyy-MM-dd') : null,
            color: '#' + formData.value.color
        }

        if (editingId.value) {
            await updateMarker(editingId.value, data)
        } else {
            await createMarker(data)
        }
        closeForm()
    } catch (error) {
        console.error('Failed to save marker:', error)
    } finally {
        saving.value = false
    }
}

// Check if form has valid date
const hasValidDate = computed(() => {
    return formData.value.dateRange?.length > 0 && formData.value.dateRange[0]
})

// Toggle marker active state
async function toggleActive(marker) {
    try {
        await updateMarker(marker.id, { is_active: !marker.is_active })
    } catch (error) {
        console.error('Failed to toggle marker:', error)
    }
}

// Remove marker
async function removeMarker(id, event) {
    event.stopPropagation()
    try {
        await deleteMarker(id)
    } catch (error) {
        console.error('Failed to delete marker:', error)
    }
}
</script>

<template>
    <div class="marker-manager">
        <!-- Existing Markers -->
        <div v-if="markers.length > 0" class="markers-list">
            <div
                v-for="marker in markers"
                :key="marker.id"
                class="marker-item"
                :class="{ 'is-inactive': !marker.is_active }"
                @click="editMarker(marker)"
            >
                <div
                    class="marker-color"
                    :style="{ backgroundColor: marker.color }"
                    :class="{ 'is-range': isRange(marker) }"
                ></div>
                <div class="marker-info">
                    <span class="marker-name">{{ marker.name }}</span>
                    <span class="marker-dates">{{ formatMarkerDates(marker) }}</span>
                </div>
                <ToggleSwitch
                    :modelValue="marker.is_active"
                    @update:modelValue="toggleActive(marker)"
                    @click.stop
                    class="marker-toggle"
                />
                <button
                    class="marker-delete"
                    @click="removeMarker(marker.id, $event)"
                    title="Markierung löschen"
                >
                    <i class="pi pi-times"></i>
                </button>
            </div>
        </div>

        <div v-else class="markers-empty">
            <span>Keine Markierungen</span>
        </div>

        <!-- Add/Edit Marker Form -->
        <div v-if="showForm" class="marker-form">
            <div class="form-header">
                {{ editingId ? 'Markierung bearbeiten' : 'Neue Markierung' }}
            </div>

            <div class="form-row">
                <InputText
                    v-model="formData.name"
                    placeholder="Name (z.B. Tramwerbung)"
                    class="w-full"
                />
            </div>

            <div class="form-row">
                <DatePicker
                    v-model="formData.dateRange"
                    selectionMode="range"
                    dateFormat="dd.mm.yy"
                    placeholder="Datum oder Zeitraum wählen"
                    showIcon
                    hideOnRangeSelection
                    class="w-full"
                />
            </div>

            <div class="form-row color-row">
                <label>Farbe:</label>
                <ColorPicker v-model="formData.color" />
            </div>

            <div class="form-actions">
                <Button
                    label="Abbrechen"
                    severity="secondary"
                    text
                    size="small"
                    @click="closeForm"
                />
                <Button
                    :label="editingId ? 'Aktualisieren' : 'Speichern'"
                    size="small"
                    :loading="saving"
                    :disabled="!formData.name || !hasValidDate"
                    @click="saveMarker"
                />
            </div>
        </div>

        <!-- Add Button -->
        <button
            v-if="!showForm"
            class="add-marker-btn"
            @click="openNewForm"
        >
            <i class="pi pi-plus"></i>
            Markierung hinzufügen
        </button>
    </div>
</template>

<style scoped>
.marker-manager {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.markers-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.marker-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background-color 0.15s;
}

.marker-item:hover {
    background: #f1f5f9;
}

.marker-item.is-inactive {
    opacity: 0.5;
}

.marker-toggle {
    flex-shrink: 0;
}

.marker-toggle :deep(.p-toggleswitch) {
    width: 2.25rem;
    height: 1.25rem;
}

.marker-toggle :deep(.p-toggleswitch-slider) {
    border-radius: 0.625rem;
}

.marker-toggle :deep(.p-toggleswitch-slider:before) {
    width: 0.875rem;
    height: 0.875rem;
}

.marker-color {
    width: 4px;
    height: 24px;
    border-radius: 2px;
    flex-shrink: 0;
}

.marker-color.is-range {
    width: 10px;
    border-radius: 3px;
}

.marker-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
}

.marker-name {
    font-weight: 500;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.marker-dates {
    font-size: 0.7rem;
    color: var(--text-color-secondary);
}

.marker-delete {
    background: none;
    border: none;
    color: var(--text-color-secondary);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s, color 0.2s;
}

.marker-item:hover .marker-delete {
    opacity: 1;
}

.marker-delete:hover {
    color: #dc2626;
}

.markers-empty {
    color: var(--text-color-secondary);
    font-size: 0.8rem;
    font-style: italic;
    padding: 0.5rem 0;
}

/* Form */
.marker-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
}

.form-header {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    margin-bottom: 0.25rem;
}

.form-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row :deep(.p-datepicker-input) {
    width: 100%;
    font-size: 0.8rem;
    padding: 0.5rem;
}

.form-row :deep(.p-datepicker-dropdown) {
    padding: 0.5rem;
}

.color-row {
    gap: 0.75rem;
}

.color-row label {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e2e8f0;
}

/* Add Button */
.add-marker-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem;
    background: none;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: var(--text-color-secondary);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.add-marker-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(59, 130, 246, 0.05);
}

.add-marker-btn i {
    font-size: 0.75rem;
}
</style>
