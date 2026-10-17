--TEST--
Potential scalar replacement must preserve object identity, references, and destructors
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
final class VisiblePair {
    public int $x = 0;
    public int $y = 0;
}
final class ObservedDestructor {
    public static int $total = 0;
    public int $n = 0;
    public function __destruct() { self::$total += $this->n; }
}
function visible(int $n): void {
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $pair = new VisiblePair();
        $alias =& $pair;
        $alias->x = $i;
        $pair->y = $i + 1;
        if ($i === 0) {
            $first = $pair;
            $weak = WeakReference::create($pair);
        }
        $sum += $pair->x + $pair->y;
        $other = new ObservedDestructor();
        $other->n = 1;
    }
    var_dump($sum, $first->x, $weak->get() === $first);
}
visible(2000);
var_dump(ObservedDestructor::$total);
?>
--EXPECT--
int(4000000)
int(0)
bool(true)
int(2000)
