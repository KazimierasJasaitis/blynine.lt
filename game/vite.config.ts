import { defineConfig } from 'vite';

export default defineConfig({
  base: '/zaidimas/',
  build: { outDir: '../zaidimas', emptyOutDir: true },
  server: { host: true, port: 5173 },
});
