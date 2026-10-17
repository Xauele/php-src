# Native zero-allocation object fold, safety case v2

Status: experimental prototype on experiment/native-sra-proof-v2. NO upstream PR.
This is a narrow opcode peephole, NOT Scalar Replacement of Aggregates (SRA).
The name is historical; the implementation does not virtualize mutable objects.

## Exact accepted bytecode

- A temporary NEW for self in a final, linked user-defined class.
- No constructor, destructor, nonstandard object handlers, magic hooks, attributes, or ancestor.
- NEW must have zero arguments (extended_value == 0), immediately followed
  by a DO_FCALL whose result is unused.
- Immediately after DO_FCALL there is a FETCH_OBJ_R of a literal named property.
- FETCH_OBJ_R consumes exactly the temp result of NEW, produces a temporary result.
- The property is declared directly in the class, unmodified public visibility
  (property_info->flags == ZEND_ACC_PUBLIC), no attributes or hooks.
- The selected field has a known non-refcounted scalar default:
  null, boolean, integer, or double.
- All unrelated instance fields also have non-refcounted defaults or are
  uninitialized (IS_UNDEF); this conservatively excludes arrays, strings,
  object values, references, and constant ASTs.
- Class constant/default initialization must be completed and backed by the
  canonical unmodified class property table; any request-local mutable
  table is excluded.

## Safety argument and its boundary

Inside this exact contiguous bytecode sequence, no userland invocation
occurs, and the object cannot escape to a PHP variable, array, caller,
argument, closure, reference or observation API. With default native
handlers and no user lifecycle methods, object construction and destruction
perform no userland side effect. The property read fetches the scalar that
was present in the class default table, so it can be replaced by that value.

The proof is incomplete for effects observable through memory allocation,
object-handle accounting, VM instrumentation hooks, and exotic extensions.
These require explicit review and tests before the optimization can be
made the default. Any new special handler, refcounted default, AST-backed
default, argument, inheritance, magic method or trait ambiguity must
return false from the optimizer and execute the original VM bytecode.

No deoptimization materialization is currently needed because this
optimization does not virtualize a live aggregate; it removes a whole
self-contained object lifetime. A true SRA requires SSA escape/alias
reasoning and deoptimization snapshots.

## Gates

- Candidate-on and baseline-off builds from the SAME source commit.
- Opcode proof for safe and deliberately excluded cases.
- PHPT correctness for argument evaluation, thrown errors, and
  default-property behavior under OPcache.
- Differential result comparison on VM, tracing JIT and function JIT.
- ASan/UBSan with a debug ZTS build.
- Full targeted Zend JIT regression suite.
- Same-host ABBA paired microbenchmark, bootstrapped confidence bounds,
  negative-control confidence interval within 0.93 to 1.07.
- Preloading, multi-request FPM, file-cache and non-x86 architectures
  are NOT fully validated in this research branch.
- No claim about Laravel, Symfony, real HTTP throughput or general PHP speed.

## Upstream decision

Do NOT ship as general SRA. First prove that the effects above are
unobservable or acceptable, profile framework workloads, and split the
minimal C code and PHPTs into a small reviewable upstream patch.
