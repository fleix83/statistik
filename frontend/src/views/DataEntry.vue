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

async function submitEntry() {
    if (!selectedUser.value) {
        toast.add({
            severity: 'warn',
            summary: 'Hinweis',
            detail: 'Bitte wählen Sie einen Bearbeiter aus',
            life: 3000
        })
        return
    }

    submitting.value = true
    try {
        // Add "andere" to referenz if filled
        const values = { ...formData.value }
        if (referenzAndere.value.trim()) {
            values.referenz = [...values.referenz, `andere: ${referenzAndere.value.trim()}`]
        }

        await entries.create({
            user_id: selectedUser.value.id,
            created_at: erfassungsdatum.value.toISOString(),
            values
        })

        toast.add({
            severity: 'success',
            summary: 'Gespeichert',
            detail: 'Eintrag wurde erfolgreich gespeichert',
            life: 3000
        })

        resetForm()
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Eintrag konnte nicht gespeichert werden',
            life: 3000
        })
    } finally {
        submitting.value = false
    }
}

function resetForm() {
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
    const mehrChip = event.target.closest('.mehr-chip')
    if (expandedEl && !expandedEl.contains(event.target) && !mehrChip) {
        expandedThema.value = null
    }
}
</script>

<template>
    <Toast />
    <div class="data-entry">
        <!-- Header -->
        <div class="header">
            <Button
                label="neue Eingabe"
                icon="pi pi-plus"
                @click="resetForm"
                class="new-entry-btn"
            />
            <div class="header-title">
                <h1>Anlaufstelle Statistik {{ currentYear }}</h1>
                <p>{{ formattedDate }}</p>
            </div>
        </div>

        <!-- Main Form -->
        <div class="form-container">
            <!-- Top Fields -->
            <div class="top-fields">
                <div class="field-row">
                    <label>Erfassungsdatum:</label>
                    <DatePicker
                        v-model="erfassungsdatum"
                        dateFormat="DD, dd. MM yy"
                        showIcon
                        class="date-input"
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
                        :loading="loading"
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
                            <div class="checkbox-row">
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

                            <!-- Geschlecht -->
                            <div class="checkbox-row">
                                <template v-for="opt in optionsBySection.person" :key="opt">
                                    <div
                                        v-if="['Frau', 'Mann'].includes(opt)"
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

                            <!-- Alter -->
                            <div class="checkbox-row">
                                <template v-for="opt in optionsBySection.person" :key="opt">
                                    <div
                                        v-if="['unter 55', 'über 55', 'über 80'].includes(opt)"
                                        class="checkbox-item"
                                        :class="{ 'is-checked': formData.person.includes(opt) }"
                                    >
                                        <Checkbox
                                            :inputId="'alter-' + opt"
                                            :value="opt"
                                            v-model="formData.person"
                                        />
                                        <label :for="'alter-' + opt">{{ opt }}</label>
                                    </div>
                                </template>
                            </div>

                            <!-- Betroffenheit -->
                            <div class="checkbox-row">
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
                            </div>

                            <!-- Dauer -->
                            <div class="checkbox-row">
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

                            <!-- Migrationshintergrund -->
                            <div class="checkbox-row no-border">
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
                                        <span
                                            v-if="hasMoreKeywords(opt)"
                                            class="mehr-chip"
                                            @click="toggleExpandedKeywords(opt, $event)"
                                        >
                                            {{ expandedThema === opt ? 'Weniger' : 'Mehr' }}
                                        </span>
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
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <div class="footer-buttons">
            <Button
                label="Eingabe speichern"
                icon="pi pi-save"
                :loading="submitting"
                @click="submitEntry"
                class="save-btn"
            />
            <Button
                label="Besuch letzte 7 Tage"
                severity="secondary"
                outlined
            />
            <Button
                label="Besuch letzte 30 Tage"
                severity="secondary"
                outlined
            />
        </div>
    </div>
</template>

<style scoped>
.data-entry {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
    background: #f8f7f5;
    min-height: 100vh;
}

/* Header */
.header {
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--surface-border);
}

.new-entry-btn {
    background: #40E0D0;
    border-color: #40E0D0;
    color: #000;
}

.new-entry-btn:hover {
    background: #3BC9BB;
    border-color: #3BC9BB;
}

.header-title {
    text-align: center;
    flex: 1;
}

.header-title h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-color);
}

.header-title p {
    margin: 0.25rem 0 0;
    color: var(--text-color-secondary);
}

/* Form Container */
.form-container {
    padding: 1rem 0;
}

/* Top Fields */
.top-fields {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
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

/* Cards Grid - Two columns layout */
.cards-grid {
    display: grid;
    grid-template-columns: 2fr 2fr;
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
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.card-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--surface-border);
    color: var(--text-color);
}

.card-subtitle {
    font-size: 0.8rem;
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
    background-color: #ffffffad;
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
    transition: all 0.15s ease;
}

/* === KONTAKT CARD CHIPS (Blue) === */
.card-person .checkbox-item {
    background: rgb(111 172 255 / 22%);
}

.card-person .checkbox-item:hover {
    background: rgb(111 172 255 / 35%);
}

.card-person .checkbox-item.is-checked {
    background: rgb(111 172 255 / 50%);
}

.card-person .checkbox-item.is-checked label {
    color: #2563eb;
}

.card-person :deep(.p-checkbox-checked .p-checkbox-box) {
    background: #3b82f6;
    border-color: #3b82f6;
}

/* === THEMA CARD CHIPS (Red/Pink) === */
.card-thema .checkbox-item {
    background: rgb(255 161 161 / 83%);
}

.card-thema .checkbox-item:hover {
    background: rgb(255 140 140 / 90%);
}

.card-thema .checkbox-item.is-checked {
    background: rgb(255 120 120 / 95%);
}

.card-thema .checkbox-item.is-checked label {
    color: #991b1b;
}

.card-thema :deep(.p-checkbox-checked .p-checkbox-box) {
    background: #dc2626;
    border-color: #dc2626;
}

/* === ZEITFENSTER CARD CHIPS (Green) === */
.card-zeitfenster .checkbox-item {
    background: rgb(91 219 166 / 56%);
}

.card-zeitfenster .checkbox-item:hover {
    background: rgb(91 219 166 / 70%);
}

.card-zeitfenster .checkbox-item.is-checked {
    background: rgb(91 219 166 / 85%);
}

.card-zeitfenster .checkbox-item.is-checked label {
    color: #166534;
}

.card-zeitfenster :deep(.p-checkbox-checked .p-checkbox-box) {
    background: #22c55e;
    border-color: #22c55e;
}

/* === REFERENZ CARD CHIPS (Beige/Tan) === */
.card-referenz .checkbox-item {
    background: rgb(217 210 177 / 50%);
}

.card-referenz .checkbox-item:hover {
    background: rgb(217 210 177 / 65%);
}

.card-referenz .checkbox-item.is-checked {
    background: rgb(217 210 177 / 80%);
}

.card-referenz .checkbox-item.is-checked label {
    color: #78716c;
}

.card-referenz :deep(.p-checkbox-checked .p-checkbox-box) {
    background: #a8a29e;
    border-color: #a8a29e;
}

/* Thema Section */
.thema-row {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

/* Thema chip with integrated keywords */
.thema-chip {
    flex-direction: row;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    transition: all 0.15s ease;
}

.thema-chip-expanded {
    background: rgb(255 140 140 / 90%);
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

/* Mehr/Weniger chip inside the thema chip */
.mehr-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.4rem;
    font-size: 0.65rem;
    font-weight: 600;
    border-radius: 4px;
    background: #fff;
    color: rgb(126, 58, 183);
    border: 1px solid rgba(168, 85, 247, 0.3);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
    align-self: flex-start;
    margin-top: 0.1rem;
}

.mehr-chip:hover {
    background: rgba(168, 85, 247, 0.08);
    border-color: rgba(168, 85, 247, 0.5);
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
    background: rgb(217 210 177 / 50%);
    border-radius: 6px;
}

.andere-row:has(.andere-input:focus) {
    background: rgb(217 210 177 / 65%);
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
.footer-buttons {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px solid var(--surface-border);
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


/* Responsive */
@media (max-width: 900px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }

    .cards-column {
        gap: 0.75rem;
    }

    .footer-buttons {
        flex-wrap: wrap;
    }
}
</style>
