import { createRouter, createWebHistory } from 'vue-router'
import DataEntry from '../views/DataEntry.vue'
import Editor from '../views/Editor.vue'
import Analytics from '../views/Analytics.vue'
import Login from '../views/Login.vue'

const routes = [
    {
        path: '/',
        name: 'dataentry',
        component: DataEntry,
        meta: { title: 'Erfassung' }
    },
    {
        path: '/editor',
        name: 'editor',
        component: Editor,
        meta: { title: 'Editor', requiresAuth: true }
    },
    {
        path: '/analytics',
        name: 'analytics',
        component: Analytics,
        meta: { title: 'Auswertung', requiresAuth: true }
    },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { title: 'Anmelden' }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation guard for protected routes
router.beforeEach((to, from, next) => {
    document.title = `${to.meta.title || 'Helpdesk'} | Statistik`

    if (to.meta.requiresAuth) {
        const token = localStorage.getItem('auth_token')
        if (!token) {
            next({ name: 'login', query: { redirect: to.fullPath } })
        } else {
            next()
        }
    } else {
        next()
    }
})

export default router
