<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import { options, entries, users } from '../services/api'

const toast = useToast()

// Form state
const selectedUser = ref(null)
const erfassungsdatum = ref(new Date())
const referenzAndere = ref('')

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

const loading = ref(false)
const submitting = ref(false)

// Message state for inline feedback
const message = ref({ type: '', text: '' })

// Highlight user select placeholder
const highlightUserSelect = ref(false)

// Confirmation dialog for editing existing entries
const showConfirmDialog = ref(false)

// Pagination state
const entriesList = ref([])
const currentEntryIndex = ref(-1)
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
    highlightUserSelect.value = true  // Highlight user dropdown for new entry
    document.addEventListener('click', handleClickOutside)
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
    // Note: dauer (länger als 20 minuten) is optional
    const hasAllSelections =
        selectedUser.value &&
        formData.value.kontaktart.length > 0 &&
        formData.value.person.length > 0 &&
        formData.value.thema.length > 0 &&
        formData.value.zeitfenster.length > 0 &&
        (formData.value.referenz.length > 0 || referenzAndere.value.trim())

    if (!hasAllSelections) {
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
            created_at: erfassungsdatum.value.toISOString(),
            values
        }

        if (isEditing) {
            // Update existing entry
            await entries.update(currentEntryId.value, payload)
            showMessage('success', 'Eintrag wurde erfolgreich geändert')
            // Reload entries list and show the updated entry
            await loadEntries()
            if (editingIndex >= 0) {
                await loadEntry(editingIndex)
            }
        } else {
            // Create new entry
            await entries.create(payload)
            showMessage('success', 'Eintrag wurde erfolgreich gespeichert')
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
    highlightUserSelect.value = true
    currentEntryIndex.value = -1
}

async function loadEntries(showLatest = false) {
    try {
        const res = await entries.list({ limit: 2000 })
        // Sort by ID ascending for intuitive navigation
        const items = res.data?.items || []
        items.sort((a, b) => a.id - b.id)
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

function goToPreviousEntry() {
    if (currentEntryIndex.value > 0) {
        loadEntry(currentEntryIndex.value - 1)
    } else if (currentEntryIndex.value === -1 && entriesList.value.length > 0) {
        loadEntry(entriesList.value.length - 1)
    }
}

function goToNextEntry() {
    if (currentEntryIndex.value < entriesList.value.length - 1) {
        loadEntry(currentEntryIndex.value + 1)
    }
}

// Navigate to entry by ID (from input field)
function goToEntryById(event) {
    const inputId = parseInt(event.target.value, 10)
    if (isNaN(inputId) || inputId <= 0) {
        event.target.value = currentEntryId.value || ''
        return
    }

    // Find entry with this ID
    const matchingIndex = entriesList.value.findIndex(entry => entry.id === inputId)

    if (matchingIndex >= 0) {
        loadEntry(matchingIndex)
        event.target.blur()
    } else {
        // Entry not found - reset input to current value
        event.target.value = currentEntryId.value || ''
        showMessage('warn', `Eintrag #${inputId} nicht gefunden`)
    }
}

// Find and load entries for a specific date
function onDateSelect(date) {
    if (!date) return

    // Format selected date as YYYY-MM-DD for comparison
    const selectedDateStr = date.toISOString().split('T')[0]

    // Find first entry matching this date
    const matchingIndex = entriesList.value.findIndex(entry => {
        const entryDateStr = new Date(entry.created_at).toISOString().split('T')[0]
        return entryDateStr === selectedDateStr
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
    event.stopPropagation()
    if (expandedThema.value === label) {
        expandedThema.value = null
    } else {
        expandedThema.value = label
    }
}

function handleClickOutside(event) {
    // Check if click is outside any expanded keywords area
    const expandedEl = document.querySelector('.keywords-expanded')
    const expandZone = event.target.closest('.expand-zone')
    if (expandedEl && !expandedEl.contains(event.target) && !expandZone) {
        expandedThema.value = null
    }
}
</script>

<template>
    <Toast />

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
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>STATISTIK</h1>
                <p>{{ formattedDate }}</p>
                <!-- Pagination -->
                <div class="entry-pagination">
                    <button
                        class="pagination-btn"
                        @click="goToPreviousEntry"
                        :disabled="entriesList.length === 0 || currentEntryIndex === 0"
                    >
                        <i class="pi pi-chevron-left"></i>
                    </button>
                    <input
                        type="text"
                        class="pagination-id"
                        :value="currentEntryId || ''"
                        placeholder="–"
                        @keydown.enter="goToEntryById($event)"
                    />
                    <button
                        class="pagination-btn"
                        @click="goToNextEntry"
                        :disabled="currentEntryIndex >= entriesList.length - 1"
                    >
                        <i class="pi pi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <div class="form-container">
            <Button
                label="neue Eingabe"
                icon="pi pi-plus"
                @click="resetForm"
                class="new-entry-btn"
            />

            <!-- Top Fields -->
            <div class="top-fields">
                <div class="field-row">
                    <label>Erfassungsdatum:</label>
                    <DatePicker
                        v-model="erfassungsdatum"
                        dateFormat="DD, dd. MM yy"
                        showIcon
                        class="date-input"
                        @date-select="onDateSelect"
                    />
                </div>
                <div class="field-row">
                    <label>Bearbeitet von:</label>
                    <Select
                        v-model="selectedUser"
                        :options="userList"
                        optionLabel="username"
                        placeholder="Auswählen"
                        class="user-select"
                        :class="{ 'highlight-placeholder': highlightUserSelect && !selectedUser }"
                        :loading="loading"
                        @change="highlightUserSelect = false"
                    />
                </div>
            </div>

            <!-- Cards Grid - Two Columns -->
            <div class="cards-grid">
                <!-- Left Column: Person + Thema -->
                <div class="cards-column">
                    <!-- Card 1: Person -->
                    <div class="card card-person">
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

                            <!-- Geschlecht + Alter -->
                            <div class="checkbox-row subgroup-person subgroup-first">
                                <template v-for="opt in optionsBySection.person" :key="opt">
                                    <div
                                        v-if="['Frau', 'Mann', 'unter 55', 'über 55', 'über 80'].includes(opt)"
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
                                </template>
                            </div>

                            <!-- Betroffenheit + Migrationshintergrund -->
                            <div class="checkbox-row subgroup-person subgroup-last">
                                <template v-for="opt in optionsBySection.person" :key="opt">
                                    <div
                                        v-if="['selbst betroffen', 'Angehörige Nachbarn und andere', 'Institution'].includes(opt)"
                                        class="checkbox-item"
                                        :class="{ 'is-checked': formData.person.includes(opt) }"
                                    >
                                        <Checkbox
                                            :inputId="'betroffen-' + opt"
                                            :value="opt"
                                            v-model="formData.person"
                                        />
                                        <label :for="'betroffen-' + opt">{{ opt }}</label>
                                    </div>
                                </template>
                                <!-- Migrationshintergrund on same line -->
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

                    <!-- Card 2: Thema -->
                    <div class="card card-thema">
                        <h3 class="card-title">Thema</h3>
                        <div class="card-content">
                            <template v-for="opt in optionsBySection.thema" :key="opt">
                                <div
                                    v-if="opt !== 'Migrationshintergrund'"
                                    class="thema-row"
                                >
                                    <div
                                        class="checkbox-item thema-chip"
                                        :class="{ 'thema-chip-expanded': expandedThema === opt, 'is-checked': formData.thema.includes(opt) }"
                                    >
                                        <Checkbox
                                            :inputId="'thema-' + opt"
                                            :value="opt"
                                            v-model="formData.thema"
                                        />
                                        <div class="thema-chip-content">
                                            <label :for="'thema-' + opt">{{ opt }}</label>
                                            <div
                                                v-if="getKeywordsForThema(opt).length > 0"
                                                class="keywords-inline"
                                            >
                                                <span
                                                    v-for="kw in (expandedThema === opt
                                                        ? getKeywordsForThema(opt)
                                                        : getFirstThreeKeywords(opt))"
                                                    :key="kw"
                                                    class="keyword-tag"
                                                >{{ kw }}</span>
                                            </div>
                                        </div>
                                        <div
                                            v-if="hasMoreKeywords(opt)"
                                            class="expand-zone"
                                            @click="toggleExpandedKeywords(opt, $event)"
                                        >
                                            <i
                                                class="pi pi-chevron-down expand-icon"
                                                :class="{ 'expanded': expandedThema === opt }"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Zeitfenster + Referenz -->
                <div class="cards-column">
                    <!-- Card 3: Zeitfenster -->
                    <div class="card card-zeitfenster">
                        <h3 class="card-title">Zeitfenster</h3>
                        <div class="card-content">
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

                    <!-- Card 4: Referenz -->
                    <div class="card card-referenz">
                        <h3 class="card-title">Referenz</h3>
                        <p class="card-subtitle">Auf uns aufmerksam gemacht durch:</p>
                        <div class="card-content">
                            <div
                                v-for="opt in optionsBySection.referenz"
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
                        @click="submitEntry"
                        class="save-btn-full"
                    />
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Patrick+Hand&display=swap');

.data-entry {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1rem 50px;
    background: linear-gradient(180deg, #ffffff, transparent);
    min-height: 100vh;
    border-radius: 30px;
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

/* Header */
.header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--surface-border);
}

.new-entry-btn {
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
    margin-bottom: 0.75rem;
}

.new-entry-btn:hover {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}

.header-title {
    text-align: right;
    margin-right: 20px;
}

.header-title h1 {
    font-family: 'Patrick Hand', cursive;
    font-size: 2.5rem;
    font-weight: 400;
    margin: 0;
    color: var(--text-color);
}

.header-title p {
    margin: 0.25rem 0 0;
    font-size: 1.1rem;
    color: var(--text-color-secondary);
}

/* Form Container */
.form-container {
    padding: 1rem 0;
    margin-top: -100px;
}

/* Top Fields */
.top-fields {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 2.4rem;
    max-width: 400px;
}

.field-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.field-row label {
    min-width: 120px;
    font-weight: 500;
}

.date-input {
    flex: 1;
}

.user-select {
    flex: 1;
    min-width: 200px;
}

.user-select.highlight-placeholder :deep(.p-select-label.p-placeholder) {
    background: var(--color-primary);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

/* Cards Grid - Two columns layout */
.cards-grid {
    display: grid;
    grid-template-columns: 3.9fr 2fr;
    gap: 1rem;
    align-items: start;
}

.cards-column {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Card Base Styles */
.card {
    border-radius: 25px;
    padding: 1.4rem;
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

/* Card backgrounds */
.card-person,
.card-thema,
.card-zeitfenster,
.card-referenz {
    background: linear-gradient(180deg, #f5f3ef, transparent);
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

/* Subgroup spacing - larger gaps between Kontaktart, Person, Dauer */
.checkbox-row.subgroup-first {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
}

.checkbox-row.subgroup-last {
    border-bottom: none;
    padding-bottom: 0.5rem;
}

/* Chip/Swatch Styles */
.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
}

.checkbox-item label {
    cursor: pointer;
    user-select: none;
    font-size: 1rem;
    font-weight: 500;
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
    background: var(--color-kontaktart-light);
}

.card-person .subgroup-kontaktart .checkbox-item:hover {
    background: var(--color-kontaktart-hover);
}

.card-person .subgroup-kontaktart .checkbox-item.is-checked {
    background: var(--color-kontaktart-checked);
}

.card-person .subgroup-kontaktart .checkbox-item.is-checked label {
    color: var(--color-kontakt-text);
}

/* Person subgroup - medium blue */
.card-person .subgroup-person .checkbox-item {
    background: var(--color-person-light);
}

.card-person .subgroup-person .checkbox-item:hover {
    background: var(--color-person-hover);
}

.card-person .subgroup-person .checkbox-item.is-checked {
    background: var(--color-person-checked);
}

.card-person .subgroup-person .checkbox-item.is-checked label {
    color: var(--color-kontakt-text);
}

/* Dauer subgroup - light blue */
.card-person .subgroup-dauer .checkbox-item {
    background: var(--color-dauer-light);
}

.card-person .subgroup-dauer .checkbox-item:hover {
    background: var(--color-dauer-hover);
}

.card-person .subgroup-dauer .checkbox-item.is-checked {
    background: var(--color-dauer-checked);
}

.card-person .subgroup-dauer .checkbox-item.is-checked label {
    color: var(--color-kontakt-text);
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
    background: var(--color-thema-light);
}

.card-thema .checkbox-item:hover {
    background: var(--color-thema-hover);
}

.card-thema .checkbox-item.is-checked {
    background: var(--color-thema-checked);
}

.card-thema .checkbox-item.is-checked label {
    color: var(--color-thema-text);
}

.card-thema :deep(.p-checkbox-checked .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-thema-checkbox) !important;
    border-color: var(--color-thema-checkbox) !important;
}

/* === ZEITFENSTER CARD CHIPS (Green) === */
.card-zeitfenster .checkbox-item {
    background: var(--color-zeitfenster-light);
}

.card-zeitfenster .checkbox-item:hover {
    background: var(--color-zeitfenster-hover);
}

.card-zeitfenster .checkbox-item.is-checked {
    background: var(--color-zeitfenster-checked);
}

.card-zeitfenster .checkbox-item.is-checked label {
    color: var(--color-zeitfenster-text);
}

.card-zeitfenster :deep(.p-checkbox-checked .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-zeitfenster-checkbox) !important;
    border-color: var(--color-zeitfenster-checkbox) !important;
}

/* === REFERENZ CARD CHIPS (Beige/Tan) === */
.card-referenz .checkbox-item {
    background: var(--color-referenz-light);
}

.card-referenz .checkbox-item:hover {
    background: var(--color-referenz-hover);
}

.card-referenz .checkbox-item.is-checked {
    background: var(--color-referenz-checked);
}

.card-referenz .checkbox-item.is-checked label {
    color: var(--color-referenz-text);
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
    padding: 0.5rem 0.75rem;
    transition: all 0.15s ease;
}

.thema-chip-expanded {
    background: var(--color-thema-hover);
    padding: 25px 20px;
}

.thema-chip-expanded .keywords-inline {
    gap: 0.7rem;
    margin-top: 0.5rem;
}

.thema-chip :deep(.p-checkbox) {
    margin-top: 0.1rem;
    align-self: flex-start;
}

.thema-chip-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.thema-chip-content label {
    font-size: 1rem;
    font-weight: 500;
}

.keywords-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.1rem;
}

.keyword-tag {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    font-size: 0.68rem;
    color: var(--text-color-secondary);
    font-weight: 400;
    background: rgb(255 255 255);
    border-radius: 3px;
}

.thema-chip-expanded .keyword-tag {
    font-size: 1rem;
    padding: 0.15rem 0.5rem;
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

/* Zeitfenster & Referenz vertical chip layouts */
.card-zeitfenster .card-content,
.card-referenz .card-content {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.zeitfenster-item,
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
    background: var(--color-referenz-light);
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
    height: 1.75rem;
    border: none;
    background: transparent;
    font-size: 0.875rem;
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
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.25rem;
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
    color: #333;
}

.pagination-id {
    width: 60px;
    text-align: center;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-color);
    padding: 0.25rem 0.5rem;
    background: #fff;
    border-radius: 4px;
    border: 1px solid #ddd;
    outline: none;
}

.pagination-id:focus {
    border-color: var(--color-kontakt-checkbox);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
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
    width: 100%;
    max-width: 400px;
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.save-btn-full:hover {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}


/* Responsive */
@media (max-width: 900px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }

    .cards-column {
        gap: 0.75rem;
    }
}
</style>
