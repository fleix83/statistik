import html2canvas from 'html2canvas'
import { jsPDF } from 'jspdf'
import { format } from 'date-fns'

// Turns rendered A4 page elements (see ReportDocument.vue) into a multi-page PDF.
// Each page is rasterised with html2canvas and placed full-bleed on an A4 portrait page.
export function useReportExport() {
    async function waitForAssets(root) {
        if (document.fonts?.ready) await document.fonts.ready
        const images = [...root.querySelectorAll('img')]
        await Promise.all(images.map(img => (
            img.complete ? null : new Promise(resolve => { img.onload = img.onerror = resolve })
        )))
    }

    async function exportReport(pageElements, options = {}) {
        const pages = (pageElements || []).filter(Boolean)
        if (pages.length === 0) throw new Error('report-empty')

        const {
            filename = `report-${format(new Date(), 'yyyy-MM-dd')}.pdf`,
            scale = 2
        } = options

        await waitForAssets(pages[0].parentElement || document.body)

        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })

        for (let i = 0; i < pages.length; i++) {
            const canvas = await html2canvas(pages[i], {
                scale,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            })
            if (i > 0) pdf.addPage()
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, 210, 297)
        }

        pdf.save(filename)
    }

    return { exportReport }
}
