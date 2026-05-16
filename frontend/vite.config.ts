import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const base = env.VITE_BASE_PATH?.trim() || '/';
  const normalizedBase = base.endsWith('/') ? base : `${base}/`;

  return {
  base: normalizedBase,
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/backend': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/backend/, ''),
      },
    },
  },
};
});
