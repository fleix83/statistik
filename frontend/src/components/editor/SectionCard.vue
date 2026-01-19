<script setup>
import { ref, watch, computed } from 'vue'
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

// Local copy of options for draggable - use computed to always reflect latest props
const localOptions = computed({
    get: () => props.options || [],
    set: (newVal) => {
        emit('reorder', props.section, newVal)
    }
})

function onDragEnd() {
    emit('reorder', props.section, localOptions.value)
}
</script>

<template>
    <div class="section-container">
        <h4 class="section-title">{{ title }}</h4>

        <div
            class="options-container"
            :class="{ 'layout-grid': layout === 'grid', 'layout-list': layout === 'list' }"
        >
            <!-- Direct v-for rendering for debugging -->
            <template v-for="element in localOptions" :key="element.id">
                <slot name="item" :element="element" :section="section" />
            </template>
        </div>

        <slot name="footer" :section="section" />
    </div>
</template>

<style scoped>
.section-container {
    background: #FFF;
    border-radius: 10px;
    padding: 24px 28px;
}

.section-title {
    font-size: 14px;
    font-weight: 400;
    color: #666;
    margin: 0 0 16px 0;
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
    background: #E8E8E8;
}
</style>
