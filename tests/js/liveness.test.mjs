// npm test (node --test): the face check's liveness rules, frame by frame.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Liveness, measure, CHALLENGES } from '../../resources/js/liveness.js';

const face = (over = {}) => ({ faces: 1, box: { x: 0.3, y: 0.25, w: 0.4, h: 0.5 }, yaw: 0.5, smile: 0.05, eyesClosed: 0.05, ...over });

// Play a list of [frame, durationMs] at 30 frames a second.
function play(session, steps, start = 0) {
    let now = start;
    let view;
    for (const [frame, ms] of steps) {
        for (let t = 0; t <= ms; t += 33) {
            view = session.update(frame, now);
            now += 33;
        }
    }
    return { view, now };
}

const moves = {
    turn: [[face({ yaw: 0.78 }), 300], [face(), 200]],
    smile: [[face({ smile: 0.8 }), 200], [face(), 100]],
    blink: [[face({ eyesClosed: 0.9 }), 150], [face(), 150]],
};

function live(order) {
    const session = new Liveness({ random: () => 0.99 });
    session.order = order;
    return session;
}

test('a real face that follows every prompt passes', () => {
    const session = live(['smile', 'turn', 'blink']);
    const { view } = play(session, [[face(), 800], ...moves.smile, ...moves.turn, ...moves.blink, [face(), 500]]);
    assert.equal(view.stage, 'passed');
    assert.deepEqual(session.result().challenges, ['smile', 'turn', 'blink']);
    assert.ok(session.result().duration_ms > 0);
});

test('a still photo never gets past the first prompt', () => {
    const session = live(['smile', 'turn', 'blink']);
    const { view } = play(session, [[face(), 800], [face(), 5000]]);
    assert.equal(view.stage, 'challenge');
    assert.equal(view.done, 0);
});

test('prompts done in the wrong order do not count', () => {
    const session = live(['blink', 'smile', 'turn']);
    const { view } = play(session, [[face(), 800], ...moves.smile, ...moves.turn]);
    assert.equal(view.challenge, 'blink');
    assert.equal(view.done, 0);
});

test('eyes kept closed are not a blink', () => {
    const session = live(['blink', 'smile', 'turn']);
    const { view } = play(session, [[face(), 800], [face({ eyesClosed: 0.9 }), 3000], [face(), 200]]);
    assert.equal(view.done, 0);
});

test('a smiling face from the start must smile more', () => {
    const session = live(['smile', 'turn', 'blink']);
    const { view } = play(session, [[face({ smile: 0.5 }), 800], [face({ smile: 0.6 }), 500]]);
    assert.equal(view.done, 0);
});

test('two faces, or none, never start the check', () => {
    const session = live(['smile', 'turn', 'blink']);
    let { view } = play(session, [[{ faces: 2 }, 2000]]);
    assert.equal(view.stage, 'position');
    assert.match(view.hint, /one face/);
    ({ view } = play(session, [[{ faces: 0 }, 2000]]));
    assert.equal(view.stage, 'position');
});

test('a face too far away is asked to move closer', () => {
    const session = live(['smile', 'turn', 'blink']);
    const { view } = play(session, [[face({ box: { x: 0.45, y: 0.45, w: 0.1, h: 0.12 } }), 1500]]);
    assert.equal(view.stage, 'position');
    assert.match(view.hint, /closer/);
});

test('a face that jumps across the frame starts again, and three strikes fail', () => {
    const session = live(['smile', 'turn', 'blink']);
    const jump = face({ box: { x: 0.1, y: 0.05, w: 0.4, h: 0.5 } }); // centre moves 0.28 in one frame, still in the oval
    let now = 0;
    for (let round = 0; round < 3; round++) {
        ({ now } = play(session, [[face(), 800]], now));
        session.update(jump, now);
        now += 33;
    }
    assert.equal(session.stage, 'failed');
});

test('too slow on a prompt counts as a failure', () => {
    const session = live(['smile', 'turn', 'blink']);
    const { view } = play(session, [[face(), 800], [face(), 13000]]);
    assert.equal(view.failures, 1);
    assert.equal(view.done, 0); // started again from the oval
});

test('the order of prompts is random', () => {
    const orders = new Set();
    let seed = 1;
    for (let i = 0; i < 40; i++) {
        const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
        orders.add(new Liveness({ random }).order.join());
    }
    assert.ok(orders.size > 2);
    assert.equal(Object.keys(CHALLENGES).length, 3);
});

test('measure reads the head turn and the blendshapes', () => {
    const landmarks = Array.from({ length: 478 }, () => ({ x: 0.5, y: 0.5 }));
    landmarks[234] = { x: 0.3, y: 0.5 };
    landmarks[454] = { x: 0.7, y: 0.5 };
    landmarks[1] = { x: 0.6, y: 0.5 };
    landmarks[0] = { x: 0.3, y: 0.2 };
    landmarks[2] = { x: 0.7, y: 0.8 };
    const categories = [
        { categoryName: 'mouthSmileLeft', score: 0.8 }, { categoryName: 'mouthSmileRight', score: 0.6 },
        { categoryName: 'eyeBlinkLeft', score: 0.1 }, { categoryName: 'eyeBlinkRight', score: 0.3 },
    ];
    const frame = measure({ faceLandmarks: [landmarks], faceBlendshapes: [{ categories }] });
    assert.equal(frame.faces, 1);
    assert.ok(Math.abs(frame.yaw - 0.75) < 1e-9);
    assert.ok(Math.abs(frame.smile - 0.7) < 1e-9);
    assert.ok(Math.abs(frame.eyesClosed - 0.2) < 1e-9);
    assert.ok(Math.abs(frame.box.w - 0.4) < 1e-9);
    assert.equal(measure({ faceLandmarks: [] }).faces, 0);
});
