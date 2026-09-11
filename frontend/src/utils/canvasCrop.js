// Crop helpers for chart snapshots used by the report.

// Crop a canvas to its non-white content (plus padding). Used for pie charts,
// whose capture is a wide strip with the donuts floating in whitespace.
export function cropCanvasToContent(canvas, padding = 24) {
    const ctx = canvas.getContext('2d')
    const { width, height } = canvas
    const data = ctx.getImageData(0, 0, width, height).data
    let minX = width, minY = height, maxX = -1, maxY = -1
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = (y * width + x) * 4
            if (data[i + 3] > 0 && (data[i] < 245 || data[i + 1] < 245 || data[i + 2] < 245)) {
                if (x < minX) minX = x
                if (x > maxX) maxX = x
                if (y < minY) minY = y
                if (y > maxY) maxY = y
            }
        }
    }
    if (maxX < 0) return canvas
    const sx = Math.max(0, minX - padding)
    const sy = Math.max(0, minY - padding)
    const sw = Math.min(width, maxX + padding + 1) - sx
    const sh = Math.min(height, maxY + padding + 1) - sy
    const out = document.createElement('canvas')
    out.width = sw
    out.height = sh
    out.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, sw, sh)
    return out
}

// Crop an image given as data URL; resolves to { src, width, height }.
export function cropImageDataUrl(src, padding = 24) {
    return new Promise((resolve, reject) => {
        const img = new Image()
        img.onload = () => {
            const canvas = document.createElement('canvas')
            canvas.width = img.naturalWidth
            canvas.height = img.naturalHeight
            canvas.getContext('2d').drawImage(img, 0, 0)
            const cropped = cropCanvasToContent(canvas, padding)
            resolve({ src: cropped.toDataURL('image/png'), width: cropped.width, height: cropped.height })
        }
        img.onerror = reject
        img.src = src
    })
}
