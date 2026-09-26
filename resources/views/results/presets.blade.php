<x-layouts.app title="LGA presets">
    <x-page-header title="LGA presets" eyebrow="Intelligence" :back="route('areas')"
        description="Reachability scales each ward’s priority (100% = easy to reach). Labels describe the approach. Starting values follow the brief: Izzi and Ikwo priority mobilisation, Abakaliki an urban digital and media target." />

    <form method="post" action="{{ route('presets.update') }}">
        @csrf @method('put')
        <x-table>
            <thead><tr><th>LGA</th><th class="w-72">Reachability</th><th>Label</th></tr></thead>
            <tbody>
                @foreach ($lgas as $lga)
                    @php
                        $reach = \App\Http\Controllers\Console\PresetController::reachPercent($lga->slug);
                    @endphp
                    <tr x-data="{ reach: {{ $reach }} }">
                        <td class="font-semibold whitespace-nowrap">{{ $lga->name }}</td>
                        <td>
                            <div class="flex items-center gap-3">
                                <input type="range" min="10" max="100" step="5" name="reach[{{ $lga->slug }}]" x-model="reach" class="w-full accent-[var(--brand)]" aria-label="Reachability for {{ $lga->name }}">
                                <span class="num w-12 text-right text-sm font-semibold" x-text="reach + '%'">{{ $reach }}%</span>
                            </div>
                        </td>
                        <td><input name="tag[{{ $lga->slug }}]" value="{{ \App\Services\Intelligence::tag($lga->slug) }}" class="input min-w-48" placeholder="e.g. Priority mobilisation" aria-label="Label for {{ $lga->name }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4 flex justify-end"><x-button icon="check">Save presets</x-button></div>
    </form>
</x-layouts.app>
