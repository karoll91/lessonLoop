import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
// Built into backend/public/board/, so whatever serves the Laravel app
// (OpenServer nginx, deploy/, artisan serve) also serves the board at /board/.
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/board/' : '/',
  build: { outDir: '../backend/public/board', emptyOutDir: true },
  plugins: [react()],
}))
