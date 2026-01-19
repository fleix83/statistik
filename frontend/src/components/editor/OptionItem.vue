<script setup>
import { ref, computed, nextTick } from 'vue'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const props = defineProps({
    option: {
        type: Object,
        required: true
    },
    section: {
        type: String,
        required: true
    },
    showDragHandle: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update', 'delete'])

// Inline editing state
const isEditing = ref(false)
const editLabel = ref('')
const inputRef = ref(null)

// Computed
const isActive = computed(() => props.option.is_active)
const hasDraftChanges = computed(() => props.option.draft_action !== null)
const isMarkedForDelete = computed(() => props.option.draft_action === 'delete')
const isNew = computed(() => props.option.draft_action === 'create')
const isModified = computed(() => props.option.draft_action === 'update')

const draftSeverity = computed(() => {
    switch (props.option.draft_action) {
        case 'create': return 'success'
        case 'update': return 'warn'
        case 'delete': return 'danger'
        default: return 'secondary'
    }
})

const draftLabel = computed(() => {
    switch (props.option.draft_action) {
        case 'create': return 'Neu'
        case 'update': return 'Geändert'
        case 'delete': return 'Löschen'
        default: return null
    }
})

// Methods
function startEditing() {
    if (isMarkedForDelete.value) return
    editLabel.value = props.option.label
    isEditing.value = true
    nextTick(() => {
        inputRef.value?.$el?.focus()
    })
}

function saveEdit() {
    const newLabel = editLabel.value.trim()
    if (newLabel && newLabel !== props.option.label) {
        emit('update', props.option.id, { label: newLabel })
    }
    isEditing.value = false
}

function cancelEdit() {
    isEditing.value = false
    editLabel.value = ''
}

function onKeydown(event) {
    if (event.key === 'Enter') {
        saveEdit()
    } else if (event.key === 'Escape') {
        cancelEdit()
    }
}

function toggleActive() {
    emit('update', props.option.id, { is_active: !isActive.value })
}

function onDelete() {
    emit('delete', props.option.id)
}
</script>

<template>
    <div
        class="option-item"
        :class="{
            'is-inactive': !isActive,
            'is-marked-delete': isMarkedForDelete,
            'has-draft': hasDraftChanges
        }"
    >
        <!-- Drag Handle -->
        <div v-if="showDragHandle" class="drag-handle">
            <i class="pi pi-bars"></i>
        </div>

        <!-- Label (editable) -->
        <div class="option-label" @click="startEditing">
            <template v-if="isEditing">
                <InputText
                    ref="inputRef"
                    v-model="editLabel"
                    class="edit-input"
                    @blur="saveEdit"
                    @keydown="onKeydown"
                />
            </template>
            <template v-else>
                <span class="label-text" :class="{ 'strikethrough': isMarkedForDelete }">
                    {{ option.label }}
                </span>
            </template>
        </div>

        <!-- Draft Status Tag -->
        <Tag
            v-if="draftLabel"
            :value="draftLabel"
            :severity="draftSeverity"
            class="draft-tag"
        />

        <!-- Actions -->
        <div class="option-actions">
            <!-- Toggle Active -->
            <ToggleSwitch
                :modelValue="isActive"
                @update:modelValue="toggleActive"
                :disabled="isMarkedForDelete"
                class="active-toggle"
            />

            <!-- Delete Button (only when inactive and not marked) -->
            <Button
                v-if="!isActive && !isMarkedForDelete"
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                size="small"
                @click="onDelete"
                v-tooltip.top="'Löschen'"
            />

            <!-- Undo delete for marked items -->
            <Button
                v-if="isMarkedForDelete"
                icon="pi pi-undo"
                severity="secondary"
                text
                rounded
                size="small"
                @click="toggleActive"
                v-tooltip.top="'Löschen rückgängig'"
            />
        </div>
    </div>
</template>

<style scoped>
.option-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    background: var(--surface-0);
    border: 1px solid var(--surface-200);
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: all 0.2s;
}

.option-item:hover {
    border-color: var(--surface-300);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.option-item.is-inactive {
    background: var(--surface-50);
    opacity: 0.7;
}

.option-item.is-marked-delete {
    background: var(--red-50);
    border-color: var(--red-200);
}

.option-item.has-draft {
    border-left: 3px solid var(--primary-500);
}

.option-item.is-marked-delete.has-draft {
    border-left-color: var(--red-500);
}

.drag-handle {
    cursor: grab;
    color: var(--text-color-secondary);
    padding: 0.25rem;
}

.drag-handle:active {
    cursor: grabbing;
}

.option-label {
    flex: 1;
    min-width: 0;
    cursor: text;
}

.label-text {
    display: block;
    padding: 0.25rem 0;
}

.label-text.strikethrough {
    text-decoration: line-through;
    color: var(--text-color-secondary);
}

.edit-input {
    width: 100%;
}

.draft-tag {
    flex-shrink: 0;
}

.option-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.active-toggle {
    transform: scale(0.85);
}
</style>
