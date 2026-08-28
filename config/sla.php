<?php

return [
    'priorities' => [
        'low' => [
            'first_response_minutes' => 480,
            'resolution_minutes' => 4320,
        ],
        'medium' => [
            'first_response_minutes' => 240,
            'resolution_minutes' => 1440,
        ],
        'high' => [
            'first_response_minutes' => 60,
            'resolution_minutes' => 480,
        ],
        'urgent' => [
            'first_response_minutes' => 30,
            'resolution_minutes' => 240,
        ],
    ],
];
