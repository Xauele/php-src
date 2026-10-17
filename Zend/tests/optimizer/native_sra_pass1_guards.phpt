--TEST--
Experimental native zero-allocation fold retains argument evaluation, errors, and nontrivial class defaults
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
--FILE--
<?php
declare(strict_types=1);

final class OtherArrayDefault {
    public int $value = 42;
    public array $other = [1, 2, 3];
    public static function value(): int { return (new self())->value; }
}

final class OtherStringDefault {
    public int $value = 17;
    public string $other = 'payload';
    public static function value(): int { return (new self())->value; }
}

final class OtherUninitializedDefault {
    public int $value = 29;
    public int $other;
    public static function value(): int { return (new self())->value; }
}

final class ArgumentEvaluation {
    public static int $calls = 0;
    public int $value = 13;
    private static function sideEffect(): int { return ++self::$calls; }
    public static function value(): int { return (new self(self::sideEffect()))->value; }
    public static function unpack(): int { return (new self(...[]))->value; }
    public static function named(): int { return (new self(unknown: 123))->value; }
}

final class UninitializedRead {
    public int $value;
    public static function value(): int { return (new self())->value; }
}

$results = [
    OtherArrayDefault::value(),
    OtherStringDefault::value(),
    OtherUninitializedDefault::value(),
    ArgumentEvaluation::value(),
    ArgumentEvaluation::$calls,
    ArgumentEvaluation::unpack(),
];

foreach ([ArgumentEvaluation::class . '::named', UninitializedRead::class . '::value'] as $callback) {
    try {
        $callback();
        $results[] = 'MISSING_ERROR';
    } catch (Error $e) {
        $results[] = 'EXPECTED_ERROR';
    }
}
var_export($results);
echo "\n";
?>
--EXPECT--
array (
  0 => 42,
  1 => 17,
  2 => 29,
  3 => 13,
  4 => 1,
  5 => 13,
  6 => 'EXPECTED_ERROR',
  7 => 'EXPECTED_ERROR',
)
