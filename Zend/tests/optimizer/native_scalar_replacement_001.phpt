--TEST--
Potential scalar replacement must preserve values of non-escaping, constructorless objects
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=tracing
opcache.jit_buffer_size=64M
opcache.jit_hot_loop=16
--FILE--
<?php
final class NonEscapingPair {
    public int $x = 0;
    public int $y = 0;
}
function total(int $n): int {
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $pair = new NonEscapingPair();
        $pair->x = $i;
        $pair->y = $i * 2;
        $sum += $pair->x + $pair->y;
    }
    return $sum;
}
var_dump(total(2000));
?>
--EXPECT--
int(5997000)
