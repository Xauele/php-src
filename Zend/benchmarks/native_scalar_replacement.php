<?php
/**
 * Baseline for a possible JIT scalar-replacement optimization.
 *
 * Each workload produces the same integer checksum. This benchmark compares
 * distinct programs and must NOT be interpreted as the measured speedup of an
 * optimizer that does not exist yet.
 *
 * Suggested commands (from the repository root, after building PHP):
 *   sapi/cli/php -n -d zend_extension=opcache -d opcache.enable_cli=1 \
 *     -d opcache.file_update_protection=0 -d opcache.jit_buffer_size=0 \
 *     Zend/benchmarks/native_scalar_replacement.php
 *   sapi/cli/php -n -d zend_extension=opcache -d opcache.enable_cli=1 \
 *     -d opcache.file_update_protection=0 -d opcache.jit=tracing \
 *     -d opcache.jit_buffer_size=128M \
 *     Zend/benchmarks/native_scalar_replacement.php
 */

declare(strict_types=1);

final class NativeScalarReplacementPair
{
    public int $x = 0;
    public int $y = 0;
}

function sraScalarLoop(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; ++$i) {
        $x = $i;
        $y = $i * 2;
        $sum += $x + $y;
    }
    return $sum;
}

function sraObjectLoop(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; ++$i) {
        $pair = new NativeScalarReplacementPair();
        $pair->x = $i;
        $pair->y = $i * 2;
        $sum += $pair->x + $pair->y;
    }
    return $sum;
}

function sraArrayLoop(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; ++$i) {
        $pair = [$i, $i * 2];
        $sum += $pair[0] + $pair[1];
    }
    return $sum;
}

if (PHP_INT_SIZE !== 8) {
    fwrite(STDERR, "This microbenchmark requires 64-bit PHP.\n");
    exit(2);
}

$iterations = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT) : 2_000_000;
if (!is_int($iterations) || $iterations < 1 || $iterations > 10_000_000) {
    fwrite(STDERR, "Usage: php native_scalar_replacement.php [1..10000000]\n");
    exit(2);
}

$cases = [
    'scalar' => 'sraScalarLoop',
    'object' => 'sraObjectLoop',
    'array' => 'sraArrayLoop',
];
$expected = intdiv(3 * ($iterations - 1) * $iterations, 2);
$samples = array_fill_keys(array_keys($cases), []);

// Warm up all three functions. JIT compilation must not be measured.
foreach ($cases as $run) {
    for ($i = 0; $i < 3; ++$i) {
        $run(20_000);
    }
}

// Rotate the case order to reduce systematic CPU/frequency bias.
$names = array_keys($cases);
for ($trial = 0; $trial < 7; ++$trial) {
    for ($j = 0; $j < count($names); ++$j) {
        $name = $names[($trial + $j) % count($names)];
        $run = $cases[$name];
        $start = hrtime(true);
        $actual = $run($iterations);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        if ($actual !== $expected) {
            throw new RuntimeException("Incorrect result for {$name}: {$actual} != {$expected}");
        }
        $samples[$name][] = round($elapsedMs, 3);
    }
}

$measurements = [];
foreach ($samples as $name => $values) {
    sort($values, SORT_NUMERIC);
    $measurements[$name] = [
        'median_ms' => $values[3],
        'samples_ms' => $values,
    ];
}

$jit = function_exists('opcache_get_status') ? (opcache_get_status(false)['jit'] ?? null) : null;
echo json_encode([
    'php' => PHP_VERSION,
    'architecture_bits' => PHP_INT_SIZE * 8,
    'jit_enabled' => (bool) ($jit['on'] ?? false),
    'jit_kind' => $jit['kind'] ?? null,
    'iterations' => $iterations,
    'trials' => 7,
    'checksum' => $expected,
    'measurements' => $measurements,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
