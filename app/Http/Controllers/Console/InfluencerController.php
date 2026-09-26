<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Influencer;
use App\Models\Ward;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Community influence notes per ward (institutions and leaders, with a
 * contact and the relationship). Religion is recorded here at community
 * level only, never per person.
 */
class InfluencerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $kind = array_key_exists((string) $request->query('kind'), config('structure.influencer_kinds')) ? $request->query('kind') : null;
        $relationship = array_key_exists((string) $request->query('relationship'), config('structure.relationships')) ? $request->query('relationship') : null;

        return view('influencers.index', [
            'influencers' => Influencer::query()->inAreaOf($user)->with('ward.lga')
                ->when($kind, fn ($query) => $query->where('kind', $kind))
                ->when($relationship, fn ($query) => $query->where('relationship', $relationship))
                ->orderBy('name')->paginate(40)->withQueryString(),
            'kind' => $kind,
            'relationship' => $relationship,
            'wards' => Ward::query()->visibleTo($user)->with('lga')->orderBy('name')->get()->mapWithKeys(fn (Ward $ward) => [$ward->id => $ward->fullName()])->all(),
            'counts' => Influencer::query()->inAreaOf($user)->selectRaw('relationship, count(*) as n')->groupBy('relationship')->pluck('n', 'relationship'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $influencer = Influencer::create([...$data, 'created_by' => $request->user()->id]);
        Audit::record('influencers.create', "Added influence note “{$influencer->name}”", ['influencer_id' => $influencer->id]);

        return back()->with('status', "{$influencer->name} is added to {$influencer->ward->name}.");
    }

    public function update(Request $request, Influencer $influencer): RedirectResponse
    {
        abort_unless($request->user()->canSeeWard($influencer->ward), 403);
        $influencer->update($this->validated($request));

        return back()->with('status', "{$influencer->name} is updated.");
    }

    public function destroy(Request $request, Influencer $influencer): RedirectResponse
    {
        abort_unless($request->user()->canSeeWard($influencer->ward), 403);
        $influencer->delete();
        Audit::record('influencers.delete', "Deleted influence note “{$influencer->name}”");

        return back()->with('status', "{$influencer->name} is removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'ward_id' => ['required', 'integer'],
            'kind' => ['required', Rule::in(array_keys(config('structure.influencer_kinds')))],
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'relationship' => ['required', Rule::in(array_keys(config('structure.relationships')))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ward = Ward::query()->visibleTo($request->user())->find($data['ward_id']);
        abort_unless($ward !== null, 403, 'Choose a ward in your area.');

        if (filled($data['contact_phone'] ?? null)) {
            $data['contact_phone'] = Phone::normalize($data['contact_phone'])
                ?? throw ValidationException::withMessages(['contact_phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }

        return $data;
    }
}
