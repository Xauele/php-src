# Native scalar replacement (research prototype)

**Status:** Benchmark and semantic regression baselines only. **No native
optimization has been implemented or validated on php-src `master` yet.**
This document is not an accepted PHP RFC and makes no release commitment.

## Hypothesis

A hot function that constructs a short-lived, non-escaping object may be able to
operate on scalar fields (SSA values / machine registers) rather than
allocating and destroying a `zend_object` on every iteration. Similar
opportunities exist for temporary, packed numeric arrays.

PHP already performs SSA-based escape analysis in
`Zend/Optimizer/escape_analysis.c`, and dead-code elimination in
`Zend/Optimizer/dce.c` can discard *dead* allocations. Scalar replacement of
*used* aggregates with observable reads and writes is a different operation.
We must not mistake the existing escape-analysis pass for an absent feature.

## Narrow first candidate

Consider a constructorless `final` PHP class with two initialized, public,
scalar typed properties. A function constructs the object, writes both
properties, reads them, and does not expose the instance. Start with a single
basic block and require a provable, monomorphic class. This is deliberately
much narrower than optimizing general `new` expressions.

A subsequent prototype should lower the temporary's fields into independent
SSA definitions, then generate equivalent machine operations without heap
allocation. If these preconditions cannot be proved, retain the existing
object path. Do not add unsound speculative assumptions.

## Semantics and safety gates

- Object identity must remain observable when requested: `===`,
  `spl_object_id()`, `WeakReference`, `WeakMap`, cloning and reflection.
- Preserve aliases, references, property typing/coercion, uninitialized
  properties, readonly/asymmetric visibility, property hooks and exceptions.
- Do not suppress or reorder user constructors, destructors, custom object
  handlers, magic properties, observers or GC-visible behavior.
- For tracing JIT, any side exit that needs the object requires correct
  *materialization* in deoptimization snapshots. If that is not implemented,
  do not optimize any path that can exit with the object live.
- Consider OPcache persistence/preloading, debug builds, ZTS, GC, 32-bit,
  AArch64/x86-64, and all JIT modes. Fail closed on unsupported cases.
- Preserve default VM behavior when optimization/JIT is disabled.

## Measurements

`Zend/benchmarks/native_scalar_replacement.php` compares three different,
checksum-equivalent workloads: direct scalars, a temporary PHP array, and a
temporary PHP object. **Their time ratios are not an optimization speedup.**
They are only a diagnostic of the current cost of different representations.

Build php-src first, run each configuration in a fresh process, and record the
full commit SHA, compiler/flags, OS/architecture, PHP version, OPcache/JIT
configuration, median, spread and checksum. The benchmark reports seven
rotated trials and verifies the checksum. Confirm JIT activation using the
reported `jit_enabled` flag, not merely the INI value.

Suggested controlled runs:

```sh
for mode in off tracing function; do
    case "$mode" in
        off) size=0 ;;
        *) size=128M ;;
    esac
    sapi/cli/php -n -d zend_extension=opcache -d opcache.enable_cli=1 \
        -d opcache.file_update_protection=0 \
        -d opcache.jit_buffer_size="$size" -d opcache.jit="$mode" \
        Zend/benchmarks/native_scalar_replacement.php 2000000
done
```

A first exploratory measurement on a *separate* stock PHP 8.4.24 executable
(not this PHP 8.7 fork) found approximately 2.06 ms for direct scalars,
47.83 ms for temporary arrays, and 78.50 ms for temporary objects with tracing
JIT at two million iterations. This is **not** a claim about modern `master`,
framework throughput, or an achievable optimization speedup.

## Acceptance criteria for any native patch

1. Demonstrate baseline reproductions and successful checksums with JIT off,
   tracing and function JIT, including optimized and debug builds.
2. Produce a minimal C/IR diff with correct dataflow and lifetime reasoning,
   including deoptimization, exceptions and object observability.
3. Pass focused PHPT, the full relevant optimizer/JIT suites, ASan/UBSan,
   Valgrind where feasible, and platform/reconfiguration matrix.
4. Measure vs the **same master SHA**, with process isolation and confidence
   bounds; require a real improvement without a significant regression in
   non-target workloads.
5. Benchmark non-synthetic applications (Laravel/Symfony/WordPress or a
   representative, reproducible workload) before making any broad impact claim.
6. Independent code review and upstream architectural discussion before
   considering a public PHP PR. Significant language behavior changes require
   the PHP RFC process.

Related prior upstream direction: https://wiki.php.net/php-7.1-ideas
("Narrowing non-escaping HashTables" and "Scalar Replacement of Aggregates")
