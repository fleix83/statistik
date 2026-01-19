<script setup>
import { computed } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import Card from 'primevue/card'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    section: {
        type: String,
        required: true
    },
    options: {
        type: Array,
        default: () => []
    },
    draggable: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['reorder', 'add'])

// Local copy for v-model binding with draggable
const localOptions = computed({
    get: () => props.options,
    set: (value) => {
        emit('reorder', props.section, value)
    }
})

function onDragEnd() {
    // Emit reorder with current order
    emit('reorder', props.section, localOptions.value)
}

function onAddClick() {
    emit('add', props.section)
}
</script>

<template>
    <Card class="section-card">
        <template #title>
            <div class="section-header">
                <span>{{ title }}</span>
                <span class="option-count">{{ options.length }}</span>
            </div>
        </template>
        <template #content>
            <draggable
                v-if="draggable"
                v-model="localOptions"
                :animation="200"
                handle=".drag-handle"
                ghost-class="ghost-item"
                @end="onDragEnd"
                item-key="id"
                class="options-list"
            >
                <template #item="{ element }">
                    <slot name="item" :element="element" :section="section" />
                </template>
            </draggable>
            <div v-else class="options-list">
                <template v-for="element in options" :key="element.id">
                    <slot name="item" :element="element" :section="section" />
                </template>
            </div>
            <slot name="footer" :section="section">
                <button class="add-option-btn" @click="onAddClick">
                    <i class="pi pi-plus"></i>
                    <span>Option hinzufügen</span>
                </button>
            </slot>
        </template>
    </Card>
</template>

<style scoped>
.section-card {
    margin-bottom: 1rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.option-count {
    background: var(--primary-100);
    color: var(--primary-700);
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.options-list {
    min-height: 50px;
}

.ghost-item {
    opacity: 0.5;
    background: var(--primary-50);
}

.add-option-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    margin-top: 0.5rem;
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
