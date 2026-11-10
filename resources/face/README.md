# Face check model

`face_landmarker.task` is Google's MediaPipe Face Landmarker model (float16, version 1),
from `https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task`.
It is licensed under the Apache License 2.0.

SHA-256: `64184e229b263107bc2b804c6625db1341ff2bb731874b0bcc2fe6544e0bc9ff`

`npm run build` copies it, with the matching WebAssembly runtime from
`@mediapipe/tasks-vision`, to `public/build/face/<version>/` (see `vite.config.js`).
The version there must match `field.face_engine` in `config/field.php`
(`GuardTest` checks). The model runs on the phone only: no face measurements
leave it.
