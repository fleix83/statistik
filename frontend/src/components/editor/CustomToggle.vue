<script setup>
const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue'])

function toggle() {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue)
    }
}
</script>

<template>
    <button
        type="button"
        class="custom-toggle"
        :class="{ 'is-active': modelValue, 'is-disabled': disabled }"
        @click="toggle"
        :disabled="disabled"
    >
        <span class="toggle-track">
            <span class="toggle-thumb">
                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17" fill="none">
                    <g filter="url(#filter0_d_toggle)">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.38502 13.9752C11.9869 13.9752 14.9068 11.0553 14.9068 7.45341C14.9068 3.85155 11.9869 0.931671 8.38502 0.931671C4.78316 0.931671 1.86328 3.85155 1.86328 7.45341C1.86328 11.0553 4.78316 13.9752 8.38502 13.9752Z" fill="white"/>
                        <path d="M8.38477 1.39749C11.7293 1.39749 14.4413 4.10869 14.4414 7.45316C14.4414 10.7977 11.7293 13.5098 8.38477 13.5098C5.0403 13.5097 2.3291 10.7977 2.3291 7.45316C2.32924 4.10878 5.04039 1.39763 8.38477 1.39749Z" stroke="#1B1B1B" stroke-width="0.931677"/>
                    </g>
                    <defs>
                        <filter id="filter0_d_toggle" x="-7.29561e-05" y="-5.96046e-06" width="16.7702" height="16.7702" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                            <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                            <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                            <feOffset dy="0.931677"/>
                            <feGaussianBlur stdDeviation="0.931677"/>
                            <feComposite in2="hardAlpha" operator="out"/>
                            <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.08 0"/>
                            <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_toggle"/>
                            <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_toggle" result="shape"/>
                        </filter>
                    </defs>
                </svg>
            </span>
        </span>
    </button>
</template>

<style scoped>
.custom-toggle {
    appearance: none;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    outline: none;
}

.custom-toggle.is-disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.toggle-track {
    display: flex;
    align-items: center;
    width: 36px;
    height: 18px;
    background: #E5E5E5;
    border-radius: 9px;
    padding: 0 2px;
    transition: background 0.2s ease;
}

.custom-toggle.is-active .toggle-track {
    background: #1B1B1B;
}

.toggle-thumb {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
}

.toggle-thumb svg {
    width: 14px;
    height: 14px;
}

.custom-toggle.is-active .toggle-thumb {
    transform: translateX(16px);
}
</style>
