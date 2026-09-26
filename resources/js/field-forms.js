// Task updates and issue reports: saved to the outbox first (with their
// photo queued behind them), then synced. Same pattern as the register form.
import { confetti } from './ui';

function formPayload(form) {
    const data = new FormData(form);
    return Object.fromEntries([...data.entries()].filter(([key, value]) => key !== '_token' && typeof value === 'string'));
}

export function registerFieldForms(Alpine) {
    const box = () => window.ccOutbox;
    // The photo picker is its own component inside the form.
    const photoOf = (root) => {
        const el = root.querySelector('[data-photo-field]');
        return el ? Alpine.$data(el) : { blob: null, clear() {} };
    };

    Alpine.data('taskReportForm', (task) => ({
        photo() {
            return photoOf(this.$root);
        },
        saved: false,
        errors: {},
        done: false,
        async submit(event) {
            if (!box()) {
                return;
            }
            event.preventDefault();
            const payload = formPayload(this.$refs.form);
            const photo = this.photo();
            payload.task_id = task.id;
            payload.done = this.done;
            payload.has_photo = Boolean(photo.blob);
            payload.reported_at = new Date().toISOString();

            this.errors = {};
            if (this.done && task.proof === 'photo' && !photo.blob) this.errors.photo = ['This task needs a photo as proof.'];
            if (!this.done && !Number(payload.count) && !payload.note && !photo.blob) this.errors.count = ['Add a count, a note or a photo.'];
            if (Object.keys(this.errors).length) {
                return;
            }

            const item = await box().add('task_report', payload, `${this.done ? 'Done' : 'Update'}: ${task.title}`);
            if (photo.blob) {
                await box().addPhoto('task_report', item.id, photo.blob, `Photo: ${task.title}`);
            }
            Alpine.store('outbox').refresh();
            this.saved = true;
            this.done && confetti();
            Alpine.store('outbox').sync();
        },
    }));

    // A survey, run by an agent with a respondent: one question per screen,
    // then saved to the outbox and straight on to the next respondent.
    Alpine.data('surveyRunner', (survey) => ({
        step: 0,
        answers: {},
        about: { gender: '', age_band: '', occupation: '', phone: '' },
        saved: 0,
        error: '',
        get total() {
            return survey.questions.length;
        },
        get question() {
            return survey.questions[this.step] || null;
        },
        get onAbout() {
            return this.step === this.total;
        },
        toggle(questionId, value) {
            const list = this.answers[questionId] || [];
            this.answers[questionId] = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
        },
        next() {
            const q = this.question;
            const value = q ? this.answers[q.id] : null;
            if (q && q.required && (value === undefined || value === '' || (Array.isArray(value) && !value.length))) {
                this.error = 'Choose an answer to go on.';
                return;
            }
            this.error = '';
            this.step++;
            window.scrollTo({ top: 0 });
        },
        back() {
            this.error = '';
            this.step = Math.max(0, this.step - 1);
        },
        async finish() {
            if (!box()) {
                return;
            }
            // Plain data: IndexedDB can't store Alpine's reactive proxies.
            const payload = JSON.parse(JSON.stringify({ survey_id: survey.id, answers: this.answers, ward_id: survey.ward_id, answered_at: new Date().toISOString(), ...this.about }));
            await box().add('survey_response', payload, `Survey: ${survey.title}`);
            Alpine.store('outbox').refresh();
            Alpine.store('outbox').sync();
            this.saved++;
            this.step = 0;
            this.answers = {};
            this.about = { gender: '', age_band: '', occupation: '', phone: '' };
            window.scrollTo({ top: 0 });
        },
    }));

    Alpine.data('issueForm', () => ({
        photo() {
            return photoOf(this.$root);
        },
        saved: null,
        errors: {},
        async submit(event) {
            if (!box()) {
                return;
            }
            event.preventDefault();
            const payload = formPayload(this.$refs.form);
            const photo = this.photo();
            payload.reported_at = new Date().toISOString();

            this.errors = {};
            if (!payload.category) this.errors.category = ['Choose what kind of issue it is.'];
            if (!payload.severity) this.errors.severity = ['Choose how serious it is.'];
            if (!payload.description || payload.description.trim().length < 5) this.errors.description = ['Describe the issue in a sentence or two.'];
            if (!payload.ward_id) this.errors.ward_id = ['Choose the ward.'];
            if (Object.keys(this.errors).length) {
                this.$nextTick(() => this.$refs.form.querySelector('[data-error]:not([style*="none"])')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }

            const label = `${this.$refs.form.querySelector('[name=category]:checked')?.closest('label')?.innerText.trim() || 'Issue'}${payload.community ? ', ' + payload.community : ''}`;
            const item = await box().add('issue', payload, label);
            if (photo.blob) {
                await box().addPhoto('issue', item.id, photo.blob, `Photo: ${label}`);
            }
            Alpine.store('outbox').refresh();
            this.saved = label;
            photo.clear();
            window.scrollTo({ top: 0 });
            Alpine.store('outbox').sync();
        },
        another() {
            this.$refs.form.reset();
            this.saved = null;
            this.errors = {};
        },
    }));
}
