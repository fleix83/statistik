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

const isAnalyticsView = computed(() => route.path === '/analytics' || route.path === '/editor' || route.path === '/')

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
                <div class="app-branding">
                    <img src="@/assets/logo_wegweiser.svg" alt="Wegweiser" class="app-logo" />
                    <h1 class="app-branding-title">STATISTIK</h1>
                </div>
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
                <div class="auth-buttons flex align-items-center gap-2">
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
@font-face {
    font-family: 'Din Next Rounded';
    src: url('/fonts/din-next-rounded-lt-w01-regular.woff2') format('woff2'),
         url('/fonts/din-next-rounded-lt-w01-regular.woff') format('woff'),
         url('/fonts/din-next-rounded-lt-w01-regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

:root {
    --font-family: 'Din Next Rounded', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body, #app {
    font-family: 'Din Next Rounded', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #fafafa;
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
    transition: max-height 0.3s ease, opacity 0.3s ease, border-color 0.3s ease;
    overflow: hidden;
    max-height: 200px;
}

.app-header.navbar-hidden {
    max-height: 0;
    opacity: 0;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    min-height: 0 !important;
}

.app-branding {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-left: -80px;
    margin-right: 3rem;
    padding: 20px;
}

.app-logo {
    height: 3rem;
    opacity: 0.6;
}

.app-branding-title {
    font-family: 'Din Next Rounded', sans-serif;
    font-size: 2.0rem;
    font-weight: 400;
    margin: -0.7rem 0 0;
    margin-left: 157px;
    color: var(--text-color);
    letter-spacing: 0.10em;
    opacity: 0.7;
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
    padding: 0;
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

/* Navbar auth buttons icon spacing */
.auth-buttons .p-button .pi {
    margin-right: 5px;
}
</style>
