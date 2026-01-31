<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

import EditorHeader from '../components/editor/EditorHeader.vue'
import SectionCard from '../components/editor/SectionCard.vue'
import OptionShort from '../components/editor/OptionShort.vue'
import OptionItemThema from '../components/editor/OptionItemThema.vue'
import KeywordEditor from '../components/editor/KeywordEditor.vue'

import { useEditorDraft } from '../composables/useEditorDraft'
import { users } from '../services/api'

const toast = useToast()
const confirm = useConfirm()

const {
    optionsBySection,
    publishState,
    loading,
    hasPendingChanges,
    loadDraft,
    createOption,
    updateOption,
    deleteOption,
    reorderSection,
    updateKeywords,
    publishChanges,
    discardChanges,
    resetToDefault
} = useEditorDraft()

// Users management (separate from options draft system)
const usersList = ref([])
const usersLoading = ref(false)
const userDialog = ref(false)
const editingUser = ref(null)
const userForm = ref({ username: '', password: '', role: 'user' })

// Action states
const publishing = ref(false)
const discarding = ref(false)
const resetting = ref(false)

// Keyword editor state
const keywordEditorVisible = ref(false)
const keywordEditorOption = ref(null)

const roles = [
    { label: 'Benutzer', value: 'user' },
    { label: 'Admin', value: 'admin' }
]

// Computed: separate options for each section
const kontaktartOptions = computed(() => optionsBySection.value['kontaktart'] || [])
const personOptions = computed(() => optionsBySection.value['person'] || [])
const dauerOptions = computed(() => optionsBySection.value['dauer'] || [])
const themaOptions = computed(() => optionsBySection.value['thema'] || [])
const zeitfensterOptions = computed(() => optionsBySection.value['zeitfenster'] || [])
const referenzOptions = computed(() => optionsBySection.value['referenz'] || [])

onMounted(async () => {
    await Promise.all([
        loadDraftData(),
        loadUsers()
    ])
})

async function loadDraftData() {
    try {
        await loadDraft()
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Felder konnten nicht geladen werden',
            life: 3000
        })
    }
}

async function loadUsers() {
    usersLoading.value = true
    try {
        const response = await users.list()
        usersList.value = response.data
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Benutzer konnten nicht geladen werden',
            life: 3000
        })
    } finally {
        usersLoading.value = false
    }
}

// Options CRUD
async function onUpdateOption(id, data) {
    try {
        await updateOption(id, data)
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Aktualisierung fehlgeschlagen',
            life: 3000
        })
    }
}

async function onDeleteOption(id) {
    try {
        await deleteOption(id)
        toast.add({
            severity: 'success',
            summary: 'Gelöscht',
            detail: 'Feld wurde gelöscht',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: error.response?.data?.error || 'Löschen fehlgeschlagen',
            life: 3000
        })
    }
}

async function onReorderSection(section, items) {
    try {
        await reorderSection(section, items)
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Neuordnung fehlgeschlagen',
            life: 3000
        })
    }
}

// Add new option to a section
async function onAddOption(section) {
    try {
        // Set sort_order to put new option first in the list
        const currentOptions = optionsBySection.value[section] || []
        const minSortOrder = currentOptions.length > 0
            ? Math.min(...currentOptions.map(o => o.sort_order))
            : 0
        const sortOrder = minSortOrder - 1

        await createOption(section, 'Neues Feld', sortOrder)
        toast.add({
            severity: 'success',
            summary: 'Erstellt',
            detail: 'Neues Feld hinzugefügt',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Feld konnte nicht erstellt werden',
            life: 3000
        })
    }
}

// Keywords
function onEditKeywords(option) {
    keywordEditorOption.value = option
    keywordEditorVisible.value = true
}

async function onSaveKeywords(id, keywords) {
    try {
        await updateKeywords(id, keywords)
        toast.add({
            severity: 'success',
            summary: 'Gespeichert',
            detail: 'Keywords wurden aktualisiert',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Keywords konnten nicht gespeichert werden',
            life: 3000
        })
    }
}

// Publish/Discard/Reset
async function onPublish() {
    confirm.require({
        message: 'Alle ausstehenden Änderungen veröffentlichen?',
        header: 'Veröffentlichen bestätigen',
        icon: 'pi pi-check-circle',
        acceptLabel: 'Veröffentlichen',
        rejectLabel: 'Abbrechen',
        accept: async () => {
            publishing.value = true
            try {
                const result = await publishChanges()
                toast.add({
                    severity: 'success',
                    summary: 'Veröffentlicht',
                    detail: `${result.stats.created} erstellt, ${result.stats.updated} aktualisiert, ${result.stats.deleted} gelöscht`,
                    life: 5000
                })
            } catch (error) {
                toast.add({
                    severity: 'error',
                    summary: 'Fehler',
                    detail: 'Veröffentlichung fehlgeschlagen',
                    life: 3000
                })
            } finally {
                publishing.value = false
            }
        }
    })
}

async function onDiscard() {
    confirm.require({
        message: 'Alle ausstehenden Änderungen verwerfen? Diese Aktion kann nicht rückgängig gemacht werden.',
        header: 'Änderungen verwerfen',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Verwerfen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: async () => {
            discarding.value = true
            try {
                await discardChanges()
                toast.add({
                    severity: 'info',
                    summary: 'Verworfen',
                    detail: 'Alle ausstehenden Änderungen wurden verworfen',
                    life: 3000
                })
            } catch (error) {
                toast.add({
                    severity: 'error',
                    summary: 'Fehler',
                    detail: 'Verwerfen fehlgeschlagen',
                    life: 3000
                })
            } finally {
                discarding.value = false
            }
        }
    })
}

async function onReset() {
    confirm.require({
        message: 'Alle Felder auf Standardwerte zurücksetzen? Die Änderungen müssen danach noch veröffentlicht werden.',
        header: 'Auf Standard zurücksetzen',
        icon: 'pi pi-refresh',
        acceptLabel: 'Zurücksetzen',
        rejectLabel: 'Abbrechen',
        accept: async () => {
            resetting.value = true
            try {
                const result = await resetToDefault()
                toast.add({
                    severity: 'success',
                    summary: 'Zurückgesetzt',
                    detail: `${result.stats.to_create} zu erstellen, ${result.stats.to_update} zu aktualisieren, ${result.stats.to_delete} zu löschen. Bitte veröffentlichen Sie die Änderungen.`,
                    life: 5000
                })
            } catch (error) {
                toast.add({
                    severity: 'error',
                    summary: 'Fehler',
                    detail: 'Zurücksetzen fehlgeschlagen',
                    life: 3000
                })
            } finally {
                resetting.value = false
            }
        }
    })
}

// Users CRUD
function openNewUser() {
    editingUser.value = null
    userForm.value = { username: '', password: '', role: 'user' }
    userDialog.value = true
}

function editUser(user) {
    editingUser.value = user
    userForm.value = { username: user.username, password: '', role: user.role }
    userDialog.value = true
}

async function saveUser() {
    try {
        if (editingUser.value) {
            await users.update(editingUser.value.id, userForm.value)
            toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Benutzer wurde aktualisiert', life: 3000 })
        } else {
            await users.create(userForm.value)
            toast.add({ severity: 'success', summary: 'Erstellt', detail: 'Benutzer wurde erstellt', life: 3000 })
        }
        userDialog.value = false
        await loadUsers()
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Speichern fehlgeschlagen', life: 3000 })
    }
}

function confirmDeleteUser(user) {
    confirm.require({
        message: `Benutzer "${user.username}" wirklich löschen?`,
        header: 'Löschen bestätigen',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Ja, löschen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await users.delete(user.id)
                toast.add({ severity: 'success', summary: 'Gelöscht', detail: 'Benutzer wurde gelöscht', life: 3000 })
                await loadUsers()
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Fehler', detail: 'Löschen fehlgeschlagen', life: 3000 })
            }
        }
    })
}
</script>

<template>
    <Toast />
    <ConfirmDialog />
    <KeywordEditor
        v-model:visible="keywordEditorVisible"
        :option="keywordEditorOption"
        @save="onSaveKeywords"
    />

    <div class="editor-page">
        <div class="editor-header">
            <h1 class="page-title">Editor</h1>
            <EditorHeader
                :hasPendingChanges="hasPendingChanges"
                :lastPublishedAt="publishState.last_published_at"
                :publishing="publishing"
                :discarding="discarding"
                :resetting="resetting"
                @publish="onPublish"
                @discard="onDiscard"
                @reset="onReset"
            />
        </div>

        <TabView class="editor-tabs">
            <!-- Options Tab -->
            <TabPanel header="Felder">
                <div class="tab-content-container">
                    <!-- Debug info -->
                    <div class="debug-info">
                        Kontaktart: {{ kontaktartOptions.length }} |
                        Person: {{ personOptions.length }} |
                        Dauer: {{ dauerOptions.length }} |
                        Thema: {{ themaOptions.length }} |
                        Zeitfenster: {{ zeitfensterOptions.length }} |
                        Referenz: {{ referenzOptions.length }}
                    </div>
                </div>
                <div class="options-layout" v-if="!loading">
                    <!-- Left Column -->
                    <div class="options-column-left">
                        <!-- Kontaktart Section -->
                        <SectionCard
                            title="Kontaktart"
                            section="kontaktart"
                            :options="kontaktartOptions"
                            layout="grid"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionShort
                                    :option="element"
                                    section="kontaktart"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Person Section -->
                        <SectionCard
                            title="Person"
                            section="person"
                            :options="personOptions"
                            layout="grid"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionShort
                                    :option="element"
                                    section="person"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Dauer Section -->
                        <SectionCard
                            title="Dauer"
                            section="dauer"
                            :options="dauerOptions"
                            layout="grid"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionShort
                                    :option="element"
                                    section="dauer"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Thema Section -->
                        <SectionCard
                            title="Thema"
                            section="thema"
                            :options="themaOptions"
                            layout="list"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionItemThema
                                    :option="element"
                                    section="thema"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                    @editKeywords="onEditKeywords"
                                />
                            </template>
                        </SectionCard>
                    </div>

                    <!-- Right Column -->
                    <div class="options-column-right">
                        <!-- Zeitfenster Section -->
                        <SectionCard
                            title="Zeitfenster"
                            section="zeitfenster"
                            :options="zeitfensterOptions"
                            layout="list"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionShort
                                    :option="element"
                                    section="zeitfenster"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Referenz Section -->
                        <SectionCard
                            title="Referenz"
                            section="referenz"
                            :options="referenzOptions"
                            layout="list"
                            @reorder="onReorderSection"
                            @add="onAddOption"
                        >
                            <template #item="{ element }">
                                <OptionShort
                                    :option="element"
                                    section="referenz"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                        </SectionCard>
                    </div>
                </div>

                <div v-else class="loading-state">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                    <span>Lade Felder...</span>
                </div>
            </TabPanel>

            <!-- Users Tab -->
            <TabPanel header="Benutzer">
                <div class="users-tab-content">
                    <div class="users-toolbar">
                        <Button
                            label="Neu"
                            icon="pi pi-plus"
                            @click="openNewUser"
                        />
                    </div>
                    <div class="users-table-wrapper">
                        <DataTable
                            :value="usersList"
                            :loading="usersLoading"
                        >
                            <Column field="username" header="Benutzername" sortable />
                            <Column field="role" header="Rolle">
                                <template #body="{ data }">
                                    {{ data.role === 'admin' ? 'Admin' : 'Benutzer' }}
                                </template>
                            </Column>
                            <Column field="created_at" header="Erstellt" sortable />
                            <Column header="Aktionen" style="width: 150px">
                                <template #body="{ data }">
                                    <Button icon="pi pi-pencil" severity="secondary" text @click="editUser(data)" />
                                    <Button icon="pi pi-trash" severity="danger" text @click="confirmDeleteUser(data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </TabPanel>
        </TabView>

        <!-- User Dialog -->
        <Dialog v-model:visible="userDialog" :header="editingUser ? 'Benutzer bearbeiten' : 'Neuer Benutzer'" modal style="width: 400px">
            <div class="flex flex-column gap-3 pt-3">
                <div class="field">
                    <label for="user-name">Benutzername</label>
                    <InputText id="user-name" v-model="userForm.username" class="w-full" />
                </div>
                <div class="field">
                    <label for="user-pass">Passwort {{ editingUser ? '(leer lassen um nicht zu ändern)' : '' }}</label>
                    <InputText id="user-pass" v-model="userForm.password" type="password" class="w-full" />
                </div>
                <div class="field">
                    <label for="user-role">Rolle</label>
                    <Select
                        id="user-role"
                        v-model="userForm.role"
                        :options="roles"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Abbrechen" severity="secondary" @click="userDialog = false" />
                <Button label="Speichern" @click="saveUser" />
            </template>
        </Dialog>

    </div>
</template>

<style scoped>
.editor-page {
    min-height: 100vh;
    background: #F5F3EF;
    padding: 24px 40px;
}

.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    max-width: 1300px;
    margin-left: auto;
    margin-right: auto;
}

.page-title {
    font-size: 16px;
    font-weight: 400;
    color: #666;
    margin: 0;
}

.editor-tabs {
    background: transparent;
}

.editor-tabs :deep(.p-tabview-panels) {
    background: transparent;
    padding: 0;
}

.editor-tabs :deep(.p-tabview-tablist-container),
.editor-tabs :deep(.p-tabview-nav-container) {
    max-width: 1177px;
    margin: 0 auto;
}

.editor-tabs :deep(.p-tabview-tablist),
.editor-tabs :deep(.p-tabview-nav) {
    background: transparent;
    border: none;
    margin-bottom: 24px;
}

.tab-content-container {
    max-width: 1177px;
    margin: 0 auto;
}

.debug-info {
    padding: 10px;
    background: #fff3cd;
    margin-bottom: 16px;
    border-radius: 4px;
    font-size: 12px;
    max-width: 1177px;
    margin-left: auto;
    margin-right: auto;
}

.options-layout {
    display: grid;
    grid-template-columns: minmax(0, 803px) 350px;
    gap: 24px;
    align-items: start;
    max-width: 1177px; /* 803 + 24 + 350 */
    margin: 0 auto;
}

.options-column-left {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.options-column-right {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 3rem;
    color: #666;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field label {
    font-weight: 500;
}

/* Users tab content container */
.users-tab-content {
    max-width: 1300px;
    margin: 0 auto;
}

.users-toolbar {
    margin-bottom: 16px;
}

/* Primary button styling to match DataEntry */
.users-toolbar :deep(.p-button) {
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
}

.users-toolbar :deep(.p-button:hover) {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}

/* Dialog primary button styling */
:deep(.p-dialog-footer .p-button:not(.p-button-secondary)) {
    background: var(--color-primary) !important;
    border-color: transparent !important;
    color: var(--color-primary-text) !important;
}

:deep(.p-dialog-footer .p-button:not(.p-button-secondary):hover) {
    background: var(--color-primary-hover) !important;
    border-color: transparent !important;
}

/* Dialog secondary button - remove border */
:deep(.p-dialog-footer .p-button-secondary) {
    border-color: transparent !important;
}

.users-table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.users-table-wrapper :deep(.p-datatable) {
    border-radius: 12px;
    border: none;
}

.users-table-wrapper :deep(.p-datatable-header),
.users-table-wrapper :deep(.p-datatable-thead > tr > th:first-child) {
    border-top-left-radius: 12px;
}

.users-table-wrapper :deep(.p-datatable-header),
.users-table-wrapper :deep(.p-datatable-thead > tr > th:last-child) {
    border-top-right-radius: 12px;
}

/* Tab styling - selected tab with white background */
.editor-tabs :deep(.p-tabview-tablist) {
    background: transparent;
    border: none;
    gap: 4px;
}

.editor-tabs :deep(.p-tabview-tablist-item) {
    border-radius: 8px;
    overflow: hidden;
}

.editor-tabs :deep(.p-tabview-tab-header) {
    border: none;
    background: transparent;
    border-radius: 8px;
    transition: background-color 0.2s;
}

.editor-tabs :deep(.p-tabview-tablist-item[data-p-active="true"] .p-tabview-tab-header) {
    background: white;
}

.editor-tabs :deep(.p-tabview-tablist-item[data-p-active="true"] .p-tabview-tab-title) {
    color: #333;
}

/* Remove default active indicator line */
.editor-tabs :deep(.p-tabview-ink-bar) {
    display: none;
}

@media (max-width: 1024px) {
    .options-layout {
        grid-template-columns: 1fr;
    }
}
</style>
