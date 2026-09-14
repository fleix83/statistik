<script setup>
import { computed, ref, nextTick } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useReportStore } from '../../stores/report'
import { useReportExport } from '../../composables/useReportExport'
import ReportDocument from './ReportDocument.vue'

const emit = defineEmits(['close'])

const store = useReportStore()
const toast = useToast()
const { exportReport } = useReportExport()

const exporting = ref(false)
const documentRef = ref(null)

// Drag-and-drop writes the reordered list straight back to the store
const localItems = computed({
    get: () => store.items,
    set: (list) => store.setItems(list)
})

function periodSummary(item) {
    return (item.periods || []).map(p => p.label).join(' · ')
}

// Inline editing: click a title, Enter/blur saves, Escape cancels. Values go to
// the store, so the PDF (ReportDocument) picks them up. editingId is an item id
// or 'heading' for the report's own heading.
const editingId = ref(null)
const editTitle = ref('')
const titleInput = ref(null)

function setTitleInput(el) {
    titleInput.value = el
}

async function startEdit(id, current) {
    editingId.value = id
    editTitle.value = current
    await nextTick()
    titleInput.value?.focus()
    titleInput.value?.select()
}

function startEditTitle(item) {
    startEdit(item.id, item.title)
}

// Report heading: shown in the panel and used as the PDF title; empty = default
const HEADING = 'heading'
const headingLabel = computed(() => store.title || 'Report')

function startEditHeading() {
    startEdit(HEADING, store.title)
}

function saveTitle() {
    if (!editingId.value) return
    const title = editTitle.value.trim()
    if (editingId.value === HEADING) {
        store.setTitle(title)
    } else if (title) {
        store.updateItem(editingId.value, { title })
    }
    editingId.value = null
}

function cancelEditTitle() {
    editingId.value = null
}

async function handleExport() {
    if (store.count === 0 || exporting.value) return
    exporting.value = true
    try {
        await store.cropLegacyPies()   // older pie captures: crop to the donuts first
        await nextTick()
        await exportReport(documentRef.value?.pageElements() || [])
        toast.add({ severity: 'success', summary: 'Report', detail: 'PDF exportiert', life: 3000 })
        emit('close')
    } catch (error) {
        console.error('Report export error:', error)
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Report-Export fehlgeschlagen', life: 3000 })
    } finally {
        exporting.value = false
    }
}
</script>

<template>
    <div class="report-panel">
        <div class="report-panel-header">
            <h3 class="report-panel-title">
                <input
                    v-if="editingId === HEADING"
                    :ref="setTitleInput"
                    v-model="editTitle"
                    class="inline-edit-input"
                    type="text"
                    placeholder="Statistik …"
                    @keydown.enter.prevent="saveTitle"
                    @keydown.esc.stop="cancelEditTitle"
                    @blur="saveTitle"
                />
                <span
                    v-else
                    class="inline-edit-text"
                    title="Überschrift des Reports bearbeiten"
                    @click="startEditHeading"
                >{{ headingLabel }}</span>
            </h3>
            <span class="report-panel-count">{{ store.count }} {{ store.count === 1 ? 'Ansicht' : 'Ansichten' }}</span>
        </div>

        <div v-if="store.count === 0" class="report-empty">
            <i class="pi pi-file-pdf"></i>
            <p>Noch keine Ansichten im Report.</p>
            <p class="report-empty-hint">In der Auswertung über «Zum Report» die aktuelle Ansicht hinzufügen.</p>
        </div>

        <draggable
            v-else
            v-model="localItems"
            handle=".drag-handle"
            ghost-class="ghost-item"
            :animation="200"
            class="report-list"
        >
            <div
                v-for="(item, index) in localItems"
                :key="item.id"
                class="report-item"
            >
                <span class="drag-handle" title="Verschieben">
                    <i class="pi pi-bars"></i>
                </span>
                <span class="report-item-index">{{ index + 1 }}</span>
                <img :src="item.image.src" alt="" class="report-item-thumb" />
                <div class="report-item-text">
                    <div class="report-item-title">
                        <input
                            v-if="editingId === item.id"
                            :ref="setTitleInput"
                            v-model="editTitle"
                            class="inline-edit-input"
                            type="text"
                            @keydown.enter.prevent="saveTitle"
                            @keydown.esc.stop="cancelEditTitle"
                            @blur="saveTitle"
                        />
                        <template v-else>
                            <span
                                class="inline-edit-text"
                                title="Titel bearbeiten"
                                @click="startEditTitle(item)"
                            >{{ item.title }}</span>
                            <span v-if="item.subtitle" class="report-item-subtitle">{{ item.subtitle }}</span>
                        </template>
                    </div>
                    <div class="report-item-meta">{{ periodSummary(item) }}</div>
                </div>
                <button
                    class="report-item-remove"
                    title="Aus Report entfernen"
                    @click="store.removeItem(item.id)"
                >
                    <i class="pi pi-times"></i>
                </button>
            </div>
        </draggable>

        <div class="report-panel-footer">
            <button
                class="report-clear-btn"
                :disabled="store.count === 0 || exporting"
                @click="store.clear()"
            >
                Alle entfernen
            </button>
            <Button
                label="PDF exportieren"
                icon="pi pi-file-pdf"
                class="report-export-btn"
                :loading="exporting"
                :disabled="store.count === 0"
                @click="handleExport"
            />
        </div>

        <!-- Rendered off-screen only while exporting -->
        <ReportDocument v-if="exporting" ref="documentRef" :items="store.items" :title="store.title" />
    </div>
</template>

<style scoped>
.report-panel {
    width: 440px;
    max-width: calc(100vw - 2rem);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.report-panel-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.report-panel-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-color);
}

.report-panel-count {
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.report-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 1.5rem 1rem;
    text-align: center;
    color: var(--text-color-secondary);
    background: #f5f3ef;
    border-radius: 16px;
}

.report-empty i {
    font-size: 1.75rem;
    margin-bottom: 0.25rem;
}

.report-empty p {
    margin: 0;
}

.report-empty-hint {
    font-size: 0.85rem;
}

.report-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 50vh;
    overflow-y: auto;
    padding-right: 2px;
}

.report-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.6rem;
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 14px;
}

.report-item.ghost-item {
    opacity: 0.4;
    background: #bad6ff;
}

.drag-handle {
    cursor: grab;
    color: #94a3b8;
    display: flex;
    padding: 0.25rem;
}

.drag-handle:active {
    cursor: grabbing;
}

.report-item-index {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    min-width: 1.1rem;
    text-align: center;
}

.report-item-thumb {
    width: 64px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    background: #fff;
    flex-shrink: 0;
}

.report-item-text {
    flex: 1;
    min-width: 0;
}

.report-item-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.report-item-subtitle {
    margin-left: 0.3rem;
    font-weight: 400;
    color: var(--text-color-secondary);
    font-size: 0.8rem;
}

/* Click-to-edit text (report heading and view titles): the text itself is the
   control; while editing it sits on a soft yellow highlight with a dark caret */
.inline-edit-text {
    cursor: text;
    border-radius: 4px;
    padding: 0 0.2rem;
    margin: 0 -0.2rem;
    transition: background 0.15s;
}

.inline-edit-text:hover {
    background: rgba(255, 234, 149, 0.45);
}

.inline-edit-input {
    width: 100%;
    box-sizing: border-box;
    font: inherit;
    font-weight: inherit;
    color: var(--text-color);
    background: rgba(255, 234, 149, 0.55);
    caret-color: #111827;
    border: none;
    border-radius: 4px;
    padding: 0 0.2rem;
    margin: 0 -0.2rem;
    outline: none;
}

.inline-edit-input::placeholder {
    color: #9ca3af;
    font-weight: 400;
}

.report-item-meta {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.report-item-remove {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 50%;
    display: flex;
    transition: background 0.15s, color 0.15s;
}

.report-item-remove:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.report-panel-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.25rem;
}

.report-clear-btn {
    border: none;
    background: transparent;
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0.4rem 0.25rem;
    text-decoration: underline;
}

.report-clear-btn:disabled {
    opacity: 0.4;
    cursor: default;
    text-decoration: none;
}

.report-export-btn {
    border-radius: 30px;
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
    padding: 0.6rem 1.1rem;
}

.report-export-btn:hover:not(:disabled) {
    background: var(--color-primary-hover) !important;
}
</style>
