import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json'
    },
    withCredentials: true  // Send session cookies with requests
})

// Add auth token to requests
api.interceptors.request.use(config => {
    const token = localStorage.getItem('auth_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

// Handle auth errors
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token')
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

// Auth
export const auth = {
    login: (username, password) => api.post('/auth/login.php', { username, password }),
    logout: () => {
        localStorage.removeItem('auth_token')
        window.location.href = '/login'
    }
}

// Options (dropdown values)
export const options = {
    getAll: () => api.get('/options/list.php'),
    getBySection: (section) => api.get(`/options/list.php?section=${section}`),
    create: (data) => api.post('/options/create.php', data),
    update: (id, data) => api.put(`/options/update.php?id=${id}`, data),
    reorder: (section, items) => api.post('/options/reorder.php', { section, items }),
    delete: (id) => api.delete(`/options/delete.php?id=${id}`),
    // Draft system endpoints
    getDraft: (section = null) => api.get('/options/draft.php' + (section ? `?section=${section}` : '')),
    publish: () => api.post('/options/publish.php'),
    discard: () => api.post('/options/discard.php'),
    reset: () => api.post('/options/reset.php'),
    updateKeywords: (id, keywords) => api.put(`/options/keywords.php?id=${id}`, { keywords })
}

// Entries (stats data)
export const entries = {
    create: (data) => api.post('/entries/create.php', data),
    list: (params) => api.get('/entries/list.php', { params }),
    get: (id) => api.get(`/entries/get.php?id=${id}`),
    delete: (id) => api.delete(`/entries/delete.php?id=${id}`)
}

// Users
export const users = {
    list: () => api.get('/users/list.php'),
    create: (data) => api.post('/users/create.php', data),
    update: (id, data) => api.put(`/users/update.php?id=${id}`, data),
    delete: (id) => api.delete(`/users/delete.php?id=${id}`)
}

// Analytics
export const analytics = {
    aggregate: (params) => api.get('/analytics/aggregate.php', { params }),
    filters: () => api.get('/analytics/filters.php'),
    timeseries: (params) => api.get('/analytics/timeseries.php', { params }),
    totals: (params) => api.get('/analytics/totals.php', { params }),
    compare: (periods, section, values) => {
        const params = {
            periods: JSON.stringify(periods),
            section,
            values: values.join(',')
        }
        return api.get('/analytics/compare.php', { params })
    },
    export: (params) => api.get('/analytics/export.php', { params, responseType: 'blob' })
}

// Chart Markers
export const markers = {
    list: () => api.get('/analytics/markers.php'),
    create: (data) => api.post('/analytics/markers.php', data),
    update: (id, data) => api.put(`/analytics/markers.php?id=${id}`, data),
    delete: (id) => api.delete(`/analytics/markers.php?id=${id}`)
}

// Saved Periods
export const savedPeriods = {
    list: () => api.get('/analytics/periods.php'),
    create: (data) => api.post('/analytics/periods.php', data),
    update: (id, data) => api.put(`/analytics/periods.php?id=${id}`, data),
    delete: (id) => api.delete(`/analytics/periods.php?id=${id}`)
}

export default api
