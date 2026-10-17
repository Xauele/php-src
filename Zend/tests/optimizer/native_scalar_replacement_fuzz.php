<?php
/**
 * Deterministic metamorphic fuzzer for temporary PHP aggregates.
 * The scalar model is the oracle; a separate process with JIT off
 * serves as an additional differential reference.
 * Passing does NOT indicate that scalar replacement is implemented.
 */
declare(strict_types=1);

final class SraFuzzPair
{
    public int $a = 0;
    public int $b = 0;
}

function sraNext(int &$state): int
{
    // Integer-only Lehmer PRNG, reproducible on 64-bit PHP.
    $state = ($state * 48271) % 2147483647;
    return $state;
}

function sraFuzz(int $seed, int $runs): array
{
    $state = $seed;
    $checksum = 0;
    $escapes = 0;
    $coverage = array_fill(0, 16, 0);
    $rp = new ReflectionProperty(SraFuzzPair::class, 'a');

    for ($i = 0; $i < $runs; ++$i) {
        $raw1 = sraNext($state);
        $raw2 = sraNext($state);
        $a = $raw1 % 201;
        $b = $raw2 % 201;
        $case = ($raw1 ^ $raw2) & 15;
        ++$coverage[$case];
        $obj = new SraFuzzPair();
        $obj->a = $a;
        $obj->b = $b;

        switch ($case) {
            case 0: $a += 3; $obj->a += 3; break;
            case 1: $b = ($b * 3) % 997; $obj->b = ($obj->b * 3) % 997; break;
            case 2: $a ^= $b; $obj->a ^= $obj->b; break;
            case 3:
                [$a, $b] = [$b, $a];
                [$obj->a, $obj->b] = [$obj->b, $obj->a];
                break;
            case 4: $a = ($a * 7) % 1009; $obj->a = ($obj->a * 7) % 1009; break;
            case 5: $a <<= 1; $obj->a <<= 1; break;
            case 6:
                if ($a > 100) { $a -= 19; $obj->a -= 19; }
                break;
            case 7:
                $ref =& $obj->a;
                $ref -= $i % 4;
                $a -= $i % 4;
                unset($ref);
                ++$escapes;
                break;
            case 8:
                $copy = clone $obj;
                ++$copy->a;
                if ($copy->a !== $a + 1 || $obj === $copy) {
                    throw new RuntimeException('Clone broken');
                }
                ++$escapes;
                break;
            case 9:
                $weak = WeakReference::create($obj);
                if ($weak->get() !== $obj) {
                    throw new RuntimeException('WeakReference broken');
                }
                ++$escapes;
                break;
            case 10:
                if ($rp->getValue($obj) !== $a) {
                    throw new RuntimeException('Reflection broken');
                }
                ++$escapes;
                break;
            case 11: $b = intdiv($b, 2); $obj->b = intdiv($obj->b, 2); break;
            case 12: $a = $b + 1; $obj->a = $obj->b + 1; break;
            case 13: $b = -$a; $obj->b = -$obj->a; break;
            case 14: $a %= 13; $obj->a %= 13; break;
            case 15: $b = $a + $b; $obj->b = $obj->a + $obj->b; break;
        }

        if ($a !== $obj->a || $b !== $obj->b) {
            throw new RuntimeException("Scalar/object mismatch at seed={$seed}, iteration={$i}, case={$case}");
        }
        $checksum = ($checksum + $a * 3 + $b * 7 + $case + 1000000007) % 1000000007;
        unset($obj);
        if ($case === 9 && $weak->get() !== null) {
            throw new RuntimeException("WeakReference lifetime mismatch at iteration={$i}");
        }
    }
    if (min($coverage) === 0) {
        throw new RuntimeException("Uncovered mutation case for seed={$seed}");
    }
    return [$checksum, $escapes, $coverage];
}

if (PHP_INT_SIZE !== 8) {
    throw new RuntimeException('This seeded oracle requires 64-bit PHP');
}

$out = [];
foreach ([1, 7, 137, 31337] as $seed) {
    $out[$seed] = sraFuzz($seed, 8192);
}
echo json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
