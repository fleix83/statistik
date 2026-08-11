<script setup>
import { ref, computed, inject, watch, onMounted, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import { options, entries, users, colors } from '../services/api'
import { useAuthStore } from '../stores/auth'
import RueckschauOverlay from '../components/RueckschauOverlay.vue'
import CardColorModal from '../components/CardColorModal.vue'

const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin)

const toast = useToast()

// Form state
const selectedUser = ref(null)
const erfassungsdatum = ref(new Date())
const referenzAndere = ref('')

const isFormValid = computed(() => {
    return selectedUser.value
        && formData.value.kontaktart.length > 0
        && formData.value.person.length > 0
        && formData.value.dauer.length > 0
        && formData.value.thema.length > 0
        && formData.value.zeitfenster.length > 0
        && (formData.value.referenz.length > 0 || referenzAndere.value.trim())
})

// Checkbox states (arrays for multi-select)
const formData = ref({
    kontaktart: [],
    person: [],
    thema: [],
    zeitfenster: [],
    dauer: [],
    referenz: []
})

// Options loaded from API
const userList = ref([])
const optionsBySection = ref({
    kontaktart: [],
    person: [],
    thema: [],
    zeitfenster: [],
    dauer: [],
    referenz: []
})

// Full thema options with keywords for tooltips
const themaOptionsWithKeywords = ref([])

// Track which thema has expanded keywords
const expandedThema = ref(null)

// Toggle card borders and backgrounds
const showBorders = inject('showBorders')
const showCardBg = inject('showCardBg')

// Formatted current date for top bar
const formattedToday = computed(() => {
    const now = new Date()
    const dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
    const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
    return `${dayNames[now.getDay()]}, ${now.getDate()}. ${monthNames[now.getMonth()]} ${now.getFullYear()}`
})

const loading = ref(false)
const submitting = ref(false)
const showSplash = ref(false)
const splashFlowers = ref([])

const flowerFallbackPalette = [
    { petal: '#ffe27a', petalDark: '#f5c542', center: '#e8961e' },
    { petal: '#ffd75e', petalDark: '#eab308', center: '#d97f10' },
]

function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255
    const max = Math.max(r, g, b), min = Math.min(r, g, b)
    const l = (max + min) / 2
    if (max === min) return [0, 0, Math.round(l * 100)]
    const d = max - min
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
    let h
    switch (max) {
        case r: h = (g - b) / d + (g < b ? 6 : 0); break
        case g: h = (b - r) / d + 2; break
        default: h = (r - g) / d + 4
    }
    return [Math.round(h * 60), Math.round(s * 100), Math.round(l * 100)]
}

// Petal colors follow the live card backgrounds so admin recoloring carries into the splash.
// The card element itself is transparent — the fill lives in the --card-bg-color property.
function splashPalette() {
    const palette = []
    const probe = document.createElement('div')
    document.body.appendChild(probe)
    document.querySelectorAll('.card').forEach(card => {
        const cs = getComputedStyle(card)
        probe.style.color = ''
        probe.style.color = cs.getPropertyValue('--card-bg-color').trim() || cs.backgroundColor
        const m = getComputedStyle(probe).color.match(/[\d.]+/g)
        if (!m || m.length < 3) return
        const n = m.map(Number)
        const a = n.length > 3 ? n[3] : 1
        if (a === 0) return
        // Flatten semi-transparent card colors onto the splash backdrop (#f5f3ef)
        const [h, s] = rgbToHsl(n[0] * a + 245 * (1 - a), n[1] * a + 243 * (1 - a), n[2] * a + 239 * (1 - a))
        const ps = Math.min(Math.max(s, 45), 70)
        palette.push({
            petal: `hsl(${h} ${ps}% 68%)`,
            petalDark: `hsl(${h} ${ps}% 54%)`,
            center: '#e8a23d',
        })
    })
    probe.remove()
    return palette.length ? palette : flowerFallbackPalette
}

const flowerShapes = ['daisy', 'aster', 'wildflower', 'poppy']

function makeSplashFlowers() {
    const palette = splashPalette()
    const flowers = []
    for (let i = 0; i < 28; i++) {
        let x, y
        // Scatter across and slightly beyond the viewport, keeping the center clear for the text
        do {
            x = Math.random() * 116 - 8
            y = Math.random() * 116 - 8
        } while (Math.abs(x - 50) < 34 && Math.abs(y - 50) < 24)
        const edgeDist = Math.max(Math.abs(x - 50), Math.abs(y - 50))
        flowers.push({
            id: i,
            x,
            y,
            size: 50 + edgeDist * 2 + Math.random() * 50,
            delay: Math.random() * 0.8,
            duration: 0.5 + Math.random() * 0.5,
            rotate: Math.random() * 60 - 30,
            sway: 1.6 + Math.random() * 1.4,
            swayPhase: Math.random() * 3,
            shape: flowerShapes[i % flowerShapes.length],
            ...palette[Math.floor(Math.random() * palette.length)],
        })
    }
    return flowers
}

function triggerSplash() {
    splashFlowers.value = makeSplashFlowers()
    showSplash.value = true
    setTimeout(() => { showSplash.value = false }, 3000)
}

// Message state for inline feedback
const message = ref({ type: '', text: '' })

// Highlight user select placeholder
const highlightUserSelect = ref(false)

// Validation errors - tracks which groups are missing
const validationErrors = ref(new Set())

// Auto-clear validation errors when user makes selections
watch(selectedUser, (val) => {
    if (val) validationErrors.value.delete('user')
})
watch(() => formData.value.kontaktart.length + formData.value.person.length + formData.value.dauer.length, () => {
    if (formData.value.kontaktart.length > 0 && formData.value.person.length > 0 && formData.value.dauer.length > 0) {
        validationErrors.value.delete('kontakt')
    }
})
watch(() => formData.value.thema.length, (len) => {
    if (len > 0) validationErrors.value.delete('thema')
})
watch(() => formData.value.zeitfenster.length, (len) => {
    if (len > 0) validationErrors.value.delete('zeitfenster')
})
watch([() => formData.value.referenz.length, referenzAndere], () => {
    if (formData.value.referenz.length > 0 || referenzAndere.value.trim()) {
        validationErrors.value.delete('referenz')
    }
})

// Rueckschau overlay
const rueckschauVisible = ref(false)
const rueckschauDays = ref(7)

function openRueckschau(days) {
    rueckschauDays.value = days
    rueckschauVisible.value = true
}


// Card color customization
const cardColors = ref({})        // Live/preview state
const savedCardColors = ref({})   // Last persisted DB state
const openModals = ref({})        // { cardKey: { anchorRect } }

function openColorModal(cardKey, event) {
    if (!isAdmin.value) return
    if (openModals.value[cardKey]) return  // Already open
    // Get the parent card's rect for positioning beside (not on top of) the card
    const cardEl = event.target.closest('.card')
    const cardRect = cardEl ? cardEl.getBoundingClientRect() : event.target.getBoundingClientRect()
    openModals.value = {
        ...openModals.value,
        [cardKey]: { anchorRect: cardRect }
    }
}

function updateCardColorsPreview(cardKey, colorData) {
    cardColors.value = { ...cardColors.value, [cardKey]: { ...colorData } }
}

async function saveCardColors(cardKey, colorData) {
    try {
        await colors.update(cardKey, colorData)
        cardColors.value = { ...cardColors.value, [cardKey]: { ...colorData } }
        savedCardColors.value = { ...savedCardColors.value, [cardKey]: { ...colorData } }
        const { [cardKey]: _, ...rest } = openModals.value
        openModals.value = rest
    } catch (error) {
        console.error('Failed to save colors:', error)
    }
}

function closeColorModal(cardKey) {
    const { [cardKey]: _, ...rest } = openModals.value
    openModals.value = rest
}

function discardCardColors(cardKey) {
    // Revert to last saved DB state
    cardColors.value = { ...cardColors.value, [cardKey]: savedCardColors.value[cardKey] || null }
    const { [cardKey]: _, ...rest } = openModals.value
    openModals.value = rest
}

function getCardStyle(cardKey) {
    const c = cardColors.value[cardKey]
    if (!c) return {}
    const style = {}
    if (c.bg_color && showCardBg.value) style['--card-bg-color'] = c.bg_color
    if (c.border_color && showBorders.value) style.borderColor = c.border_color
    if (c.swatch_default) style['--custom-swatch-default'] = c.swatch_default
    if (c.swatch_hover) style['--custom-swatch-hover'] = c.swatch_hover
    if (c.swatch_checked) style['--custom-swatch-checked'] = c.swatch_checked
    return style
}

// Confirmation dialog for editing existing entries
const showConfirmDialog = ref(false)

// Pagination state
const entriesList = ref([])
const currentEntryIndex = ref(-1)
const filterMode = ref('year') // 'total' or 'year'

// Filtered list based on mode
const filteredEntries = computed(() => {
    if (filterMode.value === 'year') {
        const year = new Date().getFullYear()
        return entriesList.value.filter(e => new Date(e.created_at).getFullYear() === year)
    }
    return entriesList.value
})

// Index within the filtered list
const filteredIndex = computed(() => {
    if (currentEntryIndex.value < 0) return -1
    const entry = entriesList.value[currentEntryIndex.value]
    if (!entry) return -1
    return filteredEntries.value.findIndex(e => e.id === entry.id)
})

// Display number: 1-based position in filtered list
const displayNumber = computed(() => {
    if (filteredIndex.value < 0) return null
    return filteredIndex.value + 1
})

// Real DB id for editing operations
const currentEntryId = computed(() => {
    if (currentEntryIndex.value >= 0 && entriesList.value[currentEntryIndex.value]) {
        return entriesList.value[currentEntryIndex.value].id
    }
    return null
})

// Formatted date for header
const formattedDate = computed(() => {
    const days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
    const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
    const d = new Date()
    return `${days[d.getDay()]}, ${d.getDate()}. ${months[d.getMonth()]} ${d.getFullYear()}`
})

const currentYear = computed(() => new Date().getFullYear())

onMounted(async () => {
    await loadData()
    await loadEntries(false)  // Load entries list but don't display any - start with new entry form
    // No highlight on initial load - only on new entry tap
    document.addEventListener('click', handleClickOutside)
    // Load card colors
    try {
        const res = await colors.getAll()
        const loaded = res.data || {}
        cardColors.value = loaded
        savedCardColors.value = JSON.parse(JSON.stringify(loaded))
    } catch (e) {
        // Colors are optional, ignore errors
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

async function loadData() {
    loading.value = true
    try {
        const [usersRes, optionsRes] = await Promise.all([
            users.list(),
            options.getAll()
        ])

        userList.value = usersRes.data

        // Group options by section
        const grouped = {
            kontaktart: [],
            person: [],
            thema: [],
            zeitfenster: [],
            dauer: [],
            referenz: []
        }

        // Store thema options with full data for keywords
        const themaOpts = []

        for (const opt of optionsRes.data) {
            if (grouped[opt.section]) {
                grouped[opt.section].push(opt.label)

                // Store full thema options with keywords
                if (opt.section === 'thema') {
                    themaOpts.push({
                        label: opt.label,
                        keywords: opt.keywords || []
                    })
                }
            }
        }

        optionsBySection.value = grouped
        themaOptionsWithKeywords.value = themaOpts
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Daten konnten nicht geladen werden',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

function showMessage(type, text, duration = 5000) {
    message.value = { type, text }
    if (duration > 0) {
        setTimeout(() => {
            message.value = { type: '', text: '' }
        }, duration)
    }
}

function validateForm() {
    // Validate all groups have at least one selection and user is selected
    const errors = new Set()

    if (!selectedUser.value) errors.add('user')
    if (!erfassungsdatum.value) errors.add('datum')
    if (formData.value.kontaktart.length === 0) errors.add('kontakt')
    if (formData.value.person.length === 0) errors.add('kontakt')
    if (formData.value.dauer.length === 0) errors.add('kontakt')
    if (formData.value.thema.length === 0) errors.add('thema')
    if (formData.value.zeitfenster.length === 0) errors.add('zeitfenster')
    if (formData.value.referenz.length === 0 && !referenzAndere.value.trim()) errors.add('referenz')

    validationErrors.value = errors

    if (errors.size > 0) {
        showMessage('warn', 'Bitte wähle mindestens eine Option aus jeder Gruppe und deinen Namen.')
        return false
    }
    return true
}

function submitEntry() {
    // Clear any previous message
    message.value = { type: '', text: '' }

    if (!validateForm()) return

    // If editing an existing entry, show confirmation dialog
    if (currentEntryId.value) {
        showConfirmDialog.value = true
        return
    }

    // New entry - save directly
    performSave()
}

function confirmSave() {
    showConfirmDialog.value = false
    performSave()
}

function cancelSave() {
    showConfirmDialog.value = false
}

// Format a Date (or date string) as local wall-clock time, never UTC. The app
// stores created_at as naive local (Swiss) time, so toISOString() would shift
// the value across the timezone offset and land entries on the wrong day.
function toLocalYmd(value) {
    const d = value instanceof Date ? value : new Date(value)
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function toLocalDateTime(value) {
    const d = value instanceof Date ? value : new Date(value)
    const hh = String(d.getHours()).padStart(2, '0')
    const mm = String(d.getMinutes()).padStart(2, '0')
    const ss = String(d.getSeconds()).padStart(2, '0')
    return `${toLocalYmd(d)} ${hh}:${mm}:${ss}`
}

async function performSave() {
    submitting.value = true
    const isEditing = currentEntryId.value !== null
    const editingIndex = currentEntryIndex.value

    try {
        // Add "andere" to referenz if filled
        const values = { ...formData.value }
        if (referenzAndere.value.trim()) {
            values.referenz = [...values.referenz, `andere: ${referenzAndere.value.trim()}`]
        }

        const payload = {
            user_id: selectedUser.value.id,
            created_at: toLocalDateTime(erfassungsdatum.value),
            values
        }

        if (isEditing) {
            // Update existing entry
            await entries.update(currentEntryId.value, payload)
            showMessage('success', 'Eintrag wurde erfolgreich geändert')
            triggerSplash()
            // Reload entries list and show the updated entry
            await loadEntries()
            if (editingIndex >= 0) {
                await loadEntry(editingIndex)
            }
        } else {
            // Create new entry
            await entries.create(payload)
            showMessage('success', 'Eintrag wurde erfolgreich gespeichert')
            triggerSplash()
            resetForm()
            await loadEntries()
        }
    } catch (error) {
        showMessage('error', 'Eintrag konnte nicht gespeichert werden')
    } finally {
        submitting.value = false
    }
}

function resetForm() {
    selectedUser.value = null
    formData.value = {
        kontaktart: [],
        person: [],
        thema: [],
        zeitfenster: [],
        dauer: [],
        referenz: []
    }
    referenzAndere.value = ''
    erfassungsdatum.value = new Date()
    message.value = { type: '', text: '' }
    validationErrors.value = new Set()
    highlightUserSelect.value = true
    currentEntryIndex.value = -1
}

async function loadEntries(showLatest = false) {
    try {
        const res = await entries.list({ limit: 99999 })
        // Sort by date ascending for chronological navigation
        const items = res.data?.items || []
        items.sort((a, b) => new Date(a.created_at) - new Date(b.created_at) || a.id - b.id)
        entriesList.value = items

        // Show latest entry if requested
        if (showLatest && items.length > 0) {
            await loadEntry(items.length - 1)
        }
    } catch (error) {
        console.error('Failed to load entries:', error)
    }
}

async function loadEntry(index) {
    if (index < 0 || index >= entriesList.value.length) return

    const entry = entriesList.value[index]
    try {
        const res = await entries.get(entry.id)
        const data = res.data

        // Populate form with entry data
        selectedUser.value = userList.value.find(u => u.id === data.user_id) || null
        erfassungsdatum.value = new Date(data.created_at)

        // Parse values
        formData.value = {
            kontaktart: data.values?.kontaktart || [],
            person: data.values?.person || [],
            thema: data.values?.thema || [],
            zeitfenster: data.values?.zeitfenster || [],
            dauer: data.values?.dauer || [],
            referenz: (data.values?.referenz || []).filter(r => !r.startsWith('andere:'))
        }

        // Extract "andere" value from referenz
        const andere = (data.values?.referenz || []).find(r => r.startsWith('andere:'))
        referenzAndere.value = andere ? andere.replace('andere:', '').trim() : ''

        currentEntryIndex.value = index
        highlightUserSelect.value = false
        message.value = { type: '', text: '' }
    } catch (error) {
        console.error('Failed to load entry:', error)
    }
}

function loadFilteredEntry(filteredIdx) {
    const entry = filteredEntries.value[filteredIdx]
    if (!entry) return
    const realIndex = entriesList.value.findIndex(e => e.id === entry.id)
    if (realIndex >= 0) loadEntry(realIndex)
}

function goToPreviousEntry() {
    const fi = filteredIndex.value
    if (fi === -1 && filteredEntries.value.length > 0) {
        // No entry loaded — start at newest
        loadFilteredEntry(filteredEntries.value.length - 1)
    } else if (fi > 0) {
        loadFilteredEntry(fi - 1)
    }
}

function goToNextEntry() {
    const fi = filteredIndex.value
    if (fi === -1 && filteredEntries.value.length > 0) {
        // No entry loaded — start at newest
        loadFilteredEntry(filteredEntries.value.length - 1)
    } else if (fi >= 0 && fi < filteredEntries.value.length - 1) {
        loadFilteredEntry(fi + 1)
    }
}

// Navigate to entry by display number (1-based position in filtered list)
function goToEntryByNumber(event) {
    const num = parseInt(event.target.value, 10)
    if (isNaN(num) || num <= 0 || num > filteredEntries.value.length) {
        event.target.value = displayNumber.value || ''
        if (num > filteredEntries.value.length) {
            showMessage('warn', `Nur ${filteredEntries.value.length} Einträge vorhanden`)
        }
        return
    }

    const entry = filteredEntries.value[num - 1]
    const realIndex = entriesList.value.findIndex(e => e.id === entry.id)
    if (realIndex >= 0) {
        loadEntry(realIndex)
        event.target.blur()
    }
}

function toggleFilterMode() {
    filterMode.value = filterMode.value === 'total' ? 'year' : 'total'
}

// Find and load entries for a specific date
function onDateSelect(date) {
    if (!date) return

    // Compare on local calendar dates (not UTC) so the day the user picked
    // matches the day the entries were recorded.
    const selectedDateStr = toLocalYmd(date)

    // Find first entry matching this date
    const matchingIndex = entriesList.value.findIndex(entry => {
        return toLocalYmd(entry.created_at) === selectedDateStr
    })

    if (matchingIndex >= 0) {
        loadEntry(matchingIndex)
    }
}

function isChecked(section, value) {
    return formData.value[section].includes(value)
}

function toggleCheckbox(section, value) {
    const arr = formData.value[section]
    const idx = arr.indexOf(value)
    if (idx === -1) {
        arr.push(value)
    } else {
        arr.splice(idx, 1)
    }
}

function getKeywordsForThema(label) {
    const opt = themaOptionsWithKeywords.value.find(o => o.label === label)
    return opt?.keywords || []
}

function getFirstThreeKeywords(label) {
    return getKeywordsForThema(label).slice(0, 3)
}

function hasMoreKeywords(label) {
    return getKeywordsForThema(label).length > 3
}

function toggleExpandedKeywords(label, event) {
    // Don't toggle expand when clicking the checkbox or its label
    if (event.target.closest('.p-checkbox') || event.target.tagName === 'LABEL') return
    if (expandedThema.value === label) {
        expandedThema.value = null
    } else {
        expandedThema.value = label
    }
}

function handleClickOutside(event) {
    if (expandedThema.value && !event.target.closest('.thema-chip')) {
        expandedThema.value = null
    }
}
</script>

<template>
    <Toast />

    <RueckschauOverlay
        :visible="rueckschauVisible"
        :initialDays="rueckschauDays"
        @close="rueckschauVisible = false"
    />

    <!-- Confirmation Dialog for editing existing entries -->
    <div v-if="showConfirmDialog" class="confirm-overlay">
        <div class="confirm-dialog">
            <div class="confirm-icon">
                <i class="pi pi-exclamation-triangle"></i>
            </div>
            <h3>Achtung</h3>
            <p>Ein bestehender Eintrag wird geändert. Möchtest du fortfahren?</p>
            <div class="confirm-buttons">
                <Button
                    label="Abbrechen"
                    severity="secondary"
                    outlined
                    @click="cancelSave"
                />
                <Button
                    label="Änderung speichern"
                    @click="confirmSave"
                    class="confirm-btn"
                />
            </div>
        </div>
    </div>

    <div class="data-entry">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="new-entry-btn" @click="resetForm">
                <i class="pi pi-plus"></i>
                Neuer Eintrag
            </button>

            <div class="top-bar-field">
                <label>Bearbeitet von</label>
                <Select
                    v-model="selectedUser"
                    :options="userList"
                    optionLabel="username"
                    placeholder="Auswählen"
                    class="user-select"
                    :class="{ 'highlight-placeholder': highlightUserSelect && !selectedUser, 'has-selection': !!selectedUser, 'validation-error-field': validationErrors.has('user') }"
                    :loading="loading"
                    @change="highlightUserSelect = false"
                />
            </div>

            <div class="top-bar-field">
                <label>Erfassungsdatum</label>
                <DatePicker
                    v-model="erfassungsdatum"
                    dateFormat="DD, dd. MM yy"
                    class="date-input"
                    @date-select="onDateSelect"
                />
            </div>

            <div class="top-bar-entries-group">
                <div class="top-bar-field">
                    <label>
                        Einträge
                        <a class="filter-toggle" @click="toggleFilterMode">
                            {{ filterMode === 'total' ? 'Total' : currentYear }}
                        </a>
                    </label>
                    <div class="entry-pagination">
                        <button
                            class="pagination-btn"
                            @click="goToPreviousEntry"
                            :disabled="filteredEntries.length === 0 || filteredIndex === 0"
                        >
                            <i class="pi pi-chevron-left"></i>
                        </button>
                        <input
                            type="text"
                            class="pagination-id"
                            :value="displayNumber || ''"
                            :placeholder="filteredEntries.length ? '–' : '0'"
                            @keydown.enter="goToEntryByNumber($event)"
                        />
                        <button
                            class="pagination-btn"
                            @click="goToNextEntry"
                            :disabled="filteredEntries.length === 0 || (filteredIndex >= 0 && filteredIndex >= filteredEntries.length - 1)"
                        >
                            <i class="pi pi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="quick-filter-row">
                    <button class="quick-filter-btn" @click="openRueckschau(7)">
                        <i class="pi pi-history"></i>
                        7 Tage
                    </button>
                    <button class="quick-filter-btn" @click="openRueckschau(30)">
                        <i class="pi pi-history"></i>
                        30 Tage
                    </button>
                </div>
            </div>
            <div class="top-bar-right">
                <div class="top-bar-date">
                    {{ formattedToday }}
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <div class="form-container">

            <!-- Cards Grid -->
            <div class="cards-grid">
                <!-- Kontakt (left, spans rows) -->
                <div class="cards-column grid-kontakt">
                    <div class="card card-person" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg, 'validation-error': validationErrors.has('kontakt') }" :style="getCardStyle('person')">
                        <span class="card-dot" :class="{ 'admin-clickable': isAdmin }" @click="openColorModal('person', $event)"></span>
                        <h3 class="card-title">Kontakt</h3>
                        <div class="card-content">
                            <!-- Kontaktart -->
                            <div class="checkbox-row subgroup-kontaktart subgroup-last">
                                <div
                                    v-for="opt in optionsBySection.kontaktart"
                                    :key="opt"
                                    class="checkbox-item"
                                    :class="{ 'is-checked': formData.kontaktart.includes(opt) }"
                                >
                                    <Checkbox
                                        :inputId="'kontakt-' + opt"
                                        :value="opt"
                                        v-model="formData.kontaktart"
                                    />
                                    <label :for="'kontakt-' + opt">{{ opt }}</label>
                                </div>
                            </div>
                            <hr class="subgroup-separator" />

                            <!-- Person -->
                            <div class="checkbox-row subgroup-person subgroup-first subgroup-last">
                                <div
                                    v-for="opt in optionsBySection.person.filter(o => o !== 'Migrationshintergrund')"
                                    :key="opt"
                                    class="checkbox-item"
                                    :class="{ 'is-checked': formData.person.includes(opt) }"
                                >
                                    <Checkbox
                                        :inputId="'person-' + opt"
                                        :value="opt"
                                        v-model="formData.person"
                                    />
                                    <label :for="'person-' + opt">{{ opt }}</label>
                                </div>
                                <!-- Migrationshintergrund -->
                                <div
                                    class="checkbox-item"
                                    :class="{ 'is-checked': formData.thema.includes('Migrationshintergrund') }"
                                >
                                    <Checkbox
                                        inputId="migration"
                                        value="Migrationshintergrund"
                                        v-model="formData.thema"
                                    />
                                    <label for="migration">Migrationshintergrund</label>
                                </div>
                            </div>
                            <hr class="subgroup-separator" />

                            <!-- Dauer (optional) -->
                            <div class="checkbox-row no-border subgroup-dauer subgroup-first">
                                <div
                                    v-for="opt in optionsBySection.dauer"
                                    :key="opt"
                                    class="checkbox-item"
                                    :class="{ 'is-checked': formData.dauer.includes(opt) }"
                                >
                                    <Checkbox
                                        :inputId="'dauer-' + opt"
                                        :value="opt"
                                        v-model="formData.dauer"
                                    />
                                    <label :for="'dauer-' + opt">{{ opt }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thema -->
                <div class="cards-column grid-thema">
                    <div class="card card-thema" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg, 'validation-error': validationErrors.has('thema') }" :style="getCardStyle('thema')">
                        <span class="card-dot" :class="{ 'admin-clickable': isAdmin }" @click="openColorModal('thema', $event)"></span>
                        <h3 class="card-title">Thema</h3>
                        <div class="card-content">
                            <template v-for="opt in optionsBySection.thema" :key="opt">
                                <div
                                    v-if="opt !== 'Migrationshintergrund'"
                                    class="thema-row"
                                >
                                    <div
                                        class="checkbox-item thema-chip"
                                        :class="{ 'is-checked': formData.thema.includes(opt), 'is-expanded': expandedThema === opt }"
                                        @click="toggleExpandedKeywords(opt, $event)"
                                    >
                                        <Checkbox
                                            :inputId="'thema-' + opt"
                                            :value="opt"
                                            v-model="formData.thema"
                                        />
                                        <div class="thema-chip-content" :class="{ 'is-expanded': expandedThema === opt }">
                                            <label :for="'thema-' + opt">{{ opt }}</label>
                                            <div
                                                v-if="getKeywordsForThema(opt).length > 0"
                                                class="keywords-inline"
                                            >
                                                <span
                                                    v-for="kw in getKeywordsForThema(opt)"
                                                    :key="kw"
                                                    class="keyword-tag"
                                                >{{ kw }}</span>
                                            </div>
                                            <i
                                                v-if="getKeywordsForThema(opt).length > 0 && expandedThema !== opt"
                                                class="pi pi-plus keywords-indicator"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Zeitfenster (vertical, 2 per row) -->
                <div class="cards-column grid-zeitfenster">
                    <div class="card card-zeitfenster" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg, 'validation-error': validationErrors.has('zeitfenster') }" :style="getCardStyle('zeitfenster')">
                        <span class="card-dot" :class="{ 'admin-clickable': isAdmin }" @click="openColorModal('zeitfenster', $event)"></span>
                        <h3 class="card-title">Zeitfenster</h3>
                        <div class="zeitfenster-grid">
                            <div
                                v-for="opt in optionsBySection.zeitfenster"
                                :key="opt"
                                class="checkbox-item zeitfenster-item"
                                :class="{ 'is-checked': formData.zeitfenster.includes(opt) }"
                            >
                                <Checkbox
                                    :inputId="'zeit-' + opt"
                                    :value="opt"
                                    v-model="formData.zeitfenster"
                                />
                                <label :for="'zeit-' + opt">{{ opt }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Referenz -->
                <div class="cards-column grid-referenz">
                    <div class="card card-referenz" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg, 'validation-error': validationErrors.has('referenz') }" :style="getCardStyle('referenz')">
                        <span class="card-dot" :class="{ 'admin-clickable': isAdmin }" @click="openColorModal('referenz', $event)"></span>
                        <h3 class="card-title">Referenz</h3>
                        <p class="card-subtitle">Auf uns aufmerksam gemacht durch:</p>
                        <div class="card-content">
                            <div
                                v-for="opt in optionsBySection.referenz.filter(o => o.toLowerCase() !== 'andere')"
                                :key="opt"
                                class="checkbox-item referenz-item"
                                :class="{ 'is-checked': formData.referenz.includes(opt) }"
                            >
                                <Checkbox
                                    :inputId="'ref-' + opt"
                                    :value="opt"
                                    v-model="formData.referenz"
                                />
                                <label :for="'ref-' + opt">{{ opt }}</label>
                            </div>
                            <div class="andere-row">
                                <Checkbox
                                    inputId="ref-andere"
                                    :binary="true"
                                    :modelValue="referenzAndere.length > 0"
                                    disabled
                                />
                                <label for="andere-input">andere:</label>
                                <InputText
                                    id="andere-input"
                                    v-model="referenzAndere"
                                    class="andere-input"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Message -->
                    <div
                        v-if="message.text"
                        class="form-message"
                        :class="'message-' + message.type"
                    >
                        {{ message.text }}
                    </div>

                    <!-- Save Button -->
                    <Button
                        label="Eingabe speichern"
                        icon="pi pi-save"
                        :loading="submitting"
                        :disabled="!isFormValid"
                        @click="submitEntry"
                        class="save-btn-full"
                    />
                </div>
            </div>
        </div>

        <CardColorModal
            v-for="(modal, key) in openModals"
            :key="key"
            :visible="true"
            :cardKey="key"
            :colors="cardColors[key]"
            :anchorRect="modal.anchorRect"
            @save="saveCardColors"
            @update="updateCardColorsPreview"
            @close="closeColorModal(key)"
            @discard="discardCardColors"
        />

        <div v-if="showSplash" class="save-splash"></div>
        <div v-if="showSplash" class="save-splash-backdrop"></div>
        <div v-if="showSplash" class="save-splash-flowers">
            <svg
                v-for="f in splashFlowers"
                :key="f.id"
                class="splash-flower"
                viewBox="0 0 100 100"
                :style="{
                    left: f.x + '%',
                    top: f.y + '%',
                    width: f.size + 'px',
                    height: f.size + 'px',
                    animationDelay: f.delay + 's',
                    animationDuration: f.duration + 's',
                    '--rot': f.rotate + 'deg',
                    '--sway-dur': f.sway + 's',
                    '--sway-phase': -f.swayPhase + 's',
                }"
            >
                <g v-if="f.shape === 'daisy'">
                    <ellipse v-for="n in 8" :key="'b' + n" cx="50" cy="22" rx="12" ry="22" :fill="f.petalDark" :transform="`rotate(${n * 45 + 22.5} 50 50)`" />
                    <ellipse v-for="n in 8" :key="'p' + n" cx="50" cy="24" rx="11" ry="20" :fill="f.petal" :transform="`rotate(${n * 45} 50 50)`" />
                    <circle cx="50" cy="50" r="11" :fill="f.center" />
                    <circle cx="47" cy="47" r="4" fill="#ffffff" opacity="0.55" />
                </g>
                <g v-else-if="f.shape === 'aster'">
                    <ellipse v-for="n in 16" :key="'a' + n" cx="50" cy="26" rx="4.5" ry="24" :fill="n % 2 ? f.petal : f.petalDark" :transform="`rotate(${n * 22.5} 50 50)`" />
                    <circle cx="50" cy="50" r="8.5" :fill="f.center" />
                    <circle cx="47.5" cy="47.5" r="3" fill="#ffffff" opacity="0.55" />
                </g>
                <g v-else-if="f.shape === 'wildflower'">
                    <path v-for="n in 5" :key="'w' + n" d="M50 52 C 37 40, 41 13, 50 6 C 59 13, 63 40, 50 52 Z" :fill="n % 2 ? f.petal : f.petalDark" :transform="`rotate(${n * 72} 50 50)`" />
                    <circle cx="50" cy="50" r="8" :fill="f.center" />
                    <circle cx="47.5" cy="47.5" r="3" fill="#ffffff" opacity="0.55" />
                </g>
                <g v-else>
                    <circle v-for="n in 6" :key="'o' + n" cx="50" cy="31" r="16" :fill="n % 2 ? f.petal : f.petalDark" :transform="`rotate(${n * 60 + 30} 50 50)`" />
                    <circle cx="50" cy="50" r="10" :fill="f.center" />
                    <circle cx="47" cy="47" r="3.5" fill="#ffffff" opacity="0.55" />
                </g>
            </svg>
        </div>
        <div v-if="showSplash" class="save-splash-text"><span class="splash-check">&#10003;</span> Eintrag gespeichert</div>
    </div>
</template>

<style scoped>
.data-entry {
    width: 100%;
    margin: 0 auto;
    padding: 1rem 40px;
    background: #fafafa;
    min-height: 100vh;
    padding-top: 0;
}

/* Confirmation Dialog */
.confirm-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.confirm-dialog {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.confirm-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    background: var(--color-primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.confirm-icon i {
    font-size: 1.75rem;
    color: #b45309;
}

.confirm-dialog h3 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: var(--text-color);
}

.confirm-dialog p {
    margin: 0 0 1.5rem;
    color: var(--text-color-secondary);
    line-height: 1.5;
}

.confirm-buttons {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
}

.confirm-buttons .p-button {
    min-width: 100px;
}

.confirm-btn {
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
}

.confirm-btn:hover {
    background: var(--color-primary-hover) !important;
}

/* Top Bar */
.top-bar {
    display: flex;
    align-items: flex-end;
    gap: 2.6rem;
    padding: 2rem 40px 1.3rem;
    background: linear-gradient(180deg, #fff0c8, transparent);
    margin-bottom: 1rem;
    margin-left: -40px;
    margin-right: -40px;
}

.top-bar :deep(.p-select-label) {
    font-size: 1rem;
    color: #334155;
}

.top-bar :deep(.p-select-label.p-placeholder) {
    color: #334155;
}

.top-bar :deep(.p-inputtext) {
    color: #334155;
}

.top-bar-separator {
    width: 1px;
    align-self: stretch;
    background: #d5d0c5;
    margin: 0.5rem 0;
}

.top-bar-entries-group {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
}

.new-entry-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1.1rem 2.75rem;
    border: none;
    background: var(--color-primary, #FFEA95);
    color: #334155;
    font-size: 1.2em;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.2s, box-shadow 0.2s;
    margin-bottom: -4px;
    margin-left: 4px;
}

.new-entry-btn:hover {
    background: var(--color-primary-hover, #ffe066);
}

.new-entry-btn .pi {
    font-size: 0.9rem;
}

.top-bar-field {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.top-bar-field > label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-left: 12px;
    margin-bottom: 2px;
}

.top-bar-field .user-select {
    width: 180px !important;
}

.top-bar-field .date-input {
    width: 240px !important;
    flex: none;
}

.quick-filter-row {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 1px;
    margin-left: 2.5rem;
}

.quick-filter-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border: none;
    background: var(--color-kontaktart-light, #dbeafe);
    color: var(--text-color);
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: 30px;
    cursor: pointer;
    transition: background 0.15s ease;
}

.quick-filter-btn:hover {
    background: var(--color-kontaktart-hover, #bfdbfe);
}

.quick-filter-btn i {
    font-size: 0.95rem;
}

.border-toggle-btn {
    padding: 0.4rem 0.75rem;
    border-radius: 12px;
    border: 1px solid #ccc;
    background: #fff;
    color: #666;
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
}

.border-toggle-btn:hover {
    background: #eee;
}

/* Form Container */
.form-container {
    padding: 0;
}

.user-select.highlight-placeholder :deep(.p-select-label) {
    background: var(--color-primary);
    border-radius: 30px;
}

.user-select.has-selection :deep(.p-select-label) {
    background: var(--color-primary);
    border-radius: 30px;
}

/* Cards Grid */
.cards-grid {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.grid-kontakt { width: 400px; margin-top: 40px; flex-shrink: 1; }

.top-bar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
}

.top-bar-date {
    font-size: 1.8rem;
    color: var(--text-color);
    white-space: nowrap;
    font-weight: 500;
    margin-right: 3rem;
    margin-bottom: 0.1rem;
}

.top-bar-toggles {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.grid-thema { width: 500px; margin-top: 20px; flex-shrink: 1; }
.grid-zeitfenster { width: 220px; flex-shrink: 0; margin-top: 40px; }
.grid-referenz { flex-shrink: 0; margin-top: 20px; }

.cards-column {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* Card Base Styles */
.card {
    border-radius: 25px;
    padding: 1.4rem;
    position: relative;
    overflow: hidden;
}

.card::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url('@/assets/Card.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0.07;
    z-index: 0;
    pointer-events: none;
}

.card-dot {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    z-index: 2;
}

.card-dot.admin-clickable {
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}

.card-dot.admin-clickable:hover {
    transform: scale(1.4);
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
}

.card-person .card-dot {
    background: var(--color-kontaktart-checked);
}

.card-thema .card-dot {
    background: var(--color-thema-checked);
}

.card-zeitfenster .card-dot {
    background: var(--color-zeitfenster-checked);
}

.card-referenz .card-dot {
    background: var(--color-referenz-checked);
}

.card-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--surface-border);
    color: var(--text-color);
}

.card-subtitle {
    font-size: 1rem;
    color: var(--text-color-secondary);
    margin: -0.5rem 0 0.75rem;
}

.card-content {
    display: flex;
    flex-direction: column;
}

/* Card color overlay (below content, above bg image) */
.card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: var(--card-bg-color, rgba(245, 243, 239, 0.92));
    z-index: 0;
}

.card > *:not(.card-dot) {
    position: relative;
    z-index: 1;
}

.card-person {
    border: 3px solid rgb(96 165 250 / 33%);
}

.card-thema {
    border: 3px solid rgb(255 120 120 / 33%);
}

.card-zeitfenster {
    border: 3px solid rgb(91 219 166 / 33%);
}

.card-referenz {
    border: 3px solid rgb(217 210 177 / 33%);
}

.card.no-borders {
    border-color: transparent;
}

/* Checkbox Row */
.checkbox-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--surface-border);
}

.checkbox-row.no-border {
    border-bottom: none;
    padding-bottom: 0;
}

.subgroup-separator {
    border: none;
    border-top: 1px solid var(--surface-border, #ddd);
    margin: 0.25rem 0;
}

/* Subgroup spacing - larger gaps between Kontaktart, Person, Dauer */
.checkbox-row.subgroup-first {
    margin-top: 0.75rem;
    padding-top: 1.0rem;
}

.checkbox-row.subgroup-last {
    border-bottom: none;
    padding-bottom: 2.0rem;
}

/* Chip/Swatch Styles */
.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.7rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
    font-size: 1.3rem;
}

.checkbox-item label {
    cursor: pointer;
    user-select: none;
    font-size: 1rem;
    font-weight: 500;
}

.checkbox-item.is-checked label {
    color: #fff;
}

/* Hide the actual checkbox visually but keep it functional */
.checkbox-item :deep(.p-checkbox) {
    width: 16px;
    height: 16px;
}

.checkbox-item :deep(.p-checkbox-box) {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    transition: none;
}

/* Remove all hover effects from checkboxes */
.checkbox-item :deep(.p-checkbox:not(.p-disabled):hover .p-checkbox-box) {
    border-color: var(--p-checkbox-border-color);
}

.checkbox-item :deep(.p-checkbox:not(.p-disabled):has(.p-checkbox-input:hover) .p-checkbox-box) {
    border-color: var(--p-checkbox-border-color);
}

.checkbox-item :deep(.p-checkbox-checked:not(.p-disabled):hover .p-checkbox-box),
.checkbox-item :deep(.p-checkbox-checked:not(.p-disabled):has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: inherit;
    border-color: inherit;
}

/* === KONTAKT CARD CHIPS (Blue subgroups) === */

/* Kontaktart subgroup - saturated blue */
.card-person .subgroup-kontaktart .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-person .subgroup-kontaktart .checkbox-item:hover {
    background: var(--custom-swatch-hover, var(--color-kontaktart-hover));
}

.card-person .subgroup-kontaktart .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, #67abff);
}

/* Person subgroup - medium blue */
.card-person .subgroup-person .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-person .subgroup-person .checkbox-item:hover {
    background: var(--custom-swatch-hover, #bfdbfe);
}

.card-person .subgroup-person .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, #67abff);
}

/* Dauer subgroup - light blue */
.card-person .subgroup-dauer .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-person .subgroup-dauer .checkbox-item:hover {
    background: var(--custom-swatch-hover, var(--color-dauer-hover));
}

.card-person .subgroup-dauer .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, #67abff);
}

/* All kontakt subgroups share the same checkbox color */
.card-person :deep(.p-checkbox-checked .p-checkbox-box),
.card-person :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-person :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-kontakt-checkbox) !important;
    border-color: var(--color-kontakt-checkbox) !important;
}

/* === THEMA CARD CHIPS (Red/Pink) === */
.card-thema .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-thema .checkbox-item:hover {
    background: var(--custom-swatch-hover, var(--color-thema-hover));
}

.card-thema .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, rgb(255 83 83 / 95%));
}

.card-thema :deep(.p-checkbox-checked .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-thema-checkbox) !important;
    border-color: var(--color-thema-checkbox) !important;
}

/* === ZEITFENSTER CARD CHIPS (Green) === */
.card-zeitfenster .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-zeitfenster .checkbox-item:hover {
    background: var(--custom-swatch-hover, var(--color-zeitfenster-hover));
}

.card-zeitfenster .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, #34c97d);
}

.card-zeitfenster :deep(.p-checkbox-checked .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-zeitfenster-checkbox) !important;
    border-color: var(--color-zeitfenster-checkbox) !important;
}

/* === REFERENZ CARD CHIPS (Beige/Tan) === */
.card-referenz .checkbox-item {
    background: var(--custom-swatch-default, #fff);
}

.card-referenz .checkbox-item:hover {
    background: var(--custom-swatch-hover, var(--color-referenz-hover));
}

.card-referenz .checkbox-item.is-checked {
    background: var(--custom-swatch-checked, rgb(153 149 129 / 80%));
}

.card-referenz :deep(.p-checkbox-checked .p-checkbox-box),
.card-referenz :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-referenz :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-referenz-checkbox) !important;
    border-color: var(--color-referenz-checkbox) !important;
}

/* Thema Section */
.thema-row {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

/* Thema chip with integrated keywords */
.thema-chip {
    position: relative;
    flex-direction: row;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.7rem 12.8px 0.75rem;
    transition: all 0.15s ease;
    width: 100%;
    overflow: hidden;
}

.thema-chip.is-expanded {
    background: transparent;
    padding: 25px 20px;
    overflow: visible;
}

.thema-chip.is-expanded .keywords-inline {
    gap: 0.7rem;
    margin-top: 0.5rem;
    display: flex;
    flex-wrap: wrap;
}

.thema-chip.is-expanded .keyword-tag {
    font-size: 1.5rem;
}

.thema-chip :deep(.p-checkbox) {
    margin-top: 0.1rem;
    align-self: flex-start;
}

.thema-chip-content {
    display: flex;
    flex-wrap: nowrap;
    align-items: baseline;
    gap: 0.4rem;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}


.thema-chip-content.is-expanded {
    flex-direction: column;
    align-items: flex-start;
    overflow: visible;
}

.thema-chip-content label {
    font-size: 1rem;
    font-weight: 500;
    margin-right: 0.5rem;
    flex-shrink: 0;
}

.keywords-indicator {
    font-size: 0.9rem;
    color: var(--text-color-secondary);
    flex-shrink: 0;
    margin: 0 10px;
    padding-left: 0.5rem;
}

.keywords-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    flex: 1;
    min-width: 0;
}

.thema-chip-content:not(.is-expanded) .keywords-inline {
    flex-wrap: nowrap;
    overflow: hidden;
}

.thema-chip-content:not(.is-expanded) .keyword-tag {
    opacity: 0.1;
    flex-shrink: 0;
}

.keyword-tag {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 1rem;
    color: var(--text-color-secondary);
    font-weight: 400;
    background: rgb(255 255 255);
    border-radius: 3px;
}

/* Expand zone for keywords */
.expand-zone {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    cursor: pointer;
    border-radius: 0 6px 0 0;
    transition: background 0.15s ease;
}

.expand-zone:hover {
    background: rgba(0, 0, 0, 0.05);
}

.expand-icon {
    font-size: 1rem;
    color: var(--color-thema-checkbox);
    transition: transform 0.2s ease;
}

.expand-icon.expanded {
    transform: rotate(180deg);
}

/* Zeitfenster vertical layout */
.card-zeitfenster {
    display: block;
}

.zeitfenster-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.card-referenz .card-content {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.zeitfenster-item {
    /* inherits chip styles from .checkbox-item */
}

.referenz-item {
    /* inherits chip styles from .checkbox-item */
}

.andere-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.4rem 0.75rem;
    background: #fff;
    border-radius: 6px;
}

.andere-row:has(.andere-input:focus) {
    background: var(--color-referenz-hover);
}

.andere-row label {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-color);
}

.andere-input {
    flex: 1;
    font-size: 1rem;
    color: var(--p-inputtext-color);
    background: var(--p-inputtext-background);
    padding-block: var(--p-inputtext-padding-y);
    padding-inline: var(--p-inputtext-padding-x);
    border: 1px solid var(--p-inputtext-border-color);
    border-radius: 30px;
    max-width: 345px;
    outline-color: transparent;
    box-shadow: var(--p-inputtext-shadow);
    transition: background var(--p-inputtext-transition-duration), color var(--p-inputtext-transition-duration), border-color var(--p-inputtext-transition-duration), outline-color var(--p-inputtext-transition-duration), box-shadow var(--p-inputtext-transition-duration);
}

.andere-input:focus {
    outline: none;
    box-shadow: none;
}

/* Footer Buttons */
/* Entry Pagination */
.entry-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: #f0f0f0;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: #e0e0e0;
}

.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pagination-btn i {
    font-size: 0.9rem;
    color: #334155;
}

.filter-toggle {
    text-decoration: underline;
    cursor: pointer;
    color: #334155;
    font-weight: 500;
    margin-left: 0.3rem;
}

.filter-toggle:hover {
    color: var(--color-kontakt-text);
}

.pagination-total {
    font-size: 0.85rem;
    color: #999;
    white-space: nowrap;
}

.pagination-id {
    width: 60px;
    text-align: center;
    font-size: 0.95rem;
    font-weight: 500;
    color: #334155;
    padding: 0.50rem 0.5rem;
    background: var(--p-inputtext-background, #fff);
    border-radius: 30px;
    border: 1px solid transparent;
    outline: none;
    transition: none;
}

.pagination-id:hover,
.pagination-id:focus {
    border-color: var(--color-primary, #FFEA95);
    box-shadow: none;
}

.pagination-id::placeholder {
    color: #999;
}

.save-btn {
    background: #40E0D0;
    border-color: #40E0D0;
    color: #000;
}

.save-btn:hover {
    background: #3BC9BB;
    border-color: #3BC9BB;
}

/* Form Message */
.form-message {
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-size: 0.95rem;
    margin-top: 1rem;
}

.message-success {
    background: var(--color-thema-light);
    color: #000;
}

.message-warn {
    background: var(--color-thema-light);
    color: #000;
}

.message-error {
    background: var(--color-thema-light);
    color: #000;
}

.save-btn-full {
    border-radius: 30px;
    width: 100%;
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
    padding: 0.75rem 1rem;
    font-size: 22px;
    margin: 20px 0px;
    height: 67px;
}

.save-btn-full:hover:not(:disabled) {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}

.save-btn-full:disabled {
    background: #20202038 !important;
    border-color: transparent !important;
    color: #999 !important;
    cursor: not-allowed;
    opacity: 1;
}

.save-splash {
    position: fixed;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: var(--color-primary, #ffea95);
    transform: translate(-50%, -50%);
    animation: splash 0.5s ease-out forwards;
    pointer-events: none;
    z-index: 9999;
}

@keyframes splash {
    0% {
        width: 0;
        height: 0;
        opacity: 0.8;
    }
    100% {
        width: 300vmax;
        height: 300vmax;
        opacity: 0;
    }
}

.save-splash-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #f5f3ef;
    z-index: 9999;
    pointer-events: none;
    animation: splash-backdrop 3s ease-out forwards;
}

@keyframes splash-backdrop {
    0% { opacity: 0; }
    10% { opacity: 1; }
    70% { opacity: 1; }
    100% { opacity: 0; }
}

.save-splash-flowers {
    position: fixed;
    inset: 0;
    overflow: hidden;
    z-index: 9999;
    pointer-events: none;
    animation: splash-flowers-layer 3s ease-out forwards;
}

@keyframes splash-flowers-layer {
    0% { opacity: 0; transform: scale(0.92); }
    10% { opacity: 0.35; }
    65% { opacity: 0.35; }
    92% { opacity: 0; }
    100% { opacity: 0; transform: scale(1.18); }
}

.splash-flower {
    position: absolute;
    transform: translate(-50%, -50%) rotate(calc(var(--rot) - 50deg)) scale(0);
    animation-name: flower-bloom;
    animation-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1);
    animation-fill-mode: forwards;
}

@keyframes flower-bloom {
    from {
        transform: translate(-50%, -50%) rotate(calc(var(--rot) - 50deg)) scale(0);
    }
    to {
        transform: translate(-50%, -50%) rotate(var(--rot)) scale(1);
    }
}

.splash-flower g {
    transform-origin: 50px 50px;
    animation: flower-sway var(--sway-dur, 2.4s) ease-in-out infinite alternate;
    animation-delay: var(--sway-phase, 0s);
}

@keyframes flower-sway {
    from { transform: rotate(-6deg); }
    to { transform: rotate(6deg); }
}

.splash-check {
    font-size: 8.4rem;
    margin-right: 0.5rem;
    line-height: 1;
    vertical-align: middle;
}

.save-splash-text {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-family: 'Din Next Rounded', sans-serif;
    font-size: 4.2rem;
    font-weight: 600;
    color: var(--color-primary, #ffea95);
    z-index: 10000;
    pointer-events: none;
    animation: splash-text 3s ease-out forwards;
}

@keyframes splash-text {
    0% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(0.8);
    }
    15% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    70% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1);
    }
}


/* Validation error highlight — slow pulsing background */
.card-person.validation-error {
    animation: pulse-kontakt 2s ease-in-out infinite;
}

.card-zeitfenster.validation-error {
    animation: pulse-zeitfenster 2s ease-in-out infinite;
}

.card-thema.validation-error {
    animation: pulse-thema 2s ease-in-out infinite;
}

.card-referenz.validation-error {
    animation: pulse-referenz 2s ease-in-out infinite;
}

.validation-error-field :deep(.p-select) {
    animation: pulse-kontakt 2s ease-in-out infinite;
    border-radius: 30px;
}

@keyframes pulse-kontakt {
    0%, 100% { background-color: #f5f3ef; }
    50% { background-color: #b5d6ff; }
}

@keyframes pulse-zeitfenster {
    0%, 100% { background-color: #f5f3ef; }
    50% { background-color: #9ae2c0; }
}

@keyframes pulse-thema {
    0%, 100% { background-color: #f5f3ef; }
    50% { background-color: #ffc6c6; }
}

@keyframes pulse-referenz {
    0%, 100% { background-color: #f5f3ef; }
    50% { background-color: #dfd9bd; }
}

/* Top bar input styling: no border, yellow on hover/active */
.top-bar :deep(.p-select),
.top-bar :deep(.p-inputtext) {
    border-color: transparent !important;
    transition: none;
}

.top-bar :deep(.p-select-label) {
    padding: 10px 14px;
}

.top-bar :deep(.p-inputtext) {
    padding: 10px 14px;
}

.top-bar :deep(.p-select:hover),
.top-bar :deep(.p-select:focus),
.top-bar :deep(.p-select.p-focus),
.top-bar :deep(.p-inputtext:hover),
.top-bar :deep(.p-inputtext:focus),
.top-bar :deep(.p-datepicker:hover .p-inputtext),
.top-bar :deep(.p-datepicker:focus-within .p-inputtext) {
    border-color: var(--color-primary, #FFEA95) !important;
}


/* Responsive */
@media (max-width: 1200px) {
    .cards-grid {
        flex-wrap: wrap;
    }

    .grid-kontakt,
    .grid-thema,
    .grid-zeitfenster,
    .grid-referenz {
        margin-top: 0;
    }
}

@media (max-width: 768px) {
    .cards-grid {
        flex-direction: column;
        align-items: stretch;
    }

    .grid-kontakt,
    .grid-thema,
    .grid-zeitfenster,
    .grid-referenz {
        width: 100%;
        margin-top: 0;
    }

    .cards-column {
        gap: 0.75rem;
    }
}
</style>

<style>
.data-entry .p-select {
    border-radius: 30px;
    border-color: var(--color-kontakt-checkbox);
}

.data-entry .p-inputtext {
    border-radius: 30px;
    width: 230px;
    border-color: var(--color-kontakt-checkbox);
}

/* DatePicker active date backgrounds — all selected states blue */
.p-datepicker-panel .p-datepicker-day-selected,
.p-datepicker-panel .p-datepicker-day-selected:hover,
.p-datepicker-panel .p-datepicker-day-selected-range,
.p-datepicker-panel .p-datepicker-day-selected-range:hover,
.p-datepicker-panel .p-datepicker-month-selected,
.p-datepicker-panel .p-datepicker-month-selected:hover,
.p-datepicker-panel .p-datepicker-year-selected,
.p-datepicker-panel .p-datepicker-year-selected:hover {
    background: var(--color-kontakt-checkbox) !important;
    color: #fff !important;
}

.p-datepicker-panel .p-datepicker-today > .p-datepicker-day:not(.p-datepicker-day-selected) {
    border-color: var(--color-kontakt-checkbox) !important;
}
</style>
