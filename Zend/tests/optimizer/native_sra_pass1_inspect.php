<?php
/**
 * Opcode proof fixture for the opt-in native SRA pass1 prototype.
 * The CI checks that NEW remains for the negative control but disappears
 * from SraFoldable::value() only when the experimental C macro is enabled.
 */
declare(strict_types=1);

final class SraFoldable
{
    public int $value = 42;

    public static function value(): int
    {
        return (new self())->value;
    }
}

final class SraExcluded
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

var_dump(SraFoldable::value(), SraExcluded::value(), SraExcluded::$constructs);
