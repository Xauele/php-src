<?php
/**
 * Microbenchmark for the opt-in native pass1 proof of concept.
 * Compare SAME function with and without the C macro at the SAME base SHA.
 * This does not measure general scalar replacement or framework throughput.
 */
declare(strict_types=1);

final class SraBenchFoldable
{
    public int $value = 42;

    public static function value(): int
    {
        return (new self())->value;
    }
}

final class SraBenchExcluded
{
    public static int $constructs = 0;
    public int $value = 42;

    public function __construct()
    {
        ++self::$constructs;
    }

    public static function value(): int
    {
        return (new self())->value;
    }
}

function sraBenchFoldable(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; ++$i) {
        $sum += SraBenchFoldable::value();
    }
    return $sum;
}

function sraBenchExcluded(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; ++$i) {
        $sum += SraBenchExcluded::value();
    }
    return $sum;
}

$iterations = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT) : 250_000;
if (!is_int($iterations) || $iterations < 1 || $iterations > 2_000_000) {
    fwrite(STDERR, "Usage: php native_sra_pass1_probe.php [1..2000000]\n");
    exit(2);
}

$cases = [
    'candidate' => 'sraBenchFoldable',
    'constructor_control' => 'sraBenchExcluded',
];
foreach ($cases as $run) {
    for ($i = 0; $i < 3; ++$i) {
        $run(2000);
    }
}
SraBenchExcluded::$constructs = 0;
$samples = ['candidate' => [], 'constructor_control' => []];

for ($trial = 0; $trial < 7; ++$trial) {
    $names = $trial % 2 === 0
        ? ['candidate', 'constructor_control']
        : ['constructor_control', 'candidate'];
    foreach ($names as $name) {
        $run = $cases[$name];
        $start = hrtime(true);
        $checksum = $run($iterations);
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        if ($checksum !== 42 * $iterations) {
            throw new RuntimeException("Checksum mismatch for {$name}");
        }
        $samples[$name][] = round($elapsed, 3);
    }
}
if (SraBenchExcluded::$constructs !== $iterations * 7) {
    throw new RuntimeException('The constructor control was incorrectly eliminated');
}

$report = [];
foreach ($samples as $name => $values) {
    sort($values, SORT_NUMERIC);
    $report[$name] = [
        'median_ms' => $values[3],
        'samples_ms' => $values,
    ];
}
echo json_encode([
    'php' => PHP_VERSION,
    'iterations' => $iterations,
    'trials' => 7,
    'checksum' => 42 * $iterations,
    'control_constructor_calls' => SraBenchExcluded::$constructs,
    'measurements' => $report,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
