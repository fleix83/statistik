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

// Selection hierarchy tracks groups in order of selection
// Each entry: { group: string, selections: { section: [values] } }
// First group = base (visualized), subsequent groups = filters (AND logic)
const selectionHierarchy = ref([])

// Ordered selections for subset drilling - tracks the order in which values were selected
// Each entry: { section: string, value: string }
const orderedSelections = ref([])

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

    // Toggle a parameter value with group tracking for hierarchy
    // behavior: 'standard' (default) or 'subtract_only' (always creates new group)
    function toggleParam(section, value, group, behavior = 'standard') {
        const current = selectedParams.value[section]
        const index = current.indexOf(value)

        // Use section as fallback group if no group provided
        const effectiveGroup = group || section

        // For subtract_only behavior, always use a unique group key to force subtraction
        // This ensures it never joins an existing group (always subtracts)
        const hierarchyGroup = behavior === 'subtract_only'
            ? `${effectiveGroup}_${value}`  // Unique key per value
            : effectiveGroup

        if (index === -1) {
            // Adding a value
            current.push(value)

            // Track in ordered selections for subset drilling (include param_group and behavior)
            orderedSelections.value.push({ section, value, group: effectiveGroup, behavior })

            // Track in hierarchy
            // For subtract_only: always create new group (never join existing)
            const hierarchyEntry = behavior === 'subtract_only'
                ? null  // Force new group
                : selectionHierarchy.value.find(h => h.group === hierarchyGroup)

            if (hierarchyEntry) {
                // Group already in hierarchy - add to existing level (OR logic)
                if (!hierarchyEntry.selections[section]) {
                    hierarchyEntry.selections[section] = []
                }
                hierarchyEntry.selections[section].push(value)
            } else {
                // New group - add new level to hierarchy (AND logic)
                selectionHierarchy.value.push({
                    group: hierarchyGroup,
                    selections: { [section]: [value] },
                    behavior: behavior
                })

                // First group determines the active section for visualization
                if (selectionHierarchy.value.length === 1) {
                    activeSection.value = section
                }
            }
        } else {
            // Removing a value
            current.splice(index, 1)

            // Remove from ordered selections
            const orderedIndex = orderedSelections.value.findIndex(
                s => s.section === section && s.value === value
            )
            if (orderedIndex !== -1) {
                orderedSelections.value.splice(orderedIndex, 1)
            }

            // Remove from hierarchy (use hierarchyGroup which may be unique for subtract_only)
            const hierarchyEntry = selectionHierarchy.value.find(h => h.group === hierarchyGroup)
            if (hierarchyEntry && hierarchyEntry.selections[section]) {
                const valIndex = hierarchyEntry.selections[section].indexOf(value)
                if (valIndex !== -1) {
                    hierarchyEntry.selections[section].splice(valIndex, 1)
                }
                // Remove section if empty
                if (hierarchyEntry.selections[section].length === 0) {
                    delete hierarchyEntry.selections[section]
                }
                // Remove group from hierarchy if no selections left
                if (Object.keys(hierarchyEntry.selections).length === 0) {
                    selectionHierarchy.value = selectionHierarchy.value.filter(h => h.group !== hierarchyGroup)
                }
            }
        }

        // Auto-fetch with debounce
        debouncedFetch()
    }

    // Clear all selections for a section
    function clearSection(section) {
        selectedParams.value[section] = []

        // Clear from ordered selections
        orderedSelections.value = orderedSelections.value.filter(s => s.section !== section)

        // Clear from hierarchy too
        selectionHierarchy.value.forEach(h => {
            if (h.selections[section]) {
                delete h.selections[section]
            }
        })
        // Remove empty groups
        selectionHierarchy.value = selectionHierarchy.value.filter(
            h => Object.keys(h.selections).length > 0
        )

        debouncedFetch()
    }

    // Clear all selections
    function clearAllSelections() {
        for (const section in selectedParams.value) {
            selectedParams.value[section] = []
        }
        // Clear hierarchy completely
        selectionHierarchy.value = []
        // Clear ordered selections
        orderedSelections.value = []

        debouncedFetch()
    }

    // Format date for API
    function formatDateForApi(date) {
        return format(date, 'yyyy-MM-dd')
    }

    // Build filters object from selection hierarchy (for API filtering)
    // Uses hierarchy for proper OR/AND logic:
    // - Values within same group: OR (add up)
    // - Values from different groups: AND (filter/subtract)
    // @param excludeSection - section to exclude from filters (used in timeseries to avoid filtering the visualized section)
    function buildFilters(excludeSection = null) {
        // If we have a hierarchy, use it for structured filtering
        if (selectionHierarchy.value.length > 0) {
            // Filter out the excluded section from hierarchy
            const filteredHierarchy = selectionHierarchy.value
                .map(h => {
                    if (!excludeSection) return { group: h.group, filters: h.selections }

                    // Remove excluded section from this hierarchy entry's selections
                    const filteredSelections = {}
                    for (const [section, values] of Object.entries(h.selections)) {
                        if (section !== excludeSection) {
                            filteredSelections[section] = values
                        }
                    }
                    return { group: h.group, filters: filteredSelections }
                })
                .filter(h => Object.keys(h.filters).length > 0) // Remove empty groups

            if (filteredHierarchy.length > 0) {
                return {
                    hierarchy: filteredHierarchy
                }
            }
            return {}
        }

        // Fallback: flat filters for options without groups
        const filters = {}
        for (const [section, values] of Object.entries(selectedParams.value)) {
            if (values.length > 0 && section !== excludeSection) {
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
        console.log('=== FETCH DATA CALLED ===')
        console.log('orderedSelections at start:', JSON.stringify(orderedSelections.value))

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
                // Check if stacked chart should use subset mode
                // For stacked charts: use ALL ordered selections (cross-section subset drilling)
                // For bar/pie: use only active section selections
                let allSelections = []

                if (chartType.value === 'stacked') {
                    // Stacked: use all selections across all sections
                    allSelections = [...orderedSelections.value]

                    // Fallback: rebuild from all selectedParams if orderedSelections is incomplete
                    const totalSelectedCount = Object.values(selectedParams.value)
                        .reduce((sum, vals) => sum + vals.length, 0)
                    if (allSelections.length < totalSelectedCount) {
                        allSelections = []
                        for (const [section, values] of Object.entries(selectedParams.value)) {
                            for (const value of values) {
                                allSelections.push({ section, value, group: section })
                            }
                        }
                    }
                } else {
                    // Bar/Pie: use only active section selections
                    allSelections = orderedSelections.value.filter(
                        s => s.section === activeSection.value
                    )

                    // Fallback: rebuild from selectedParams if needed
                    const selectedValues = selectedParams.value[activeSection.value] || []
                    if (allSelections.length < selectedValues.length) {
                        allSelections = selectedValues.map(value => ({
                            section: activeSection.value,
                            value,
                            group: activeSection.value
                        }))
                    }
                }

                // Check for subset mode: multiple values from different param_groups
                const selectionsByGroup = {}
                const groupOrder = []
                for (const sel of allSelections) {
                    const group = sel.group || sel.section
                    if (!selectionsByGroup[group]) {
                        selectionsByGroup[group] = []
                        groupOrder.push(group)
                    }
                    selectionsByGroup[group].push(sel.value)
                }

                const useStackedSubsetMode = chartType.value === 'stacked' && groupOrder.length > 1

                if (useStackedSubsetMode) {
                    // STACKED SUBSET MODE: Only show leaf values (last group)
                    // Base values are excluded - they represent the combined total

                    const baseGroup = groupOrder[0]
                    const filterGroups = groupOrder.slice(1)

                    // Last group contains the leaf values to display
                    const lastGroup = filterGroups[filterGroups.length - 1]

                    // Build a map of group -> section for lookup
                    // (we need to know which section each selection belongs to)
                    const groupToSelections = {}
                    for (const sel of allSelections) {
                        const group = sel.group || sel.section
                        if (!groupToSelections[group]) {
                            groupToSelections[group] = []
                        }
                        groupToSelections[group].push({ section: sel.section, value: sel.value })
                    }

                    // Get base values and their section
                    const baseSelections = groupToSelections[baseGroup] || []
                    const baseValues = baseSelections.map(s => s.value)

                    // Get intermediate (prefix) selections
                    const intermediateGroups = filterGroups.slice(0, -1)
                    const prefixSelections = intermediateGroups.flatMap(g => groupToSelections[g] || [])

                    // Get leaf selections (last group)
                    const leafSelections = groupToSelections[lastGroup] || []
                    const leafValues = leafSelections.map(s => s.value)

                    console.log('STACKED SUBSET MODE')
                    console.log('baseGroup:', baseGroup, 'baseSelections:', baseSelections)
                    console.log('prefixSelections:', prefixSelections)
                    console.log('lastGroup:', lastGroup, 'leafSelections:', leafSelections)

                    // Fetch data for each leaf value with intersection filter
                    const stackedDatasets = []

                    for (const leafSel of leafSelections) {
                        const leafData = []

                        for (const period of periods.value) {
                            // Build intersection filter grouped by section
                            // Combine base + prefix + this leaf value
                            const allFilterSelections = [
                                ...baseSelections,
                                ...prefixSelections,
                                leafSel
                            ]

                            // Group by section for the intersection filter
                            const intersectionBySection = {}
                            for (const sel of allFilterSelections) {
                                if (!intersectionBySection[sel.section]) {
                                    intersectionBySection[sel.section] = []
                                }
                                intersectionBySection[sel.section].push(sel.value)
                            }

                            const intersectionFilter = {
                                intersection: intersectionBySection
                            }

                            console.log(`Fetching leaf ${leafSel.value}:`, JSON.stringify(intersectionFilter))

                            const params = {
                                start_date: formatDateForApi(period.start),
                                end_date: formatDateForApi(period.end),
                                filters: JSON.stringify(intersectionFilter)
                            }

                            const response = await analytics.totals(params)
                            leafData.push(response.data.total)
                        }

                        stackedDatasets.push({
                            label: leafSel.value,
                            data: leafData
                        })
                    }

                    // Calculate total (sum of leaf values for primary period)
                    const primaryPeriodTotal = stackedDatasets.reduce((sum, ds) => sum + (ds.data[0] || 0), 0)

                    chartData.value = {
                        mode: 'stacked',
                        subsetMode: true,
                        labels: periods.value.map(p => p.label),
                        datasets: stackedDatasets,
                        total: primaryPeriodTotal,
                        baseLabel: baseValues.join(' / ')
                    }

                    summaryData.value = {
                        total: primaryPeriodTotal,
                        periods: periods.value.map((p, i) => ({
                            label: p.label,
                            total: stackedDatasets.reduce((sum, ds) => sum + (ds.data[i] || 0), 0),
                            isComparison: p.isComparison
                        }))
                    }
                } else {
                    // Standard aggregate mode for bar/pie/stacked without subset drilling
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
                    const allDatasets = []
                    let displayLabels = []
                    let rawLabels = []
                    let granularityUsed = 'month'

                    // Get ALL ordered selections for subset drilling (cross-section support)
                    let allSelections = [...orderedSelections.value]

                    // Fallback: if orderedSelections is incomplete, rebuild from all selectedParams
                    const totalSelectedCount = Object.values(selectedParams.value)
                        .reduce((sum, vals) => sum + vals.length, 0)
                    if (allSelections.length < totalSelectedCount) {
                        console.log('Rebuilding allSelections from selectedParams')
                        allSelections = []
                        for (const [section, values] of Object.entries(selectedParams.value)) {
                            for (const value of values) {
                                allSelections.push({ section, value, group: section })
                            }
                        }
                    }

                    // DEBUG: Log selection state
                    console.log('=== SUBSET MODE DEBUG ===')
                    console.log('activeSection:', activeSection.value)
                    console.log('totalSelectedCount:', totalSelectedCount)
                    console.log('orderedSelections:', JSON.stringify(orderedSelections.value))
                    console.log('allSelections:', JSON.stringify(allSelections))
                    console.log('allSelections.length:', allSelections.length)

                    // Check if we should use subset drilling mode:
                    // Multiple values selected (can be cross-section)
                    const useSubsetMode = allSelections.length > 1
                    console.log('useSubsetMode:', useSubsetMode)

                    if (useSubsetMode) {
                        // SUBSET DRILLING MODE:
                        // - First group = BASE (highest count)
                        // - Subsequent groups = SUBTRACT from base (AND logic)
                        // - Within same group = PARALLEL (values add together)
                        //
                        // Example: Mann + unter55 + über55
                        // - Mann (gender) = base
                        // - unter55, über55 (age) = subtract from base, but parallel to each other
                        // - Lines: Mann, Mann∩unter55, Mann∩über55
                        // - Total: (Mann∩unter55) + (Mann∩über55)

                        // Group selections by param_group in order of first appearance
                        const selectionsByGroup = {}
                        const groupOrder = []
                        for (const sel of allSelections) {
                            const group = sel.group || sel.section
                            if (!selectionsByGroup[group]) {
                                selectionsByGroup[group] = []
                                groupOrder.push(group)
                            }
                            selectionsByGroup[group].push(sel.value)
                        }

                        console.log('selectionsByGroup:', JSON.stringify(selectionsByGroup))
                        console.log('groupOrder:', JSON.stringify(groupOrder))

                        // Build a map of group -> selections (with section info for cross-section filters)
                        const groupToSelections = {}
                        for (const sel of allSelections) {
                            const group = sel.group || sel.section
                            if (!groupToSelections[group]) {
                                groupToSelections[group] = []
                            }
                            groupToSelections[group].push({ section: sel.section, value: sel.value })
                        }

                        // First group = base, subsequent groups = filters that subtract
                        // Within each group, values are parallel (shown separately, totals sum)
                        const baseGroup = groupOrder[0]
                        const baseSelections = groupToSelections[baseGroup] || []
                        const baseValues = baseSelections.map(s => s.value)
                        const filterGroups = groupOrder.slice(1)

                        console.log('baseGroup:', baseGroup, 'baseSelections:', JSON.stringify(baseSelections))
                        console.log('filterGroups:', JSON.stringify(filterGroups))

                        for (let periodIndex = 0; periodIndex < periods.value.length; periodIndex++) {
                            const period = periods.value[periodIndex]

                            // Build lines:
                            // 1. Base line: first group's values (if multiple, they're parallel - show each)
                            // 2. For subsequent groups: each value is a filter that subtracts from base
                            //    Values within the same group are parallel (shown separately)
                            //
                            // The LAST group contains the "leaf" values - their totals sum up
                            // All previous groups form the "filter prefix"

                            let linesToCreate = []

                            if (filterGroups.length === 0) {
                                // Only one group: show parallel lines for each base value
                                linesToCreate = baseSelections.map((sel, i) => ({
                                    type: i === 0 ? 'base' : 'parallel',
                                    selections: [sel],  // Track section info
                                    displayValue: sel.value,
                                    isLeaf: true  // These are the leaf values to sum
                                }))
                            } else {
                                // Multiple groups:
                                // - First show base (first group values)
                                // - Then for each value in the LAST group, show (prefix AND value)
                                // - Prefix = all values from groups between base and last, AND'd together

                                // Build prefix from intermediate groups (between first and last)
                                const intermediateGroups = filterGroups.slice(0, -1)
                                const prefixSelections = intermediateGroups.flatMap(g => groupToSelections[g] || [])

                                // Last group selections are the parallel "leaves"
                                const lastGroup = filterGroups[filterGroups.length - 1]
                                const leafSelections = groupToSelections[lastGroup] || []

                                // Base line (first group, potentially with prefix)
                                const baseFilterSelections = [...baseSelections, ...prefixSelections]
                                linesToCreate.push({
                                    type: 'base',
                                    selections: baseFilterSelections,
                                    displayValue: baseValues.join(' / '),
                                    isLeaf: false
                                })

                                // Leaf lines (base + prefix + each last group value)
                                for (const leafSel of leafSelections) {
                                    linesToCreate.push({
                                        type: 'subset',
                                        selections: [...baseFilterSelections, leafSel],
                                        displayValue: leafSel.value,
                                        isLeaf: true  // These sum up for total
                                    })
                                }
                            }

                            console.log('linesToCreate:', JSON.stringify(linesToCreate.map(l => ({ type: l.type, displayValue: l.displayValue, isLeaf: l.isLeaf }))))

                            for (let lineIndex = 0; lineIndex < linesToCreate.length; lineIndex++) {
                                const lineConfig = linesToCreate[lineIndex]
                                const selections = lineConfig.selections
                                const displayValue = lineConfig.displayValue

                                // Build intersection filter grouped by section (for cross-section support)
                                const intersectionBySection = {}
                                for (const sel of selections) {
                                    if (!intersectionBySection[sel.section]) {
                                        intersectionBySection[sel.section] = []
                                    }
                                    intersectionBySection[sel.section].push(sel.value)
                                }

                                const intersectionFilter = {
                                    intersection: intersectionBySection
                                }

                                console.log(`Fetching line ${lineIndex}: displayValue=${displayValue}, filter=${JSON.stringify(intersectionFilter)}`)

                                const params = {
                                    start_date: formatDateForApi(period.start),
                                    end_date: formatDateForApi(period.end),
                                    granularity: 'auto',
                                    filters: JSON.stringify(intersectionFilter)
                                }

                                const response = await analytics.totals(params)
                                granularityUsed = response.data.granularity

                                // Format labels (only for first dataset)
                                if (allDatasets.length === 0) {
                                    rawLabels = response.data.labels.map(l => {
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

                                    displayLabels = response.data.labels.map(l => {
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
                                }

                                // Build label for this line
                                const fullLabel = periods.value.length > 1
                                    ? `${displayValue} (${period.label})`
                                    : displayValue

                                console.log(`Dataset ${allDatasets.length}: ${fullLabel}, total=${response.data.total}, lineIndex=${lineIndex}`)

                                allDatasets.push({
                                    label: fullLabel,
                                    data: response.data.data,
                                    valueLabel: displayValue,
                                    filterValues: filterValues,
                                    valueIndex: lineIndex,
                                    periodIndex: periodIndex,
                                    periodLabel: period.label,
                                    total: response.data.total,
                                    isComparison: periodIndex > 0,
                                    isBase: lineConfig.type === 'base',
                                    isSubset: lineConfig.type === 'subset' || lineConfig.type === 'parallel',
                                    isLeaf: lineConfig.isLeaf  // Leaf values sum up for total
                                })
                            }
                        }
                        console.log('Total datasets created:', allDatasets.length)
                        console.log('Datasets:', allDatasets.map(d => ({ label: d.label, total: d.total })))
                    } else {
                        // STANDARD MODE: Show each value as a separate line
                        // IMPORTANT: Exclude active section from filters so each value shows its own count
                        // (not filtered by other values from the same section)
                        const standardFilters = buildFilters(activeSection.value)
                        const standardFiltersJson = Object.keys(standardFilters).length > 0
                            ? JSON.stringify(standardFilters)
                            : undefined

                        console.log('STANDARD MODE: excluding section', activeSection.value, 'from filters')
                        console.log('standardFilters:', JSON.stringify(standardFilters))

                        for (let periodIndex = 0; periodIndex < periods.value.length; periodIndex++) {
                            const period = periods.value[periodIndex]
                            const params = {
                                section: activeSection.value,
                                values: activeValues.value.join(','),
                                start_date: formatDateForApi(period.start),
                                end_date: formatDateForApi(period.end),
                                granularity: 'auto',
                                filters: standardFiltersJson
                            }

                            const response = await analytics.timeseries(params)
                            granularityUsed = response.data.granularity

                            // Use labels from first period
                            if (periodIndex === 0) {
                                rawLabels = response.data.labels.map(l => {
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

                                displayLabels = response.data.labels.map(l => {
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
                            }

                            // Add datasets with period info
                            response.data.datasets.forEach((ds, valueIndex) => {
                                allDatasets.push({
                                    label: periods.value.length > 1
                                        ? `${ds.label} (${period.label})`
                                        : ds.label,
                                    data: ds.data,
                                    valueLabel: ds.label,
                                    valueIndex: valueIndex,
                                    periodIndex: periodIndex,
                                    periodLabel: period.label,
                                    isComparison: periodIndex > 0
                                })
                            })
                        }
                    }

                    console.log('=== CHART DATA ASSIGNMENT ===')
                    console.log('mode: timeseries, subsetMode:', useSubsetMode)
                    console.log('allDatasets.length:', allDatasets.length)
                    console.log('numValues:', useSubsetMode ? allSelections.length : activeValues.value.length)

                    chartData.value = {
                        mode: 'timeseries',
                        subsetMode: useSubsetMode,
                        granularity: granularityUsed,
                        labels: displayLabels,
                        rawLabels: rawLabels,
                        datasets: allDatasets,
                        numPeriods: periods.value.length,
                        numValues: useSubsetMode ? allSelections.length : activeValues.value.length
                    }

                    // Calculate totals based on leaf/base logic:
                    // - Leaf datasets (last group's parallel values): sum their totals
                    // - If no leaf datasets, use the base total
                    const periodSummaries = periods.value.map((period, i) => {
                            const periodDatasets = allDatasets.filter(ds => ds.periodIndex === i)

                            let periodTotal
                            if (useSubsetMode) {
                                // Sum only leaf datasets (the most specific/drilled-down values)
                                const leafDatasets = periodDatasets.filter(ds => ds.isLeaf)
                                console.log(`Period ${i}: leafDatasets =`, leafDatasets.map(d => ({ label: d.label, total: d.total })))
                                if (leafDatasets.length > 0) {
                                    // Sum all leaf datasets
                                    periodTotal = leafDatasets.reduce((sum, ds) => sum + (ds.total || 0), 0)
                                } else {
                                    // Fallback: use the base total
                                    const baseDataset = periodDatasets.find(ds => ds.isBase)
                                    periodTotal = baseDataset?.total || 0
                                }
                            } else {
                                periodTotal = periodDatasets.reduce((sum, ds) =>
                                    sum + (ds.total || ds.data.reduce((a, b) => a + b, 0)), 0)
                            }

                            return {
                                label: period.label,
                                total: periodTotal,
                                isComparison: i > 0
                            }
                        })

                    // Use primary period's total (first period, not comparison)
                    const primaryPeriodSummary = periodSummaries.find(p => !p.isComparison) || periodSummaries[0]
                    summaryData.value = {
                        total: primaryPeriodSummary?.total || 0,
                        periods: periodSummaries
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
        // Remove from active list if present
        activeSavedPeriodIds.value = activeSavedPeriodIds.value.filter(i => i !== id)
    }

    return {
        // State
        periods,
        selectedParams,
        selectionHierarchy,
        orderedSelections,
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
