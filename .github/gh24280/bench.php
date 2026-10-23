<?php
class Plain {
    public $a = 1, $b = 2, $c = 3, $d = 4;
}
class Typed {
    public int $a = 1, $b = 2, $c = 3, $d = 4;
}
class Restricted {
    public private(set) int $a = 1, $b = 2, $c = 3, $d = 4;
    public function run(int $count): int {
        $sum = 0;
        for ($i = 0; $i < $count; ++$i) {
            foreach ($this as &$value) { $sum += $value; }
            unset($value);
        }
        return $sum;
    }
}
function run(string $case, int $count): int {
    if ($case === 'restricted-inside') { return (new Restricted)->run($count); }
    $data = match ($case) {
        'array' => [1, 2, 3, 4],
        'stdclass' => (object) ['a'=>1, 'b'=>2, 'c'=>3, 'd'=>4],
        'plain', 'retained' => new Plain,
        'typed' => new Typed,
    };
    $retained = [];
    if ($case === 'retained') {
        foreach ($data as &$value) { $retained[] =& $value; }
        unset($value);
    }
    $sum = 0;
    for ($i = 0; $i < $count; ++$i) {
        foreach ($data as &$value) { $sum += $value; }
        unset($value);
    }
    return $sum;
}
$case = $argv[1];
run($case, 10000);
$start = hrtime(true);
$sum = run($case, 1500000);
$elapsed = (hrtime(true) - $start) / 1e6;
if ($sum !== 15000000) { throw new LogicException('Wrong result'); }
echo json_encode(['ms'=>$elapsed,'sum'=>$sum]), "\n";
