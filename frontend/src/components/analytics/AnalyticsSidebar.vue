<script setup>
import { computed, ref, onMounted } from 'vue'
import Button from 'primevue/button'
import PeriodSelector from './PeriodSelector.vue'
import ParameterSection from './ParameterSection.vue'
import MarkerManager from './MarkerManager.vue'
import { useAnalyticsState } from '../../composables/useAnalyticsState'

const emit = defineEmits(['fetch', 'export', 'toggle'])

const collapsed = ref(false)
const activeTab = ref('period') // 'period' | 'markers'

const {
    filterOptions,
    loading,
    fetchData,
    clearAllSelections,
    loadMarkers
} = useAnalyticsState()

// Load markers on mount
onMounted(() => {
    loadMarkers()
})

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
            <!-- Period & Markers Card with Tabs -->
            <div class="sidebar-card">
                <div class="sidebar-card-tabs">
                    <button
                        class="tab-btn"
                        :class="{ active: activeTab === 'period' }"
                        @click="activeTab = 'period'"
                    >
                        <i class="pi pi-calendar"></i>
                        Zeitperiode
                    </button>
                    <button
                        class="tab-btn"
                        :class="{ active: activeTab === 'markers' }"
                        @click="activeTab = 'markers'"
                    >
                        <i class="pi pi-flag"></i>
                        Markierungen
                    </button>
                </div>
                <div class="sidebar-card-content">
                    <PeriodSelector v-if="activeTab === 'period'" />
                    <MarkerManager v-else />
                </div>
            </div>

            <!-- Filter & Anzeige Card -->
            <div class="sidebar-card filters-section">
                <div class="sidebar-card-header">
                    <i class="pi pi-filter"></i>
                    Filter & Anzeige
                    <button class="reset-link" @click="clearAllSelections" title="Alle Filter zurücksetzen">
                        Zurücksetzen
                    </button>
                </div>
                <div class="sidebar-card-content">
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
            </div>

            <!-- Action Buttons -->
            <div class="sidebar-section action-buttons">
                <Button
                    label="Anzeigen"
                    icon="pi pi-search"
                    :loading="loading"
                    @click="onFetch"
                    class="w-full action-btn-primary"
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
    height: 100%;
    transition: width 0.3s ease, min-width 0.3s ease;
    overflow: visible;
}

.analytics-sidebar.collapsed {
    width: 40px;
    min-width: 40px;
}

.sidebar-content {
    padding: 1rem;
    height: 100%;
    overflow-y: auto;
}

.sidebar-toggle {
    position: absolute;
    top: 16px;
    right: -9px;
    width: 24px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 1px solid #e2e8f0;
    border-left: none;
    border-radius: 0 6px 6px 0;
    cursor: pointer;
    color: var(--text-color-secondary);
    transition: background-color 0.2s, color 0.2s;
    z-index: 10;
}

.sidebar-toggle i {
    font-size: 0.75rem;
}

.sidebar-toggle:hover {
    background: #f8fafc;
    color: var(--text-color);
    width: 16px;
}

.collapsed .sidebar-toggle {
    right: -12px;
    top: 16px;
}

.sidebar-section {
    margin-bottom: 0.5rem;
}

/* Sidebar Card Styling */
.sidebar-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.sidebar-card:first-child {
    border-top-right-radius: 0;
}

.sidebar-card-header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 1rem;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sidebar-card-header i {
    font-size: 0.875rem;
}

.sidebar-card-header .reset-link {
    margin-left: auto;
}

/* Tab Header */
.sidebar-card-tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
}

.tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: none;
    border: none;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
}

.tab-btn:first-child {
    border-top-left-radius: 12px;
}

.tab-btn:last-child {
    border-top-right-radius: 0;
}

.tab-btn i {
    font-size: 0.8rem;
}

.tab-btn:hover {
    background: #f8fafc;
    color: var(--text-color);
}

.tab-btn.active {
    color: var(--primary-color);
    background: #f8fafc;
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary-color);
}

.sidebar-card-content {
    padding: 1rem;
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

.filters-section .sidebar-card-content {
    max-height: 50vh;
    overflow-y: auto;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

:deep(.action-btn-primary.p-button) {
    background: #3b82f6 !important;
    border: none !important;
    border-radius: 8px !important;
}

:deep(.action-btn-primary.p-button:hover) {
    background: #2563eb !important;
}

:deep(.action-buttons .p-button-outlined) {
    border: none !important;
}
</style>
