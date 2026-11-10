// The face check on the registration form: the voter's face on camera,
// three random prompts (turn, smile, blink) to show it's a real person
// in front of the phone, then a cropped photo of the face.
//
// The face engine (MediaPipe Face Landmarker: WebAssembly + a 3.7 MB model)
// is served from /build/face/<version>/ and kept by the service worker in
// its own cache, so after the first download it works offline. Nothing
// leaves the phone but the photo and which prompts were passed: no face
// measurements are stored or sent.
//
// When the engine can't run (no WebAssembly SIMD, or it was never
// downloaded and there's no network) or the camera is blocked, the agent
// takes a plain photo instead: "photo only", which earns points only once a
// coordinator verifies the registration.
import { Liveness, measure } from './liveness';
import { compressImage } from './photos';

const PHOTO_SIDE = 480;

let enginePromise = null;

// Kept out of Alpine's reactive state: a proxied engine or stream breaks.
const live = { engine: null, stream: null, session: null };

function loadEngine(base) {
    enginePromise ??= (async () => {
        const vision = await import('@mediapipe/tasks-vision');
        if (!(await vision.FilesetResolver.isSimdSupported())) {
            throw new Error('nosimd');
        }
        return vision.FaceLandmarker.createFromOptions(
            { wasmLoaderPath: `${base}vision_wasm_internal.js`, wasmBinaryPath: `${base}vision_wasm_internal.wasm` },
            {
                baseOptions: { modelAssetPath: `${base}face_landmarker.task`, delegate: 'CPU' },
                runningMode: 'VIDEO',
                numFaces: 2,
                outputFaceBlendshapes: true,
                minFaceDetectionConfidence: 0.6,
                minTrackingConfidence: 0.6,
            },
        );
    })().catch((error) => {
        enginePromise = null;
        throw error;
    });
    return enginePromise;
}

export function registerFaceCheck(Alpine) {
    // Nested in registerForm: writes the parent's `face`.
    Alpine.data('faceCheck', (options) => ({
        base: options.base,
        files: options.files,
        bytes: options.bytes,
        open: false,
        state: 'idle', // loading · camera · passed · failed · fallback
        view: {},
        facing: 'environment',
        cameras: 1,
        reason: '',
        cameraWorks: false,
        ready: null, // engine files on this phone: null (checking), true, false
        downloading: false,
        lastFrame: null,
        timer: null,

        async init() {
            this.ready = await this.cached();
            // Fetch the engine once, while there's network, so it works offline later.
            if (!this.ready && navigator.onLine && !navigator.connection?.saveData) {
                this.warm();
            }
        },

        destroy() {
            this.stop();
        },

        async cached() {
            if (!('caches' in window)) {
                return false;
            }
            const hits = await Promise.all(this.files.map((file) => caches.match(this.base + file).catch(() => null)));
            return hits.every(Boolean);
        },

        async warm() {
            this.downloading = true;
            try {
                await Promise.all(this.files.map((file) => fetch(this.base + file).then((response) => response.ok && response.arrayBuffer())));
                this.ready = await this.cached();
            } catch (error) {
                this.ready = false;
            } finally {
                this.downloading = false;
            }
        },

        get megabytes() {
            return Math.round(this.bytes / 1e6);
        },

        async start() {
            this.open = true;
            this.state = 'loading';
            this.reason = '';
            document.documentElement.classList.add('overflow-hidden');

            try {
                await this.openCamera();
            } catch (error) {
                this.fallback(error?.name === 'NotAllowedError'
                    ? 'The camera is blocked for this app. Allow it in the phone’s settings, or take a photo instead.'
                    : 'The camera couldn’t start. Take a photo instead.', false);
                return;
            }

            try {
                live.engine = await loadEngine(this.base);
                this.ready = true;
            } catch (error) {
                this.fallback(error?.message === 'nosimd'
                    ? 'This phone is too old for the face check.'
                    : navigator.onLine ? 'The face check couldn’t load.' : 'The face check hasn’t been downloaded to this phone yet. Connect to the internet once to get it.', true);
                return;
            }

            this.begin();
        },

        begin() {
            live.session = new Liveness();
            this.state = 'camera';
            this.view = { stage: 'position', hint: 'Point the camera at the voter’s face.', done: 0, total: 3 };
            this.loop();
        },

        async openCamera() {
            this.stopCamera();
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error('nocamera');
            }
            live.stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: this.facing }, width: { ideal: 640 }, height: { ideal: 480 } },
            });
            const video = document.getElementById('face-video');
            video.srcObject = live.stream;
            await video.play();
            const devices = await navigator.mediaDevices.enumerateDevices().catch(() => []);
            this.cameras = devices.filter((device) => device.kind === 'videoinput').length || 1;
        },

        get video() {
            return document.getElementById('face-video');
        },

        get mirrored() {
            return this.facing === 'user';
        },

        async flip() {
            this.facing = this.facing === 'user' ? 'environment' : 'user';
            try {
                await this.openCamera();
                if (this.state === 'camera') {
                    live.session = new Liveness(); // a new face on screen: start over
                }
            } catch (error) {
                this.fallback('The camera couldn’t start. Take a photo instead.', false);
            }
        },

        loop() {
            const video = document.getElementById('face-video');
            let last = 0;
            const tick = (now) => {
                if (this.state !== 'camera') {
                    return;
                }
                // About 15 checks a second is plenty, and kind to cheap phones.
                if (now - last >= 66 && video.readyState >= 2) {
                    last = now;
                    const frame = measure(live.engine.detectForVideo(video, now));
                    if (frame.box) {
                        this.lastFrame = frame;
                    }
                    this.view = live.session.update(frame, now);
                    if (this.view.stage === 'passed') {
                        this.capture(live.session.result());
                        return;
                    }
                    if (this.view.stage === 'failed') {
                        this.state = 'failed';
                        navigator.vibrate?.(200);
                        return;
                    }
                }
                this.timer = requestAnimationFrame(tick);
            };
            this.timer = requestAnimationFrame(tick);
        },

        // The photo: a square around the face (or the middle of the frame).
        async capture(result) {
            const video = document.getElementById('face-video');
            const [vw, vh] = [video.videoWidth, video.videoHeight];
            let side = Math.min(vw, vh);
            let [sx, sy] = [(vw - side) / 2, (vh - side) / 2];
            const box = this.lastFrame?.box;
            if (box) {
                side = Math.min(Math.max(box.w * vw, box.h * vh) * 1.7, vw, vh);
                sx = Math.min(Math.max((box.x + box.w / 2) * vw - side / 2, 0), vw - side);
                sy = Math.min(Math.max((box.y + box.h / 2) * vh - side / 2, 0), vh - side);
            }
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = PHOTO_SIDE;
            canvas.getContext('2d').drawImage(video, sx, sy, side, side, 0, 0, PHOTO_SIDE, PHOTO_SIDE);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            this.done({ ...result, camera: this.facing }, blob);
        },

        fallback(reason, cameraWorks) {
            this.state = 'fallback';
            this.reason = reason;
            this.cameraWorks = cameraWorks;
        },

        // Photo only: a still from the live camera, or the phone's own camera app.
        async snap() {
            await this.capture({ status: 'photo_only', reason: this.reason || 'Face check not passed' });
        },

        async pick(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) {
                return;
            }
            const blob = await compressImage(file, PHOTO_SIDE * 2, 0.85);
            this.done({ status: 'photo_only', reason: this.reason || 'Camera not available' }, blob);
        },

        photoOnly() {
            this.fallback('The face check didn’t pass.', Boolean(live.stream));
        },

        retry() {
            this.begin();
        },

        done(result, blob) {
            this.face?.preview && URL.revokeObjectURL(this.face.preview);
            this.face = { result: JSON.parse(JSON.stringify(result)), blob, preview: URL.createObjectURL(blob) };
            if (this.errors?.face) {
                delete this.errors.face;
            }
            navigator.vibrate?.(60);
            this.close();
        },

        stopCamera() {
            live.stream?.getTracks().forEach((track) => track.stop());
            live.stream = null;
        },

        stop() {
            cancelAnimationFrame(this.timer);
            this.stopCamera();
        },

        close() {
            this.stop();
            this.state = 'idle';
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },

        retake() {
            this.face?.preview && URL.revokeObjectURL(this.face.preview);
            this.face = null;
            this.start();
        },
    }));
}
