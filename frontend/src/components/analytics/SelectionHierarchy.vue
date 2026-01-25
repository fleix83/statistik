<script setup>
import { computed } from 'vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

const { selectionHierarchy } = useAnalyticsState()

// Get all values from a hierarchy level as flat array
function getLevelValues(level) {
    const values = []
    for (const [section, sectionValues] of Object.entries(level.selections)) {
        for (const value of sectionValues) {
            values.push(value)
        }
    }
    return values
}

// Check if hierarchy has any selections
const hasSelections = computed(() => selectionHierarchy.value.length > 0)

// Build hierarchy levels for stair display
const hierarchyLevels = computed(() => {
    if (!hasSelections.value) return []

    const total = selectionHierarchy.value.length
    return selectionHierarchy.value.map((level, index) => {
        const values = getLevelValues(level)
        return {
            text: values.join(' + '),
            hasNext: index < total - 1
        }
    })
})
</script>

<template>
    <div v-if="hasSelections" class="selection-hierarchy">
        <span
            v-for="(level, index) in hierarchyLevels"
            :key="index"
            class="level-item"
            :style="{ top: (index * 18) + 'px', left: index > 0 ? '-9px' : '0' }"
        >
            <span class="level-text">{{ level.text }}</span>
            <span v-if="level.hasNext" class="connector">┐</span>
        </span>
    </div>
</template>

<style scoped>
.selection-hierarchy {
    margin-top: 0.5rem;
    white-space: nowrap;
}

.level-item {
    position: relative;
    display: inline-flex;
    align-items: baseline;
}

.level-text {
    font-size: 0.9375rem;
    color: #64748b;
    font-weight: 400;
}

.connector {
    font-size: 0.9375rem;
    color: #94a3b8;
    margin-left: 0.375rem;
    margin-right: 0.375rem;
}
</style>
