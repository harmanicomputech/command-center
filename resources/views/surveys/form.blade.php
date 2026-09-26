@php
    $questions = $survey
        ? $survey->questions->map(fn ($q) => ['type' => $q->type, 'prompt' => $q->prompt, 'options' => $q->options ?? [], 'required' => $q->required])->values()
        : collect([['type' => 'intention', 'prompt' => 'If the election were today, who would you vote for?', 'options' => [], 'required' => true], ['type' => 'issue', 'prompt' => 'Which issue matters most to you?', 'options' => [], 'required' => true]]);
    $questions = old('questions') ? json_decode(old('questions'), true) : $questions;
    $leader = auth()->user()->role === \App\Enums\UserRole::LgaLeader;
@endphp
<x-layouts.app :title="$survey ? 'Edit survey' : 'New survey'">
    <x-page-header :title="$survey ? 'Edit survey' : 'New survey'" eyebrow="Surveys" :back="route('surveys')" description="Short surveys get more answers: five questions or fewer is best in the field." />

    <form method="post" action="{{ $survey ? route('surveys.update', $survey) : route('surveys.store') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]"
        x-data="{ questions: @js($questions), types: @js(config('surveys.types')) }">
        @csrf
        @if ($survey) @method('put') @endif
        <input type="hidden" name="questions" :value="JSON.stringify(questions)">

        <div class="min-w-0 space-y-4">
            <x-card>
                <div class="space-y-5">
                    <x-input name="title" label="Title" :value="$survey?->title" required placeholder="e.g. Izzi voter pulse, October" />
                    <x-textarea name="intro" label="Introduction read to respondents" :value="$survey?->intro" optional rows="2" />
                </div>
            </x-card>

            @error('questions')<x-alert tone="bad">{{ $message }}</x-alert>@enderror

            <template x-for="(question, index) in questions" :key="index">
                <section class="card p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <span class="eyebrow" x-text="'Question ' + (index + 1)"></span>
                        <div class="flex gap-1">
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" x-on:click="index > 0 && questions.splice(index - 1, 0, questions.splice(index, 1)[0])" :disabled="index === 0" aria-label="Move up"><x-icon name="chevron-up" /></button>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" x-on:click="index < questions.length - 1 && questions.splice(index + 1, 0, questions.splice(index, 1)[0])" :disabled="index === questions.length - 1" aria-label="Move down"><x-icon name="chevron-down" /></button>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" x-on:click="questions.splice(index, 1)" aria-label="Remove question"><x-icon name="trash-2" /></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[200px_minmax(0,1fr)]">
                        <select class="input" x-model="question.type" aria-label="Type">
                            <template x-for="(label, key) in types" :key="key"><option :value="key" x-text="label" :selected="key === question.type"></option></template>
                        </select>
                        <input class="input" x-model="question.prompt" placeholder="The question, as it will be read" aria-label="Question">
                    </div>
                    <div class="mt-4 space-y-2" x-show="question.type === 'single' || question.type === 'multiple'">
                        <template x-for="(option, i) in question.options" :key="i">
                            <div class="flex gap-2">
                                <input class="input" x-model="question.options[i]" :placeholder="'Choice ' + (i + 1)" aria-label="Choice">
                                <button type="button" class="btn btn-ghost btn-icon" x-on:click="question.options.splice(i, 1)" aria-label="Remove choice"><x-icon name="x" /></button>
                            </div>
                        </template>
                        <button type="button" class="btn btn-soft btn-sm" x-on:click="question.options.push('')"><x-icon name="plus" />Add a choice</button>
                    </div>
                    <p class="mt-3 text-sm text-muted" x-show="question.type === 'intention'">Choices: definitely / probably our candidate, undecided, probably / definitely another. These answers feed the ward zones.</p>
                    <p class="mt-3 text-sm text-muted" x-show="question.type === 'issue'">Choices: the campaign’s issue list (roads, water, jobs…).</p>
                    <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" x-model="question.required"> Required</label>
                </section>
            </template>
            <button type="button" class="btn btn-secondary w-full" x-on:click="questions.push({ type: 'single', prompt: '', options: ['', ''], required: true })"><x-icon name="plus" />Add a question</button>
        </div>

        <div class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Where and how">
                <div class="space-y-5">
                    @unless ($leader)
                        <fieldset>
                            <legend class="label">LGAs <span class="font-normal text-subtle">(none = all)</span></legend>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($lgas as $lga)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="lga_ids[]" value="{{ $lga->id }}" class="peer sr-only" @checked(in_array($lga->id, old('lga_ids', $survey?->lga_ids ?? [])))>
                                        <span class="inline-flex h-8 items-center rounded-full border border-line-strong px-3 text-[13px] font-medium peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-fg">{{ $lga->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <p class="text-sm text-muted">Runs in {{ auth()->user()->lga?->name }}.</p>
                    @endunless
                    <x-input name="quota_per_ward" type="number" min="1" label="Responses wanted per ward" :value="$survey?->quota_per_ward" optional hint="Agents stop seeing it once their ward reaches this." />
                    <fieldset>
                        <legend class="label">Channels</legend>
                        <div class="space-y-2">
                            @foreach (config('surveys.channels') as $key => $label)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, old('channels', $survey?->channels ?? ['field']), true))> {{ $label }}</label>
                            @endforeach
                        </div>
                        @error('channels')<p class="field-error"><x-icon name="circle-alert" />{{ $message }}</p>@enderror
                    </fieldset>
                    <x-input name="sms_keyword" label="SMS keyword" :value="$survey?->sms_keyword" optional placeholder="e.g. PULSE" hint="Texting “PULSE 2” answers the first question with choice 2." />
                </div>
            </x-card>
            <x-button icon="check" class="w-full" size="lg">Save draft</x-button>
        </div>
    </form>
</x-layouts.app>
