import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  // The PHP entry point serves the app from /SEB/, not the web root.
  base: './',
  plugins: [react()],
  server: {
    port: 5173,
    host: '0.0.0.0',
  },
});
