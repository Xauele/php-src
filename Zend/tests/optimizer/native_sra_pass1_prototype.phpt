--TEST--
Experimental SRA pass1 folding preserves constructor, destructor, property-hook, magic and exception semantics
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
--FILE--
<?php
declare(strict_types=1);
final class SraNativeCandidate {
    public int $value = 42;
    public bool $enabled = true;
    public ?int $nullable = null;
    public static function value(): int { return (new self())->value; }
    public static function enabled(): bool { return (new self())->enabled; }
    public static function nullable(): ?int { return (new self())->nullable; }
}
final class SraNativeCtor {
    public static int $calls = 0;
    public int $value = 7;
    public function __construct() { ++self::$calls; }
    public static function value(): int { return (new self())->value; }
}
final class SraNativeDtor {
    public static int $calls = 0;
    public int $value = 8;
    public function __destruct() { ++self::$calls; }
    public static function value(): int { return (new self())->value; }
}
final class SraNativeHook {
    public static int $calls = 0;
    public int $value { get { ++self::$calls; return 9; } }
    public static function value(): int { return (new self())->value; }
}
final class SraNativeMagic {
    public static int $calls = 0;
    public int $value = 11;
    public function __get(string $name): mixed { ++self::$calls; return 0; }
    public static function value(): int { return (new self())->value; }
}
class SraNativeParent { public int $value = 13; }
final class SraNativeChild extends SraNativeParent {
    public static function value(): int { return (new self())->value; }
}
final class SraNativeThrows {
    public static int $calls = 0;
    public int $value = 1;
    public function __construct() { ++self::$calls; throw new DomainException('ctor'); }
    public static function value(): int { return (new self())->value; }
}
$out = [
    SraNativeCandidate::value(),
    SraNativeCandidate::enabled(),
    SraNativeCandidate::nullable(),
    SraNativeCtor::value(),
    SraNativeCtor::$calls,
    SraNativeDtor::value(),
    SraNativeDtor::$calls,
    SraNativeHook::value(),
    SraNativeHook::$calls,
    SraNativeMagic::value(),
    SraNativeMagic::$calls,
    SraNativeChild::value(),
];
try { SraNativeThrows::value(); } catch (DomainException $e) { $out[] = $e->getMessage(); }
$out[] = SraNativeThrows::$calls;
for ($i = 0; $i < 2048; ++$i) {
    if (SraNativeCandidate::value() !== 42) { throw new LogicException('Inconsistent constant'); }
}
var_export($out);
echo "\n";
?>
--EXPECT--
array (
  0 => 42,
  1 => true,
  2 => NULL,
  3 => 7,
  4 => 1,
  5 => 8,
  6 => 1,
  7 => 9,
  8 => 1,
  9 => 11,
  10 => 0,
  11 => 13,
  12 => 'ctor',
  13 => 1,
)
