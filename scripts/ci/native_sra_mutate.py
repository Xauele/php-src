#!/usr/bin/env python3
"""Temporarily introduce unsafe C mutants to prove native regression gates."""
from __future__ import annotations

import sys
from pathlib import Path

FILE = Path("Zend/Optimizer/pass1.c")


def replace_one(code: str, old: str, new: str) -> str:
    if code.count(old) != 1:
        raise RuntimeError("Expected exactly one C guard: " + old)
    return code.replace(old, new)


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit("Usage: native_sra_mutate.py constructor|unrelated-default|visibility")
    kind = sys.argv[1]
    code = FILE.read_text()
    if kind == "constructor":
        code = replace_one(code, " || ce->constructor || ce->destructor", " || ce->destructor")
    elif kind == "visibility":
        code = replace_one(code, " || prop_info->flags != ZEND_ACC_PUBLIC", "")
    elif kind == "unrelated-default":
        begin = "\tfor (uint32_t i = 0; i < (uint32_t) ce->default_properties_count; ++i) {"
        end = "\n\tZVAL_COPY_VALUE(result, initial);"
        if code.count(begin) != 1 or code.count(end) != 1:
            raise RuntimeError("Could not identify the unrelated-default guard")
        start = code.index(begin)
        finish = code.index(end, start)
        code = code[:start] + code[finish:]
    else:
        raise SystemExit("Unknown mutation: " + kind)
    FILE.write_text(code)
    print("Introduced unsafe test-only C mutation: " + kind)


if __name__ == "__main__":
    main()
