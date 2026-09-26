<x-layouts.base :title="$survey->title">
    <main id="main" class="mx-auto max-w-xl px-4 py-10 sm:py-14">
        <div class="mb-8 flex items-center gap-3"><x-logo :size="36" /><span class="text-lg font-bold tracking-tight">{{ $appName }}</span></div>

        @if ($done)
            <div class="card p-8 text-center">
                <div class="mx-auto mb-5 grid size-16 place-items-center rounded-full bg-brand text-on-brand"><x-icon name="check" size="30" /></div>
                <h1 class="text-2xl font-bold tracking-tight">Thank you</h1>
                <p class="mt-2 text-muted">Your answers are saved.</p>
            </div>
        @elseif (! $open)
            <div class="card"><x-empty icon="lock" title="This survey is closed" description="Thank you for your interest." /></div>
        @else
            <h1 class="text-3xl font-bold tracking-tight">{{ $survey->title }}</h1>
            @if ($survey->intro)<p class="mt-3 text-base text-muted">{{ $survey->intro }}</p>@endif
            <p class="mt-3 text-sm text-subtle">Your answers are anonymous. See our <a href="{{ route('privacy') }}" class="link">privacy notice</a>.</p>

            @if ($errors->any())<x-alert tone="bad" class="mt-6">{{ $errors->first() }}</x-alert>@endif

            <form method="post" action="{{ route('survey.public.store', $survey->web_token) }}" class="mt-8 space-y-8">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                @foreach ($survey->questions as $i => $question)
                    <fieldset class="card p-5">
                        <legend class="sr-only">{{ $question->prompt }}</legend>
                        <p class="mb-4 font-semibold">{{ $i + 1 }}. {{ $question->prompt }}@unless ($question->required) <span class="font-normal text-subtle">(optional)</span>@endunless</p>
                        @if ($question->type === 'text')
                            <textarea name="answers[{{ $question->id }}]" class="input" rows="3" maxlength="500" aria-label="{{ $question->prompt }}">{{ old('answers.'.$question->id) }}</textarea>
                        @else
                            <div class="segmented" style="--cols: {{ $question->type === 'rating' ? 5 : 1 }}">
                                @foreach ($question->choices() as $value => $label)
                                    <label class="{{ $question->type === 'rating' ? '' : '!justify-start !px-4' }}">
                                        <input type="{{ $question->type === 'multiple' ? 'checkbox' : 'radio' }}" name="answers[{{ $question->id }}]{{ $question->type === 'multiple' ? '[]' : '' }}" value="{{ $value }}"
                                            @checked(in_array((string) $value, (array) old('answers.'.$question->id, []), true))>{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                @endforeach

                <fieldset class="card space-y-5 p-5" x-data="{ lga: '' }">
                    <legend class="sr-only">About you</legend>
                    <p class="font-semibold">About you <span class="font-normal text-subtle">(optional)</span></p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="p-lga">LGA</label>
                            <select id="p-lga" class="input" x-model="lga">
                                <option value="">Choose</option>
                                @foreach ($lgas as $lga)<option value="{{ $lga->id }}">{{ $lga->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="p-ward">Ward</label>
                            <select id="p-ward" name="ward_id" class="input">
                                <option value="">Choose</option>
                                @foreach ($lgas as $lga)
                                    @foreach ($lga->wards as $ward)<option value="{{ $ward->id }}" x-show="lga === '{{ $lga->id }}'">{{ $ward->name }}</option>@endforeach
                                @endforeach
                            </select>
                        </div>
                        <x-select name="age_band" label="Age" :options="config('canvass.age_bands')" placeholder="Choose" />
                        <x-select name="occupation" label="Occupation" :options="config('canvass.occupations')" placeholder="Choose" />
                    </div>
                    <x-input name="phone" type="tel" label="Phone number" optional hint="Only so nobody answers twice. We don’t keep the number." />
                </fieldset>

                <x-button size="xl" icon="send" class="w-full">Send my answers</x-button>
            </form>
        @endif
    </main>
</x-layouts.base>
