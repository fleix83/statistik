import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
    plugins: [vue()],
    base: '/statistik/', // Deployed to subdirectory
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
})
