<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import { useToast } from 'primevue/usetoast'
import { useRueckschauState } from '../../composables/useRueckschauState'
import { useEditorDraft } from '../../composables/useEditorDraft'

const toast = useToast()

const { configuredFields, saving, loadFields, saveFields } = useRueckschauState()
const { optionsBySection } = useEditorDraft()

// Local working copy for drag-and-drop
const localFields = ref([])
const isDirty = ref(false)

// All database sections
const allSections = ['kontaktart', 'person', 'dauer', 'thema', 'zeitfenster', 'referenz']

const sectionLabels = {
    kontaktart: 'Kontaktart',
    person: 'Person',
    dauer: 'Dauer',
    thema: 'Thema',
    zeitfenster: 'Zeitfenster',
    referenz: 'Referenz'
}

// Available fields: all active options not yet configured
const availableBySection = computed(() => {
    const result = {}
    for (const section of allSections) {
        const sectionOpts = optionsBySection.value[section] || []
        const activeOpts = sectionOpts
            .filter(o => o.is_active !== false && o.draft_action !== 'delete')
            .map(o => o.label)

        // Filter out already-configured ones
        const configuredKeys = new Set(
            localFields.value
                .filter(f => f.section === section)
                .map(f => f.value_text)
        )

        const available = activeOpts.filter(label => !configuredKeys.has(label))
        if (available.length > 0) {
            result[section] = available
        }
    }
    return result
})

onMounted(async () => {
    try {
        await loadFields()
        syncFromConfigured()
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Felder konnten nicht geladen werden', life: 3000 })
    }
})

watch(configuredFields, () => {
    if (!isDirty.value) {
        syncFromConfigured()
    }
})

function syncFromConfigured() {
    localFields.value = configuredFields.value.map(f => ({ ...f }))
}

function onDragEnd() {
    isDirty.value = true
}

function addField(section, valueText) {
    localFields.value.push({ section, value_text: valueText })
    isDirty.value = true
}

function removeField(index) {
    localFields.value.splice(index, 1)
    isDirty.value = true
}

async function onSave() {
    try {
        const fieldsToSave = localFields.value.map((f, i) => ({
            section: f.section,
            value_text: f.value_text,
            sort_order: i
        }))
        await saveFields(fieldsToSave)
        isDirty.value = false
        toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Rückschau-Felder wurden aktualisiert', life: 3000 })
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Speichern fehlgeschlagen', life: 3000 })
    }
}

function getSectionColor(section) {
    const colors = {
        kontaktart: 'var(--color-kontaktart-checked)',
        person: 'var(--color-person-checked)',
        dauer: 'var(--color-dauer-checked)',
        thema: 'var(--color-thema)',
        zeitfenster: 'var(--color-zeitfenster)',
        referenz: 'var(--color-referenz)'
    }
    return colors[section] || '#ccc'
}
</script>

<template>
    <div class="rueckschau-config">
        <div class="config-panels">
            <!-- Left: Configured fields -->
            <div class="panel panel-configured">
                <div class="panel-header">
                    <h4 class="panel-title">Rückschau</h4>
                    <span class="field-count">{{ localFields.length }} Felder</span>
                </div>

                <div v-if="localFields.length === 0" class="empty-state">
                    Felder aus der rechten Spalte hierher ziehen oder anklicken.
                </div>

                <draggable
                    v-model="localFields"
                    handle=".field-drag-handle"
                    ghost-class="ghost-field"
                    :animation="200"
                    class="configured-list"
                    @end="onDragEnd"
                >
                    <div
                        v-for="(field, index) in localFields"
                        :key="field.section + '|' + field.value_text"
                        class="configured-field field-drag-handle"
                    >
                        <span class="field-dot" :style="{ background: getSectionColor(field.section) }"></span>
                        <span class="field-label">{{ field.value_text }}</span>
                        <span class="field-section-tag">{{ sectionLabels[field.section] }}</span>
                        <button class="field-remove" @click="removeField(index)" title="Entfernen">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </draggable>

                <div class="panel-footer" v-if="isDirty">
                    <button class="save-btn" :disabled="saving" @click="onSave">
                        <i v-if="saving" class="pi pi-spin pi-spinner"></i>
                        <i v-else class="pi pi-check"></i>
                        Speichern
                    </button>
                </div>
            </div>

            <!-- Right: Available fields -->
            <div class="panel panel-available">
                <div class="panel-header">
                    <h4 class="panel-title">Verfügbare Felder</h4>
                </div>

                <div v-if="Object.keys(availableBySection).length === 0" class="empty-state">
                    Alle Felder sind bereits konfiguriert.
                </div>

                <div class="available-sections">
                    <div
                        v-for="section in allSections"
                        :key="section"
                        v-show="availableBySection[section]"
                        class="available-section"
                    >
                        <h5 class="available-section-title">
                            <span class="section-dot" :style="{ background: getSectionColor(section) }"></span>
                            {{ sectionLabels[section] }}
                        </h5>
                        <div class="available-fields">
                            <button
                                v-for="label in (availableBySection[section] || [])"
                                :key="label"
                                class="available-field"
                                @click="addField(section, label)"
                            >
                                {{ label }}
                                <i class="pi pi-plus add-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rueckschau-config {
    max-width: 1177px;
    margin: 0 auto;
}

.config-panels {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}

.panel {
    background: #fff;
    border-radius: 12px;
    padding: 24px 28px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.panel-title {
    font-size: 14px;
    font-weight: 400;
    color: #666;
    margin: 0;
}

.field-count {
    font-size: 12px;
    color: #999;
}

.empty-state {
    padding: 24px 16px;
    text-align: center;
    color: #999;
    font-size: 13px;
    border: 2px dashed #e5e5e5;
    border-radius: 8px;
}

/* Configured fields list */
.configured-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 60px;
}

.configured-field {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #f9f9f9;
    border-radius: 8px;
    cursor: grab;
    transition: background 0.15s;
}

.configured-field:hover {
    background: #f0f0f0;
}

.ghost-field {
    opacity: 0.5;
    background: #fff5a79e !important;
}

.field-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.field-label {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

.field-section-tag {
    font-size: 11px;
    color: #999;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 4px;
}

.field-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    color: #ccc;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.15s;
}

.field-remove:hover {
    background: #fee2e2;
    color: #dc2626;
}

.panel-footer {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f0;
}

.save-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border: none;
    background: var(--color-primary);
    color: var(--color-primary-text);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}

.save-btn:hover {
    background: var(--color-primary-hover);
}

.save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Available fields */
.available-sections {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.available-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
    color: #888;
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.section-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.available-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.available-field {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid #e5e5e5;
    background: #fff;
    border-radius: 6px;
    font-size: 13px;
    color: #555;
    cursor: pointer;
    transition: all 0.15s;
}

.available-field:hover {
    background: #f5f5f5;
    border-color: #ccc;
}

.available-field .add-icon {
    font-size: 10px;
    color: #999;
}

@media (max-width: 800px) {
    .config-panels {
        grid-template-columns: 1fr;
    }
}
</style>
