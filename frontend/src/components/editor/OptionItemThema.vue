<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue'
import InputText from 'primevue/inputtext'
import CustomToggle from './CustomToggle.vue'

const props = defineProps({
    option: {
        type: Object,
        required: true
    },
    section: {
        type: String,
        required: true
    }
})

const emit = defineEmits(['update', 'delete', 'editKeywords'])

// Inline editing state
const isEditing = ref(false)
const editLabel = ref('')
const inputRef = ref(null)

// Keywords overflow detection
const keywordsSectionRef = ref(null)
const measureContainerRef = ref(null)
const visibleKeywordCount = ref(null) // null = show all (measuring mode)
const hasOverflow = ref(false)

// Computed
const isActive = computed(() => props.option.is_active)
const isMarkedForDelete = computed(() => props.option.draft_action === 'delete')
const isNew = computed(() => props.option.label === 'Neues Feld')
const keywords = computed(() => props.option.keywords || [])
const visibleKeywords = computed(() => {
    if (visibleKeywordCount.value === null) return keywords.value
    return keywords.value.slice(0, visibleKeywordCount.value)
})

// Calculate how many keywords fit on one line using the hidden measurement container
function calculateVisibleKeywords() {
    if (!measureContainerRef.value || !keywordsSectionRef.value || keywords.value.length === 0) {
        visibleKeywordCount.value = keywords.value.length
        hasOverflow.value = false
        return
    }

    const sectionWidth = keywordsSectionRef.value.offsetWidth
    const tags = measureContainerRef.value.querySelectorAll('.keyword-tag-measure')
    const gap = 8 // gap between tags

    let totalWidth = 0
    let count = 0

    for (const tag of tags) {
        const tagWidth = tag.offsetWidth
        if (totalWidth + tagWidth + (count > 0 ? gap : 0) <= sectionWidth) {
            totalWidth += tagWidth + (count > 0 ? gap : 0)
            count++
        } else {
            break
        }
    }

    // If not all fit, we have overflow
    if (count < keywords.value.length) {
        hasOverflow.value = true
        visibleKeywordCount.value = Math.max(1, count) // Show at least 1
    } else {
        hasOverflow.value = false
        visibleKeywordCount.value = keywords.value.length
    }
}

// Initial calculation after mount and on keyword changes
onMounted(() => {
    nextTick(() => {
        calculateVisibleKeywords()
    })
})

watch(() => props.option.keywords, () => {
    visibleKeywordCount.value = null // Reset to measure all
    nextTick(() => {
        calculateVisibleKeywords()
    })
}, { deep: true })

// Methods
function startEditing() {
    if (isMarkedForDelete.value) return
    editLabel.value = props.option.label
    isEditing.value = true
    nextTick(() => {
        inputRef.value?.$el?.focus()
    })
}

function saveEdit() {
    const newLabel = editLabel.value.trim()
    if (newLabel && newLabel !== props.option.label) {
        emit('update', props.option.id, { label: newLabel })
    }
    isEditing.value = false
}

function cancelEdit() {
    isEditing.value = false
    editLabel.value = ''
}

function onKeydown(event) {
    if (event.key === 'Enter') {
        saveEdit()
    } else if (event.key === 'Escape') {
        cancelEdit()
    }
}

function toggleActive() {
    emit('update', props.option.id, { is_active: !isActive.value })
}

function onEditKeywords() {
    emit('editKeywords', props.option)
}
</script>

<template>
    <div class="option-wrapper">
        <div
            class="option-thema drag-handle"
            :class="{
                'is-inactive': !isActive,
                'is-marked-delete': isMarkedForDelete,
                'is-new': isNew
            }"
            @dblclick="onEditKeywords"
        >
            <!-- Label (editable) -->
            <div class="option-label" @click.stop="startEditing">
                <template v-if="isEditing">
                    <InputText
                        ref="inputRef"
                        v-model="editLabel"
                        class="edit-input"
                        @blur="saveEdit"
                        @keydown="onKeydown"
                        @click.stop
                    />
                </template>
                <template v-else>
                    <span class="label-text" :class="{ 'strikethrough': isMarkedForDelete }">
                        {{ option.label }}
                    </span>
                </template>
            </div>

            <!-- Keywords section -->
            <div ref="keywordsSectionRef" class="keywords-section">
                <!-- Hidden measurement container (renders all keywords for width calculation) -->
                <div ref="measureContainerRef" class="keywords-measure" aria-hidden="true">
                    <span
                        v-for="keyword in keywords"
                        :key="'measure-' + keyword"
                        class="keyword-tag-measure"
                    >
                        {{ keyword }}
                    </span>
                </div>

                <!-- Keywords displayed inline (limited to one line) -->
                <div
                    class="keywords-inline"
                    v-if="keywords.length > 0"
                    @click.stop="onEditKeywords"
                >
                    <span
                        v-for="keyword in visibleKeywords"
                        :key="keyword"
                        class="keyword-tag"
                    >
                        {{ keyword }}
                    </span>
                </div>
                <div class="keywords-placeholder" v-else @click.stop="onEditKeywords">
                    Bitte mit Keywords differenzieren
                </div>

                <!-- Overflow chevron -->
                <div
                    v-if="hasOverflow"
                    class="keywords-overflow-chevron"
                    @click.stop="onEditKeywords"
                    :title="`${keywords.length - visibleKeywordCount} weitere Keywords`"
                >
                    <i class="pi pi-chevron-down"></i>
                </div>
            </div>

            <!-- Toggle (always right-aligned) -->
            <div class="toggle-wrapper">
                <CustomToggle
                    :modelValue="isActive"
                    @update:modelValue="toggleActive"
                    :disabled="isMarkedForDelete"
                    @click.stop
                />
            </div>
        </div>

        <!-- Delete link for deactivated options -->
        <a
            v-if="!isActive && !isMarkedForDelete"
            href="#"
            class="delete-link"
            @click.prevent="emit('delete', option.id)"
        >
            Feld löschen
        </a>
    </div>
</template>

<style scoped>
.option-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    width: 100%;
}

.delete-link {
    font-size: 12px;
    color: #D32F2F;
    text-decoration: none;
    padding-left: 4px;
}

.delete-link:hover {
    text-decoration: underline;
}

.option-thema {
    display: inline-flex;
    min-height: 66px;
    padding: 14px 28px 14px 35px;
    justify-content: flex-start;
    align-items: center;
    gap: 32px;
    border-radius: 7px;
    border: 1px solid #B7B7B7;
    background: #FFF;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.option-thema:hover {
    background: #fff5a79e;
    border-color: #999;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    cursor: move;
}

.option-thema:active,
.option-thema.sortable-chosen,
.option-thema.sortable-ghost {
    background: #fff5a79e;
    cursor: move;
}

.option-thema.is-inactive {
    opacity: 0.6;
}

.option-thema.is-marked-delete {
    background: #FEE;
    border-color: #E5A;
    opacity: 0.7;
}

.option-thema.is-new {
    background: #ffc7c7;
}

.option-thema.is-new .label-text {
    color: #ff6262;
}

.option-label {
    flex-shrink: 0;
    cursor: text;
}

.label-text {
    font-size: 17px;
    font-weight: 500;
    color: #1B1B1B;
    white-space: nowrap;
}

.label-text.strikethrough {
    text-decoration: line-through;
    color: #999;
}

.edit-input {
    width: auto;
    min-width: 80px;
}

.edit-input :deep(.p-inputtext) {
    padding: 4px 8px;
    font-size: 16px;
    font-weight: 500;
}

.keywords-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 0;
    gap: 4px;
    position: relative;
}

/* Hidden container for measuring keyword widths */
.keywords-measure {
    position: absolute;
    visibility: hidden;
    white-space: nowrap;
    display: flex;
    gap: 8px;
    pointer-events: none;
}

.keyword-tag-measure {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    font-size: 13px;
    white-space: nowrap;
}

.keywords-inline {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    width: 100%;
    overflow: hidden;
    cursor: pointer;
}

.keywords-placeholder {
    width: 100%;
    font-style: italic;
    color: #8c8c8c;
    cursor: pointer;
    font-size: 14px;
}

.keywords-overflow-chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #888;
    padding: 2px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}

.keywords-overflow-chevron:hover {
    background: #f0f0f0;
    color: #555;
}

.keywords-overflow-chevron i {
    font-size: 12px;
}

.toggle-wrapper {
    margin-left: auto;
    flex-shrink: 0;
}

.keyword-tag {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #e7e7e7;
    border-radius: 2px;
    font-size: 13px;
    color: #000000;
}

.keyword-tag:hover {
    background: #EBEBEB;
}
</style>
