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
            'privacy' => [
                'title' => 'Privacy',
                'description' => 'Shown on the public privacy notice linked from the registration form.',
                'fields' => [
                    'privacy.controller' => ['label' => 'Data controller', 'type' => 'text', 'default' => '', 'help' => 'The campaign organisation’s legal name. Registering with the NDPC as a data controller is recommended.'],
                    'privacy.dpo' => ['label' => 'Data protection officer', 'type' => 'text', 'default' => '', 'help' => 'A name and a way to reach them, e.g. an office phone or a generic email.'],
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
