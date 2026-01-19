<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const props = defineProps({
    hasPendingChanges: {
        type: Boolean,
        default: false
    },
    lastPublishedAt: {
        type: String,
        default: null
    },
    publishing: {
        type: Boolean,
        default: false
    },
    discarding: {
        type: Boolean,
        default: false
    },
    resetting: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['publish', 'discard', 'reset'])

const formattedLastPublished = computed(() => {
    if (!props.lastPublishedAt) return null
    const date = new Date(props.lastPublishedAt)
    return date.toLocaleDateString('de-CH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
})

function onPublish() {
    emit('publish')
}

function onDiscard() {
    emit('discard')
}

function onReset() {
    emit('reset')
}
</script>

<template>
    <div class="header-controls">
        <div class="status-info">
            <Tag
                v-if="hasPendingChanges"
                value="Unveröffentlichte Änderungen"
                severity="warn"
                icon="pi pi-exclamation-circle"
            />
            <span v-else-if="formattedLastPublished" class="last-published">
                Zuletzt veröffentlicht: {{ formattedLastPublished }}
            </span>
        </div>

        <div class="header-actions">
            <Button
                label="Zurücksetzen"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                size="small"
                :loading="resetting"
                @click="onReset"
                v-tooltip.bottom="'Auf Standardwerte zurücksetzen'"
            />
            <Button
                v-if="hasPendingChanges"
                label="Verwerfen"
                icon="pi pi-times"
                severity="danger"
                outlined
                size="small"
                :loading="discarding"
                @click="onDiscard"
            />
            <Button
                label="Veröffentlichen"
                icon="pi pi-check"
                size="small"
                :disabled="!hasPendingChanges"
                :loading="publishing"
                @click="onPublish"
            />
        </div>
    </div>
</template>

<style scoped>
.header-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.status-info {
    min-height: 24px;
}

.last-published {
    color: #999;
    font-size: 13px;
}

.header-actions {
    display: flex;
    gap: 8px;
}

@media (max-width: 768px) {
    .header-controls {
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
}
</style>
