import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/adminkit/light.css',
        'resources/css/adminkit/dark.css',
        'resources/js/app.js',
         'resources/css/app.css',
         'resources/js/adminkit.js',
        'resources/js/pages/dispatch.js',
      ],
      refresh: true,
    }),
  ],
});
