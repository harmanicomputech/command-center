// Photos are shrunk on the phone before they are queued: long side at most
// 1600px, JPEG at about 0.7, so they send quickly on a weak network.
export async function compressImage(file, maxSide = 1600, quality = 0.7) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' }).catch(() => null);
    const source = bitmap || (await loadImage(file));
    const scale = Math.min(1, maxSide / Math.max(source.width, source.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(source.width * scale);
    canvas.height = Math.round(source.height * scale);
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);
    bitmap?.close?.();
    return new Promise((resolve) => canvas.toBlob((blob) => resolve(blob || file), 'image/jpeg', quality));
}

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = URL.createObjectURL(file);
    });
}

export function registerPhotoField(Alpine) {
    // <div x-data="photoField()">: pick or take a photo, keep the shrunk blob.
    Alpine.data('photoField', () => ({
        blob: null,
        preview: null,
        busy: false,
        async pick(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }
            this.busy = true;
            try {
                this.blob = await compressImage(file);
                this.preview && URL.revokeObjectURL(this.preview);
                this.preview = URL.createObjectURL(this.blob);
            } finally {
                this.busy = false;
                event.target.value = '';
            }
        },
        clear() {
            this.preview && URL.revokeObjectURL(this.preview);
            this.blob = null;
            this.preview = null;
        },
    }));
}
