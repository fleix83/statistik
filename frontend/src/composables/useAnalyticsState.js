import { ref, computed, watch } from 'vue'
import { analytics, markers as markersApi, savedPeriods as savedPeriodsApi } from '../services/api'
import { format, startOfYear, endOfYear } from 'date-fns'
import { de } from 'date-fns/locale'

const currentYear = new Date().getFullYear()
const lastYear = currentYear - 1

// Shared state (singleton pattern)
const periods = ref([
    {
        id: 1,
        start: startOfYear(new Date(currentYear, 0, 1)),
        end: endOfYear(new Date(currentYear, 0, 1)),
        label: String(currentYear),
        isComparison: false
    },
    {
        id: 2,
        start: startOfYear(new Date(lastYear, 0, 1)),
        end: endOfYear(new Date(lastYear, 0, 1)),
        label: String(lastYear),
        isComparison: true
    }
])

const selectedParams = ref({
    kontaktart: [],
    person: [],
    dauer: [],
    thema: [],
    zeitfenster: [],
    referenz: []
})

const chartType = ref('line') // 'line' | 'bar' | 'pie' - default to line
const activeSection = ref('thema') // Which section to visualize
const loading = ref(false)
const error = ref(null)

// Filter options from API
const filterOptions = ref({
    kontakt: { kontaktart: [], person: [], dauer: [] },
    thema: [],
    zeitfenster: [],
    referenz: []
})

// Chart data
const chartData = ref(null)
const summaryData = ref({ total: 0, periods: [] })

// Chart markers
const markers = ref([])

// Saved periods (multiple can be active for comparison)
const savedPeriodsConfigs = ref([])
const activeSavedPeriodIds = ref([])

// Debounce timer for data fetching
let fetchDebounceTimer = null

let nextPeriodId = 3

export function useAnalyticsState() {
    // Computed: check if we're in comparison mode (multiple periods)
    const isCompareMode = computed(() => periods.value.length > 1)

    // Computed: get selected values for the active section
    const activeValues = computed(() => {
        return selectedParams.value[activeSection.value] || []
    })

    // Computed: check if showing totals (no specific values selected AND line chart)
    const isShowingTotals = computed(() =>
        activeValues.value.length === 0 && chartType.value === 'line'
    )

    // Computed: all sections that have selections
    const sectionsWithSelections = computed(() => {
        const sections = []
        for (const [section, values] of Object.entries(selectedParams.value)) {
            if (values.length > 0) {
                sections.push({ section, values })
            }
        }
        return sections
    })

    // Load filter options
    async function loadFilters() {
        try {
            const response = await analytics.filters()
            filterOptions.value = response.data
        } catch (err) {
            console.error('Failed to load filters:', err)
            error.value = 'Filter konnten nicht geladen werden'
        }
    }

    // Add a new period
    function addPeriod() {
        const lastPeriod = periods.value[periods.value.length - 1]
        const yearBefore = new Date(lastPeriod.start)
        yearBefore.setFullYear(yearBefore.getFullYear() - 1)
        const yearBeforeEnd = new Date(lastPeriod.end)
        yearBeforeEnd.setFullYear(yearBeforeEnd.getFullYear() - 1)

        periods.value.push({
            id: nextPeriodId++,
            start: yearBefore,
            end: yearBeforeEnd,
            label: `Periode ${periods.value.length + 1}`,
            isComparison: true
        })
    }

    // Remove a period
    function removePeriod(id) {
        if (periods.value.length > 1) {
            periods.value = periods.value.filter(p => p.id !== id)
        }
    }

    // Update period dates
    function updatePeriod(id, updates) {
        const period = periods.value.find(p => p.id === id)
        if (period) {
            Object.assign(period, updates)
        }
    }

    // Set period to specific year (keeps comparison year)
    function setYearPeriod(year) {
        periods.value = [
            {
                id: 1,
                start: startOfYear(new Date(year, 0, 1)),
                end: endOfYear(new Date(year, 0, 1)),
                label: String(year),
                isComparison: false
            },
            {
                id: 2,
                start: startOfYear(new Date(year - 1, 0, 1)),
                end: endOfYear(new Date(year - 1, 0, 1)),
                label: String(year - 1),
                isComparison: true
            }
        ]
    }

    // Toggle a parameter value
    function toggleParam(section, value) {
        const current = selectedParams.value[section]
        const index = current.indexOf(value)
        if (index === -1) {
            current.push(value)
            // Auto-set active section when selecting a parameter
            activeSection.value = section
        } else {
            current.splice(index, 1)
        }
        // Auto-fetch with debounce
        debouncedFetch()
    }

    // Clear all selections for a section
    function clearSection(section) {
        selectedParams.value[section] = []
        debouncedFetch()
    }

    // Clear all selections
    function clearAllSelections() {
        for (const section in selectedParams.value) {
            selectedParams.value[section] = []
        }
        debouncedFetch()
    }

    // Format date for API
    function formatDateForApi(date) {
        return format(date, 'yyyy-MM-dd')
    }

    // Build filters object from all selected params (for API filtering)
    function buildFilters() {
        const filters = {}
        for (const [section, values] of Object.entries(selectedParams.value)) {
            if (values.length > 0) {
                filters[section] = values
            }
        }
        return filters
    }

    // Debounced fetch
    function debouncedFetch() {
        if (fetchDebounceTimer) {
            clearTimeout(fetchDebounceTimer)
        }
        fetchDebounceTimer = setTimeout(() => {
            fetchData()
        }, 300)
    }

    // Fetch chart data based on current state
    async function fetchData() {
        if (periods.value.length === 0) return

        loading.value = true
        error.value = null

        try {
            const primaryPeriod = periods.value[0]

            // Build filters for API calls
            const filters = buildFilters()
            const filtersJson = Object.keys(filters).length > 0 ? JSON.stringify(filters) : undefined

            // Bar, Stacked, and Pie charts use aggregate data
            if (chartType.value === 'bar' || chartType.value === 'stacked' || chartType.value === 'pie') {
                // Fetch aggregate data for all periods (for grouped bar comparison)
                const periodDatasets = []
                let allLabels = new Set()

                for (const period of periods.value) {
                    const params = {
                        section: activeSection.value,
                        start_date: formatDateForApi(period.start),
                        end_date: formatDateForApi(period.end),
                        filters: filtersJson
                    }

                    const response = await analytics.aggregate(params)

                    // Filter to selected values if any
                    let items = response.data.items
                    if (activeValues.value.length > 0) {
                        items = items.filter(item =>
                            activeValues.value.includes(item.label)
                        )
                    }

                    // Collect all labels
                    items.forEach(item => allLabels.add(item.label))

                    periodDatasets.push({
                        label: period.label,
                        items: items,
                        total: response.data.total,
                        isComparison: period.isComparison
                    })
                }

                // Convert labels to array and sort
                const labels = Array.from(allLabels)

                // Stacked bar chart mode - X-axis is periods, bars stacked by values
                if (chartType.value === 'stacked') {
                    chartData.value = {
                        mode: 'stacked',
                        labels: periods.value.map(p => p.label),  // Period labels on X-axis
                        datasets: labels.map(value => ({
                            label: value,
                            data: periods.value.map(p => {
                                const periodData = periodDatasets.find(d => d.label === p.label)
                                const item = periodData?.items.find(item => item.label === value)
                                return item?.count ?? 0
                            })
                        })),
                        total: periodDatasets[0].total
                    }
                }
                // For single period or pie chart, use simple mode
                else if (periods.value.length === 1 || chartType.value === 'pie') {
                    chartData.value = {
                        mode: 'aggregate',
                        items: periodDatasets[0].items,
                        total: periodDatasets[0].total
                    }
                } else {
                    // Multiple periods - use grouped bar mode
                    chartData.value = {
                        mode: 'aggregate-compare',
                        labels: labels,
                        datasets: periodDatasets.map(ds => ({
                            label: ds.label,
                            data: labels.map(lbl => {
                                const item = ds.items.find(i => i.label === lbl)
                                return item ? item.count : 0
                            }),
                            isComparison: ds.isComparison
                        })),
                        total: periodDatasets[0].total
                    }
                }

                summaryData.value = {
                    total: periodDatasets[0].total,
                    periods: periodDatasets.map(ds => ({
                        label: ds.label,
                        total: ds.total,
                        isComparison: ds.isComparison
                    }))
                }
            } else if (chartType.value === 'line' || chartType.value === 'stream') {
                // Line chart and Stream graph mode
                if (activeValues.value.length === 0) {
                    // Show total entries over time (default view)
                    const datasets = []
                    let allLabels = []
                    let granularityUsed = 'month'

                    // Fetch totals for each period
                    let rawLabels = []
                    for (const period of periods.value) {
                        const params = {
                            start_date: formatDateForApi(period.start),
                            end_date: formatDateForApi(period.end),
                            granularity: 'auto',
                            filters: filtersJson
                        }
                        const response = await analytics.totals(params)
                        granularityUsed = response.data.granularity

                        // Store raw labels for tooltip (first period only)
                        if (rawLabels.length === 0) {
                            rawLabels = response.data.labels.map(l => {
                                if (granularityUsed === 'day') {
                                    // Format: 2025-01-15 → 15. Januar 2025
                                    const date = new Date(l)
                                    return format(date, 'd. MMMM yyyy', { locale: de })
                                } else if (granularityUsed === 'week') {
                                    // Format: 2025-W03 → week start date
                                    const [year, week] = l.split('-W')
                                    const jan4 = new Date(parseInt(year), 0, 4)
                                    const weekStart = new Date(jan4)
                                    weekStart.setDate(jan4.getDate() - jan4.getDay() + 1 + (parseInt(week) - 1) * 7)
                                    return format(weekStart, 'd. MMMM yyyy', { locale: de })
                                } else {
                                    // Format: 2025-01 → Januar 2025
                                    const [year, month] = l.split('-')
                                    const date = new Date(parseInt(year), parseInt(month) - 1, 1)
                                    return format(date, 'MMMM yyyy', { locale: de })
                                }
                            })
                        }

                        // Format labels based on granularity
                        const labels = response.data.labels.map(l => {
                            if (granularityUsed === 'day') {
                                // Format: 2025-01-15 → 15.01.
                                const parts = l.split('-')
                                return `${parts[2]}.${parts[1]}.`
                            } else if (granularityUsed === 'week') {
                                // Format: 2025-W03 → KW03
                                return l.replace(/^\d{4}-W/, 'KW')
                            } else {
                                // Format: 2025-01 → month number for comparison
                                const parts = l.split('-')
                                return parts[1]
                            }
                        })

                        if (allLabels.length === 0) {
                            allLabels = labels
                        }

                        datasets.push({
                            label: period.label,
                            data: response.data.data,
                            total: response.data.total,
                            isComparison: period.isComparison,
                            rawLabels: rawLabels
                        })
                    }

                    // Convert month numbers to names (only for monthly granularity)
                    if (granularityUsed === 'month') {
                        const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
                        allLabels = allLabels.map(m => monthNames[parseInt(m) - 1] || m)
                    }

                    chartData.value = {
                        mode: 'totals',
                        granularity: granularityUsed,
                        labels: allLabels,
                        rawLabels: rawLabels,
                        datasets: datasets
                    }

                    summaryData.value = {
                        total: datasets.reduce((sum, ds) => sum + ds.total, 0),
                        periods: datasets.map(ds => ({
                            label: ds.label,
                            total: ds.total,
                            isComparison: ds.isComparison
                        }))
                    }
                } else {
                    // Show selected values over time
                    const params = {
                        section: activeSection.value,
                        values: activeValues.value.join(','),
                        start_date: formatDateForApi(primaryPeriod.start),
                        end_date: formatDateForApi(primaryPeriod.end),
                        granularity: 'auto',
                        filters: filtersJson
                    }

                    const response = await analytics.timeseries(params)
                    const granularityUsed = response.data.granularity

                    // Create rawLabels for tooltip display
                    const rawLabels = response.data.labels.map(l => {
                        if (granularityUsed === 'day') {
                            const date = new Date(l)
                            return format(date, 'd. MMMM yyyy', { locale: de })
                        } else if (granularityUsed === 'week') {
                            const [year, week] = l.split('-W')
                            const jan4 = new Date(parseInt(year), 0, 4)
                            const weekStart = new Date(jan4)
                            weekStart.setDate(jan4.getDate() - jan4.getDay() + 1 + (parseInt(week) - 1) * 7)
                            return format(weekStart, 'd. MMMM yyyy', { locale: de })
                        } else {
                            const [year, month] = l.split('-')
                            const date = new Date(parseInt(year), parseInt(month) - 1, 1)
                            return format(date, 'MMMM yyyy', { locale: de })
                        }
                    })

                    // Format display labels
                    const displayLabels = response.data.labels.map(l => {
                        if (granularityUsed === 'day') {
                            const parts = l.split('-')
                            return `${parts[2]}.${parts[1]}.`
                        } else if (granularityUsed === 'week') {
                            return l.replace(/^\d{4}-W/, 'KW')
                        } else {
                            const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
                            const parts = l.split('-')
                            return monthNames[parseInt(parts[1]) - 1] || l
                        }
                    })

                    chartData.value = {
                        mode: 'timeseries',
                        granularity: granularityUsed,
                        labels: displayLabels,
                        rawLabels: rawLabels,
                        datasets: response.data.datasets
                    }

                    summaryData.value = {
                        total: 0,
                        periods: [{
                            label: primaryPeriod.label,
                            total: response.data.datasets.reduce((sum, ds) =>
                                sum + ds.data.reduce((a, b) => a + b, 0), 0),
                            isComparison: false
                        }]
                    }
                }
            }
        } catch (err) {
            console.error('Failed to fetch data:', err)
            error.value = 'Daten konnten nicht geladen werden'
            chartData.value = null
        } finally {
            loading.value = false
        }
    }

    // Watch for chart type changes to refetch data
    watch(chartType, () => {
        fetchData()
    })

    // Watch for period changes to refetch data automatically
    watch(periods, () => {
        debouncedFetch()
    }, { deep: true })

    // Watch for active section changes to refetch data
    watch(activeSection, () => {
        debouncedFetch()
    })

    // Load markers from API
    async function loadMarkers() {
        try {
            const response = await markersApi.list()
            markers.value = response.data
        } catch (err) {
            console.error('Failed to load markers:', err)
        }
    }

    // Create a new marker
    async function createMarker(data) {
        const response = await markersApi.create(data)
        markers.value.unshift(response.data)
        return response.data
    }

    // Update a marker
    async function updateMarker(id, data) {
        const response = await markersApi.update(id, data)
        const index = markers.value.findIndex(m => m.id === id)
        if (index !== -1) {
            // Use splice to ensure Vue reactivity triggers
            markers.value.splice(index, 1, response.data)
        }
        return response.data
    }

    // Delete a marker
    async function deleteMarker(id) {
        await markersApi.delete(id)
        markers.value = markers.value.filter(m => m.id !== id)
    }

    // Load saved periods from API
    async function loadSavedPeriods() {
        try {
            const response = await savedPeriodsApi.list()
            savedPeriodsConfigs.value = response.data

            // Check for active configs and load them
            const activeConfigs = response.data.filter(c => c.is_active)
            if (activeConfigs.length > 0) {
                activeSavedPeriodIds.value = activeConfigs.map(c => c.id)
                applyMultiplePeriodConfigs(activeConfigs)
            }
        } catch (err) {
            console.error('Failed to load saved periods:', err)
        }
    }

    // Apply a single period configuration to the current state
    function applyPeriodConfig(config) {
        // Convert date strings back to Date objects
        const convertedPeriods = config.periods.map((p, index) => ({
            id: index + 1,
            start: new Date(p.start),
            end: new Date(p.end),
            label: p.label,
            isComparison: p.isComparison
        }))
        periods.value = convertedPeriods
        nextPeriodId = convertedPeriods.length + 1
    }

    // Apply multiple period configurations (merge all periods)
    function applyMultiplePeriodConfigs(configs) {
        let allPeriods = []
        let id = 1

        configs.forEach((config, configIndex) => {
            config.periods.forEach(p => {
                allPeriods.push({
                    id: id++,
                    start: new Date(p.start),
                    end: new Date(p.end),
                    label: p.label,
                    isComparison: configIndex > 0 // First config is primary, rest are comparison
                })
            })
        })

        periods.value = allPeriods
        nextPeriodId = id
    }

    // Serialize a single period for storage
    function serializePeriod(period) {
        return {
            start: format(period.start, 'yyyy-MM-dd'),
            end: format(period.end, 'yyyy-MM-dd'),
            label: period.label,
            isComparison: period.isComparison
        }
    }

    // Save a single period configuration
    async function savePeriodConfig(period) {
        const name = period.label
        const serializedPeriods = [serializePeriod(period)]

        // Check if a config with this exact name exists
        const existingConfig = savedPeriodsConfigs.value.find(c => c.name === name)

        try {
            if (existingConfig) {
                // Update existing config (matched by name)
                const response = await savedPeriodsApi.update(existingConfig.id, {
                    periods: serializedPeriods
                })
                const index = savedPeriodsConfigs.value.findIndex(c => c.id === existingConfig.id)
                if (index !== -1) {
                    savedPeriodsConfigs.value.splice(index, 1, response.data)
                }
            } else {
                // Create new config (not active by default - user can activate it)
                const response = await savedPeriodsApi.create({
                    name,
                    periods: serializedPeriods,
                    is_active: false
                })
                savedPeriodsConfigs.value.unshift(response.data)
            }
            return true
        } catch (err) {
            console.error('Failed to save period config:', err)
            return false
        }
    }

    // Toggle a saved period configuration (add/remove from active list)
    async function togglePeriodConfig(id) {
        const config = savedPeriodsConfigs.value.find(c => c.id === id)
        if (!config) return

        const isCurrentlyActive = activeSavedPeriodIds.value.includes(id)

        try {
            if (isCurrentlyActive) {
                // Deactivate this config
                await savedPeriodsApi.update(id, { is_active: false })
                config.is_active = false
                activeSavedPeriodIds.value = activeSavedPeriodIds.value.filter(i => i !== id)
            } else {
                // Activate this config
                await savedPeriodsApi.update(id, { is_active: true })
                config.is_active = true
                activeSavedPeriodIds.value.push(id)
            }

            // Rebuild periods from all active configs
            const activeConfigs = savedPeriodsConfigs.value.filter(c => activeSavedPeriodIds.value.includes(c.id))
            if (activeConfigs.length > 0) {
                applyMultiplePeriodConfigs(activeConfigs)
            } else {
                // No active configs - reset to default
                periods.value = [{
                    id: 1,
                    start: startOfYear(new Date(currentYear, 0, 1)),
                    end: endOfYear(new Date(currentYear, 0, 1)),
                    label: String(currentYear),
                    isComparison: false
                }]
                nextPeriodId = 2
            }
        } catch (err) {
            console.error('Failed to toggle period config:', err)
        }
    }

    // Update saved period name
    async function updateSavedPeriodName(id, name) {
        try {
            const response = await savedPeriodsApi.update(id, { name })
            const index = savedPeriodsConfigs.value.findIndex(c => c.id === id)
            if (index !== -1) {
                savedPeriodsConfigs.value.splice(index, 1, response.data)
            }
            return response.data
        } catch (err) {
            console.error('Failed to update period name:', err)
            throw err
        }
    }

    // Delete a saved period configuration
    async function deleteSavedPeriod(id) {
        await savedPeriodsApi.delete(id)
        savedPeriodsConfigs.value = savedPeriodsConfigs.value.filter(c => c.id !== id)
        if (activeSavedPeriodId.value === id) {
            activeSavedPeriodId.value = null
        }
    }

    return {
        // State
        periods,
        selectedParams,
        chartType,
        activeSection,
        loading,
        error,
        filterOptions,
        chartData,
        summaryData,
        markers,
        savedPeriodsConfigs,
        activeSavedPeriodIds,

        // Computed
        isCompareMode,
        activeValues,
        isShowingTotals,
        sectionsWithSelections,

        // Actions
        loadFilters,
        addPeriod,
        removePeriod,
        updatePeriod,
        setYearPeriod,
        toggleParam,
        clearSection,
        clearAllSelections,
        fetchData,
        loadMarkers,
        createMarker,
        updateMarker,
        deleteMarker,
        loadSavedPeriods,
        togglePeriodConfig,
        updateSavedPeriodName,
        deleteSavedPeriod,
        savePeriodConfig
    }
}
