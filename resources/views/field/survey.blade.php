@php
    $data = [
        'id' => $survey->id,
        'title' => $survey->title,
        'ward_id' => $user->ward_id,
        'questions' => $survey->questions->map(fn ($q) => ['id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'required' => $q->required, 'choices' => collect($q->choices())->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()])->values(),
    ];
@endphp
<x-layouts.field :title="$survey->title" :back="route('field.surveys')">
    <div x-data="surveyRunner(@js($data))">
        <div class="mb-5">
            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-muted">
                <span x-text="onAbout ? 'About the respondent' : 'Question ' + (step + 1) + ' of ' + total"></span>
                <span x-show="saved" x-cloak class="text-good" x-text="saved + ' done this session'"></span>
            </div>
            <div class="bar"><span :style="'width:' + (100 * step / (total + 1)) + '%'" style="width: 0"></span></div>
        </div>

        <template x-if="step === 0 && ! saved">
            <div class="mb-5 rounded-2xl bg-surface-2 p-4 text-sm text-muted">
                <p class="font-semibold text-ink">{{ $survey->title }}</p>
                @if ($survey->intro)<p class="mt-1">{{ $survey->intro }}</p>@endif
                <p class="mt-2">Read the questions out as written. Answers are anonymous; a phone number is only used so nobody answers twice.</p>
            </div>
        </template>

        <template x-if="question">
            <section>
                <h1 class="text-xl leading-snug font-bold tracking-tight" x-text="question.prompt"></h1>
                <p class="mt-1 text-sm text-subtle" x-show="question.type === 'multiple'">Choose all that apply.</p>
                <div class="mt-5">
                    <template x-if="question.type === 'text'">
                        <textarea class="input" rows="4" maxlength="500" x-model="answers[question.id]" placeholder="Their answer"></textarea>
                    </template>
                    <template x-if="question.type === 'rating'">
                        <div class="segmented" style="--cols: 5">
                            <template x-for="choice in question.choices" :key="choice.value">
                                <label><input type="radio" :name="'q' + question.id" :value="choice.value" x-model="answers[question.id]"><span x-text="choice.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="question.type === 'multiple'">
                        <div class="segmented" style="--cols: 1">
                            <template x-for="choice in question.choices" :key="choice.value">
                                <label class="!justify-start !px-4"><input type="checkbox" :checked="(answers[question.id] || []).includes(choice.value)" x-on:change="toggle(question.id, choice.value)"><span x-text="choice.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="['single', 'issue', 'intention'].includes(question.type)">
                        <div class="segmented" style="--cols: 1">
                            <template x-for="choice in question.choices" :key="choice.value">
                                <label class="!justify-start !px-4"><input type="radio" :name="'q' + question.id" :value="choice.value" x-model="answers[question.id]"><span x-text="choice.label"></span></label>
                            </template>
                        </div>
                    </template>
                </div>
            </section>
        </template>

        <template x-if="onAbout">
            <section class="space-y-6">
                <h1 class="text-xl font-bold tracking-tight">About the respondent</h1>
                <p class="-mt-4 text-sm text-muted">All optional. It lets results be compared by age and occupation.</p>
                <div>
                    <p class="label">Gender</p>
                    <div class="segmented" style="--cols: 2">
                        @foreach (config('canvass.genders') as $value => $label)
                            <label><input type="radio" name="s_gender" value="{{ $value }}" x-model="about.gender">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="label">Age</p>
                    <div class="segmented" style="--cols: 3">
                        @foreach (config('canvass.age_bands') as $value => $label)
                            <label><input type="radio" name="s_age" value="{{ $value }}" x-model="about.age_band">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="label">Occupation</p>
                    <div class="segmented" style="--cols: 2">
                        @foreach (config('canvass.occupations') as $value => $label)
                            <label><input type="radio" name="s_occ" value="{{ $value }}" x-model="about.occupation">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label for="s-phone" class="label">Phone <span class="font-normal text-subtle">(optional, stops repeat answers)</span></label>
                    <input id="s-phone" type="tel" inputmode="tel" class="input" x-model="about.phone" placeholder="0803 123 4567">
                </div>
            </section>
        </template>

        <p class="field-error mt-4" x-show="error" x-cloak><x-icon name="circle-alert" /><span x-text="error"></span></p>

        <div class="mt-8 flex gap-3">
            <x-button type="button" variant="secondary" size="xl" icon="arrow-left" square x-show="step > 0" x-on:click="back()" aria-label="Back" />
            <x-button type="button" size="xl" class="flex-1" x-show="! onAbout" x-on:click="next()" icon-right="arrow-right">Next</x-button>
            <x-button type="button" size="xl" class="flex-1" x-show="onAbout" x-cloak x-on:click="finish()" icon="check">Save and ask the next person</x-button>
        </div>
    </div>
</x-layouts.field>
