<?php

return [

    'types' => [
        'single' => 'Single choice',
        'multiple' => 'Multiple choice',
        'rating' => 'Rating (1–5)',
        'text' => 'Short text',
        'issue' => 'Which issue matters most',
        'intention' => 'Voting intention',
    ],

    // The answers to a voting-intention question, and how much each counts
    // towards "our share" (the same scale as canvass support levels).
    'intention_options' => [
        'definitely_us' => ['label' => 'Definitely our candidate', 'weight' => 1.0],
        'probably_us' => ['label' => 'Probably our candidate', 'weight' => 0.75],
        'undecided' => ['label' => 'Undecided', 'weight' => 0.5],
        'probably_other' => ['label' => 'Probably another candidate', 'weight' => 0.25],
        'definitely_other' => ['label' => 'Definitely another candidate', 'weight' => 0.0],
    ],

    'channels' => ['field' => 'Agents in the field', 'web' => 'Web link', 'sms' => 'SMS / USSD'],

    // Figures resting on fewer responses are faded and marked "small sample".
    'small_sample' => 30,

];
