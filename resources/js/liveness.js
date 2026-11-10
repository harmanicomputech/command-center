// The face check's liveness rules, kept free of the camera and the face
// engine so they can be tested on their own (tests/js/liveness.test.mjs).
//
// Each video frame is reduced to a few numbers (see measure()) and fed to
// a Liveness session, which walks through:
//
//   position   exactly one face, big enough, in the oval, held still
//   challenge  three prompts in a random order: turn the head, smile, blink
//   capture    facing the camera again, neutral, eyes open: take the photo
//
// A printed photo can't smile or blink, and a random order makes a
// pre-recorded video hard to time. The face may not jump between frames
// (someone swapping in another phone or photo), and every prompt has a
// time limit.

export const CHALLENGES = {
    turn: 'Turn your head slowly to one side, then back',
    smile: 'Give a big smile',
    blink: 'Close both eyes, then open them',
};

// Tuned on landmark ratios and MediaPipe blendshape scores (0 to 1).
export const RULES = {
    minFaceWidth: 0.22, // face width as a share of the frame
    maxFaceWidth: 0.9,
    maxCentreOffset: 0.22, // how far the face centre may sit from the frame centre
    holdMs: 700, // hold still in the oval before the first prompt
    turnAway: 0.17, // nose this far from the middle of the face = turned
    frontal: 0.09, // and back within this = facing the camera
    smile: 0.55,
    smileRise: 0.25, // above the neutral face measured at the start
    eyesClosed: 0.5,
    eyesOpen: 0.3,
    blinkMaxMs: 2000, // eyes closed for longer than this isn't a blink
    challengeMs: 12000,
    lostMs: 1500, // face out of view this long: back to the oval
    maxJump: 0.25, // face centre moving more than this in one frame
    maxFailures: 3,
    captureHoldMs: 350,
};

/**
 * Reduce one face-engine result to the numbers the rules use.
 *
 * @param {{faceLandmarks?: Array<Array<{x:number,y:number}>>, faceBlendshapes?: Array<{categories: Array<{categoryName:string, score:number}>}>}} result
 */
export function measure(result) {
    const faces = result?.faceLandmarks || [];
    if (faces.length !== 1) {
        return { faces: faces.length };
    }

    const points = faces[0];
    let minX = 1, maxX = 0, minY = 1, maxY = 0;
    for (const p of points) {
        minX = Math.min(minX, p.x); maxX = Math.max(maxX, p.x);
        minY = Math.min(minY, p.y); maxY = Math.max(maxY, p.y);
    }

    // Where the nose tip (1) sits between the cheeks (234, 454): 0.5 when
    // facing the camera, towards 0 or 1 when the head turns.
    const nose = points[1], left = points[234], right = points[454];
    const span = right.x - left.x;
    const yaw = span !== 0 ? (nose.x - left.x) / span : 0.5;

    const score = (name) => result.faceBlendshapes?.[0]?.categories?.find((c) => c.categoryName === name)?.score ?? 0;

    return {
        faces: 1,
        box: { x: minX, y: minY, w: maxX - minX, h: maxY - minY },
        yaw,
        smile: (score('mouthSmileLeft') + score('mouthSmileRight')) / 2,
        eyesClosed: (score('eyeBlinkLeft') + score('eyeBlinkRight')) / 2,
    };
}

function shuffle(list, random) {
    const copy = [...list];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

export class Liveness {
    constructor({ random = Math.random, rules = RULES } = {}) {
        this.rules = rules;
        this.random = random;
        this.order = shuffle(Object.keys(CHALLENGES), random);
        this.step = 0;
        this.stage = 'position';
        this.failures = 0;
        this.passed = [];
        this.startedAt = null;
        this.reset('position');
    }

    reset(stage) {
        this.stage = stage;
        this.since = null; // when the current condition started holding
        this.phase = null; // within a challenge: 'away', 'closed', ...
        this.challengeStart = null;
        this.lostAt = null;
    }

    get current() {
        return this.order[this.step] || null;
    }

    /**
     * Feed one measured frame. Returns what the screen should show.
     *
     * @param {ReturnType<typeof measure>} frame
     * @param {number} now milliseconds
     */
    update(frame, now) {
        const r = this.rules;
        if (this.stage === 'passed' || this.stage === 'failed') {
            return this.view();
        }
        this.startedAt ??= now;

        // Problems with the face itself come first, at every stage.
        if (frame.faces !== 1 || !this.inFrame(frame)) {
            this.lastBox = null;
            if (this.stage === 'position') {
                this.since = null;
            } else {
                this.lostAt ??= now;
                if (now - this.lostAt > r.lostMs) {
                    this.restartChallenge('position');
                }
            }
            return this.view(frame.faces > 1 ? 'Only one face, please.' : frame.faces === 1 ? this.framingHint(frame) : 'Point the camera at the voter’s face.');
        }
        this.lostAt = null;

        // The face jumped across the frame: not the same person moving.
        const centre = { x: frame.box.x + frame.box.w / 2, y: frame.box.y + frame.box.h / 2 };
        if (this.lastBox && Math.hypot(centre.x - this.lastBox.x, centre.y - this.lastBox.y) > r.maxJump && this.stage !== 'position') {
            this.lastBox = centre;
            this.fail('The face moved too fast. Start again.');
            return this.view();
        }
        this.lastBox = centre;

        if (this.stage === 'position') {
            this.since ??= now;
            if (now - this.since >= r.holdMs) {
                this.baseline = { smile: frame.smile, eyesClosed: frame.eyesClosed };
                this.reset('challenge');
                this.challengeStart = now;
            }
            return this.view('Hold still…');
        }

        if (this.stage === 'challenge') {
            if (now - this.challengeStart > r.challengeMs) {
                this.fail('Too slow. Try again.');
                return this.view();
            }
            if (this.check(frame, now)) {
                this.passed.push(this.current);
                this.step += 1;
                const next = this.current;
                this.reset(next ? 'challenge' : 'capture');
                this.challengeStart = now;
            }
            return this.view();
        }

        // capture: facing the camera, neutral, eyes open.
        const steady = Math.abs(frame.yaw - 0.5) < r.frontal && frame.smile < r.smile && frame.eyesClosed < r.eyesOpen;
        if (!steady) {
            this.since = null;
            return this.view('Look straight at the camera, eyes open.');
        }
        this.since ??= now;
        if (now - this.since >= r.captureHoldMs) {
            this.stage = 'passed';
            this.durationMs = Math.round(now - this.startedAt);
        }
        return this.view('Hold still for the photo…');
    }

    inFrame(frame) {
        const r = this.rules;
        const { box } = frame;
        const cx = box.x + box.w / 2, cy = box.y + box.h / 2;
        return box.w >= r.minFaceWidth && box.w <= r.maxFaceWidth && Math.abs(cx - 0.5) <= r.maxCentreOffset && Math.abs(cy - 0.5) <= r.maxCentreOffset + 0.08;
    }

    framingHint(frame) {
        const r = this.rules;
        if (frame.box.w < r.minFaceWidth) return 'Move closer: the face should fill the oval.';
        if (frame.box.w > r.maxFaceWidth) return 'Move back a little.';
        return 'Put the face in the middle of the oval.';
    }

    check(frame, now) {
        const r = this.rules;
        const turned = Math.abs(frame.yaw - 0.5);

        switch (this.current) {
            case 'turn':
                if (this.phase !== 'away') {
                    if (turned > r.turnAway) this.phase = 'away';
                    return false;
                }
                return turned < r.frontal;
            case 'smile':
                return frame.smile > Math.max(r.smile, (this.baseline?.smile ?? 0) + r.smileRise);
            case 'blink':
                if (this.phase === 'reopen') {
                    // After eyes held shut too long, they must open before a new blink counts.
                    if (frame.eyesClosed < r.eyesOpen) this.phase = null;
                    return false;
                }
                if (this.phase !== 'closed') {
                    if (frame.eyesClosed > r.eyesClosed) {
                        this.phase = 'closed';
                        this.closedAt = now;
                    }
                    return false;
                }
                if (now - this.closedAt > r.blinkMaxMs) {
                    // Eyes shut too long (or a photo of closed eyes): not a blink.
                    this.phase = 'reopen';
                    return false;
                }
                return frame.eyesClosed < r.eyesOpen;
            default:
                return false;
        }
    }

    restartChallenge(stage) {
        this.reset(stage);
        this.lastBox = null;
    }

    fail(message) {
        this.failures += 1;
        this.message = message;
        if (this.failures >= this.rules.maxFailures) {
            this.stage = 'failed';
            return;
        }
        // Start over with a new order, so a recording can't simply be replayed.
        this.order = shuffle(this.order, this.random);
        this.step = 0;
        this.passed = [];
        this.restartChallenge('position');
    }

    view(hint = null) {
        const message = this.message;
        this.message = null;
        return {
            stage: this.stage,
            prompt: this.stage === 'challenge' ? CHALLENGES[this.current] : null,
            challenge: this.stage === 'challenge' ? this.current : null,
            done: this.passed.length,
            total: this.order.length,
            hint: message || hint,
            failures: this.failures,
        };
    }

    /** What the server keeps: which prompts were passed and how long it took. */
    result() {
        return { status: 'passed', challenges: [...this.passed], duration_ms: this.durationMs ?? null, failures: this.failures };
    }
}
