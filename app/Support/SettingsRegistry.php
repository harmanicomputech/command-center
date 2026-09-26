<?php

namespace App\Support;

/**
 * The settings the owner edits on the Settings page, grouped, with their
 * defaults. Every placeholder the owner still has to decide lives here, so
 * features work before the real values are in.
 */
class SettingsRegistry
{
    /**
     * @return array<string, array{title: string, description: string, fields: array<string, array{label: string, type: string, default: string, help?: string, min?: int|float, max?: int|float, suffix?: string}>}>
     */
    public static function groups(): array
    {
        return [
            'campaign' => [
                'title' => 'Campaign',
                'description' => 'How the app names the campaign and the candidate.',
                'fields' => [
                    'campaign.name' => ['label' => 'App name', 'type' => 'text', 'default' => 'Command Center', 'help' => 'Shown in the sidebar, the phone app and printouts.'],
                    'campaign.candidate' => ['label' => 'Candidate', 'type' => 'text', 'default' => '', 'help' => 'Used in AI message drafts and news keyword alerts.'],
                    'campaign.party' => ['label' => 'Party', 'type' => 'text', 'default' => '', 'help' => 'The party code on the ballot, e.g. APC. Our share in past results is this party’s.'],
                ],
            ],
            'intelligence' => [
                'title' => 'Voter intelligence',
                'description' => 'How “our share” blends its sources, and where the zone lines are. Sources with no data are left out and the others re-weighted.',
                'fields' => [
                    'intel.weight_results' => ['label' => 'Weight: past results', 'type' => 'int', 'default' => '50', 'min' => 0, 'max' => 100, 'suffix' => '%'],
                    'intel.weight_canvass' => ['label' => 'Weight: canvassing', 'type' => 'int', 'default' => '35', 'min' => 0, 'max' => 100, 'suffix' => '%'],
                    'intel.weight_survey' => ['label' => 'Weight: surveys', 'type' => 'int', 'default' => '15', 'min' => 0, 'max' => 100, 'suffix' => '%'],
                    'intel.min_sample' => ['label' => 'Smallest canvass or survey sample to use', 'type' => 'int', 'default' => '30', 'min' => 1, 'max' => 10000, 'suffix' => 'people', 'help' => 'Below this, a ward’s canvassing or survey figure is left out of its share.'],
                    'intel.stronghold' => ['label' => 'Stronghold from', 'type' => 'int', 'default' => '55', 'min' => 1, 'max' => 100, 'suffix' => '%'],
                    'intel.swing' => ['label' => 'Swing from', 'type' => 'int', 'default' => '40', 'min' => 1, 'max' => 100, 'suffix' => '%', 'help' => 'Below this is weak.'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy',
                'description' => 'Shown on the public privacy notice linked from the registration form.',
                'fields' => [
                    'privacy.controller' => ['label' => 'Data controller', 'type' => 'text', 'default' => '', 'help' => 'The campaign organisation’s legal name. Registering with the NDPC as a data controller is recommended.'],
                    'privacy.dpo' => ['label' => 'Data protection officer', 'type' => 'text', 'default' => '', 'help' => 'A name and a way to reach them, e.g. an office phone or a generic email.'],
                ],
            ],
            'messaging' => [
                'title' => 'Messaging and AI',
                'description' => 'Placeholders until the campaign decides. The API keys are on the System page.',
                'fields' => [
                    'ai.monthly_budget' => ['label' => 'AI budget per month', 'type' => 'int', 'default' => '50', 'min' => 0, 'max' => 100000, 'suffix' => 'US$', 'help' => 'Drafting stops for the month once the logged cost reaches this. 0 means no limit.'],
                    'sms.cost_per_part' => ['label' => 'SMS cost per message part', 'type' => 'text', 'default' => '4.00', 'help' => 'In naira, for the cost preview before a broadcast. Check the Africa’s Talking price for your sender ID.'],
                    'sms.footer' => ['label' => 'Opt-out line added to every SMS', 'type' => 'text', 'default' => 'Reply STOP to opt out', 'help' => 'Keep it short: it counts towards the 160 characters.'],
                    'news.keywords' => ['label' => 'News alert keywords', 'type' => 'text', 'default' => 'Ebonyi, governorship, Abakaliki', 'help' => 'Comma-separated: opponents’ names, issues, places. The candidate’s name is always included.'],
                ],
            ],
            'targets' => [
                'title' => 'Registration targets',
                'description' => 'Placeholders until the campaign sets real targets. Progress rings and the dashboard use them.',
                'fields' => [
                    'target.agent_daily' => ['label' => 'Per agent, per day', 'type' => 'int', 'default' => '20', 'min' => 1, 'max' => 1000, 'suffix' => 'voters'],
                    'target.ward_daily' => ['label' => 'Per ward, per day', 'type' => 'int', 'default' => '100', 'min' => 1, 'max' => 100000, 'suffix' => 'voters'],
                    'target.total' => ['label' => 'Campaign total', 'type' => 'int', 'default' => '500000', 'min' => 1, 'max' => 10000000, 'suffix' => 'voters'],
                ],
            ],
            'points' => [
                'title' => 'Points',
                'description' => 'What each action earns on the leaderboards. Points are worked out from the records, so a change applies to everyone’s totals at once.',
                'fields' => [
                    'points.registration_verified' => ['label' => 'Verified registration', 'type' => 'int', 'default' => '10', 'min' => 0, 'max' => 1000, 'suffix' => 'points'],
                    'points.registration_unverified' => ['label' => 'Registration, not yet verified', 'type' => 'int', 'default' => '3', 'min' => 0, 'max' => 1000, 'suffix' => 'points', 'help' => 'Goes up to the verified amount once a coordinator verifies it.'],
                    'points.task' => ['label' => 'Task completed with proof', 'type' => 'int', 'default' => '15', 'min' => 0, 'max' => 1000, 'suffix' => 'points'],
                    'points.issue' => ['label' => 'Issue report accepted', 'type' => 'int', 'default' => '5', 'min' => 0, 'max' => 1000, 'suffix' => 'points'],
                    'points.survey' => ['label' => 'Survey response', 'type' => 'int', 'default' => '2', 'min' => 0, 'max' => 1000, 'suffix' => 'points'],
                    'points.event' => ['label' => 'Event attendance', 'type' => 'int', 'default' => '5', 'min' => 0, 'max' => 1000, 'suffix' => 'points'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, type: string, default: string}>
     */
    public static function fields(): array
    {
        return array_merge(...array_values(array_map(fn (array $group) => $group['fields'], self::groups())));
    }

    public static function default(string $key): ?string
    {
        return self::fields()[$key]['default'] ?? null;
    }
}
