import json
import pathlib
import statistics
import subprocess
import sys

base, patched, script = map(lambda p: str(pathlib.Path(p).resolve()), sys.argv[1:])
results = {}
for case in ['array', 'stdclass', 'plain', 'typed', 'retained', 'restricted-inside']:
    values = {'base': [], 'patched': []}
    for repeat in range(20):
        order = [('base', base), ('patched', patched)]
        if repeat % 2:
            order.reverse()
        for name, binary in order:
            run = subprocess.run([binary, '-n', '-d', 'opcache.enable_cli=0', script, case], capture_output=True, text=True)
            assert run.returncode == 0 and not run.stderr, run.stdout + run.stderr
            value = json.loads(run.stdout)
            assert value['sum'] == 15000000
            values[name].append(value['ms'])
    medians = {name: statistics.median(samples) for name, samples in values.items()}
    delta = 100 * (medians['patched'] / medians['base'] - 1)
    results[case] = {'samples': values, 'median_ms': medians, 'delta_percent': delta}
    print(f'{case}: base {medians["base"]:.3f} ms; patched {medians["patched"]:.3f} ms; delta {delta:+.2f}%', flush=True)
pathlib.Path('benchmark.json').write_text(json.dumps(results, indent=2))
