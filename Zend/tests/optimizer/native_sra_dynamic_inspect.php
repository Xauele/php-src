<?php
declare(strict_types=1);

final class SraDynCandidate {
    public int $value = 0;
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}

final class SraDynConstructorExcluded {
    public static int $calls = 0;
    public int $value = 0;
    public function __construct() { ++self::$calls; }
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}

final class SraDynStringExcluded {
    public int $value = 0;
    public string $other = 'a';
    public static function identity(int $value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}

final class SraDynRefExcluded {
    public int $value = 0;
    public static function identity(int &$value): int {
        $tmp = new self;
        $tmp->value = $value;
        return $tmp->value;
    }
}

$x = 13;
$actual = [
    SraDynCandidate::identity(75),
    SraDynCandidate::identity(-17),
    SraDynConstructorExcluded::identity(9),
    SraDynStringExcluded::identity(53),
    SraDynRefExcluded::identity($x),
    $x,
    SraDynConstructorExcluded::$calls,
];
if ($actual !== [75, -17, 9, 53, 13, 13, 1]) {
    throw new RuntimeException('Runtime scalar forwarding mismatch');
}
for ($i = 0; $i < 2048; ++$i) {
    if (SraDynCandidate::identity($i - 1024) !== $i - 1024) {
        throw new RuntimeException('Dynamic value was lost');
    }
}
echo "DYNAMIC_SRA_OK\n";
