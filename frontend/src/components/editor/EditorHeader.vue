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
    <div class="editor-header">
        <div class="header-left">
            <div class="title-row">
                <i class="pi pi-cog"></i>
                <h1>Editor</h1>
            </div>
            <div class="status-row">
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
        </div>

        <div class="header-actions">
            <Button
                label="Zurücksetzen"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
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
                :loading="discarding"
                @click="onDiscard"
            />
            <Button
                label="Veröffentlichen"
                icon="pi pi-check"
                :disabled="!hasPendingChanges"
                :loading="publishing"
                @click="onPublish"
            />
        </div>
    </div>
</template>

<style scoped>
.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 1.5rem;
    background: var(--surface-card);
    border-bottom: 1px solid var(--surface-border);
    margin-bottom: 1.5rem;
    border-radius: 8px;
}

.header-left {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.title-row i {
    font-size: 1.5rem;
    color: var(--primary-500);
}

.title-row h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.status-row {
    min-height: 1.5rem;
}

.last-published {
    color: var(--text-color-secondary);
    font-size: 0.875rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .editor-header {
        flex-direction: column;
        gap: 1rem;
    }

    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
