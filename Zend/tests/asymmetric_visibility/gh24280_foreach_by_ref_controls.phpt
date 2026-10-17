--TEST--
GH-24280: foreach by-reference preserves legal writes, typed reference guards and hook errors
--FILE--
<?php
class OwnedPrivateControl {
    public private(set) string $value = 'before';

    public function modify(): void {
        foreach ($this as &$entry) {
            $entry = 'after';
        }
        unset($entry);
    }
}

class OwnedProtectedParentControl {
    public protected(set) int $value = 1;
}

class OwnedProtectedChildControl extends OwnedProtectedParentControl {
    public function modify(): void {
        foreach ($this as &$entry) {
            $entry = 8;
        }
        unset($entry);
    }
}

class InvisibleControl {
    private string $hidden = 'secret';
    public string $visible = 'initial';

    public function getHidden(): string {
        return $this->hidden;
    }
}

#[AllowDynamicProperties]
class DynamicControl {
    public string $regular = 'initial';
}

class TypedControl {
    public int $value = 1;
}

class HookedControl {
    public string $value {
        get { return 'hook'; }
        set { }
    }
}

$private = new OwnedPrivateControl();
$private->modify();
echo "private own: {$private->value}\n";

$protected = new OwnedProtectedChildControl();
$protected->modify();
echo "protected child: {$protected->value}\n";

$invisible = new InvisibleControl();
foreach ($invisible as &$entry) {
    $entry = 'changed';
}
unset($entry);
echo "private get hidden: {$invisible->getHidden()}\n";
echo "public shown: {$invisible->visible}\n";

$dynamic = new DynamicControl();
$dynamic->extra = 'created';
foreach ($dynamic as &$entry) {
    $entry = 'changed';
}
unset($entry);
echo "dynamic: {$dynamic->regular}, {$dynamic->extra}\n";

$typed = new TypedControl();
foreach ($typed as &$entry) {
    try {
        $entry = 'not-an-int';
    } catch (TypeError $exception) {
        echo "typed-ref: rejected\n";
    }
    $entry = 42;
}
unset($entry);
echo "typed: {$typed->value}\n";

$std = (object) ['value' => 'first'];
foreach ($std as &$entry) {
    $entry = 'second';
}
unset($entry);
echo "std: {$std->value}\n";

try {
    $hooked = new HookedControl();
    foreach ($hooked as &$entry) {
        $entry = 'nope';
    }
    unset($entry);
    echo "hooked: wrongly accepted\n";
} catch (Error $exception) {
    echo "hooked: rejected\n";
}
?>
--EXPECT--
private own: after
protected child: 8
private get hidden: secret
public shown: changed
dynamic: changed, changed
typed-ref: rejected
typed: 42
std: second
hooked: rejected
