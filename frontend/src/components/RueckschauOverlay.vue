<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useRueckschauState } from '../composables/useRueckschauState'

const props = defineProps({
    visible: { type: Boolean, default: false },
    initialDays: { type: Number, default: 7 }
})

const emit = defineEmits(['close'])

const router = useRouter()
const authStore = useAuthStore()

// Navbar always visible in Rückschau

function navigateTo(route) {
    emit('close')
    router.push(route)
}

const navItems = computed(() => {
    const items = [
        { label: 'Erfassung', icon: 'pi pi-pencil', route: '/' }
    ]
    if (authStore.isAuthenticated) {
        items.push(
            { label: 'Editor', icon: 'pi pi-cog', route: '/editor' },
            { label: 'Auswertung', icon: 'pi pi-chart-bar', route: '/analytics' }
        )
    }
    return items
})


const { gridData, loading, loadFields, loadGridData } = useRueckschauState()

const activeDays = ref(props.initialDays)

const weekdayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']

const todayStr = computed(() => {
    const d = new Date()
    return d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0')
})

// Columns: merge consecutive Sa+So into a single "WE" column
const columns = computed(() => {
    if (!gridData.value?.dates) return []
    const rawDates = gridData.value.dates
    const cols = []
    let i = 0
    while (i < rawDates.length) {
        const dateStr = rawDates[i]
        const d = new Date(dateStr + 'T00:00:00')
        const dayOfWeek = d.getDay()

        if (dayOfWeek === 6 && i + 1 < rawDates.length) {
            // Saturday — check if Sunday follows
            const nextStr = rawDates[i + 1]
            const nextD = new Date(nextStr + 'T00:00:00')
            if (nextD.getDay() === 0) {
                // Merge Sa+So into one WE column
                cols.push({
                    type: 'weekend',
                    label: 'WE',
                    isToday: false,
                    isWeekend: true,
                    sourceIndices: [i, i + 1]
                })
                i += 2
                continue
            }
        }

        // Single day column (including a lone Sa or So at edges)
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            cols.push({
                type: 'weekend',
                label: 'WE',
                isToday: false,
                isWeekend: true,
                sourceIndices: [i]
            })
        } else {
            const day = String(d.getDate()).padStart(2, '0')
            const month = String(d.getMonth() + 1).padStart(2, '0')
            cols.push({
                type: 'weekday',
                weekday: weekdayNames[dayOfWeek],
                display: `${day}.${month}.`,
                isToday: dateStr === todayStr.value,
                isWeekend: false,
                sourceIndices: [i]
            })
        }
        i++
    }
    return cols
})

// For each row, compute merged cell values matching columns
function getRowCells(rowData) {
    return columns.value.map(col => {
        // Sum counts across all source indices for this column
        let total = 0
        for (const idx of col.sourceIndices) {
            total += rowData[idx] || 0
        }
        return total
    })
}

function getSectionColor(section) {
    const colors = {
        kontaktart: 'var(--color-kontaktart-checked)',
        person: 'var(--color-person-checked)',
        dauer: 'var(--color-dauer-checked)',
        thema: 'var(--color-thema)',
        zeitfenster: 'var(--color-zeitfenster)',
        referenz: 'var(--color-referenz)'
    }
    return colors[section] || '#ccc'
}

async function fetchData() {
    try {
        await loadFields()
        await loadGridData(activeDays.value)
    } catch {
        // error handled in composable
    }
}

function switchDays(days) {
    activeDays.value = days
    loadGridData(days)
}

function close() {
    emit('close')
}

function onKeydown(e) {
    if (e.key === 'Escape') close()
}

// Lock body scroll when visible
watch(() => props.visible, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden'
        document.addEventListener('keydown', onKeydown)
        fetchData()
    } else {
        document.body.style.overflow = ''
        document.removeEventListener('keydown', onKeydown)
    }
})

watch(() => props.initialDays, (days) => {
    activeDays.value = days
})

onUnmounted(() => {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <Teleport to="body">
        <Transition name="overlay-fade">
            <div v-if="visible" class="rueckschau-overlay">
                <!-- Nav Bar -->
                <div class="overlay-navbar">
                    <div class="overlay-nav-brand">
                        <img src="@/assets/logo_wegweiser.svg" alt="Wegweiser" class="overlay-nav-logo" />
                        <h1 class="overlay-nav-title">STATISTIK</h1>
                    </div>
                    <div class="overlay-nav-items">
                        <button
                            v-for="item in navItems"
                            :key="item.route"
                            class="overlay-nav-item"
                            :class="{ active: item.route === '/' }"
                            @click="navigateTo(item.route)"
                        >
                            <i :class="item.icon"></i>
                            {{ item.label }}
                        </button>
                    </div>
                    <button class="close-btn" @click="close" title="Schliessen">
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <!-- Header -->
                <div class="overlay-header">
                    <div class="header-left">
                        <h2 class="overlay-title">Rückschau</h2>
                        <div class="days-toggle">
                            <button
                                class="toggle-btn"
                                :class="{ active: activeDays === 7 }"
                                @click="switchDays(7)"
                            >7 Tage</button>
                            <button
                                class="toggle-btn"
                                :class="{ active: activeDays === 30 }"
                                @click="switchDays(30)"
                            >30 Tage</button>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="overlay-content">
                    <!-- Loading -->
                    <div v-if="loading" class="loading-state">
                        <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                        <span>Lade Daten...</span>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="!gridData?.fields?.length" class="empty-state">
                        <i class="pi pi-th-large" style="font-size: 2.5rem; color: #ccc"></i>
                        <p>Keine Felder konfiguriert.</p>
                        <p class="empty-hint">Konfiguriere Felder im Editor unter "Felder Rückschau".</p>
                    </div>

                    <!-- Grid -->
                    <div v-else class="grid-wrapper">
                        <table class="data-grid">
                            <thead>
                                <tr>
                                    <th class="field-name-header">Feld</th>
                                    <th
                                        v-for="(col, colIdx) in columns"
                                        :key="colIdx"
                                        class="date-header"
                                        :class="{ 'is-today': col.isToday, 'is-weekend': col.isWeekend }"
                                    >
                                        <template v-if="col.type === 'weekend'">
                                            <span class="date-we">WE</span>
                                        </template>
                                        <template v-else>
                                            <span class="date-weekday">{{ col.weekday }}</span>
                                            <span class="date-day">{{ col.display }}</span>
                                        </template>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIdx) in gridData.counts"
                                    :key="row.section + '|' + row.value_text"
                                >
                                    <td class="field-name-cell">
                                        <span class="field-dot" :style="{ background: getSectionColor(row.section) }"></span>
                                        <span class="field-text">{{ row.value_text }}</span>
                                    </td>
                                    <td
                                        v-for="(count, colIdx) in getRowCells(row.data)"
                                        :key="colIdx"
                                        class="count-cell"
                                        :class="{
                                            'is-today': columns[colIdx]?.isToday,
                                            'is-weekend': columns[colIdx]?.isWeekend
                                        }"
                                    >
                                        <span v-if="count > 0" class="count-dot">{{ count }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.rueckschau-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    background: #F5F3EF;
    display: flex;
    flex-direction: column;
}

/* Fade transition */
.overlay-fade-enter-active,
.overlay-fade-leave-active {
    transition: opacity 0.25s ease;
}
.overlay-fade-enter-from,
.overlay-fade-leave-to {
    opacity: 0;
}

/* Overlay Navbar */
.overlay-navbar {
    display: flex;
    align-items: center;
    padding: 0 1rem 0 0;
    background: #ffecba;
    flex-shrink: 0;
}

.overlay-nav-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-left: -80px;
    margin-right: 3rem;
    padding: 20px;
}

.overlay-nav-logo {
    height: 3rem;
    opacity: 0.6;
}

.overlay-nav-title {
    font-family: 'Din Next Rounded', sans-serif;
    font-size: 2.0rem;
    font-weight: 400;
    margin: -0.7rem 0 0;
    margin-left: 157px;
    color: var(--text-color);
    letter-spacing: 0.10em;
    opacity: 0.7;
}

.overlay-nav-items {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.overlay-nav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: none;
    background: transparent;
    border-radius: var(--border-radius, 6px);
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-color);
    cursor: pointer;
    transition: background-color 0.2s;
    font-family: inherit;
}

.overlay-nav-item:hover {
    background: var(--surface-hover, rgba(0, 0, 0, 0.06));
}

.overlay-nav-item.active {
    background: var(--primary-color, #FFEA95);
    color: var(--primary-color-text, #333);
}

/* Header */
.overlay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 40px 40px;
    padding-top: 92px;
    margin-bottom: -20px;
    background: linear-gradient(180deg, #ffecba, transparent);
    border-bottom: none;
    flex-shrink: 0;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 24px;
}

.overlay-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.days-toggle {
    display: flex;
    background: #f0f0f0;
    border-radius: 8px;
    padding: 3px;
}

.toggle-btn {
    padding: 6px 16px;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    transition: all 0.15s;
}

.toggle-btn.active {
    background: #FFF5A7;
    color: #333;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: none;
    background: transparent;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s;
    margin-left: auto;
    margin-right: 3rem;
}

.close-btn:hover {
    background: rgba(0, 0, 0, 0.06);
}

.close-btn i {
    font-size: 18px;
    color: #666;
}

/* Content */
.overlay-content {
    flex: 1;
    overflow: auto;
    padding: 24px 40px;
}

.loading-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 4rem;
    color: #666;
}

.empty-hint {
    font-size: 14px;
    color: #999;
}

/* Grid wrapper */
.grid-wrapper {
    background: #fff;
    border-radius: 12px;
    overflow-x: auto;
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.data-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

/* Header cells */
.field-name-header {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #fff;
    text-align: left;
    padding: 12px 16px;
    font-weight: 500;
    color: #999;
    font-size: 12px;
    border-bottom: 1px dotted #e5e5e5;
    border-right: 1px dotted #e5e5e5;
    min-width: 180px;
}

.date-header {
    padding: 8px 4px;
    text-align: center;
    font-weight: 400;
    border-bottom: 1px dotted #e5e5e5;
    border-right: 1px dotted #e5e5e5;
    min-width: 52px;
    vertical-align: bottom;
}

.date-header:last-child {
    border-right: none;
}

.date-header.is-today {
    background: rgba(255, 234, 149, 0.3);
}

.date-header.is-weekend {
    background: #fafafa;
    min-width: 36px;
}

.date-weekday {
    display: block;
    font-size: 11px;
    color: #999;
    margin-bottom: 2px;
}

.date-day {
    display: block;
    font-size: 12px;
    color: #666;
}

.date-we {
    display: block;
    font-size: 11px;
    color: #ccc;
    font-weight: 500;
}

/* Body cells */
.field-name-cell {
    position: sticky;
    left: 0;
    z-index: 1;
    background: #fff;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px dotted #e5e5e5;
    border-right: 1px dotted #e5e5e5;
    white-space: nowrap;
}

.field-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.field-text {
    font-weight: 500;
    color: #333;
    font-size: 16px;
}

.count-cell {
    padding: 6px 4px;
    text-align: center;
    border-bottom: 1px dotted #e5e5e5;
    border-right: 1px dotted #e5e5e5;
    vertical-align: middle;
}

.count-cell:last-child {
    border-right: none;
}

.count-cell.is-today {
    background: rgba(255, 234, 149, 0.15);
}

.count-cell.is-weekend {
    background: #fafafa;
}

.count-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #60a5fa;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}
</style>
