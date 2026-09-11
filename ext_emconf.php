<?php

$EM_CONF['tor_blocker'] = [
    'title' => 'Tor Blocker',
    'description' => 'Blocks frontend requests from Tor exit nodes',
    'category' => 'fe',
    'author' => 'Michael Straschek',
    'author_email' => 'hallo@straschek.io',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-13.4.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'StraschekIo\\TorBlocker\\' => 'Classes/',
        ],
    ],
];
