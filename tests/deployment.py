#!/usr/bin/env python3
"""Exercise deployment publication against disposable local Git repositories."""
from pathlib import Path
import importlib.util
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', root / 'scripts/publish-deploy.py')
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)


def run(*command):
    return subprocess.check_output(command, text=True, stderr=subprocess.STDOUT).strip()


with tempfile.TemporaryDirectory(prefix='blynine-publish-test-') as temporary:
    temporary = Path(temporary)
    remote, source, runtime, consumer = [temporary / name for name in ('remote.git', 'source', 'runtime', 'hosting')]
    run('git', 'init', '--bare', str(remote))
    run('git', 'init', '--initial-branch=main', str(source))
    (source / 'source.txt').write_text('Source stays on its own branch.\n')
    run('git', '-C', str(source), 'add', '.')
    run('git', '-C', str(source), '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'Initial source')
    run('git', '-C', str(source), 'remote', 'add', 'origin', str(remote))
    (runtime / 'zaidimas').mkdir(parents=True)
    for name, content in {'.htaccess': 'Options -Indexes\n', '.gitignore': '.env\nenv.local.php\n', '.env.example': 'DB_NAME=\n', 'index.php': '<?php echo "ready";', 'zaidimas/index.html': '<title>Game</title>', 'zaidimas/old.js': 'old bundle'}.items():
        (runtime / name).write_text(content)
    branch = 'codex/hostinger-deploy'
    publisher.publish(source, runtime, branch)
    deployed = run('git', '--git-dir=' + str(remote), 'ls-tree', '-r', '--name-only', branch).splitlines()
    assert 'zaidimas/index.html' in deployed and 'source.txt' not in deployed
    assert '.env.example' in deployed and '.env' not in deployed
    print('PASS: first publication includes compiled assets and excludes source and credentials')
    first_commit = run('git', '--git-dir=' + str(remote), 'rev-parse', branch)
    publisher.publish(source, runtime, branch)
    assert run('git', '--git-dir=' + str(remote), 'rev-parse', branch) == first_commit
    print('PASS: unchanged output does not create redundant deployments')
    run('git', 'clone', '--branch', branch, str(remote), str(consumer))
    (consumer / '.env').write_text('LOCAL_ONLY=private-hosting-test\n')
    (runtime / 'zaidimas/old.js').unlink()
    (runtime / 'zaidimas/new.js').write_text('new bundle')
    publisher.publish(source, runtime, branch)
    run('git', '-C', str(consumer), 'pull', '--ff-only')
    assert not (consumer / 'zaidimas/old.js').exists() and (consumer / 'zaidimas/new.js').is_file()
    assert (consumer / '.env').read_text() == 'LOCAL_ONLY=private-hosting-test\n'
    print('PASS: updates replace old bundles while preserving an untracked hosting .env')
    assert run('git', '-C', str(source), 'branch', '--show-current') == 'main'
    assert (source / 'source.txt').is_file() and not run('git', '-C', str(source), 'status', '--porcelain')
    print('PASS: deployment leaves the source branch and its checkout unchanged')
    (runtime / '.env').write_text('DO_NOT_PUBLISH=test\n')
    try:
        publisher.publish(source, runtime, branch)
        raise AssertionError('Credentials must not be published.')
    except SystemExit as error:
        assert 'Refusing' in str(error)
    print('PASS: publication rejects server credentials')
