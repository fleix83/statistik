<script setup>
import { ref, nextTick } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

const props = defineProps({
    section: {
        type: String,
        required: true
    },
    placeholder: {
        type: String,
        default: 'Neue Option eingeben...'
    }
})

const emit = defineEmits(['add'])

const isAdding = ref(false)
const newLabel = ref('')
const inputRef = ref(null)

function startAdding() {
    isAdding.value = true
    newLabel.value = ''
    nextTick(() => {
        inputRef.value?.$el?.focus()
    })
}

function confirmAdd() {
    const label = newLabel.value.trim()
    if (label) {
        emit('add', props.section, label)
        newLabel.value = ''
        isAdding.value = false
    }
}

function cancelAdd() {
    isAdding.value = false
    newLabel.value = ''
}

function onKeydown(event) {
    if (event.key === 'Enter') {
        confirmAdd()
    } else if (event.key === 'Escape') {
        cancelAdd()
    }
}
</script>

<template>
    <div class="add-option-wrapper">
        <div v-if="isAdding" class="add-option-form">
            <InputText
                ref="inputRef"
                v-model="newLabel"
                :placeholder="placeholder"
                class="add-input"
                @keydown="onKeydown"
                @blur="cancelAdd"
            />
            <Button
                icon="pi pi-check"
                severity="success"
                text
                rounded
                size="small"
                @mousedown.prevent="confirmAdd"
            />
            <Button
                icon="pi pi-times"
                severity="secondary"
                text
                rounded
                size="small"
                @mousedown.prevent="cancelAdd"
            />
        </div>
        <button v-else class="add-option-btn" @click="startAdding">
            <i class="pi pi-plus"></i>
            <span>Option hinzufügen</span>
        </button>
    </div>
</template>

<style scoped>
.add-option-wrapper {
    margin-top: 0.5rem;
}

.add-option-form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--surface-50);
    border: 1px solid var(--surface-200);
    border-radius: 6px;
}

.add-input {
    flex: 1;
}

.add-option-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    background: transparent;
    border: 2px dashed var(--surface-300);
    border-radius: 6px;
    color: var(--text-color-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.add-option-btn:hover {
    border-color: var(--primary-500);
    color: var(--primary-500);
    background: var(--primary-50);
}
</style>
