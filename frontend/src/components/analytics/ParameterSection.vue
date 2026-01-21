<script setup>
import { ref, computed } from 'vue'
import Panel from 'primevue/panel'
import Chip from 'primevue/chip'
import Button from 'primevue/button'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

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
    // For grouped sections like Kontakt
    groups: {
        type: Object,
        default: null
    }
})

const {
    selectedParams,
    activeSection,
    toggleParam,
    clearSection
} = useAnalyticsState()

const collapsed = ref(false)

// Check if a value is selected
function isSelected(section, value) {
    return selectedParams.value[section]?.includes(value)
}

// Get selection count for this section
const selectionCount = computed(() => {
    if (props.groups) {
        let count = 0
        for (const groupSection of Object.keys(props.groups)) {
            count += selectedParams.value[groupSection]?.length || 0
        }
        return count
    }
    return selectedParams.value[props.section]?.length || 0
})

// Check if this section is active for visualization
const isActiveSection = computed(() => {
    if (props.groups) {
        return Object.keys(props.groups).includes(activeSection.value)
    }
    return activeSection.value === props.section
})

// Set this section as active for visualization
function setActive(section) {
    activeSection.value = section || props.section
}

// Clear all selections in this section
function clearAll() {
    if (props.groups) {
        for (const groupSection of Object.keys(props.groups)) {
            clearSection(groupSection)
        }
    } else {
        clearSection(props.section)
    }
}

// Group label mapping
const groupLabels = {
    kontaktart: 'Kontaktart',
    person: 'Person',
    dauer: 'Dauer'
}
</script>

<template>
    <Panel
        :header="title"
        :toggleable="true"
        v-model:collapsed="collapsed"
        class="parameter-section"
        :class="{ 'is-active': isActiveSection }"
    >
        <template #header>
            <div class="panel-header">
                <span class="panel-title">{{ title }}</span>
                <span v-if="selectionCount > 0" class="selection-badge">
                    {{ selectionCount }}
                </span>
            </div>
        </template>

        <template #icons>
            <Button
                v-if="selectionCount > 0"
                icon="pi pi-times"
                severity="secondary"
                text
                size="small"
                @click.stop="clearAll"
                v-tooltip.top="'Auswahl löschen'"
            />
        </template>

        <!-- Grouped options (e.g., Kontakt) -->
        <template v-if="groups">
            <div
                v-for="(groupOptions, groupSection) in groups"
                :key="groupSection"
                class="option-group"
            >
                <div class="group-header">
                    <span class="group-label">{{ groupLabels[groupSection] || groupSection }}</span>
                    <Button
                        size="small"
                        :outlined="activeSection !== groupSection"
                        label="Anzeigen"
                        @click="setActive(groupSection)"
                        class="show-btn"
                    />
                </div>
                <div class="chips-container">
                    <Chip
                        v-for="value in groupOptions"
                        :key="value"
                        :label="value"
                        :class="{ 'chip-selected': isSelected(groupSection, value) }"
                        @click="toggleParam(groupSection, value)"
                    />
                </div>
            </div>
        </template>

        <!-- Simple options -->
        <template v-else>
            <div class="section-header">
                <Button
                    size="small"
                    :outlined="activeSection !== section"
                    label="Anzeigen"
                    @click="setActive(section)"
                    class="show-btn"
                />
            </div>
            <div class="chips-container">
                <Chip
                    v-for="value in options"
                    :key="value"
                    :label="value"
                    :class="{ 'chip-selected': isSelected(section, value) }"
                    @click="toggleParam(section, value)"
                />
            </div>
        </template>
    </Panel>
</template>

<style scoped>
.parameter-section {
    margin-bottom: 0.5rem;
}

.parameter-section.is-active :deep(.p-panel-header) {
    border-left: 3px solid var(--primary-color);
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.panel-title {
    font-weight: 600;
}

.selection-badge {
    background: var(--primary-color);
    color: white;
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 10px;
    font-weight: 600;
}

.option-group {
    margin-bottom: 1rem;
}

.option-group:last-child {
    margin-bottom: 0;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.group-label {
    font-size: 0.875rem;
    color: var(--text-color-secondary);
    font-weight: 500;
}

.section-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.5rem;
}

.show-btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.chips-container :deep(.p-chip) {
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.8125rem;
    padding: 0.25rem 0.625rem;
    background: #f1f5f9;
    color: #334155;
    border: 2px solid transparent;
}

.chips-container :deep(.p-chip:hover) {
    background: #e0e7ff;
    border-color: #a5b4fc;
}

.chips-container :deep(.p-chip.chip-selected) {
    background: #6366f1;
    color: white;
    border-color: #4f46e5;
    font-weight: 600;
}

.chips-container :deep(.p-chip.chip-selected:hover) {
    background: #4f46e5;
    border-color: #4338ca;
}
</style>
