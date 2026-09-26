<?php

return [

    // Community influence, recorded per ward (never religion per person).
    'influencer_kinds' => [
        'traditional_ruler' => 'Traditional ruler',
        'church' => 'Church',
        'mosque' => 'Mosque',
        'age_grade' => 'Age grade',
        'town_union' => 'Town union',
        'market_association' => 'Market association',
        'youth_group' => 'Youth group',
        'women_group' => 'Women’s group',
        'transport_union' => 'Transport union',
        'other' => 'Other',
    ],

    'relationships' => [
        'ally' => ['label' => 'Ally', 'tone' => 'good'],
        'friendly' => ['label' => 'Friendly', 'tone' => 'good'],
        'neutral' => ['label' => 'Neutral', 'tone' => 'warn'],
        'unfriendly' => ['label' => 'Unfriendly', 'tone' => 'bad'],
        'unknown' => ['label' => 'Not yet engaged', 'tone' => null],
    ],

    'event_types' => [
        'meeting' => 'Ward meeting',
        'town_hall' => 'Town hall',
        'rally' => 'Rally',
        'training' => 'Training',
        'market_storm' => 'Market storm',
        'door_to_door' => 'Door-to-door',
        'stakeholder' => 'Stakeholder visit',
        'other' => 'Other',
    ],

    // Engagement from the last 14 days; a ward is flagged red with no
    // coordinator or no activity in 7 days.
    'engagement_days' => 14,
    'quiet_ward_days' => 7,

];
