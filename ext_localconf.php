<?php

defined('TYPO3') or defined('TYPO3_MODE') or die();

// TYPO3 10 registers status providers here, from TYPO3 12 on the interface is autoconfigured
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['reports']['tx_reports']['status']['providers']['Tor Blocker'][]
    = \StraschekIo\TorBlocker\Report\ExitNodeListStatus::class;
