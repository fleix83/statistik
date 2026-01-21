import { ref, computed, watch } from 'vue'
import { analytics } from '../services/api'
import { format, startOfYear, endOfYear } from 'date-fns'

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

// Debounce timer
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

            // Bar and Pie charts always use aggregate data
            if (chartType.value === 'bar' || chartType.value === 'pie') {
                const params = {
                    section: activeSection.value,
                    start_date: formatDateForApi(primaryPeriod.start),
                    end_date: formatDateForApi(primaryPeriod.end),
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

                chartData.value = {
                    mode: 'aggregate',
                    items: items,
                    total: response.data.total
                }

                summaryData.value = {
                    total: response.data.total,
                    periods: [{
                        label: primaryPeriod.label,
                        total: response.data.total
                    }]
                }
            } else if (chartType.value === 'line') {
                // Line chart mode
                if (activeValues.value.length === 0) {
                    // Show total entries over time (default view)
                    const datasets = []
                    let allLabels = []

                    // Fetch totals for each period
                    for (const period of periods.value) {
                        const params = {
                            start_date: formatDateForApi(period.start),
                            end_date: formatDateForApi(period.end),
                            granularity: 'month',
                            filters: filtersJson
                        }
                        const response = await analytics.totals(params)

                        // Use month names for labels (strip year for comparison)
                        const labels = response.data.labels.map(l => {
                            const parts = l.split('-')
                            return parts[1] // Just month number
                        })

                        if (allLabels.length === 0) {
                            allLabels = labels
                        }

                        datasets.push({
                            label: period.label,
                            data: response.data.data,
                            total: response.data.total,
                            isComparison: period.isComparison
                        })
                    }

                    // Convert month numbers to names
                    const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
                    allLabels = allLabels.map(m => monthNames[parseInt(m) - 1] || m)

                    chartData.value = {
                        mode: 'totals',
                        granularity: 'month',
                        labels: allLabels,
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

                    chartData.value = {
                        mode: 'timeseries',
                        granularity: response.data.granularity,
                        labels: response.data.labels,
                        datasets: response.data.datasets
                    }

                    summaryData.value = {
                        total: 0,
                        periods: [{
                            label: primaryPeriod.label,
                            total: response.data.datasets.reduce((sum, ds) =>
                                sum + ds.data.reduce((a, b) => a + b, 0), 0)
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

    // Watch for active section changes to refetch data
    watch(activeSection, () => {
        debouncedFetch()
    })

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
        fetchData
    }
}
