<?php

return [

    // What a volunteer can offer (the /join page and the website form).
    'help' => [
        'canvassing' => 'Door-to-door canvassing',
        'polling_agent' => 'Polling unit agent on election day',
        'mobilisation' => 'Mobilising my community',
        'events' => 'Rallies and events',
        'social_media' => 'Social media',
        'transport' => 'Transport and logistics',
        'skills' => 'Professional skills (legal, medical, IT…)',
        'other' => 'Something else',
    ],

    'statuses' => [
        'new' => ['label' => 'New', 'tone' => 'info'],
        'contacted' => ['label' => 'Contacted', 'tone' => 'warn'],
        'joined' => ['label' => 'Joined the team', 'tone' => 'good'],
        'not_now' => ['label' => 'Not now', 'tone' => null],
    ],

    // Change the version whenever the text changes: each sign-up keeps the
    // version it agreed to.
    'consent_version' => '1',
    'consent_text' => 'I agree that the campaign can store my details and contact me about volunteering.',

];
