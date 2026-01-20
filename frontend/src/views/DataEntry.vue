<script setup>
import { ref, computed, onMounted } from 'vue'
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

function formatKeywordsTooltip(label) {
    const keywords = getKeywordsForThema(label)
    if (keywords.length === 0) return null
    return keywords.join(', ')
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
                        <h3 class="card-title">Person</h3>
                        <div class="card-content">
                            <!-- Kontaktart -->
                            <div class="checkbox-row">
                                <div
                                    v-for="opt in optionsBySection.kontaktart"
                                    :key="opt"
                                    class="checkbox-item"
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
                            <div class="checkbox-row no-border" v-if="optionsBySection.thema.includes('Migrationshintergrund')">
                                <div class="checkbox-item">
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
                                    <div class="checkbox-item">
                                        <Checkbox
                                            :inputId="'thema-' + opt"
                                            :value="opt"
                                            v-model="formData.thema"
                                        />
                                        <label :for="'thema-' + opt">{{ opt }}</label>
                                    </div>
                                    <span
                                        v-if="getKeywordsForThema(opt).length > 0"
                                        class="keywords-hint"
                                    >
                                        {{ getKeywordsForThema(opt).join(', ') }}
                                    </span>
                                    <Button
                                        v-if="getKeywordsForThema(opt).length > 0"
                                        icon="pi pi-info-circle"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="info-btn"
                                        v-tooltip.left="formatKeywordsTooltip(opt)"
                                    />
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
            <Button
                label="Beenden"
                severity="danger"
                class="end-btn"
            />
        </div>
    </div>
</template>

<style scoped>
.data-entry {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
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
    grid-template-columns: 3fr 2fr;
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

/* Card color accents */
.card-person {
    background-color: rgba(99, 102, 241, 0.04);
}

.card-thema {
    background-color: rgba(34, 197, 94, 0.04);
}

.card-zeitfenster {
    background-color: rgba(234, 179, 8, 0.04);
}

.card-referenz {
    background-color: rgba(6, 182, 212, 0.04);
}

/* Checkbox Styling */
.checkbox-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--surface-border);
}

.checkbox-row.no-border {
    border-bottom: none;
    padding-bottom: 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.checkbox-item label {
    cursor: pointer;
    user-select: none;
}

/* Thema Section */
.thema-section {
    margin: 0;
}

.thema-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.35rem 0;
    border-bottom: 1px solid var(--surface-border);
}

.thema-row:last-child {
    border-bottom: none;
}

.thema-row .checkbox-item {
    flex: 1;
    min-width: 0;
}

.keywords-hint {
    flex: 1;
    font-size: 0.75rem;
    color: var(--text-color-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}

.info-btn {
    width: 1.5rem;
    height: 1.5rem;
    flex-shrink: 0;
}

/* Zeitfenster items */
.zeitfenster-item {
    padding: 0.3rem 0;
}

/* Referenz items */
.referenz-item {
    padding: 0.3rem 0;
}

.andere-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
}

.andere-input {
    flex: 1;
    height: 2rem;
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

.end-btn {
    margin-left: auto;
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
