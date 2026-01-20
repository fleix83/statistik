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

const emit = defineEmits(['update', 'delete', 'editKeywords'])

// Inline editing state
const isEditing = ref(false)
const editLabel = ref('')
const inputRef = ref(null)

// Computed
const isActive = computed(() => props.option.is_active)
const isMarkedForDelete = computed(() => props.option.draft_action === 'delete')
const keywords = computed(() => props.option.keywords || [])

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

function onEditKeywords() {
    emit('editKeywords', props.option)
}
</script>

<template>
    <div class="option-wrapper">
        <div
            class="option-thema drag-handle"
            :class="{
                'is-inactive': !isActive,
                'is-marked-delete': isMarkedForDelete
            }"
            @dblclick="onEditKeywords"
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

            <!-- Keywords displayed inline -->
            <div class="keywords-inline" v-if="keywords.length > 0" @click.stop="onEditKeywords">
                <span
                    v-for="keyword in keywords"
                    :key="keyword"
                    class="keyword-tag"
                >
                    {{ keyword }}
                </span>
            </div>

            <!-- Toggle (always right-aligned) -->
            <div class="toggle-wrapper">
                <CustomToggle
                    :modelValue="isActive"
                    @update:modelValue="toggleActive"
                    :disabled="isMarkedForDelete"
                    @click.stop
                />
            </div>
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
    width: 100%;
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

.option-thema {
    display: inline-flex;
    min-height: 66px;
    padding: 14px 28px 14px 35px;
    justify-content: flex-start;
    align-items: center;
    gap: 32px;
    border-radius: 7px;
    border: 1px solid #B7B7B7;
    background: #FFF;
    cursor: grab;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.option-thema:hover {
    border-color: #999;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.option-thema:active {
    cursor: grabbing;
}

.option-thema.is-inactive {
    opacity: 0.6;
}

.option-thema.is-marked-delete {
    background: #FEE;
    border-color: #E5A;
    opacity: 0.7;
}

.option-label {
    flex-shrink: 0;
    cursor: text;
}

.label-text {
    font-size: 16px;
    font-weight: 500;
    color: #1B1B1B;
    white-space: nowrap;
}

.label-text.strikethrough {
    text-decoration: line-through;
    color: #999;
}

.edit-input {
    width: auto;
    min-width: 80px;
}

.edit-input :deep(.p-inputtext) {
    padding: 4px 8px;
    font-size: 16px;
    font-weight: 500;
}

.keywords-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex: 1;
    cursor: pointer;
}

.toggle-wrapper {
    margin-left: auto;
    flex-shrink: 0;
}

.keyword-tag {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #e7e7e7;
    border-radius: 2px;
    font-size: 13px;
    color: #000000;
}

.keyword-tag:hover {
    background: #EBEBEB;
}
</style>
