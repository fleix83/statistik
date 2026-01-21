<script setup>
import { ref, watch } from 'vue'
import { VueDraggableNext as draggable } from 'vue-draggable-next'

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
    layout: {
        type: String,
        default: 'grid',
        validator: (value) => ['grid', 'list'].includes(value)
    }
})

const emit = defineEmits(['reorder', 'add'])

// Local copy of options for draggable - must be a ref for v-model
const localOptions = ref([...(props.options || [])])

// Sync when props change
watch(() => props.options, (newOptions) => {
    localOptions.value = [...(newOptions || [])]
}, { deep: true })

function onDragEnd() {
    emit('reorder', props.section, localOptions.value)
}
</script>

<template>
    <div class="section-container">
        <div class="section-header">
            <h4 class="section-title">{{ title }}</h4>
            <button class="add-option-btn" @click="emit('add', section)">
                <span class="add-icon">+</span>
                <span class="add-text">Feld hinzufügen</span>
            </button>
        </div>

        <draggable
            v-model="localOptions"
            handle=".drag-handle"
            ghost-class="ghost-item"
            :animation="200"
            class="options-container"
            :class="{ 'layout-grid': layout === 'grid', 'layout-list': layout === 'list' }"
            @end="onDragEnd"
        >
            <div v-for="element in localOptions" :key="element.id" class="draggable-item drag-handle">
                <slot name="item" :element="element" :section="section" />
            </div>
        </draggable>

        <slot name="footer" :section="section" />
    </div>
</template>

<style scoped>
.section-container {
    background: #FFF;
    border-radius: 10px;
    padding: 24px 28px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-title {
    font-size: 14px;
    font-weight: 400;
    color: #666;
    margin: 0;
}

.add-option-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px 6px 8px;
    border: none;
    background: #FFF;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    color: #666;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
}

.add-option-btn:hover {
    background: #F8F8F8;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.add-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background: #FFF;
    border: 1px solid #DDD;
    border-radius: 50%;
    font-size: 16px;
    font-weight: 300;
    color: #666;
}

.add-text {
    font-weight: 400;
}

.options-container {
    min-height: 40px;
}

.options-container.layout-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.options-container.layout-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ghost-item {
    opacity: 0.5;
}

.ghost-item :deep(.option-short),
.ghost-item :deep(.option-thema) {
    background: #fff5a79e;
}

.draggable-item.sortable-chosen :deep(.option-short),
.draggable-item.sortable-chosen :deep(.option-thema) {
    background: #fff5a79e;
}
</style>
