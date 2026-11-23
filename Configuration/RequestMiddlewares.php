<?php

return [
    'frontend' => [
        'straschek-io/typo3-tor-blocker' => [
            'target' => \StraschekIo\TorBlocker\Middleware\TorBlockerMiddleware::class,
            'description' => 'Answers requests from Tor exit nodes with 403 before anything else runs',
            'before' => [
                'staticfilecache/fallback',
                'typo3/cms-frontend/timetracker',
            ],
        ],
    ],
    'backend' => [
        'straschek-io/typo3-tor-blocker' => [
            'target' => \StraschekIo\TorBlocker\Middleware\BackendTorBlockerMiddleware::class,
            'description' => 'Answers backend requests from Tor exit nodes with 403 before the login runs',
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-backend/locked-backend',
            ],
        ],
    ],
];
