<?php
/** Controlled native scalar forwarding benchmark with runtime int values. */
declare(strict_types=1);
final class SraDynBenchCandidate {
    public int $value = 0;
    public static function identity(int $v): int {
        $tmp = new self;
        $tmp->value = $v;
        return $tmp->value;
    }
}
final class SraDynBenchControl {
    public static int $calls = 0;
    public int $value = 0;
    public function __construct() { ++self::$calls; }
    public static function identity(int $v): int {
        $tmp = new self;
        $tmp->value = $v;
        return $tmp->value;
    }
}
function dynamicLoop(int $n): int {
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $value = ($i & 127) - 64;
        $sum += SraDynBenchCandidate::identity($value) + (42 - $value);
    }
    return $sum;
}
function dynamicControlLoop(int $n): int {
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $value = ($i & 127) - 64;
        $sum += SraDynBenchControl::identity($value) + (42 - $value);
    }
    return $sum;
}
$iterations = isset($argv[1]) ? filter_var($argv[1], FILTER_VALIDATE_INT) : 250000;
if (!is_int($iterations) || $iterations < 1 || $iterations > 2000000) {
    fwrite(STDERR, "Usage: native_sra_dynamic_probe.php [1..2000000]\n");
    exit(2);
}
for ($i=0; $i<3; $i++) { dynamicLoop(2000); dynamicControlLoop(2000); }
SraDynBenchControl::$calls=0;
$samples=['candidate'=>[],'constructor_control'=>[]];
$functions=['candidate'=>'dynamicLoop','constructor_control'=>'dynamicControlLoop'];
for ($trial=0; $trial<7; $trial++) {
    $order=$trial%2 ? ['constructor_control','candidate'] : ['candidate','constructor_control'];
    foreach ($order as $name) {
        $start=hrtime(true);
        $checksum=$functions[$name]($iterations);
        $ms=(hrtime(true)-$start)/1000000;
        if ($checksum !== 42*$iterations) throw new RuntimeException("Wrong checksum: ".$name);
        $samples[$name][]=round($ms,3);
    }
}
if (SraDynBenchControl::$calls !== 7*$iterations) {
    throw new RuntimeException('Constructor control was incorrectly optimized');
}
foreach($samples as &$values) { sort($values,SORT_NUMERIC); } unset($values);
$out=['php'=>PHP_VERSION,'iterations'=>$iterations,'trials'=>7,
      'checksum'=>42*$iterations,'control_constructor_calls'=>SraDynBenchControl::$calls,
      'measurements'=>[]];
foreach($samples as $name=>$values){
    $out['measurements'][$name]=['median_ms'=>$values[3],'samples_ms'=>$values];
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
