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

function onChange(event) {
    if (!props.disabled) {
        emit('update:modelValue', event.target.checked)
    }
}
</script>

<template>
    <label class="switch" :class="{ 'is-disabled': disabled }">
        <input
            type="checkbox"
            :checked="modelValue"
            :disabled="disabled"
            @change="onChange"
        />
        <span class="slider round"></span>
    </label>
</template>

<style scoped>
.switch {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
}

.switch.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

input:checked + .slider {
    background-color: #1B1B1B;
}

input:checked + .slider:before {
    transform: translateX(16px);
}

input:disabled + .slider {
    cursor: not-allowed;
}

.slider.round {
    border-radius: 20px;
}

.slider.round:before {
    border-radius: 50%;
}
</style>
