import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { copyFileSync, mkdirSync, readFileSync } from 'node:fs';

// The face check's engine (resources/js/face-check.js): the MediaPipe
// WebAssembly runtime and the face model, copied next to the build under a
// versioned folder the service worker keeps across releases.
function faceEngine() {
    return {
        name: 'face-engine',
        closeBundle() {
            const version = JSON.parse(readFileSync('node_modules/@mediapipe/tasks-vision/package.json', 'utf8')).version;
            const target = `public/build/face/${version}`;
            mkdirSync(target, { recursive: true });
            for (const file of ['vision_wasm_internal.js', 'vision_wasm_internal.wasm']) {
                copyFileSync(`node_modules/@mediapipe/tasks-vision/wasm/${file}`, `${target}/${file}`);
            }
            copyFileSync('resources/face/face_landmarker.task', `${target}/face_landmarker.task`);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        faceEngine(),
    ],
    build: {
        // Fonts are self-hosted from public/fonts (the service worker caches them).
        assetsInlineLimit: 0,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
