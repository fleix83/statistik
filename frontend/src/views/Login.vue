<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '../stores/auth'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const authStore = useAuthStore()

const username = ref('')
const password = ref('')
const loading = ref(false)

async function login() {
    if (!username.value || !password.value) {
        toast.add({
            severity: 'warn',
            summary: 'Hinweis',
            detail: 'Bitte Benutzername und Passwort eingeben',
            life: 3000
        })
        return
    }

    loading.value = true
    try {
        await authStore.login(username.value, password.value)

        toast.add({
            severity: 'success',
            summary: 'Willkommen',
            detail: 'Erfolgreich angemeldet',
            life: 2000
        })

        // Redirect to intended page or editor
        const redirect = route.query.redirect || '/editor'
        setTimeout(() => {
            router.push(redirect)
        }, 500)
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Benutzername oder Passwort falsch',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <Toast />
    <div class="login-page">
        <Card class="login-card">
            <template #title>
                <div class="flex align-items-center justify-content-center gap-2">
                    <i class="pi pi-lock"></i>
                    <span>Anmelden</span>
                </div>
            </template>
            <template #content>
                <form @submit.prevent="login" class="flex flex-column gap-3">
                    <div class="field">
                        <label for="username">Benutzername</label>
                        <InputText
                            id="username"
                            v-model="username"
                            class="w-full"
                            autocomplete="username"
                        />
                    </div>
                    <div class="field">
                        <label for="password">Passwort</label>
                        <Password
                            id="password"
                            v-model="password"
                            class="w-full"
                            inputClass="w-full"
                            :feedback="false"
                            toggleMask
                            autocomplete="current-password"
                        />
                    </div>
                    <Button
                        type="submit"
                        label="Anmelden"
                        icon="pi pi-sign-in"
                        :loading="loading"
                        class="w-full mt-2"
                    />
                </form>
            </template>
            <template #footer>
                <div class="text-center">
                    <router-link to="/" class="text-primary">
                        <i class="pi pi-arrow-left mr-1"></i>
                        Zurück zur Erfassung
                    </router-link>
                </div>
            </template>
        </Card>
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: var(--surface-ground);
}

.login-card {
    width: 100%;
    max-width: 400px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.field label {
    font-weight: 500;
}
</style>
