<script setup>
import { computed, ref } from 'vue'
import { format, parse, isValid } from 'date-fns'
import { de } from 'date-fns/locale'

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
                <svg class="report-logo" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 452.68 201.61">
                <path d="M49.31,148.61C22.12,148.61,0,126.49,0,99.31s22.12-49.31,49.31-49.31c14.11,0,26.13,4.37,34.78,12.64,1.55,1.49,2.45,3.5,2.49,5.66s-.75,4.21-2.24,5.76c-1.53,1.59-3.59,2.48-5.81,2.48-2.08,0-4.07-.79-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.31,0-32.49,15.39-32.49,32.95s14.88,32.95,32.49,32.95c15.05,0,25.99-8.62,29.43-23.12h-17.29c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.62c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.06-12.21,34.89-8.5,8.9-20.47,13.59-34.61,13.59h0Z"/>
                <path d="M137.13,201.61c-32.13,0-57.19-30.89-47.02-64.54,6.15-20.37,25.25-33.93,46.53-34.08,14.32-.1,26.52,4.27,35.27,12.64,1.56,1.49,2.45,3.5,2.49,5.66.05,2.16-.75,4.2-2.24,5.76-1.52,1.59-3.59,2.48-5.8,2.48-2.08,0-4.07-.79-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.32,0-32.49,15.39-32.49,32.95s14.88,32.96,32.49,32.96c15.04,0,25.98-8.62,29.43-23.12h-17.3c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.61c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.06-12.21,34.89-8.49,8.9-20.46,13.59-34.6,13.59h0Z"/>
                <path d="M137.13,98.61c-27.19,0-49.32-22.12-49.32-49.31S109.93,0,137.13,0c14.11,0,26.13,4.37,34.78,12.64,1.56,1.49,2.45,3.5,2.49,5.66.05,2.16-.75,4.2-2.24,5.76-1.52,1.59-3.59,2.48-5.8,2.48-2.08,0-4.07-.78-5.6-2.22-5.65-5.28-13.59-7.96-23.62-7.96-17.32,0-32.49,15.39-32.49,32.95s14.88,32.95,32.49,32.95c15.04,0,25.98-8.62,29.43-23.12h-17.3c-4.29,0-7.77-3.59-7.77-8s3.49-8,7.77-8h30.61c1.88,0,3.4,2.09,3.66,3.58.21,1.15.36,2.53.4,3.42.59,13.67-3.74,26.05-12.21,34.89-8.49,8.9-20.46,13.59-34.6,13.59h0Z"/>
                <g>
                <path d="M241.45,144.99l-7.59-26.86h-.08l-7.34,25.58c-.54,1.84-1.3,2.59-2.59,2.59s-2.1-.75-2.59-2.59l-7.99-31.25c-.11-.43-.17-.86-.17-1.13,0-1.24.86-2.27,2.32-2.27,1.13,0,1.99.64,2.32,2.05l6.31,26.01h.12l7.35-26.34c.44-1.6,1.27-2.38,2.54-2.38s2.04.77,2.49,2.38l7.51,27.34h.1l6.4-27.37c.34-1.48,1.25-2.15,2.44-2.15,1.53,0,2.44,1.08,2.44,2.38,0,.29-.06.74-.17,1.19l-8.39,32.81c-.51,1.93-1.36,2.72-2.72,2.72-1.36.02-2.15-.78-2.73-2.71Z"/>
                <path d="M256.67,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.42,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM273.14,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.09-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                <path d="M298.64,122.14v-.67c0-1.57,1.07-2.52,2.5-2.52s2.5.95,2.5,2.52v23.16c0,7.98-4.53,12-12.45,12-3.75,0-7.33-1.45-9.05-3.18-.47-.44-.71-1-.71-1.51,0-1.23.95-2.18,2.26-2.18.71,0,1.37.28,2.03.73,1.61,1.11,3.58,1.79,5.66,1.79,4.58,0,7.26-2.12,7.26-7.25v-2.9h-.05c-1.43,2.23-3.81,3.52-7.97,3.52-4.76,0-8.16-2.45-9.59-6.65-.66-1.9-.95-3.91-.95-6.87s.3-4.97.95-6.87c1.43-4.18,4.83-6.65,9.59-6.65,4.22,0,6.61,1.51,7.97,3.52h.05ZM298.49,137.53c.44-1.27.61-2.76.61-4.97s-.17-3.65-.61-4.92c-.94-2.76-2.76-4.14-5.64-4.14s-4.7,1.38-5.63,4.14c-.44,1.27-.61,2.76-.61,4.92s.17,3.7.61,4.97c.94,2.76,2.76,4.14,5.63,4.14s4.7-1.38,5.64-4.14Z"/>
                <path d="M331.81,143.94l-5.6-17.81h-.11l-5.66,17.81c-.56,1.7-1.47,2.44-2.83,2.44s-2.26-.74-2.83-2.44l-7.19-21.95c-.11-.34-.17-.74-.17-1.02,0-1.3.9-2.26,2.32-2.26,1.19,0,1.92.68,2.32,2.04l5.71,18.38h.11l5.66-18.1c.51-1.64,1.3-2.32,2.6-2.32s2.09.68,2.6,2.32l5.77,18.1h.11l5.6-18.38c.4-1.36,1.14-2.04,2.32-2.04,1.36,0,2.26.96,2.26,2.26,0,.29-.05.68-.16,1.02l-7.19,21.95c-.56,1.7-1.47,2.44-2.77,2.44-1.43,0-2.38-.74-2.9-2.44Z"/>
                <path d="M347.73,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.42,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM364.19,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.09-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                <path d="M373.23,111.45c0-1.71,1.38-3.04,3.04-3.04s3.04,1.33,3.04,3.04-1.38,3.04-3.04,3.04-3.04-1.38-3.04-3.04ZM373.64,121.15c0-1.67,1.07-2.68,2.5-2.68s2.5,1.01,2.5,2.68v23.76c0,1.67-1.07,2.68-2.5,2.68s-2.5-1.01-2.5-2.68v-23.76Z"/>
                <path d="M383.99,143.94c-.72-.5-1.1-1.16-1.1-1.93,0-1.1.88-2.1,2.15-2.1.44,0,.88.11,1.55.5,2.15,1.27,4.42,2.32,7.4,2.32,3.81,0,5.8-1.66,5.8-3.98s-.99-3.31-5.19-3.76l-2.76-.28c-5.2-.55-7.9-3.2-7.9-7.46,0-5.03,3.59-8.07,9.78-8.07,3.1,0,5.86.77,7.9,1.93.94.5,1.38,1.11,1.38,1.93,0,1.11-.88,1.99-1.99,1.99-.44,0-.94-.11-1.66-.44-1.77-.83-3.76-1.38-5.86-1.38-3.31,0-5.03,1.49-5.03,3.65s1.21,3.21,5.19,3.59l2.76.28c5.52.61,7.96,3.2,7.96,7.51,0,5.19-3.76,8.62-10.77,8.62-4.03.01-7.4-1.37-9.61-2.92Z"/>
                <path d="M408.76,139.39c-.55-1.66-.88-3.54-.88-6.69s.28-5.03.83-6.69c1.49-4.59,5.3-7.13,10.33-7.13s8.95,2.54,10.39,7.07c.61,1.77.88,3.81.88,6.41,0,1.05-.72,1.77-1.82,1.77h-15.53c-.18,0-.33.15-.33.33,0,1.27.11,2.15.44,3.2.99,3.04,3.43,4.59,6.69,4.59,2.6,0,4.37-.77,6.35-2.26.38-.3.73-.5,1.1-.6,1.29-.33,2.59.85,2.41,2.17-.07.53-.33.99-.7,1.36-2.27,2.1-5.41,3.65-9.5,3.65-5.47.01-9.17-2.53-10.66-7.18ZM425.22,131.13c.18,0,.33-.15.33-.33,0-1.22-.11-2.15-.39-2.98-.88-2.65-3.1-4.14-6.08-4.14s-5.19,1.49-6.08,4.14c-.28.83-.39,1.77-.39,2.98,0,.18.15.33.33.33h12.28Z"/>
                <path d="M434.64,143.96v-22.05c0-1.55,1.07-2.49,2.5-2.49s2.5.94,2.5,2.49v1.22h.06c1.19-2.38,3.81-4.03,7.32-4.03,1.96,0,3.28.44,4.22.94,1.01.5,1.43,1.21,1.43,1.99,0,1.33-1.01,2.32-2.5,2.32-.36,0-.83-.11-1.25-.28-1.07-.39-2.08-.66-3.28-.66-4.29,0-6.01,3.32-6.01,7.74v12.82c0,1.55-1.07,2.49-2.5,2.49s-2.5-.95-2.5-2.5Z"/>
                </g>
                </svg>
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

                <div class="report-chart">
                    <div v-if="item.stackedBaseLabel" class="report-chart-heading">{{ item.stackedBaseLabel }}</div>
                    <img
                        :src="item.image.src"
                        alt=""
                        class="report-chart-image"
                        :style="{ aspectRatio: `${item.image.width} / ${item.image.height}` }"
                    />
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
    width: auto;
    fill: #000000;
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
    display: block;
}

.report-chart-heading {
    font-size: 11px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}

/* The image box itself keeps the image's aspect ratio (set inline), because the
   PDF renderer ignores object-fit and would stretch a mismatched box */
.report-chart-image {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 100%;
}

.report-chart-heading + .report-chart-image {
    max-height: calc(100% - 20px);
}

.report-page-footer {
    margin-top: auto;
    display: flex;
    justify-content: flex-end;
    font-size: 9px;
    color: #9ca3af;
}
</style>
