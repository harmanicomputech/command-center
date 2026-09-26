<?php

return [

    /*
    | AI drafting (Claude, through the official Anthropic PHP SDK). The key is
    | ANTHROPIC_API_KEY in .env, or set on the System page (stored encrypted).
    | Prices are US dollars per million tokens, used for the cost log; cache
    | reads cost 0.1× and cache writes 1.25× the input price.
    */
    'ai' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        'max_tokens' => 16000,
        'timeout' => 90,
        'prices' => [
            'claude-opus-5' => [5.00, 25.00],
            'claude-opus-4-8' => [5.00, 25.00],
            'claude-sonnet-5' => [2.00, 10.00],
            'claude-haiku-4-5' => [1.00, 5.00],
        ],
        'default_price' => [5.00, 25.00],
    ],

    'channels' => [
        'sms' => ['label' => 'SMS', 'hint' => 'One SMS: 160 characters', 'limit' => 160],
        'whatsapp' => ['label' => 'WhatsApp', 'hint' => 'A short message to forward', 'limit' => 700],
        'radio' => ['label' => 'Radio script', 'hint' => 'About 60 seconds read aloud', 'limit' => 1200],
        'town_hall' => ['label' => 'Town-hall points', 'hint' => 'Talking points for a speaker', 'limit' => 1500],
        'flyer' => ['label' => 'Flyer', 'hint' => 'A headline and a few lines', 'limit' => 600],
    ],

    'languages' => [
        'en' => 'English',
        'ig' => 'Igbo',
        'both' => 'English and Igbo',
    ],

    'tones' => [
        'hopeful' => 'Hopeful',
        'practical' => 'Practical',
        'urgent' => 'Urgent',
        'respectful' => 'Respectful, for elders',
    ],

    // The policy knowledge base: one or more briefs per topic.
    'policy_topics' => [
        'general' => 'Candidate and vision', 'agriculture' => 'Agriculture', 'jobs' => 'Youth jobs', 'markets' => 'Markets',
        'roads' => 'Roads', 'water' => 'Water', 'security' => 'Security', 'education' => 'Education',
        'health' => 'Health', 'electricity' => 'Electricity', 'other' => 'Other',
    ],

    // Narratives: what people are saying, reported by agents and the media team.
    'sources' => [
        'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp', 'radio' => 'Radio', 'market' => 'Market talk',
        'blog' => 'Blog or news site', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'church' => 'Church or mosque', 'other' => 'Other',
    ],

    'narrative_topics' => [
        'candidate' => 'Our candidate', 'opponent' => 'An opponent', 'party' => 'The party', 'election' => 'The election',
        'roads' => 'Roads', 'water' => 'Water', 'jobs' => 'Jobs', 'agriculture' => 'Agriculture', 'markets' => 'Markets',
        'security' => 'Security', 'health' => 'Health', 'education' => 'Education', 'electricity' => 'Electricity', 'other' => 'Other',
    ],

    'narrative_tones' => [
        'positive' => ['label' => 'Positive', 'tone' => 'good'],
        'neutral' => ['label' => 'Neutral', 'tone' => null],
        'negative' => ['label' => 'Negative', 'tone' => 'bad'],
    ],

    'narrative_statuses' => [
        'new' => ['label' => 'New', 'tone' => 'info'],
        'watching' => ['label' => 'Watching', 'tone' => 'warn'],
        'responding' => ['label' => 'Responding', 'tone' => 'brand'],
        'closed' => ['label' => 'Closed', 'tone' => null],
    ],

    // A narrative "spikes" (push alert) at this many reports in 24 hours.
    'spike_reports' => 5,

    'platforms' => ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp channel'],

    // News tracker: fetched from the background runner.
    'news_every_minutes' => 30,
    'news_keep_days' => 60,

    // SMS: Africa's Talking bulk SMS in batches.
    'sms_batch' => 100,

];
