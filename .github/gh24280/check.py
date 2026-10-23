import json
import pathlib
import subprocess
import sys
import tempfile

kind, binary, tests = sys.argv[1:]
binary = str(pathlib.Path(binary).resolve())
tests = pathlib.Path(tests)
files = sorted(tests.glob('gh24280*.phpt'))
assert len(files) == 3
version = subprocess.check_output([binary, '-n', '-v'], text=True)
print(version)

with tempfile.TemporaryDirectory() as directory:
    if kind == 'baseline':
        source = (tests / 'gh24280_foreach_by_ref.phpt').read_text()
        script = pathlib.Path(directory) / 'baseline.php'
        script.write_text(source.split('--FILE--\n', 1)[1].split('--EXPECT--\n', 1)[0])
        result = subprocess.run([binary, '-n', str(script)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
        for signal in ['private outside: accepted', 'existing reference outside: accepted',
                       'private child: accepted', 'protected outside: accepted']:
            assert signal in result.stdout, result.stdout
        assert 'readonly outside: Cannot acquire reference' in result.stdout
        print('RED: four setter-visibility bypasses reproduced on this exact base')
        sys.exit(0)

    module = pathlib.Path(binary).parents[2] / 'modules/opcache.so'
    extension = ['-d', f'zend_extension={module}'] if module.exists() else []
    modes = {
        'interpreter': ['-n', '-d', 'opcache.enable_cli=0'],
        'opcache': ['-n', *extension, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=disable'],
        'jit-tracing': ['-n', *extension, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit_buffer_size=64M', '-d', 'opcache.jit=tracing'],
        'jit-function': ['-n', *extension, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit_buffer_size=64M', '-d', 'opcache.jit=function'],
    }
    for mode, options in modes.items():
        if mode != 'interpreter':
            status = subprocess.run([binary, *options, '-r',
                "echo json_encode(opcache_get_status(false));"], capture_output=True, text=True)
            assert status.returncode == 0 and not status.stderr, status.stdout + status.stderr
            state = json.loads(status.stdout)
            assert isinstance(state, dict) and state['opcache_enabled']
            if mode.startswith('jit-'):
                assert state['jit']['on'], state
        for test in files:
            content = test.read_text()
            script = pathlib.Path(directory) / (test.stem + '.php')
            script.write_text(content.split('--FILE--\n', 1)[1].split('--EXPECT--\n', 1)[0])
            result = subprocess.run([binary, *options, str(script)], capture_output=True, text=True)
            expected = content.split('--EXPECT--\n', 1)[1].strip()
            assert result.returncode == 0 and not result.stderr and result.stdout.strip() == expected, (
                f'{test.name}/{mode}: {result.returncode}\n{result.stdout}\n{result.stderr}')
            print(f'PASS {test.name}/{mode}')
