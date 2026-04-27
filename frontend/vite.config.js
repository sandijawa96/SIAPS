import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  base: "/",
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      workbox: {
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        skipWaiting: true,
      },
      manifest: {
        name: 'Sistem Absensi',
        short_name: 'Absensi',
        description: 'Aplikasi Sistem Absensi',
        theme_color: '#ffffff',
        icons: [
          {
            src: 'logo192.png',
            type: 'image/png',
            sizes: '192x192'
          },
          {
            src: 'logo512.png',
            type: 'image/png',
            sizes: '512x512'
          }
        ]
      }
    })
  ],

  server: {
    port: 3000,
    host: '0.0.0.0',
    open: true,
    historyApiFallback: true
  },
  preview: {
    port: 3000,
    host: '0.0.0.0',
    open: true
  },
  build: {
    outDir: 'build',
    sourcemap: false,
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      maxParallelFileOps: 2,
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom'],
          'mui-bundle': ['@mui/material', '@mui/system', '@emotion/react', '@emotion/styled'],
          'mui-icons': ['@mui/icons-material'],
          'mui-pickers': ['@mui/x-date-pickers'],
          'forms': ['react-hook-form', 'react-dropzone'],
          'utils': ['date-fns', 'jwt-decode', 'axios']
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    }
  },
  define: {
    'process.env': {},
    global: 'globalThis'
  },
  optimizeDeps: {
    include: [
      '@mui/material',
      '@mui/system',
      '@emotion/react',
      '@emotion/styled',
      'react-hook-form',
      'react-dropzone'
    ]
  },
  publicDir: 'public'
})
