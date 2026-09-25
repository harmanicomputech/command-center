<?php

/*
| The choices on the field registration form. Keys are stored; labels are
| shown. Add options at the end: stored keys must never change meaning.
*/

return [

    'genders' => ['female' => 'Female', 'male' => 'Male'],

    'age_bands' => ['18-24' => '18–24', '25-34' => '25–34', '35-44' => '35–44', '45-59' => '45–59', '60+' => '60+'],

    'occupations' => [
        'farmer' => 'Farmer', 'trader' => 'Trader', 'civil_servant' => 'Civil servant', 'student' => 'Student',
        'artisan' => 'Artisan', 'transport' => 'Transport', 'unemployed' => 'Unemployed', 'other' => 'Other',
    ],

    // Ordered from us to the opponent; 'weight' is how much each counts
    // towards "our share" in voter intelligence (phase 5).
    'support_levels' => [
        'strong' => ['label' => 'Strong supporter', 'weight' => 1.0, 'tone' => 'good'],
        'leaning' => ['label' => 'Leaning us', 'weight' => 0.75, 'tone' => 'good'],
        'undecided' => ['label' => 'Undecided', 'weight' => 0.5, 'tone' => 'warn'],
        'leaning_opponent' => ['label' => 'Leaning opponent', 'weight' => 0.25, 'tone' => 'bad'],
        'opponent' => ['label' => 'Opponent', 'weight' => 0.0, 'tone' => 'bad'],
    ],

    'issues' => [
        'roads' => 'Roads', 'water' => 'Water', 'jobs' => 'Youth jobs', 'agriculture' => 'Agriculture',
        'markets' => 'Markets', 'security' => 'Security', 'health' => 'Health', 'education' => 'Education',
        'electricity' => 'Electricity', 'other' => 'Other',
    ],

    /*
    | Consent. Change the version whenever the text changes: each record keeps
    | the version its voter agreed to.
    */
    'consent_version' => '1',

    'consent_text' => 'I agree that the campaign can store my details and contact me.',

];
