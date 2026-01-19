<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Chip from 'primevue/chip'

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    option: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'save'])

// Local state
const keywords = ref([])
const newKeyword = ref('')
const inputRef = ref(null)

// Watch for option changes to reset keywords
watch(() => props.option, (newOption) => {
    if (newOption) {
        keywords.value = [...(newOption.keywords || [])]
    }
}, { immediate: true })

// Dialog visibility
const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
})

const optionLabel = computed(() => props.option?.label || '')

function addKeyword() {
    const keyword = newKeyword.value.trim()
    if (keyword && !keywords.value.includes(keyword)) {
        keywords.value.push(keyword)
        newKeyword.value = ''
        nextTick(() => {
            inputRef.value?.$el?.focus()
        })
    }
}

function removeKeyword(index) {
    keywords.value.splice(index, 1)
}

function onKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault()
        addKeyword()
    }
}

function onSave() {
    emit('save', props.option.id, keywords.value)
    dialogVisible.value = false
}

function onCancel() {
    dialogVisible.value = false
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        :header="`Keywords für: ${optionLabel}`"
        modal
        :style="{ width: '450px' }"
        :closable="true"
    >
        <div class="keyword-editor">
            <p class="editor-description">
                Keywords helfen Benutzern, das richtige Thema zu finden. Sie werden als Hinweise bei der Dateneingabe angezeigt.
            </p>

            <!-- Current Keywords -->
            <div class="keywords-list">
                <Chip
                    v-for="(keyword, index) in keywords"
                    :key="keyword"
                    :label="keyword"
                    removable
                    @remove="removeKeyword(index)"
                    class="keyword-chip"
                />
                <span v-if="keywords.length === 0" class="no-keywords">
                    Keine Keywords definiert
                </span>
            </div>

            <!-- Add New Keyword -->
            <div class="add-keyword">
                <InputText
                    ref="inputRef"
                    v-model="newKeyword"
                    placeholder="Neues Keyword eingeben..."
                    class="keyword-input"
                    @keydown="onKeydown"
                />
                <Button
                    icon="pi pi-plus"
                    @click="addKeyword"
                    :disabled="!newKeyword.trim()"
                />
            </div>
        </div>

        <template #footer>
            <Button
                label="Abbrechen"
                severity="secondary"
                @click="onCancel"
            />
            <Button
                label="Speichern"
                icon="pi pi-check"
                @click="onSave"
            />
        </template>
    </Dialog>
</template>

<style scoped>
.keyword-editor {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.editor-description {
    margin: 0;
    color: var(--text-color-secondary);
    font-size: 0.875rem;
}

.keywords-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    min-height: 40px;
    padding: 0.75rem;
    background: var(--surface-50);
    border-radius: 6px;
    border: 1px solid var(--surface-200);
}

.keyword-chip {
    background: var(--primary-100);
    color: var(--primary-700);
}

.no-keywords {
    color: var(--text-color-secondary);
    font-style: italic;
}

.add-keyword {
    display: flex;
    gap: 0.5rem;
}

.keyword-input {
    flex: 1;
}
</style>
