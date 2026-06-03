import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

// Builds the theme CSS/JS into ./build with a manifest so functions.php can
// enqueue hashed filenames. Tailwind v4 is CSS-first (config lives in src/css/app.css
// via @theme inline), so there is no tailwind.config.js — matching the source app.
export default defineConfig({
  // Relative base so url() references inside the compiled CSS (the hashed Geist
  // woff2) resolve relative to the stylesheet's own location under
  // /wp-content/themes/buckleup/build/assets/ — an absolute "/assets/…" would
  // 404 against the WP doc root. The manifest's `file` keys stay outDir-relative
  // either way, so functions.php's enqueue is unaffected.
  base: './',
  plugins: [tailwindcss()],
  build: {
    outDir: resolve(__dirname, 'build'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'src/css/app.css'),
        main: resolve(__dirname, 'src/js/main.js'),
      },
      output: {
        assetFileNames: 'assets/[name].[hash][extname]',
        entryFileNames: 'assets/[name].[hash].js',
      },
    },
  },
});
