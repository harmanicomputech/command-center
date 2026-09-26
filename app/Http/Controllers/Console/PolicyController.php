<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Services\Ai\Prompts;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The policy knowledge base the AI drafts from: the campaign's positions
 * by topic, maintained in the app.
 */
class PolicyController extends Controller
{
    public function index(): View
    {
        $documents = PolicyDocument::query()->with('editor')->orderBy('topic')->orderBy('id')->get();

        return view('messages.policies', [
            'documents' => $documents->groupBy('topic'),
            'words' => str_word_count(Prompts::knowledgeBase()),
            'missing' => collect(config('messaging.policy_topics'))->except($documents->where('active', true)->pluck('topic')->all())->except(['other']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $document = PolicyDocument::create([...$this->validated($request), 'updated_by' => $request->user()->id]);
        Audit::record('policies.create', "Added the policy brief “{$document->title}”", ['policy_id' => $document->id]);

        return redirect()->route('policies')->with('status', 'Policy brief added. New drafts use it straight away.');
    }

    public function update(Request $request, PolicyDocument $policy): RedirectResponse
    {
        $policy->update([...$this->validated($request), 'updated_by' => $request->user()->id]);
        Audit::record('policies.update', "Updated the policy brief “{$policy->title}”", ['policy_id' => $policy->id]);

        return redirect()->route('policies')->with('status', 'Policy brief saved.');
    }

    public function destroy(PolicyDocument $policy): RedirectResponse
    {
        $policy->delete();
        Audit::record('policies.delete', "Deleted the policy brief “{$policy->title}”");

        return redirect()->route('policies')->with('status', 'Policy brief deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'topic' => ['required', Rule::in(array_keys(config('messaging.policy_topics')))],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:20', 'max:20000'],
        ]);

        return [...$data, 'active' => $request->boolean('active')];
    }
}
