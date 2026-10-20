import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
// The classroom server (deploy/) serves the built board at /board/.
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/board/' : '/',
  plugins: [react()],
}))
