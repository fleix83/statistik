import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
    plugins: [vue()],
    // Dev: base '/' so API calls go to /api/* (intercepted by proxy)
    // Build: base '/statistik/' for production deployment
    base: command === 'serve' ? '/' : '/statistik/',
    server: {
        port: 5173,
        proxy: {
            '/api': {
                target: 'http://localhost/statistik/api',
                changeOrigin: true,
                rewrite: (path) => path.replace(/^\/api/, ''),
                cookieDomainRewrite: 'localhost',
                secure: false
            }
        }
    },
    resolve: {
        alias: {
            '@': '/src'
        }
    }
}))
