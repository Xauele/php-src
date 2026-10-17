<?php
/**
 * Differential semantic oracle for JIT scalar-replacement research.
 * Passing does not mean that scalar replacement has been implemented.
 * Run this script in independent PHP processes with JIT off/tracing/function.
 */
declare(strict_types=1);

final class SraSimple
{
    public int $x = 0;
    public int $y = 0;
}
final class SraHooked
{
    public static int $gets = 0;
    public static int $sets = 0;
    public int $x {
        get {
            ++self::$gets;
            return $this->x;
        }
        set (int $value) {
            ++self::$sets;
            $this->x = $value;
        }
    }
}
final class SraDestructor
{
    public static int $calls = 0;
    public static int $checksum = 0;
    public function __construct(public int $x) {}
    public function __destruct()
    {
        ++self::$calls;
        self::$checksum += $this->x;
    }
}
final class SraCtor
{
    public static int $calls = 0;
    public function __construct(public int $x)
    {
        ++self::$calls;
    }
}
final class SraUninit
{
    public int $x;
}
final class SraReadonly
{
    public function __construct(public readonly int $x) {}
}
final class SraMagic
{
    public static int $reads = 0;
    public static int $writes = 0;
    private int $stored = 0;
    public function __set(string $name, mixed $value): void
    {
        if ($name !== 'x') {
            throw new LogicException('Unexpected property');
        }
        ++self::$writes;
        $this->stored = $value;
    }
    public function __get(string $name): mixed
    {
        if ($name !== 'x') {
            throw new LogicException('Unexpected property');
        }
        ++self::$reads;
        return $this->stored;
    }
}
final class SraThrowCtor
{
    public static int $calls = 0;
    public function __construct(int $x)
    {
        ++self::$calls;
        if (($x % 17) === 0) {
            throw new DomainException('Deliberate failure');
        }
    }
}
function verifySra(string $name, mixed $actual, mixed $expected): mixed
{
    if ($actual !== $expected) {
        throw new RuntimeException($name . ': ' . var_export($actual, true)
            . ' expected ' . var_export($expected, true));
    }
    return $actual;
}

$n = 1024;
$out = [];

// Eligible candidate: a temporary final object with two scalar properties.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $pair = new SraSimple();
    $pair->x = $i;
    $pair->y = 2 * $i;
    $sum += $pair->x + $pair->y;
}
$out['simple'] = verifySra('simple', $sum, 1_571_328);
unset($pair);

// References must not lose writes.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $ref =& $box->x;
    $ref = $i;
    $sum += $box->x;
    unset($ref, $box);
}
$out['reference'] = verifySra('reference', $sum, 523_776);

// Object identity must remain observable.
$identities = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $alias = $box;
    $identities += (spl_object_id($box) > 0 && $alias === $box) ? 1 : 0;
}
$out['identity'] = verifySra('identity', $identities, $n);
unset($box, $alias);

// Property hooks must run exactly once per access.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraHooked();
    $box->x = $i;
    $sum += $box->x;
}
$out['hooked'] = verifySra('hooked',
    [$sum, SraHooked::$sets, SraHooked::$gets], [523_776, $n, $n]);
unset($box);

// Constructors and destructors have externally observable side effects.
for ($i = 0; $i < $n; ++$i) {
    $box = new SraCtor($i);
    unset($box);
    $box = new SraDestructor($i);
    unset($box);
}
$out['lifecycle'] = verifySra('lifecycle',
    [SraCtor::$calls, SraDestructor::$calls, SraDestructor::$checksum],
    [$n, $n, 523_776]);

// Uninitialized typed property reads must throw.
$throws = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraUninit();
    try {
        $value = $box->x;
        throw new LogicException('Uninitialized read unexpectedly succeeded');
    } catch (Error $e) {
        ++$throws;
    }
}
$out['uninitialized'] = verifySra('uninitialized', $throws, $n);
unset($box);

// Readonly constraints must survive optimization.
$throws = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraReadonly($i);
    try {
        $box->x = $i + 1;
        throw new LogicException('Readonly write unexpectedly succeeded');
    } catch (Error $e) {
        ++$throws;
    }
}
$out['readonly'] = verifySra('readonly', $throws, $n);
unset($box);

// Magic __get and __set dispatches are observable.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraMagic();
    $box->x = $i;
    $sum += $box->x;
}
$out['magic'] = verifySra('magic',
    [$sum, SraMagic::$writes, SraMagic::$reads], [523_776, $n, $n]);
unset($box);

// Reflection forces materialization of property storage.
$sum = 0;
$rp = new ReflectionProperty(SraSimple::class, 'x');
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $box->x = $i;
    $sum += $rp->getValue($box);
}
$out['reflection'] = verifySra('reflection', $sum, 523_776);
unset($box);

// WeakReference must preserve identity and object release timing.
$stillAlive = $released = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $weak = WeakReference::create($box);
    $stillAlive += ($weak->get() === $box) ? 1 : 0;
    unset($box);
    $released += ($weak->get() === null) ? 1 : 0;
}
$out['weak_reference'] = verifySra('weak_reference',
    [$stillAlive, $released], [$n, $n]);
unset($weak);

// WeakMap must drop a key when its object dies.
$alive = $expired = 0;
for ($i = 0; $i < $n; ++$i) {
    $map = new WeakMap();
    $box = new SraSimple();
    $map[$box] = $i;
    $alive += (count($map) === 1 && $map[$box] === $i) ? 1 : 0;
    unset($box);
    $expired += (count($map) === 0) ? 1 : 0;
}
$out['weak_map'] = verifySra('weak_map', [$alive, $expired], [$n, $n]);
unset($map);

// Cloning creates an independent object with its own state.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $box->x = $i;
    $copy = clone $box;
    $copy->x += 2;
    $sum += $box->x + $copy->x;
}
$out['clone'] = verifySra('clone', $sum, 1_049_600);
unset($box, $copy);

// A closure can outlive the scope of the source object variable.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $box->x = $i;
    $read = static fn(): int => $box->x;
    unset($box);
    $sum += $read();
}
$out['closure_escape'] = verifySra('closure_escape', $sum, 523_776);
unset($read);

// Constructor exceptions cannot be suppressed or reordered.
$exceptions = 0;
for ($i = 0; $i < $n; ++$i) {
    try {
        new SraThrowCtor($i);
    } catch (DomainException $e) {
        ++$exceptions;
    }
}
$out['exceptions'] = verifySra('exceptions',
    [SraThrowCtor::$calls, $exceptions], [$n, 61]);

// Polymorphic call-site after monomorphic JIT warmup.
$sum = 0;
for ($i = 0; $i < 2 * $n; ++$i) {
    $box = $i < $n ? new SraSimple() : new SraHooked();
    $box->x = $i;
    $sum += $box->x;
}
$out['polymorphic'] = verifySra('polymorphic', $sum, 2_096_128);
$out['polymorphic_hook_counts'] = verifySra('polymorphic_hook_counts',
    [SraHooked::$sets, SraHooked::$gets], [2 * $n, 2 * $n]);
unset($box);

// Property enumeration is externally visible.
$sum = 0;
for ($i = 0; $i < $n; ++$i) {
    $box = new SraSimple();
    $box->x = $i;
    $sum += get_object_vars($box)['x'];
}
$out['enumeration'] = verifySra('enumeration', $sum, 523_776);

echo json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
