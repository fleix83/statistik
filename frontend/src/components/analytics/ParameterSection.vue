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
    clearSection,
    fetchData
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

// Select all options in this section
function selectAll() {
    if (props.groups) {
        for (const [groupSection, groupOptions] of Object.entries(props.groups)) {
            selectedParams.value[groupSection] = groupOptions.map(opt => getOptionLabel(opt))
        }
    } else {
        selectedParams.value[props.section] = props.options.map(opt => getOptionLabel(opt))
    }
    fetchData()
}

// Group label mapping
const groupLabels = {
    kontaktart: 'Kontaktart',
    person: 'Person',
    dauer: 'Dauer'
}

// Helper to get option label (supports both string and object format)
function getOptionLabel(opt) {
    return typeof opt === 'object' ? opt.label : opt
}

// Helper to get option group (supports both string and object format)
function getOptionGroup(opt) {
    return typeof opt === 'object' ? opt.group : null
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
                <button class="select-all-link" @click.stop="selectAll">Alle</button>
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
                </div>
                <div class="chips-container">
                    <Chip
                        v-for="opt in groupOptions"
                        :key="getOptionLabel(opt)"
                        :label="getOptionLabel(opt)"
                        :class="{ 'chip-selected': isSelected(groupSection, getOptionLabel(opt)) }"
                        @click="toggleParam(groupSection, getOptionLabel(opt), getOptionGroup(opt))"
                    />
                </div>
            </div>
        </template>

        <!-- Simple options -->
        <template v-else>
            <div class="chips-container">
                <Chip
                    v-for="opt in options"
                    :key="getOptionLabel(opt)"
                    :label="getOptionLabel(opt)"
                    :class="{ 'chip-selected': isSelected(section, getOptionLabel(opt)) }"
                    @click="toggleParam(section, getOptionLabel(opt), getOptionGroup(opt))"
                />
            </div>
        </template>
    </Panel>
</template>

<style scoped>
.parameter-section {
    margin-bottom: 0.75rem;
}

.parameter-section :deep(.p-panel) {
    border-radius: 12px;
    border: none !important;
    box-shadow: none;
    overflow: hidden;
}

.parameter-section :deep(.p-panel-header) {
    background: white;
    border: none !important;
    padding: 0.75rem 1rem;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.parameter-section :deep(.p-panel-content) {
    padding: 0.75rem 1rem;
    border: none !important;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.panel-title {
    font-weight: 600;
}

.select-all-link {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.select-all-link:hover {
    background: var(--surface-100);
    text-decoration: underline;
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

.chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.975rem;
    margin-bottom: 24px;
}

.chips-container:last-child {
    margin-bottom: 0;
}

.chips-container :deep(.p-chip) {
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.8125rem;
    padding: 0.25rem 0.625rem;
    background: #f5f3ef;
    color: #334155;
    border: none;
}

.chips-container :deep(.p-chip:hover) {
    background: #e0e7ff;
}

.chips-container :deep(.p-chip.chip-selected) {
    background: #ef4444;
    color: white;
    font-weight: 600;
}

.chips-container :deep(.p-chip.chip-selected:hover) {
    background: #dc2626;
}
</style>
