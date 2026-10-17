--TEST--
Dynamic one-field native scalar replacement preserves parameter values and excluded lifecycle
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
--FILE--
<?php
declare(strict_types=1);
final class SraIntCandidate {
    public int $value = 0;
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}
final class SraIntCtorExcluded {
    public int $value = 0;
    public static int $calls = 0;
    public function __construct() { ++self::$calls; }
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}
final class SraIntRefExcluded {
    public int $value = 0;
    public static function identity(int &$value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}
final class SraIntExtraExcluded {
    public int $value = 0;
    public string $extra = "referenced";
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}
$values = [0, 1, -1, 4096, -8192, PHP_INT_MAX, PHP_INT_MIN];
$output = [];
foreach ($values as $value) {
    $output[] = SraIntCandidate::identity($value) === $value;
}
$x = -1234;
$output[] = SraIntRefExcluded::identity($x) === $x;
$output[] = SraIntCtorExcluded::identity(42);
$output[] = SraIntCtorExcluded::$calls;
$output[] = SraIntExtraExcluded::identity(-7);
var_export($output);
echo "\n";
?>
--EXPECT--
array (
  0 => true,
  1 => true,
  2 => true,
  3 => true,
  4 => true,
  5 => true,
  6 => true,
  7 => true,
  8 => 42,
  9 => 1,
  10 => -7,
)
