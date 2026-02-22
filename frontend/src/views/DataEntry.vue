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

// Toggle card borders and backgrounds
const showBorders = ref(true)
const showCardBg = ref(true)

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
    // No highlight on initial load - only on new entry tap
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
                    :class="{ 'highlight-placeholder': highlightUserSelect && !selectedUser }"
                    :loading="loading"
                    @change="highlightUserSelect = false"
                />
            </div>

            <div class="top-bar-field">
                <label>Erfassungsdatum</label>
                <DatePicker
                    v-model="erfassungsdatum"
                    dateFormat="DD, dd. MM yy"
                    showIcon
                    class="date-input"
                    @date-select="onDateSelect"
                />
            </div>

            <div class="top-bar-separator"></div>

            <div class="top-bar-entries-group">
                <div class="top-bar-field">
                    <label>Einträge</label>
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

                <div class="quick-filter-row">
                    <button class="quick-filter-btn">
                        <i class="pi pi-history"></i>
                        7 Tage
                    </button>
                    <button class="quick-filter-btn">
                        <i class="pi pi-history"></i>
                        30 Tage
                    </button>
                </div>
            </div>
            <button class="border-toggle-btn" @click="showBorders = !showBorders">
                {{ showBorders ? 'Borders ON' : 'Borders OFF' }}
            </button>
            <button class="border-toggle-btn" @click="showCardBg = !showCardBg">
                {{ showCardBg ? 'BG ON' : 'BG OFF' }}
            </button>
        </div>

        <!-- Main Form -->
        <div class="form-container">

            <!-- Cards Grid -->
            <div class="cards-grid">
                <!-- Kontakt (left, spans rows) -->
                <div class="cards-column grid-kontakt">
                    <div class="card card-person" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg }">
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

                <!-- Zeitfenster (spans center + right, single row) -->
                <div class="card card-zeitfenster grid-zeitfenster" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg }">
                    <h3 class="card-title">Zeitfenster</h3>
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

                <!-- Thema (center) -->
                <div class="cards-column grid-thema">
                    <div class="card card-thema" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg }">
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

                <!-- Right Column: Referenz + Save -->
                <div class="cards-column grid-referenz">
                    <div class="card card-referenz" :class="{ 'no-borders': !showBorders, 'has-card-bg': showCardBg }">
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
                        @click="submitEntry"
                        class="save-btn-full"
                    />
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.data-entry {
    width: 100%;
    margin: 0 auto;
    padding: 1rem 40px;
    background: #fafafa;
    min-height: 100vh;
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
    gap: 3.25rem;
    padding: 0.4rem 1.2rem 0.5rem;
    background: #f1eee9;
    margin-bottom: 1rem;
    border-radius: 25px;
    border-bottom: 7px solid #e7e3d0;
    border-top: 4px solid #e7e3d052;
}

.top-bar :deep(.p-select-label) {
    font-size: 1rem;
}

.top-bar :deep(.p-select-label.p-placeholder) {
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
    padding: 1.1rem 1.75rem;
    border: none;
    background: var(--color-primary, #FFEA95);
    color: #404040;
    font-size: 1.2em;
    font-weight: 600;
    border-radius: 25px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.2s;
    margin-bottom: -4px;
    margin-left: -11px;
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
    border-radius: 8px;
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
    margin-left: auto;
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

.user-select.highlight-placeholder :deep(.p-select-label.p-placeholder) {
    background: var(--color-primary);
    border-radius: 4px;
}

/* Cards Grid */
.cards-grid {
    display: grid;
    grid-template-columns: 1fr minmax(0, 2.3fr) 1fr;
    grid-template-rows: auto 1fr;
    grid-template-areas:
        "kontakt zeitfenster zeitfenster"
        "kontakt thema referenz";
    gap: 1rem;
    align-items: start;
}

.grid-kontakt { grid-area: kontakt; }
.grid-zeitfenster { grid-area: zeitfenster; }
.grid-thema { grid-area: thema; }
.grid-referenz { grid-area: referenz; }

.cards-column {
    display: flex;
    flex-direction: column;
    gap: 0;
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
    background: #f5f3ef;
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

.card-person.has-card-bg {
    background: rgb(96 165 250 / 33%);
}

.card-thema.has-card-bg {
    background: rgb(255 120 120 / 33%);
}

.card-zeitfenster.has-card-bg {
    background: rgb(91 219 166 / 33%);
}

.card-referenz.has-card-bg {
    background: rgb(217 210 177 / 33%);
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
    background: #fff;
}

.card-person .subgroup-kontaktart .checkbox-item:hover {
    background: var(--color-kontaktart-hover);
}

.card-person .subgroup-kontaktart .checkbox-item.is-checked {
    background: var(--color-kontaktart-checked);
}

/* Person subgroup - medium blue */
.card-person .subgroup-person .checkbox-item {
    background: #fff;
}

.card-person .subgroup-person .checkbox-item:hover {
    background: #bfdbfe;
}

.card-person .subgroup-person .checkbox-item.is-checked {
    background: var(--color-person-checked);
}

/* Dauer subgroup - light blue */
.card-person .subgroup-dauer .checkbox-item {
    background: #fff;
}

.card-person .subgroup-dauer .checkbox-item:hover {
    background: var(--color-dauer-hover);
}

.card-person .subgroup-dauer .checkbox-item.is-checked {
    background: var(--color-dauer-checked);
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
    background: #fff;
}

.card-thema .checkbox-item:hover {
    background: var(--color-thema-hover);
}

.card-thema .checkbox-item.is-checked {
    background: var(--color-thema-checked);
}

.card-thema :deep(.p-checkbox-checked .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-thema :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-thema-checkbox) !important;
    border-color: var(--color-thema-checkbox) !important;
}

/* === ZEITFENSTER CARD CHIPS (Green) === */
.card-zeitfenster .checkbox-item {
    background: #fff;
}

.card-zeitfenster .checkbox-item:hover {
    background: var(--color-zeitfenster-hover);
}

.card-zeitfenster .checkbox-item.is-checked {
    background: var(--color-zeitfenster-checked);
}

.card-zeitfenster :deep(.p-checkbox-checked .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:hover .p-checkbox-box),
.card-zeitfenster :deep(.p-checkbox-checked:has(.p-checkbox-input:hover) .p-checkbox-box) {
    background: var(--color-zeitfenster-checkbox) !important;
    border-color: var(--color-zeitfenster-checkbox) !important;
}

/* === REFERENZ CARD CHIPS (Beige/Tan) === */
.card-referenz .checkbox-item {
    background: #fff;
}

.card-referenz .checkbox-item:hover {
    background: var(--color-referenz-hover);
}

.card-referenz .checkbox-item.is-checked {
    background: var(--color-referenz-checked);
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
    background: var(--color-thema-hover);
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
    flex-wrap: wrap;
    overflow: visible;
}

.thema-chip-content label {
    font-size: 1.3rem;
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

/* Zeitfenster & Referenz chip layouts */
.card-zeitfenster {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.75rem;
}

.card-zeitfenster .card-title {
    margin: 0;
    padding: 0;
    border: none;
    white-space: nowrap;
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
    border-radius: 25px;
    width: 100%;
    max-width: 340px;
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
    padding: 0.75rem 1rem;
    font-size: 22px;
    margin: 20px 20px;
    height: 67px;
}

.save-btn-full:hover {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}


/* Responsive */
@media (max-width: 1200px) {
    .cards-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }

    .cards-column {
        gap: 0.75rem;
    }
}
</style>
