#!/usr/bin/env python3
"""Assert native scalar-folding eligibility from optimized PHP opcode evidence.

Input is OPcache's post-optimizer dump, not source text. In particular, it is
not enough that a PHPT produces the expected value: unsafe folding may hide
when a test only checks the visible value.
"""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

FOLDABLE = [
    "SraFoldable::value",
    "SraBooleanFoldable::value",
    "SraNullFoldable::value",
    "SraDoubleFoldable::value",
    "SraOtherPlainFoldable::value",
]
EXCLUDED = [
    "SraExcluded::value",  # constructor with observable side effects
    "SraDestructorExcluded::value",  # destructor with observable side effects
    "SraHookExcluded::value",  # property hook
    "SraMagicExcluded::value",  # user-defined magic property handler
    "SraInheritedExcluded::value",  # inherited property
    "SraClassAttrExcluded::value",  # class attribute
    "SraPropertyAttrExcluded::value",  # property attribute
    "SraAsymmetricExcluded::value",  # public private(set), not ordinary public
    "SraStringExcluded::value",  # own non-scalar default
    "SraExtraStringExcluded::value",  # unrelated refcounted default
    "SraExtraArrayExcluded::value",  # unrelated refcounted default
    "SraArgumentsExcluded::value",  # argument evaluation and nonzero call
    "SraEmptyUnpackExcluded::value",  # SEND_UNPACK must remain
    "SraStaticBindingExcluded::value",  # different class fetch semantics
    "SraUninitializedSelectedExcluded::value",  # throws on property read  # non-scalar default with refcounting
]


def extract_method(log: str, method: str) -> str:
    marker = method + ":"
    assert log.count(marker) >= 1, f"No opcode dump for {method}"
    blocks = log.split(marker)[1:]
    optimized = [
        block.split("\n\n", 1)[0]
        for block in blocks
        if "(after optimizer)" in block.split("\n\n", 1)[0]
    ]
    assert optimized, f"No post-optimizer dump for {method}"
    return optimized[-1]


def new_present(opcodes: str) -> bool:
    return bool(re.search(r"(?m)^\s*\d+\s+.*\bNEW\b", opcodes))


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--log", type=Path, required=True)
    ap.add_argument("--enabled", choices=["true", "false"], required=True)
    ap.add_argument("--output", type=Path, required=True)
    args = ap.parse_args()

    enabled = args.enabled == "true"
    log = args.log.read_text()
    proof: dict[str, dict[str, object]] = {}

    for name in FOLDABLE + EXCLUDED:
        chunk = extract_method(log, name)
        observed = new_present(chunk)
        must_have_new = name in EXCLUDED or not enabled
        assert observed == must_have_new, (
            f"Unexpected optimization for {name}: "
            f"macro enabled={enabled}, NEW present={observed}, "
            f"expected NEW present={must_have_new}\n{chunk}"
        )
        proof[name] = {"new_present": observed, "expected_new": must_have_new}

    if enabled:
        chunk = extract_method(log, "SraFoldable::value")
        assert re.search(r"(?m)^\s*\d+\s+RETURN\s+int\(42\)", chunk), (
            "Foldable::value() not lowered to expected constant return:\n" + chunk
        )
    result = {
        "experimental_enabled": enabled,
        "positive_cases": len(FOLDABLE),
        "negative_cases": len(EXCLUDED),
        "checks": proof,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n")
    print(
        f"PASS: {len(FOLDABLE)} foldable cases "
        f"and {len(EXCLUDED)} excluded cases verified, enabled={enabled}"
    )


if __name__ == "__main__":
    main()
