<?php

return [

    'task_types' => [
        'door_to_door' => 'Door-to-door',
        'community_meeting' => 'Community meeting',
        'market_storm' => 'Market storm',
        'flyers' => 'Distribute flyers',
        'follow_up' => 'Follow up undecided voters',
        'other' => 'Other',
    ],

    'target_units' => ['households' => 'households', 'people' => 'people', 'flyers' => 'flyers', 'voters' => 'voters'],

    // What proves a task is done.
    'proofs' => ['photo' => 'A photo', 'count' => 'A count', 'none' => 'Nothing'],

    'issue_categories' => [
        'road' => 'Bad road', 'water' => 'Water', 'electricity' => 'Electricity', 'health' => 'Health centre',
        'school' => 'School', 'security' => 'Security', 'flooding' => 'Flooding or erosion', 'market' => 'Market', 'other' => 'Other',
    ],

    'severities' => [
        'low' => ['label' => 'Low', 'tone' => null],
        'medium' => ['label' => 'Medium', 'tone' => 'warn'],
        'high' => ['label' => 'High', 'tone' => 'bad'],
        'critical' => ['label' => 'Critical', 'tone' => 'bad'],
    ],

    'issue_statuses' => [
        'new' => ['label' => 'New', 'tone' => 'info'],
        'noted' => ['label' => 'Noted', 'tone' => null],
        'used' => ['label' => 'Used in a message', 'tone' => 'brand'],
        'addressed' => ['label' => 'Addressed', 'tone' => 'good'],
        'rejected' => ['label' => 'Not accepted', 'tone' => 'bad'],
    ],

    'reward_kinds' => ['airtime' => 'Airtime', 'shout_out' => 'Recognition shout-out', 'data' => 'Data bundle', 'other' => 'Other'],

    // Photos: the phone shrinks them (long side 1600px, JPEG ~0.7) before queuing.
    'photo_max_side' => 1600,
    'photo_thumb_side' => 400,
    'photo_max_kb' => 6144,

    // Anti-gaming review thresholds (flags are for coordinators only, never public).
    'burst_count' => 8,
    'burst_minutes' => 10,
    'all_strong_min' => 20,

];
