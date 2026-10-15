import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

// `vite build --mode demo` produce la demo statica per GitHub Pages in ../demo (API simulata).
export default defineConfig(({ mode }) => {
  const demo = mode === 'demo';

  return {
    plugins: [vue],
    base: demo ? './' : '/',
    build: demo ? { outDir: '../demo', emptyOutDir: true } : {},
    define: demo
      ? {
          'import.meta.env.VITE_DEMO': JSON.stringify('1'),
          'import.meta.env.VITE_API_BASE_URL': JSON.stringify('https://demo.movi.invalid/api/v1'),
        }
      : {},
    server: {
      port: 5173,
    },
  };
});
