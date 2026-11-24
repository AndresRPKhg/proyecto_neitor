// Vite solo compila y minimiza los assets (CSS y JS) hacia public/assets. No reemplaza a Apache ni a PHP.
import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false, // no copiar public/ dentro de public/assets
  build: {
    outDir: 'public/assets',
    emptyOutDir: false,
    cssCodeSplit: false,
    rollupOptions: {
      input: 'resources/js/app.js',
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunk-[name].js',
        assetFileNames: (info) => (info.name && info.name.endsWith('.css') ? 'app.css' : 'files/[name][extname]'),
      },
    },
  },
});
