<?php
/* Opcache file-cache, preloading and multi-request correctness fixture. */
final class SraCacheFoldable
{
    public int $value = 42;
    public int $other = 19;
    public bool $flag = false;
    public static function value(): int { return (new self())->value; }
}

final class SraCacheExcluded
{
    public static int $constructions = 0;
    public int $value = 73;
    public string $other = 'unrelated, reference-counted string';
    public function __construct() { ++self::$constructions; }
    public static function value(): int { return (new self())->value; }
}
