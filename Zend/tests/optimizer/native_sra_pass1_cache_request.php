<?php
declare(strict_types=1);

/* Runtime semantics must hold whether classes are freshly loaded, preloaded,
 * read from OPcache shared memory, or restored from its file cache. */
if (ini_get('opcache.preload') && !class_exists('SraCacheFoldable', false)) {
    throw new RuntimeException('Preloaded class is missing');
}
if (!class_exists('SraCacheFoldable', false)) {
    require __DIR__ . '/native_sra_pass1_cache_classes.inc';
}

for ($i = 0; $i < 4096; ++$i) {
    if (SraCacheFoldable::value() !== 42) {
        throw new RuntimeException('Native cache folding returned incorrect value');
    }
}
if (SraCacheExcluded::value() !== 73 || SraCacheExcluded::$constructions !== 1) {
    throw new RuntimeException('Object lifecycle was not preserved');
}

header('Content-Type: application/json');
echo json_encode([
    'sapi' => PHP_SAPI,
    'folded' => SraCacheFoldable::value(),
    'constructor_calls' => SraCacheExcluded::$constructions,
    'opcache_enabled' => function_exists('opcache_get_status')
        ? (bool) (opcache_get_status(false)['opcache_enabled'] ?? false)
        : false,
], JSON_THROW_ON_ERROR), "\n";
