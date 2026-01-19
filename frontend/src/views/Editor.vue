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
import OptionItem from '../components/editor/OptionItem.vue'
import OptionItemThema from '../components/editor/OptionItemThema.vue'
import AddOptionButton from '../components/editor/AddOptionButton.vue'
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

// Section labels for display
const sectionLabels = {
    kontaktart: 'Kontaktart',
    person: 'Person',
    dauer: 'Dauer',
    thema: 'Thema',
    zeitfenster: 'Zeitfenster',
    referenz: 'Referenz'
}

const roles = [
    { label: 'Benutzer', value: 'user' },
    { label: 'Admin', value: 'admin' }
]

// Computed getters for each section
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
            detail: 'Optionen konnten nicht geladen werden',
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
async function onAddOption(section, label) {
    try {
        await createOption(section, label)
        toast.add({
            severity: 'success',
            summary: 'Erstellt',
            detail: 'Option wurde als Entwurf erstellt',
            life: 3000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: error.response?.data?.error || 'Erstellen fehlgeschlagen',
            life: 3000
        })
    }
}

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
            severity: 'info',
            summary: 'Markiert',
            detail: 'Option zum Löschen markiert',
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
        message: 'Alle Optionen auf Standardwerte zurücksetzen? Die Änderungen müssen danach noch veröffentlicht werden.',
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

    <div class="editor">
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

        <TabView>
            <!-- Options Tab -->
            <TabPanel header="Dropdown-Optionen">
                <div class="options-grid" v-if="!loading">
                    <!-- Left Column: Person Section (kontaktart + person + dauer) -->
                    <div class="options-column">
                        <h3 class="column-title">Person</h3>

                        <!-- Kontaktart -->
                        <SectionCard
                            title="Kontaktart"
                            section="kontaktart"
                            :options="kontaktartOptions"
                            @reorder="onReorderSection"
                        >
                            <template #item="{ element }">
                                <OptionItem
                                    :option="element"
                                    section="kontaktart"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                            <template #footer>
                                <AddOptionButton
                                    section="kontaktart"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Person -->
                        <SectionCard
                            title="Person"
                            section="person"
                            :options="personOptions"
                            @reorder="onReorderSection"
                        >
                            <template #item="{ element }">
                                <OptionItem
                                    :option="element"
                                    section="person"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                            <template #footer>
                                <AddOptionButton
                                    section="person"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Dauer -->
                        <SectionCard
                            title="Dauer"
                            section="dauer"
                            :options="dauerOptions"
                            @reorder="onReorderSection"
                        >
                            <template #item="{ element }">
                                <OptionItem
                                    :option="element"
                                    section="dauer"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                            <template #footer>
                                <AddOptionButton
                                    section="dauer"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>
                    </div>

                    <!-- Middle Column: Thema -->
                    <div class="options-column thema-column">
                        <h3 class="column-title">Thema</h3>

                        <SectionCard
                            title="Thema"
                            section="thema"
                            :options="themaOptions"
                            @reorder="onReorderSection"
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
                            <template #footer>
                                <AddOptionButton
                                    section="thema"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>
                    </div>

                    <!-- Right Column: Zeitfenster + Referenz -->
                    <div class="options-column">
                        <h3 class="column-title">Zeit & Referenz</h3>

                        <!-- Zeitfenster -->
                        <SectionCard
                            title="Zeitfenster"
                            section="zeitfenster"
                            :options="zeitfensterOptions"
                            @reorder="onReorderSection"
                        >
                            <template #item="{ element }">
                                <OptionItem
                                    :option="element"
                                    section="zeitfenster"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                            <template #footer>
                                <AddOptionButton
                                    section="zeitfenster"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>

                        <!-- Referenz -->
                        <SectionCard
                            title="Referenz"
                            section="referenz"
                            :options="referenzOptions"
                            @reorder="onReorderSection"
                        >
                            <template #item="{ element }">
                                <OptionItem
                                    :option="element"
                                    section="referenz"
                                    @update="onUpdateOption"
                                    @delete="onDeleteOption"
                                />
                            </template>
                            <template #footer>
                                <AddOptionButton
                                    section="referenz"
                                    @add="onAddOption"
                                />
                            </template>
                        </SectionCard>
                    </div>
                </div>

                <div v-else class="loading-state">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                    <span>Lade Optionen...</span>
                </div>
            </TabPanel>

            <!-- Users Tab -->
            <TabPanel header="Benutzer">
                <div class="mb-3">
                    <Button
                        label="Neuer Benutzer"
                        icon="pi pi-plus"
                        @click="openNewUser"
                    />
                </div>
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
.editor {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.options-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr 1fr;
    gap: 1.5rem;
}

.options-column {
    min-width: 0;
}

.thema-column {
    /* Thema column is wider */
}

.column-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--surface-200);
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 3rem;
    color: var(--text-color-secondary);
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field label {
    font-weight: 500;
}

@media (max-width: 1200px) {
    .options-grid {
        grid-template-columns: 1fr 1fr;
    }

    .thema-column {
        grid-column: span 2;
    }
}

@media (max-width: 768px) {
    .options-grid {
        grid-template-columns: 1fr;
    }

    .thema-column {
        grid-column: span 1;
    }
}
</style>
