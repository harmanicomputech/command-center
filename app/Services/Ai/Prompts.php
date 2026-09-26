<?php

namespace App\Services\Ai;

use App\Models\PolicyDocument;
use App\Support\Settings;

/**
 * The stable part of every prompt: who we are and the rules, then the
 * policy knowledge base. Both are deterministic (no dates, fixed order), so
 * the prompt cache holds across drafts until a policy brief changes.
 */
class Prompts
{
    /**
     * @return list<array{text: string, cache?: bool}>
     */
    public static function system(): array
    {
        return [
            ['text' => self::instructions()],
            ['text' => self::knowledgeBase(), 'cache' => true],
        ];
    }

    public static function instructions(): string
    {
        $candidate = Settings::get('campaign.candidate') ?: 'our candidate';
        $party = Settings::get('campaign.party');
        $partySuffix = $party ? " ({$party})" : '';

        return <<<TEXT
        You write campaign communications for {$candidate}'s governorship campaign in Ebonyi State, Nigeria{$partySuffix}.
        The election is on 6 February 2027. Your drafts are reviewed and edited by the campaign's communications team before anything is used; nothing you write is sent automatically.

        How to write:
        - Speak to the audience's daily life: their work, their roads, markets, water, farms, schools and clinics. Be concrete and local, using the place names and issue counts you are given.
        - Only promise what the policy brief below supports. If the brief says nothing about something, don't invent a policy, a figure or a project.
        - Use the numbers you are given as they are ("14 reports about the road"); never make up statistics, names, quotes or endorsements.
        - Be respectful and hopeful. No insults, no claims about opponents' private lives, and nothing that divides people by religion, ethnicity, clan or gender.
        - Never ask for money, voter cards (PVCs), or personal details, and never suggest vote buying or anything unlawful.
        - Igbo: write natural Ebonyi-readable Igbo with the correct dotted vowels (ị, ọ, ụ) and tone-free spelling. "Both" means an English version followed by an Igbo version.
        - SMS must fit the character limit given, including spaces; don't add a sign-off beyond the candidate's name unless there is room.
        TEXT;
    }

    public static function knowledgeBase(): string
    {
        $documents = PolicyDocument::query()->where('active', true)->orderBy('topic')->orderBy('id')->get(['topic', 'title', 'body']);

        if ($documents->isEmpty()) {
            return "POLICY BRIEF\n\nThe campaign has not written its policy brief yet. Keep drafts general and focused on listening, and do not make specific policy promises.";
        }

        $sections = $documents->groupBy('topic')->map(function ($group, $topic) {
            $label = config("messaging.policy_topics.{$topic}", $topic);

            return "## {$label}\n\n".$group->map(fn ($doc) => "### {$doc->title}\n".trim($doc->body))->implode("\n\n");
        });

        return "POLICY BRIEF (the campaign's positions; the only source for promises)\n\n".$sections->implode("\n\n");
    }
}
