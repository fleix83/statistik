<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import Menubar from 'primevue/menubar'
import Button from 'primevue/button'

const route = useRoute()
const authStore = useAuthStore()

// Auto-hide navbar for Analytics view
const navbarVisible = ref(true)
let hideTimer = null

const isAnalyticsView = computed(() => route.path === '/analytics' || route.path === '/editor')

function showNavbar() {
    navbarVisible.value = true
    resetHideTimer()
}

function resetHideTimer() {
    if (hideTimer) clearTimeout(hideTimer)
    if (isAnalyticsView.value) {
        hideTimer = setTimeout(() => {
            navbarVisible.value = false
        }, 4000)
    }
}

function handleMouseMove(e) {
    if (!isAnalyticsView.value) return
    // Show navbar when mouse is at top edge (within 5px)
    if (e.clientY <= 5) {
        showNavbar()
    }
}

// Watch for route changes
watch(isAnalyticsView, (isAnalytics) => {
    if (isAnalytics) {
        resetHideTimer()
    } else {
        navbarVisible.value = true
        if (hideTimer) clearTimeout(hideTimer)
    }
}, { immediate: true })

onMounted(() => {
    document.addEventListener('mousemove', handleMouseMove)
})

onUnmounted(() => {
    document.removeEventListener('mousemove', handleMouseMove)
    if (hideTimer) clearTimeout(hideTimer)
})

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
        <Menubar :model="menuItems" class="app-header" :class="{ 'navbar-hidden': !navbarVisible && isAnalyticsView }">
            <template #start>
                <img src="@/assets/logo.svg" alt="Logo" class="app-logo" />
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
    background: #f5f3ef;
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
    transition: margin-top 0.3s ease;
}

.app-header.navbar-hidden {
    margin-top: -54px;
}

.app-logo {
    height: 2rem;
    margin-left: 20px;
    margin-right: 2rem;
    filter: brightness(0);
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

/* Year button selected state - blue background */
.year-buttons .year-selected.p-button {
    background-color: #3b82f6 !important;
    color: white !important;
    border: none !important;
}

.year-buttons .year-selected.p-button:hover {
    background-color: #2563eb !important;
}
</style>
