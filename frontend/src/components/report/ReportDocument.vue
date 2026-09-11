<script setup>
import { computed, ref } from 'vue'
import { format, parse, isValid } from 'date-fns'
import { de } from 'date-fns/locale'
import logoUrl from '@/assets/logo_wegweiser.svg'

// Renders the report as A4 portrait pages (two views per page) off-screen.
// ReportPanel captures these page elements with html2canvas to build the PDF.
const props = defineProps({
    items: { type: Array, required: true }
})

const VIEWS_PER_PAGE = 2

const pages = computed(() => {
    const out = []
    for (let i = 0; i < props.items.length; i += VIEWS_PER_PAGE) {
        out.push(props.items.slice(i, i + VIEWS_PER_PAGE))
    }
    return out
})

// --- Heading: "Statistik <Jahr>" and the overall date range of all views ---------

function periodDates(p) {
    // Newer snapshots carry ISO dates; older ones only the display range "01.09.2025 – 30.09.2025"
    let start = p.start ? parse(p.start, 'yyyy-MM-dd', new Date()) : null
    let end = p.end ? parse(p.end, 'yyyy-MM-dd', new Date()) : null
    if ((!start || !end) && p.dateRange) {
        const parts = p.dateRange.split(/\s*[–-]\s*/)
        if (parts.length === 2) {
            start = start || parse(parts[0], 'dd.MM.yyyy', new Date())
            end = end || parse(parts[1], 'dd.MM.yyyy', new Date())
        }
    }
    return { start: start && isValid(start) ? start : null, end: end && isValid(end) ? end : null }
}

const dateSpan = computed(() => {
    let min = null
    let max = null
    for (const item of props.items) {
        for (const p of item.periods || []) {
            const { start, end } = periodDates(p)
            if (start && (!min || start < min)) min = start
            if (end && (!max || end > max)) max = end
        }
    }
    return { min, max }
})

const reportTitle = computed(() => {
    const { min, max } = dateSpan.value
    if (!max) return 'Statistik'
    const y1 = min ? min.getFullYear() : max.getFullYear()
    const y2 = max.getFullYear()
    return y1 === y2 ? `Statistik ${y2}` : `Statistik ${y1}–${y2}`
})

const reportRange = computed(() => {
    const { min, max } = dateSpan.value
    if (!min || !max) return format(new Date(), 'd. MMMM yyyy', { locale: de })
    const sameYear = min.getFullYear() === max.getFullYear()
    const from = format(min, sameYear ? 'd. MMMM' : 'd. MMMM yyyy', { locale: de })
    return `${from} – ${format(max, 'd. MMMM yyyy', { locale: de })}`
})

// --- Per view helpers -----------------------------------------------------------

function periodLine(item) {
    return (item.periods || [])
        .map(p => `${p.label} · ${p.dateRange} · ${formatCount(p.count)} Anfragen`)
        .join('   |   ')
}

function swatchColor(item, label) {
    const hit = item.legend?.find(l => l.label === label)
    return hit ? hit.color : '#cbd5e1'
}

function formatCount(n) {
    return Number(n || 0).toLocaleString('de-CH')
}

// Legend tables as compact blocks placed above the chart:
// one block per period side by side; a long single-period list splits in two.
function tableBlocks(item) {
    const tables = item.tables || []
    if (tables.length === 0) return []
    if (tables.length > 1) {
        return tables.map(t => ({ periodLabel: t.periodLabel, rows: t.rows, total: t.total, showTotal: true }))
    }
    const t = tables[0]
    if (t.rows.length <= 8) {
        return [{ periodLabel: t.periodLabel, rows: t.rows, total: t.total, showTotal: true }]
    }
    const half = Math.ceil(t.rows.length / 2)
    return [
        { periodLabel: t.periodLabel, rows: t.rows.slice(0, half), total: t.total, showTotal: false },
        { periodLabel: t.periodLabel ? ' ' : null, rows: t.rows.slice(half), total: t.total, showTotal: true }
    ]
}

const pageRefs = ref([])
function setPageRef(el, index) {
    if (el) pageRefs.value[index] = el
}

defineExpose({
    pageElements: () => pageRefs.value.filter(Boolean)
})
</script>

<template>
    <div class="report-document" aria-hidden="true">
        <div
            v-for="(page, pageIndex) in pages"
            :key="pageIndex"
            class="report-page"
            :ref="el => setPageRef(el, pageIndex)"
        >
            <header class="report-heading" :class="{ compact: pageIndex > 0 }">
                <div class="report-heading-text">
                    <h1 class="report-title">{{ reportTitle }}</h1>
                    <div class="report-range">{{ reportRange }}</div>
                </div>
                <img :src="logoUrl" alt="" class="report-logo" />
            </header>

            <section
                v-for="item in page"
                :key="item.id"
                class="report-view"
            >
                <h2 class="report-view-title">
                    <span>{{ item.title }}</span>
                    <span v-if="item.subtitle" class="report-view-subtitle">{{ item.subtitle }}</span>
                </h2>
                <div class="report-view-info">{{ periodLine(item) }}</div>
                <div v-if="item.hierarchy?.length" class="report-view-filters">
                    {{ item.hierarchy.join('  ›  ') }}
                </div>

                <div class="report-tables">
                    <div
                        v-for="(block, bIndex) in tableBlocks(item)"
                        :key="bIndex"
                        class="report-table"
                    >
                        <div v-if="block.periodLabel" class="report-table-period">{{ block.periodLabel }}</div>
                        <div
                            v-for="(row, rIndex) in block.rows"
                            :key="rIndex"
                            class="report-row"
                        >
                            <span class="report-swatch" :style="{ backgroundColor: swatchColor(item, row.label) }"></span>
                            <span class="report-row-label">{{ row.label }}</span>
                            <span class="report-row-count">{{ formatCount(row.count) }}</span>
                            <span class="report-row-percent">{{ row.percent }}%</span>
                        </div>
                        <div v-if="block.showTotal" class="report-row report-row-total">
                            <span class="report-swatch report-swatch-empty"></span>
                            <span class="report-row-label">Total</span>
                            <span class="report-row-count">{{ formatCount(block.total) }}</span>
                            <span class="report-row-percent">100%</span>
                        </div>
                    </div>
                </div>

                <div class="report-chart" :class="{ narrow: item.chartType === 'pie' }">
                    <div v-if="item.stackedBaseLabel" class="report-chart-heading">{{ item.stackedBaseLabel }}</div>
                    <img :src="item.image.src" alt="" class="report-chart-image" />
                </div>
            </section>

            <footer class="report-page-footer">
                <span>Seite {{ pageIndex + 1 }} / {{ pages.length }}</span>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* Rendered off-screen (must stay visible to the renderer, so no display:none) */
.report-document {
    position: fixed;
    top: 0;
    left: -20000px;
    z-index: -1;
    pointer-events: none;
}

/* A4 portrait at 96 dpi */
.report-page {
    width: 794px;
    height: 1123px;
    box-sizing: border-box;
    padding: 52px 56px 32px;
    background: #ffffff;
    color: #111827;
    font-family: 'Din Next Rounded', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    display: flex;
    flex-direction: column;
}

/* Heading: title + date range left, logo right */
.report-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 44px;
}

.report-heading-text {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.report-title {
    margin: 0;
    font-size: 34px;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.05;
    color: #111827;
}

.report-range {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    padding-left: 4px;
}

.report-logo {
    height: 44px;
    opacity: 0.85;
}

/* Smaller heading from the second page on */
.report-heading.compact {
    margin-bottom: 28px;
    align-items: center;
}

.report-heading.compact .report-title {
    font-size: 20px;
}

.report-heading.compact .report-range {
    font-size: 12px;
    font-weight: 500;
}

.report-heading.compact .report-logo {
    height: 30px;
}

/* Two view blocks per page, no card box */
.report-view {
    flex: 1 1 0;
    min-height: 0;
    max-height: calc(50% - 20px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.report-view + .report-view {
    margin-top: 40px;
}

.report-view-title {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}

.report-view-subtitle {
    margin-left: 5px;
}

.report-view-info {
    font-size: 11px;
    color: #4b5563;
}

.report-view-filters {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
}

/* Legend tables: compact cards directly above the chart */
.report-tables {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin: 14px 0 12px;
    flex: 0 1 auto;
    min-height: 0;
}

.report-table {
    flex: 0 1 auto;
    min-width: 210px;
    max-width: 360px;
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.10);
    padding: 10px 14px;
    font-size: 10.5px;
}

.report-table-period {
    font-size: 8.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #374151;
    margin-bottom: 4px;
    min-height: 10px;
}

.report-row {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr) auto auto;
    align-items: center;
    column-gap: 10px;
    padding: 2.5px 0;
}

.report-row-total {
    border-top: 1px solid rgba(0, 0, 0, 0.10);
    margin-top: 4px;
    padding-top: 6px;
    font-weight: 600;
}

.report-swatch {
    width: 20px;
    height: 9px;
    border-radius: 2px;
}

.report-swatch-empty {
    background: transparent;
}

.report-row-label {
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.report-row-count {
    color: #111827;
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.report-row-percent {
    color: #6b7280;
    font-variant-numeric: tabular-nums;
    text-align: right;
    min-width: 36px;
}

/* Chart: left-aligned, full width for wide charts, takes the remaining height;
   pies (centered donuts with whitespace around) stay at two thirds */
.report-chart {
    flex: 1 1 0;
    min-height: 60px;
    max-width: 100%;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}

.report-chart.narrow {
    max-width: 68%;
}

.report-chart-heading {
    font-size: 11px;
    font-weight: 600;
    color: #111827;
}

.report-chart-image {
    flex: 1 1 0;
    min-height: 0;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    object-position: left top;
    display: block;
}

.report-page-footer {
    margin-top: auto;
    display: flex;
    justify-content: flex-end;
    font-size: 9px;
    color: #9ca3af;
}
</style>
