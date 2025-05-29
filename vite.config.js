// vite.config.js - исправленный
import { defineConfig } from "vite";

export default defineConfig({
  root: ".",
  publicDir: "public",
  build: {
    outDir: "public/assets/dist",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: "src/js/main.js",
        styles: "src/css/main.css"
      }
    }
  },
  server: {
    proxy: {
      '/api': 'http://localhost'
    }
  }
});
