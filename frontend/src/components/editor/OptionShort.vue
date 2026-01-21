<script setup>
import { ref, computed, nextTick } from 'vue'
import InputText from 'primevue/inputtext'
import CustomToggle from './CustomToggle.vue'

const props = defineProps({
    option: {
        type: Object,
        required: true
    },
    section: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['update', 'delete'])

// Inline editing state
const isEditing = ref(false)
const editLabel = ref('')
const inputRef = ref(null)

// Computed
const isActive = computed(() => props.option.is_active)
const isMarkedForDelete = computed(() => props.option.draft_action === 'delete')
const isNew = computed(() => props.option.label === 'Neues Feld')

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
</script>

<template>
    <div class="option-wrapper">
        <div
            class="option-short drag-handle"
            :class="{
                'is-inactive': !isActive,
                'is-marked-delete': isMarkedForDelete,
                'is-new': isNew
            }"
        >
            <!-- Label (editable) -->
            <div class="option-label" @click.stop="startEditing">
                <template v-if="isEditing">
                    <InputText
                        ref="inputRef"
                        v-model="editLabel"
                        class="edit-input"
                        @blur="saveEdit"
                        @keydown="onKeydown"
                        @click.stop
                    />
                </template>
                <template v-else>
                    <span class="label-text" :class="{ 'strikethrough': isMarkedForDelete }">
                        {{ option.label }}
                    </span>
                </template>
            </div>

            <!-- Toggle -->
            <CustomToggle
                :modelValue="isActive"
                @update:modelValue="toggleActive"
                :disabled="isMarkedForDelete"
                @click.stop
            />
        </div>

        <!-- Delete link for deactivated options -->
        <a
            v-if="!isActive && !isMarkedForDelete"
            href="#"
            class="delete-link"
            @click.prevent="emit('delete', option.id)"
        >
            Feld löschen
        </a>
    </div>
</template>

<style scoped>
.option-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
}

.delete-link {
    font-size: 12px;
    color: #D32F2F;
    text-decoration: none;
    padding-left: 4px;
}

.delete-link:hover {
    text-decoration: underline;
}

.option-short {
    display: flex;
    min-width: 193px;
    width: fit-content;
    height: 30px;
    padding: 14px 20px;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    border-radius: 5px;
    border: 0.714px solid #B7B7B7;
    background: #FFF;
    transition: all 0.2s;
    box-sizing: content-box;
}

.option-short:hover {
    background: #fff5a79e;
    border-color: #999;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    cursor: move;
}

.option-short:active,
.option-short.sortable-chosen,
.option-short.sortable-ghost {
    background: #fff5a79e;
    cursor: move;
}

.option-short.is-inactive {
    opacity: 0.6;
}

.option-short.is-marked-delete {
    background: #FEE;
    border-color: #E5A;
    opacity: 0.7;
}

.option-short.is-new {
    background: #ffc7c7;
}

.option-short.is-new .label-text {
    color: #ff6262;
}

.option-label {
    flex: 1;
    min-width: 0;
    cursor: text;
}

.label-text {
    font-size: 14px;
    color: #1B1B1B;
    white-space: nowrap;
}

.label-text.strikethrough {
    text-decoration: line-through;
    color: #999;
}

.edit-input {
    width: 100%;
    font-size: 14px;
    padding: 2px 4px;
}

.edit-input :deep(.p-inputtext) {
    padding: 2px 4px;
    font-size: 14px;
}
</style>
