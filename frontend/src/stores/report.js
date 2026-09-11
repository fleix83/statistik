import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cropImageDataUrl } from '../utils/canvasCrop'

// Report: an ordered list of analytics view snapshots (image + legend + numbers),
// kept in localStorage so it survives reloads. Display-only, nothing touches the API.
const STORAGE_KEY = 'report_items'

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

    return { items, count, addItem, removeItem, setItems, clear, cropLegacyPies }
})
