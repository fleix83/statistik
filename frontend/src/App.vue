<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import Menubar from 'primevue/menubar'
import Button from 'primevue/button'

const route = useRoute()
const authStore = useAuthStore()

const menuItems = computed(() => {
    const items = [
        {
            label: 'Erfassung',
            icon: 'pi pi-pencil',
            route: '/'
        }
    ]

    if (authStore.isAuthenticated) {
        items.push(
            {
                label: 'Editor',
                icon: 'pi pi-cog',
                route: '/editor'
            },
            {
                label: 'Auswertung',
                icon: 'pi pi-chart-bar',
                route: '/analytics'
            }
        )
    }

    return items
})

function isActive(item) {
    return route.path === item.route
}
</script>

<template>
    <div class="app-layout">
        <Menubar :model="menuItems" class="app-header">
            <template #start>
                <span class="app-title">
                    <i class="pi pi-chart-line mr-2"></i>
                    GGG Wegweiser Statistik
                </span>
            </template>
            <template #item="{ item }">
                <router-link
                    :to="item.route"
                    class="menu-item"
                    :class="{ active: isActive(item) }"
                >
                    <i :class="item.icon" class="mr-2"></i>
                    {{ item.label }}
                </router-link>
            </template>
            <template #end>
                <div class="flex align-items-center gap-2">
                    <Button
                        v-if="authStore.isAuthenticated"
                        icon="pi pi-sign-out"
                        label="Abmelden"
                        severity="secondary"
                        text
                        @click="authStore.logout"
                    />
                    <router-link v-else to="/login">
                        <Button
                            icon="pi pi-sign-in"
                            label="Admin"
                            severity="secondary"
                            text
                        />
                    </router-link>
                </div>
            </template>
        </Menubar>

        <main class="app-main">
            <router-view />
        </main>
    </div>
</template>

<style>
:root {
    --font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body, #app {
    font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--surface-ground);
    color: var(--text-color);
    min-height: 100vh;
}

button, input, select, textarea {
    font-family: inherit;
}

.app-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.app-header {
    border-radius: 0;
    border-left: 0;
    border-right: 0;
    border-top: 0;
}

.app-title {
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-right: 2rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--text-color);
    border-radius: var(--border-radius);
    transition: background-color 0.2s;
}

.menu-item:hover {
    background: var(--surface-hover);
}

.menu-item.active {
    background: var(--primary-color);
    color: var(--primary-color-text);
}

.app-main {
    flex: 1;
    padding: 1rem;
}
</style>
