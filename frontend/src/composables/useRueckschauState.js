import { ref } from 'vue'
import { rueckschau } from '../services/api'

// Shared state (singleton pattern)
const configuredFields = ref([])
const gridData = ref(null)
const loading = ref(false)
const saving = ref(false)

export function useRueckschauState() {
    async function loadFields() {
        try {
            const res = await rueckschau.getFields()
            configuredFields.value = res.data
        } catch (err) {
            console.error('Failed to load rueckschau fields:', err)
            throw err
        }
    }

    async function saveFields(fields) {
        saving.value = true
        try {
            await rueckschau.saveFields(fields)
            configuredFields.value = fields
        } catch (err) {
            console.error('Failed to save rueckschau fields:', err)
            throw err
        } finally {
            saving.value = false
        }
    }

    async function loadGridData(days = 7) {
        loading.value = true
        try {
            const res = await rueckschau.getData(days)
            gridData.value = res.data
        } catch (err) {
            console.error('Failed to load rueckschau data:', err)
            throw err
        } finally {
            loading.value = false
        }
    }

    return {
        configuredFields,
        gridData,
        loading,
        saving,
        loadFields,
        saveFields,
        loadGridData
    }
}
