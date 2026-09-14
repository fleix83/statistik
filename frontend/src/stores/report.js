import { defineStore, acceptHMRUpdate } from 'pinia'
import { ref, computed } from 'vue'
import { cropImageDataUrl } from '../utils/canvasCrop'

// Report: an ordered list of analytics view snapshots (image + legend + numbers),
// kept in localStorage so it survives reloads. Display-only, nothing touches the API.
const STORAGE_KEY = 'report_items'
const TITLE_KEY = 'report_title'

function load() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        const parsed = raw ? JSON.parse(raw) : []
        return Array.isArray(parsed) ? parsed : []
    } catch {
        return []
    }
}

export const useReportStore = defineStore('report', () => {
    const items = ref(load())

    // Optional custom heading for the PDF (empty = automatic "Statistik <Jahr>")
    const title = ref(localStorage.getItem(TITLE_KEY) || '')

    function setTitle(value) {
        title.value = (value || '').trim()
        try {
            if (title.value) localStorage.setItem(TITLE_KEY, title.value)
            else localStorage.removeItem(TITLE_KEY)
        } catch {
            // storage unavailable: keep it for this session only
        }
    }

    const count = computed(() => items.value.length)

    // Returns false when the browser refuses the write (storage quota exceeded)
    function persist() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value))
            return true
        } catch {
            return false
        }
    }

    function addItem(snapshot) {
        const item = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            addedAt: new Date().toISOString(),
            ...snapshot
        }
        items.value.push(item)
        if (!persist()) {
            items.value.pop()
            throw new Error('report-storage-full')
        }
        return item
    }

    function removeItem(id) {
        items.value = items.value.filter(i => i.id !== id)
        persist()
    }

    // Merge changes into one item (e.g. a renamed title) and persist
    function updateItem(id, patch) {
        const item = items.value.find(i => i.id === id)
        if (!item) return
        Object.assign(item, patch)
        persist()
    }

    // Replace the whole list (used for drag-and-drop reordering)
    function setItems(list) {
        items.value = [...list]
        persist()
    }

    function clear() {
        items.value = []
        persist()
    }

    // Pie views captured before cropping was introduced are wide strips with a
    // small donut in the middle; crop them once so they render large and round.
    async function cropLegacyPies() {
        for (const item of items.value) {
            const img = item.image
            if (item.chartType !== 'pie' || !img?.src || !(img.width / img.height > 2.5)) continue
            try {
                item.image = await cropImageDataUrl(img.src)
            } catch {
                // keep the original capture
            }
        }
        persist()
    }
    cropLegacyPies()

    return { items, count, title, setTitle, addItem, updateItem, removeItem, setItems, clear, cropLegacyPies }
})

// Dev only: swap the store definition in place on hot reload. Without this an
// already created store keeps its old state and actions after the file changes,
// so newly added fields such as the report heading only appear after a reload.
if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useReportStore, import.meta.hot))
}
