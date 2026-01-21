<script setup>
import { computed, ref } from 'vue'
import Divider from 'primevue/divider'
import Button from 'primevue/button'
import PeriodSelector from './PeriodSelector.vue'
import ParameterSection from './ParameterSection.vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

const emit = defineEmits(['fetch', 'export', 'toggle'])

const collapsed = ref(false)

const {
    filterOptions,
    loading,
    fetchData,
    clearAllSelections
} = useAnalyticsState()

function toggleSidebar() {
    collapsed.value = !collapsed.value
    emit('toggle', collapsed.value)
}

// Kontakt group for sidebar
const kontaktGroups = computed(() => filterOptions.value.kontakt)

function onFetch() {
    fetchData()
}

function onExport() {
    emit('export')
}
</script>

<template>
    <div class="analytics-sidebar" :class="{ collapsed }">
        <!-- Toggle Button -->
        <button class="sidebar-toggle" @click="toggleSidebar" :title="collapsed ? 'Sidebar einblenden' : 'Sidebar ausblenden'">
            <i :class="collapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'"></i>
        </button>

        <div class="sidebar-content" v-show="!collapsed">
            <!-- Period Selection -->
            <div class="sidebar-section">
                <h3 class="section-title">
                    <i class="pi pi-calendar"></i>
                    Zeitperiode
                </h3>
                <PeriodSelector />
            </div>

            <Divider />

            <!-- Parameter Filters -->
            <div class="sidebar-section filters-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="pi pi-filter"></i>
                        Filter & Anzeige
                    </h3>
                    <button class="reset-link" @click="clearAllSelections" title="Alle Filter zurücksetzen">
                        Zurücksetzen
                    </button>
                </div>

                <ParameterSection
                    title="Kontakt"
                    section="kontaktart"
                    :groups="kontaktGroups"
                />

                <ParameterSection
                    title="Thema"
                    section="thema"
                    :options="filterOptions.thema"
                />

                <ParameterSection
                    title="Zeitfenster"
                    section="zeitfenster"
                    :options="filterOptions.zeitfenster"
                />

                <ParameterSection
                    title="Referenz"
                    section="referenz"
                    :options="filterOptions.referenz"
                />
            </div>

            <Divider />

            <!-- Action Buttons -->
            <div class="sidebar-section action-buttons">
                <Button
                    label="Anzeigen"
                    icon="pi pi-search"
                    :loading="loading"
                    @click="onFetch"
                    class="w-full"
                />
                <Button
                    label="Export CSV"
                    icon="pi pi-download"
                    severity="secondary"
                    outlined
                    @click="onExport"
                    class="w-full"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.analytics-sidebar {
    position: relative;
    width: 320px;
    min-width: 320px;
    background: var(--surface-card);
    border-right: 1px solid var(--surface-border);
    overflow-y: auto;
    height: 100%;
    transition: width 0.3s ease, min-width 0.3s ease;
}

.analytics-sidebar.collapsed {
    width: 40px;
    min-width: 40px;
}

.sidebar-content {
    padding: 1rem;
}

.sidebar-toggle {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface-100);
    border: 1px solid var(--surface-border);
    border-radius: 4px;
    cursor: pointer;
    color: var(--text-color-secondary);
    transition: background-color 0.2s, color 0.2s;
    z-index: 10;
}

.sidebar-toggle:hover {
    background: var(--surface-200);
    color: var(--text-color);
}

.collapsed .sidebar-toggle {
    right: 50%;
    transform: translateX(50%);
}

.sidebar-section {
    margin-bottom: 0.5rem;
}

.section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    font-size: 0.875rem;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.section-header .section-title {
    margin-bottom: 0;
}

.reset-link {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.reset-link:hover {
    background: var(--surface-100);
    text-decoration: underline;
}

.filters-section {
    max-height: 50vh;
    overflow-y: auto;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

:deep(.p-divider) {
    margin: 1rem 0;
}
</style>
