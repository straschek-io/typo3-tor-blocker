<?php

$EM_CONF['tor_blocker'] = [
    'title' => 'Tor Blocker',
    'description' => 'Blocks frontend requests from Tor exit nodes',
    'category' => 'fe',
    'author' => 'Michael Straschek',
    'author_email' => 'hallo@straschek.io',
    'state' => 'stable',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-14.3.99',
            'fluid' => '10.4.0-14.3.99',
            'php' => '7.4.0-8.5.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'StraschekIo\\TorBlocker\\' => 'Classes/',
        ],
    ],
];
