<?php
/**
 * Native scalar-folding opcode proof fixture.
 *
 * Compile the same source with the opt-in C macro ON and OFF. A successful
 * run alone is insufficient: CI also requires the expected presence/absence
 * of NEW in the optimized opcodes of each named method.
 */
declare(strict_types=1);

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final class SraProbeMarker {}

final class SraFoldable
{
    public int $value = 42;
    public static function value(): int { return (new self())->value; }
}

final class SraBooleanFoldable
{
    public bool $value = true;
    public static function value(): bool { return (new self())->value; }
}

final class SraNullFoldable
{
    public ?int $value = null;
    public static function value(): ?int { return (new self())->value; }
}

final class SraDoubleFoldable
{
    public float $value = 2.5;
    public static function value(): float { return (new self())->value; }
}

final class SraExcluded
{
    public static int $constructs = 0;
    public int $value = 42;
    public function __construct() { ++self::$constructs; }
    public static function value(): int { return (new self())->value; }
}

final class SraDestructorExcluded
{
    public static int $destructions = 0;
    public int $value = 42;
    public function __destruct() { ++self::$destructions; }
    public static function value(): int { return (new self())->value; }
}

final class SraHookExcluded
{
    public static int $reads = 0;
    public int $value { get { ++self::$reads; return 42; } }
    public static function value(): int { return (new self())->value; }
}

final class SraMagicExcluded
{
    public int $value = 42;
    public function __get(string $name): mixed { return 999; }
    public static function value(): int { return (new self())->value; }
}

class SraParent
{
    public int $value = 42;
}

final class SraInheritedExcluded extends SraParent
{
    public static function value(): int { return (new self())->value; }
}

#[SraProbeMarker]
final class SraClassAttrExcluded
{
    public int $value = 42;
    public static function value(): int { return (new self())->value; }
}

final class SraPropertyAttrExcluded
{
    #[SraProbeMarker]
    public int $value = 42;
    public static function value(): int { return (new self())->value; }
}

final class SraAsymmetricExcluded
{
    public private(set) int $value = 42;
    public static function value(): int { return (new self())->value; }
}

final class SraStringExcluded
{
    public string $value = '42';
    public static function value(): string { return (new self())->value; }
}

$actual = [
    SraFoldable::value(),
    SraBooleanFoldable::value(),
    SraNullFoldable::value(),
    SraDoubleFoldable::value(),
    SraExcluded::value(),
    SraDestructorExcluded::value(),
    SraHookExcluded::value(),
    SraMagicExcluded::value(),
    SraInheritedExcluded::value(),
    SraClassAttrExcluded::value(),
    SraPropertyAttrExcluded::value(),
    SraAsymmetricExcluded::value(),
    SraStringExcluded::value(),
    SraExcluded::$constructs,
    SraDestructorExcluded::$destructions,
    SraHookExcluded::$reads,
];

$expected = [
    42, true, null, 2.5, 42, 42, 42, 42, 42, 42, 42, 42, '42', 1, 1, 1,
];
if ($actual !== $expected) {
    throw new RuntimeException('Unexpected runtime semantics: ' . var_export($actual, true));
}
echo "NATIVE_SRA_SEMANTICS_OK\n";
