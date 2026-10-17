#!/usr/bin/env python3
"""Same-host, checksum-checked, paired PHP A/B benchmark for an opt-in Zend C change.

Benchmark signals are reported separately for the optimized candidate and its
constructor-observable negative control. This deliberately cannot establish
whole-application performance. Both compiled binaries are from the same commit,
configuration and CPU runner; the ONLY intended difference is the C macro.
"""
from __future__ import annotations

import argparse
import json
import math
import platform
import random
import statistics
import subprocess
import sys
from pathlib import Path

NATIVE_BENCH = "Zend/benchmarks/native_sra_pass1_probe.php"


def percentiles(xs: list[float], lo: float = 0.025, hi: float = 0.975) -> tuple[float, float]:
    xs = sorted(xs)
    return xs[int((len(xs) - 1) * lo)], xs[int((len(xs) - 1) * hi)]


def run_once(binary: str, iterations: int) -> dict:
    cmd = [
        binary, "-n",
        "-d", "opcache.enable_cli=1",
        "-d", "opcache.file_update_protection=0",
        "-d", "opcache.jit_buffer_size=0",
        NATIVE_BENCH, str(iterations),
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=90, check=True)
    payload = json.loads(result.stdout)
    assert payload["checksum"] == 42 * iterations, payload
    assert payload["control_constructor_calls"] == 7 * iterations, payload
    assert payload["trials"] == 7, payload
    assert payload["iterations"] == iterations, payload
    assert payload["php"].startswith("8.7."), payload
    timings = payload["measurements"]
    for key in ("candidate", "constructor_control"):
        assert len(timings[key]["samples_ms"]) == 7
        assert float(timings[key]["median_ms"]) > 0
        assert all(float(v) > 0 for v in timings[key]["samples_ms"])
    return payload


def geo_mean(xs: list[float]) -> float:
    return math.exp(statistics.mean(math.log(x) for x in xs))


def bootstrap_ci(paired: list[float], seed: int = 137, samples: int = 4000) -> tuple[float, float]:
    assert paired
    rnd = random.Random(seed)
    n = len(paired)
    draws = [geo_mean([paired[rnd.randrange(n)] for _ in range(n)]) for _ in range(samples)]
    return percentiles(draws)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--baseline", required=True)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--iterations", type=int, default=500_000)
    parser.add_argument("--pairs", type=int, default=10)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    assert 5 <= args.pairs <= 30
    assert 100_000 <= args.iterations <= 2_000_000

    binaries = {"baseline": str(Path(args.baseline).resolve()),
                "candidate": str(Path(args.candidate).resolve())}
    for mode, binary in binaries.items():
        assert Path(binary).is_file(), (mode, binary)
        print(mode, subprocess.check_output([binary, "-v"], text=True).splitlines()[0], flush=True)

    results = []
    for round_index in range(args.pairs):
        # ABBA, BAAB alternating to counter drift and time-of-run bias.
        sequence = (
            ["baseline", "candidate", "candidate", "baseline"]
            if round_index % 2 == 0 else
            ["candidate", "baseline", "baseline", "candidate"]
        )
        observations = {name: [] for name in binaries}
        for name in sequence:
            payload = run_once(binaries[name], args.iterations)
            observations[name].append(payload)
        # Two independent PHP process medians per mode in each paired round.
        def per_round(key: str, name: str) -> float:
            return statistics.mean([
                float(p["measurements"][key]["median_ms"])
                for p in observations[name]
            ])

        result = {
            "round": round_index,
            "baseline_ms": per_round("candidate", "baseline"),
            "candidate_ms": per_round("candidate", "candidate"),
            "control_baseline_ms": per_round("constructor_control", "baseline"),
            "control_candidate_ms": per_round("constructor_control", "candidate"),
        }
        result["speedup"] = result["baseline_ms"] / result["candidate_ms"]
        result["control_ratio"] = result["control_baseline_ms"] / result["control_candidate_ms"]
        results.append(result)
        print(
            f"round {round_index + 1:02d}: "
            f"candidate speedup {result['speedup']:.3f}x, "
            f"constructor-control ratio {result['control_ratio']:.3f}x",
            flush=True,
        )

    speedups = [r["speedup"] for r in results]
    controls = [r["control_ratio"] for r in results]
    ci = bootstrap_ci(speedups)
    control_ci = bootstrap_ci(controls)
    report = {
        "purpose": "Opt-in PHP 8.7 pass1 peephole microbenchmark; not whole-PHP performance",
        "source": NATIVE_BENCH,
        "platform": platform.platform(),
        "processor": platform.processor(),
        "python": sys.version.split()[0],
        "iterations": args.iterations,
        "pairs": args.pairs,
        "processes": 4 * args.pairs,
        "jit": "off",
        "rounds": results,
        "median_baseline_ms": statistics.median([r["baseline_ms"] for r in results]),
        "median_candidate_ms": statistics.median([r["candidate_ms"] for r in results]),
        "geometric_mean_speedup": geo_mean(speedups),
        "speedup_bootstrap_95pct_ci": ci,
        "geometric_mean_control_ratio": geo_mean(controls),
        "control_bootstrap_95pct_ci": control_ci,
    }
    Path(args.output).parent.mkdir(parents=True, exist_ok=True)
    Path(args.output).write_text(json.dumps(report, indent=2, sort_keys=True) + "\n")
    print(json.dumps({
        "geometric_mean_speedup": report["geometric_mean_speedup"],
        "speedup_ci": ci,
        "negative_control_ratio": report["geometric_mean_control_ratio"],
        "negative_control_ci": control_ci,
    }, indent=2), flush=True)
    # Regressions must fail; negative control is a necessary falsification
    # check and should not benefit materially from the peephole.
    if ci[0] <= 1.10:
        print("FAIL: insufficient evidence of a >=10% real speedup.", file=sys.stderr)
        return 1
    if not (0.75 < report["geometric_mean_control_ratio"] < 1.33):
        print("FAIL: negative control diverged; timing is confounded.", file=sys.stderr)
        return 2
    print("PASS: positive improvement and stable constructor control on one runner.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
