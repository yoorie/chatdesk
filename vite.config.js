import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  // Relative to the built file. An absolute `/build/` base makes dynamic page
  // chunks load from the domain root, which 404s when the app is in a subfolder.
  base: './',
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
    }),
    react(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
  build: {
    // Open tabs still reference previous fingerprinted chunks after a rebuild.
    // Keep them available instead of breaking lazy page navigation.
    emptyOutDir: false,
    chunkSizeWarningLimit: 800,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return;
          const reactPkgs = [
            '/node_modules/react/',
            '/node_modules/react-dom/',
            '/node_modules/scheduler/',
            '/node_modules/react-is/',
            '/node_modules/use-sync-external-store/',
          ];
          if (reactPkgs.some((pkg) => id.includes(pkg))) {
            return 'react';
          }
          // Let Rollup share dependencies only between the routes that use them.
          // A catch-all vendor chunk forces flow editors and realtime onto public pages.
        },
      },
    },
  },
});
