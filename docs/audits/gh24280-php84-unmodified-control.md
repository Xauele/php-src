# GH-24280 independent unmodified-base control

This fork-only **DRAFT** intentionally changes no PHP source, Zend VM code, tests, or build configuration.

- Exact parent: `PHP-8.4` SHA `6ec698758e50d0356a7befeca6b2bfaffffa15aa`
- Candidate under test: [Xauele/php-src#2](https://github.com/Xauele/php-src/pull/2), SHA `7b5662a162b8ca9a18921cd119f28740a2c3017b`.
- Candidate macOS Tracing JIT twice fails `ext/zip/tests/oo_torrentzip.phpt`, even though its patch changes only Zend foreach-by-reference and three PHPTs.
- Candidate FreeBSD 13.5 fails package catalog bootstrap before compiling PHP: `pkgmir.geo.freebsd.org/FreeBSD:13:amd64/latest` is Not Found.

This control makes GitHub Actions run the **existing unchanged repository CI matrix** against an unmodified PHP-8.4 tree. It will distinguish a pre-existing platform/environment failure from a patch-specific regression. Results are not predicted.

**Acceptance policy:** the control and candidate must use equivalent jobs/toolchain. If the control reproduces the same failure, classify it as environmental/pre-existing and attach both logs; if the control passes, investigate the candidate, with **NO-GO** for upstream until resolved. This control PR is never intended to merge.
