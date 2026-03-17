<?php

if (! defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

require_once __DIR__ . '/src/FreshdeskImportAdapter.php';
require_once __DIR__ . '/src/FreshdeskClient.php';
require_once __DIR__ . '/src/FreshdeskFieldMapper.php';

use Escalated\Plugins\ImportFreshdesk\FreshdeskImportAdapter;

escalated_add_filter('import.adapters', function (array $adapters) {
    $adapters[] = new FreshdeskImportAdapter();
    return $adapters;
}, 10);
