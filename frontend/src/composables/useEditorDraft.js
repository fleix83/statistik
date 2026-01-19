import { ref, computed } from 'vue'
import { options } from '../services/api'

// Section mapping: Editor visual sections -> database sections
const SECTION_GROUPS = {
    person: ['kontaktart', 'person', 'dauer'],
    thema: ['thema'],
    zeitfenster: ['zeitfenster'],
    referenz: ['referenz']
}

// Shared state (singleton pattern for composable)
const optionsBySection = ref({})
const publishState = ref({
    has_pending_changes: false,
    last_published_at: null,
    last_published_by: null
})
const loading = ref(false)
const error = ref(null)

export function useEditorDraft() {
    // Load draft data from API
    async function loadDraft() {
        loading.value = true
        error.value = null
        try {
            const response = await options.getDraft()
            const data = response.data

            // Group options by section
            const grouped = {}
            for (const opt of data.options) {
                if (!grouped[opt.section]) {
                    grouped[opt.section] = []
                }
                grouped[opt.section].push(opt)
            }

            // Sort each section by sort_order
            for (const section in grouped) {
                grouped[section].sort((a, b) => a.sort_order - b.sort_order)
            }

            optionsBySection.value = grouped
            publishState.value = data.publish_state
        } catch (err) {
            error.value = err.message || 'Laden fehlgeschlagen'
            throw err
        } finally {
            loading.value = false
        }
    }

    // Get options for a specific database section
    function getSection(section) {
        return computed(() => optionsBySection.value[section] || [])
    }

    // Get options for a visual editor group (e.g., 'person' includes kontaktart, person, dauer)
    function getEditorGroup(groupName) {
        return computed(() => {
            const sections = SECTION_GROUPS[groupName] || []
            const result = {}
            for (const section of sections) {
                result[section] = optionsBySection.value[section] || []
            }
            return result
        })
    }

    // Check if there are pending changes
    const hasPendingChanges = computed(() => publishState.value.has_pending_changes)

    // Create new option (goes to draft)
    async function createOption(section, label, sortOrder = 0, keywords = []) {
        const response = await options.create({ section, label, sort_order: sortOrder, keywords })
        await loadDraft()
        return response.data
    }

    // Update option (goes to draft)
    async function updateOption(id, data) {
        const response = await options.update(id, data)
        await loadDraft()
        return response.data
    }

    // Delete option (goes to draft)
    async function deleteOption(id) {
        const response = await options.delete(id)
        await loadDraft()
        return response.data
    }

    // Reorder options within a section
    async function reorderSection(section, items) {
        const reorderData = items.map((item, index) => ({
            id: item.id,
            sort_order: index
        }))
        await options.reorder(section, reorderData)
        await loadDraft()
    }

    // Update keywords for a thema option
    async function updateKeywords(id, keywords) {
        const response = await options.updateKeywords(id, keywords)
        await loadDraft()
        return response.data
    }

    // Publish all draft changes
    async function publishChanges() {
        const response = await options.publish()
        await loadDraft()
        return response.data
    }

    // Discard all draft changes
    async function discardChanges() {
        const response = await options.discard()
        await loadDraft()
        return response.data
    }

    // Reset to default configuration
    async function resetToDefault() {
        const response = await options.reset()
        await loadDraft()
        return response.data
    }

    // Check if an option has draft changes
    function hasDraftChanges(option) {
        return option.draft_action !== null
    }

    // Get draft status label
    function getDraftStatusLabel(option) {
        switch (option.draft_action) {
            case 'create': return 'Neu'
            case 'update': return 'Geändert'
            case 'delete': return 'Zum Löschen markiert'
            default: return null
        }
    }

    return {
        // State
        optionsBySection,
        publishState,
        loading,
        error,
        hasPendingChanges,

        // Section helpers
        getSection,
        getEditorGroup,
        SECTION_GROUPS,

        // Actions
        loadDraft,
        createOption,
        updateOption,
        deleteOption,
        reorderSection,
        updateKeywords,
        publishChanges,
        discardChanges,
        resetToDefault,

        // Helpers
        hasDraftChanges,
        getDraftStatusLabel
    }
}
