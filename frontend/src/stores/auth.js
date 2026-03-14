import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { auth } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const storedUser = localStorage.getItem('auth_user')
    const user = ref(storedUser ? JSON.parse(storedUser) : null)
    const token = ref(localStorage.getItem('auth_token'))

    const isAuthenticated = computed(() => !!token.value)
    const isAdmin = computed(() => user.value?.role === 'admin')

    async function login(username, password) {
        const response = await auth.login(username, password)
        token.value = response.data.token
        user.value = response.data.user
        localStorage.setItem('auth_token', token.value)
        localStorage.setItem('auth_user', JSON.stringify(user.value))
        return response.data
    }

    function logout() {
        token.value = null
        user.value = null
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
    }

    return {
        user,
        token,
        isAuthenticated,
        isAdmin,
        login,
        logout
    }
})
