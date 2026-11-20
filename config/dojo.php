<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passing score
    |--------------------------------------------------------------------------
    |
    | Minimum attempt score (0-100) that marks a tier as passed and unlocks
    | the next tier of the same module.
    |
    */

    'pass_score' => (int) env('DOJO_PASS_SCORE', 80),

    /*
    |--------------------------------------------------------------------------
    | Default quiz sizes
    |--------------------------------------------------------------------------
    |
    | How many questions one attempt draws per tier when a module file does
    | not define its own `quiz` sizes.
    |
    */

    'quiz_sizes' => [
        'A' => 10,
        'B' => 8,
        'C' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scenario pass ratio
    |--------------------------------------------------------------------------
    |
    | Default fraction (0-1) of checklist items a scenario answer must hit
    | when the question does not set its own `pass_score`.
    |
    */

    'scenario_pass_ratio' => 0.6,

    /*
    |--------------------------------------------------------------------------
    | Content directory
    |--------------------------------------------------------------------------
    |
    | Where module files live for `php artisan dojo:import` and the seeder.
    |
    */

    'content_path' => base_path('content'),

];
