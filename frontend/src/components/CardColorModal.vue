<script setup>
import { ref, watch, computed, onMounted } from 'vue'

const props = defineProps({
    visible: Boolean,
    cardKey: String,
    colors: Object,
    anchorRect: Object,
    showImageOpacity: Boolean
})

const emit = defineEmits(['save', 'update', 'close', 'discard'])

// CSS defaults per card, per field
const defaults = {
    person: {
        bg_color: 'rgba(159, 201, 253, 1)',
        border_color: 'rgba(96, 165, 250, 0.33)',
        swatch_default: 'rgba(255, 255, 255, 1)',
        swatch_hover: 'rgba(191, 219, 254, 1)',
        swatch_checked: 'rgba(103, 171, 255, 1)'
    },
    thema: {
        bg_color: 'rgba(255, 135, 135, 1)',
        border_color: 'rgba(255, 120, 120, 0.33)',
        swatch_default: 'rgba(255, 255, 255, 1)',
        swatch_hover: 'rgba(255, 140, 140, 0.9)',
        swatch_checked: 'rgba(255, 83, 83, 0.95)'
    },
    zeitfenster: {
        bg_color: 'rgba(91, 215, 151, 1)',
        border_color: 'rgba(91, 219, 166, 0.33)',
        swatch_default: 'rgba(255, 255, 255, 1)',
        swatch_hover: 'rgba(91, 219, 166, 0.7)',
        swatch_checked: 'rgba(52, 201, 125, 1)'
    },
    referenz: {
        bg_color: 'rgba(195, 188, 155, 0.33)',
        border_color: 'rgba(217, 210, 177, 0.33)',
        swatch_default: 'rgba(255, 255, 255, 1)',
        swatch_hover: 'rgba(217, 210, 177, 0.65)',
        swatch_checked: 'rgba(153, 149, 129, 0.8)'
    }
}

const fields = [
    { key: 'bg_color', label: 'Hintergrund' },
    { key: 'border_color', label: 'Umrandung' },
    { key: 'swatch_default', label: 'Feld' },
    { key: 'swatch_hover', label: 'Feld Hover' },
    { key: 'swatch_checked', label: 'Feld ausgewählt' }
]

const localColors = ref({})
const customized = ref(new Set())

// Background image (Card.png texture) opacity, stored as 0-1
const IMAGE_OPACITY_DEFAULT = 0.07
const imageOpacity = ref(IMAGE_OPACITY_DEFAULT)
const imageCustomized = ref(false)
const imageOpacityPercent = computed(() => Math.round(imageOpacity.value * 100))

function initColors() {
    const src = props.colors || {}
    const def = defaults[props.cardKey] || {}
    localColors.value = {}
    for (const f of fields) {
        localColors.value[f.key] = src[f.key] || def[f.key] || null
    }
    customized.value = new Set(
        fields.filter(f => src[f.key]).map(f => f.key)
    )
    const imgVal = src.bg_image_opacity
    imageCustomized.value = imgVal !== null && imgVal !== undefined
    imageOpacity.value = imageCustomized.value ? parseFloat(imgVal) : IMAGE_OPACITY_DEFAULT
}

onMounted(initColors)

function parseColor(rgba) {
    if (!rgba) return { hex: '#ffffff', opacity: 100 }

    if (rgba.startsWith('#')) {
        return { hex: rgba.slice(0, 7), opacity: 100 }
    }

    const match = rgba.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.%]+))?\s*\)/)
    if (!match) return { hex: '#ffffff', opacity: 100 }

    const r = Math.round(parseFloat(match[1]))
    const g = Math.round(parseFloat(match[2]))
    const b = Math.round(parseFloat(match[3]))
    let a = match[4] ? parseFloat(match[4]) : 1
    if (match[4] && match[4].includes('%')) {
        a = parseFloat(match[4]) / 100
    }

    const hex = '#' + [r, g, b].map(c => c.toString(16).padStart(2, '0')).join('')
    return { hex, opacity: Math.round(a * 100) }
}

function toRgba(hex, opacity) {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    const a = opacity / 100
    return `rgba(${r}, ${g}, ${b}, ${a})`
}

function getHex(key) {
    return parseColor(localColors.value[key]).hex
}

function getOpacity(key) {
    return parseColor(localColors.value[key]).opacity
}

function setHex(key, hex) {
    const opacity = getOpacity(key)
    localColors.value[key] = toRgba(hex, opacity)
    customized.value.add(key)
    emitUpdate()
}

function setOpacity(key, opacity) {
    const hex = getHex(key)
    localColors.value[key] = toRgba(hex, opacity)
    customized.value.add(key)
    emitUpdate()
}

function applyHexInput(key, value) {
    const hex = value.trim().startsWith('#') ? value.trim() : '#' + value.trim()
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        setHex(key, hex)
    }
}

function clearField(key) {
    const def = defaults[props.cardKey] || {}
    localColors.value[key] = def[key] || null
    customized.value.delete(key)
    emitUpdate()
}

function setImageOpacity(percent) {
    imageOpacity.value = percent / 100
    imageCustomized.value = true
    emitUpdate()
}

function clearImageOpacity() {
    imageOpacity.value = IMAGE_OPACITY_DEFAULT
    imageCustomized.value = false
    emitUpdate()
}

function resetAll() {
    const def = defaults[props.cardKey] || {}
    for (const f of fields) {
        localColors.value[f.key] = def[f.key] || null
    }
    customized.value.clear()
    imageOpacity.value = IMAGE_OPACITY_DEFAULT
    imageCustomized.value = false
    emitUpdate()
}

function buildData() {
    // Only customized fields carry values, null for defaults
    const data = {}
    for (const f of fields) {
        data[f.key] = customized.value.has(f.key) ? localColors.value[f.key] : null
    }
    if (props.showImageOpacity) {
        data.bg_image_opacity = imageCustomized.value ? imageOpacity.value : null
    }
    return data
}

function emitUpdate() {
    emit('update', props.cardKey, buildData())
}

function save() {
    emit('save', props.cardKey, buildData())
}

function close() {
    emit('close')
}

function discard() {
    emit('discard', props.cardKey)
}

const MODAL_WIDTH = 340

const modalStyle = computed(() => {
    if (!props.anchorRect) return {}
    const r = props.anchorRect
    const gap = 12
    const spaceRight = window.innerWidth - r.right
    const spaceLeft = r.left

    const style = { position: 'fixed' }

    // Vertically align with card top
    style.top = `${r.top}px`

    // Prefer right side, fall back to left
    if (spaceRight >= MODAL_WIDTH + gap) {
        style.left = `${r.right + gap}px`
    } else if (spaceLeft >= MODAL_WIDTH + gap) {
        style.left = `${r.left - MODAL_WIDTH - gap}px`
    } else {
        // Not enough space on either side — place below the card
        style.top = `${r.bottom + gap}px`
        style.right = `${window.innerWidth - r.right}px`
    }

    return style
})
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" class="color-modal-wrapper">
            <div class="color-modal" :style="modalStyle">
                <div class="color-modal-header">
                    <h4>Farben: {{ cardKey }}</h4>
                    <button class="color-modal-close" @click="close">
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <div class="color-modal-body">
                    <div v-for="f in fields" :key="f.key" class="color-row">
                        <span class="color-label">{{ f.label }}</span>
                        <div class="color-controls">
                            <input
                                type="color"
                                :value="getHex(f.key)"
                                @input="setHex(f.key, $event.target.value)"
                                class="color-picker"
                            />
                            <input
                                type="text"
                                :value="getHex(f.key)"
                                @change="applyHexInput(f.key, $event.target.value)"
                                class="hex-input"
                                maxlength="7"
                                spellcheck="false"
                            />
                            <input
                                type="range"
                                min="0"
                                max="100"
                                :value="getOpacity(f.key)"
                                @input="setOpacity(f.key, parseInt($event.target.value))"
                                class="opacity-slider"
                            />
                            <span class="opacity-value">{{ getOpacity(f.key) }}%</span>
                            <button
                                v-if="customized.has(f.key)"
                                class="clear-btn"
                                @click="clearField(f.key)"
                                title="Zurücksetzen"
                            >
                                <i class="pi pi-times-circle"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="showImageOpacity" class="color-row">
                        <span class="color-label">Hintergrundbild</span>
                        <div class="color-controls">
                            <input
                                type="range"
                                min="0"
                                max="100"
                                :value="imageOpacityPercent"
                                @input="setImageOpacity(parseInt($event.target.value))"
                                class="opacity-slider"
                            />
                            <span class="opacity-value">{{ imageOpacityPercent }}%</span>
                            <button
                                v-if="imageCustomized"
                                class="clear-btn"
                                @click="clearImageOpacity"
                                title="Zurücksetzen"
                            >
                                <i class="pi pi-times-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="color-modal-footer">
                    <button class="modal-btn modal-btn-reset" @click="discard">
                        Verwerfen
                    </button>
                    <button class="modal-btn modal-btn-save" @click="save">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

</template>

<style scoped>
.color-modal-wrapper {
    pointer-events: none;
}

.color-modal {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
    width: 340px;
    z-index: 2001;
    overflow: hidden;
    pointer-events: auto;
}

.color-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #eee;
}

.color-modal-header h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    text-transform: capitalize;
}

.color-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem;
    color: #666;
    font-size: 0.9rem;
}

.color-modal-close:hover {
    color: #000;
}

.color-modal-body {
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.color-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.color-label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #555;
}

.color-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.color-picker {
    width: 32px;
    height: 28px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0;
    cursor: pointer;
    background: none;
}

.color-picker::-webkit-color-swatch-wrapper {
    padding: 2px;
}

.color-picker::-webkit-color-swatch {
    border: none;
    border-radius: 2px;
}

.hex-input {
    width: 62px;
    font-size: 0.75rem;
    font-family: monospace;
    padding: 2px 4px;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333;
}

.hex-input:focus {
    outline: none;
    border-color: #999;
}

.opacity-slider {
    flex: 1;
    height: 4px;
    accent-color: #333;
}

.opacity-value {
    font-size: 0.75rem;
    color: #888;
    width: 32px;
    text-align: right;
}

.clear-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #ccc;
    padding: 0.1rem;
    font-size: 0.85rem;
}

.clear-btn:hover {
    color: #e74c3c;
}

.color-modal-footer {
    display: flex;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-top: 1px solid #eee;
    justify-content: flex-end;
}

.modal-btn {
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background 0.15s;
}

.modal-btn-reset {
    background: #f0f0f0;
    color: #555;
}

.modal-btn-reset:hover {
    background: #e0e0e0;
}

.modal-btn-save {
    background: var(--color-primary, #ffea95);
    color: #000;
}

.modal-btn-save:hover {
    background: var(--color-primary-hover, #ffe066);
}
</style>
