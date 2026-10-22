import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
// Built into backend/public/admin/, so whatever serves the Laravel app
// (OpenServer nginx, deploy/, artisan serve) also serves the panel at /admin/.
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/admin/' : '/',
  build: { outDir: '../backend/public/admin', emptyOutDir: true },
  plugins: [react()],
}))
