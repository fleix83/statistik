import html2canvas from 'html2canvas'
import { jsPDF } from 'jspdf'
import { format } from 'date-fns'

export function usePdfExport() {
    async function exportToPdf(element, options = {}) {
        const {
            filename = `statistik-${format(new Date(), 'yyyy-MM-dd')}.pdf`,
            margin = [10, 10, 10, 10],
            jsPDF: pdfOptions = {},
            html2canvas: canvasOptions = {}
        } = options

        const orientation = pdfOptions.orientation || 'landscape'
        const isLandscape = orientation === 'landscape'

        // A4 dimensions in mm
        const pageWidth = isLandscape ? 297 : 210
        const pageHeight = isLandscape ? 210 : 297

        // Content area (page minus margins)
        const [marginTop, marginRight, marginBottom, marginLeft] = margin
        const contentWidth = pageWidth - marginLeft - marginRight
        const contentHeight = pageHeight - marginTop - marginBottom

        // Use high scale for print quality (300 DPI)
        // html2canvas scale determines pixel density of the captured image
        const printScale = canvasOptions.scale || 3

        // Capture element to canvas
        const canvas = await html2canvas(element, {
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            ...canvasOptions,
            scale: printScale
        })

        // Calculate scale to fit content area while maintaining aspect ratio
        const imgWidth = canvas.width
        const imgHeight = canvas.height
        const imgAspect = imgWidth / imgHeight
        const contentAspect = contentWidth / contentHeight

        let finalWidth, finalHeight
        if (imgAspect > contentAspect) {
            // Image is wider than content area - fit to width
            finalWidth = contentWidth
            finalHeight = contentWidth / imgAspect
        } else {
            // Image is taller than content area - fit to height
            finalHeight = contentHeight
            finalWidth = contentHeight * imgAspect
        }

        // Center the image on the page
        const xOffset = marginLeft + (contentWidth - finalWidth) / 2
        const yOffset = marginTop + (contentHeight - finalHeight) / 2

        // Create PDF and add the scaled image
        const pdf = new jsPDF({
            orientation,
            unit: 'mm',
            format: 'a4'
        })

        const imgData = canvas.toDataURL('image/png')
        pdf.addImage(imgData, 'PNG', xOffset, yOffset, finalWidth, finalHeight)
        pdf.save(filename)
    }

    return { exportToPdf }
}
