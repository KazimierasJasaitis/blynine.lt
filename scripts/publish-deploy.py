#!/usr/bin/env python3
"""Publish only prepared runtime files to Hostinger's deployment branch."""
import argparse
from pathlib import Path
import shutil
import subprocess
import tempfile
import uuid


def git(repository, *arguments, capture=False):
    return subprocess.run(['git', '-C', str(repository), *arguments], check=True,
                          text=True, stdout=subprocess.PIPE if capture else None).stdout


def publish(repository, directory, branch='codex/hostinger-deploy', remote='origin'):
    repository = Path(repository).resolve()
    directory = Path(directory).resolve()
    git(repository, 'check-ref-format', '--branch', branch, capture=True)
    source_branch = git(repository, 'branch', '--show-current', capture=True).strip()
    if source_branch == branch:
        raise SystemExit('Run this from the source branch, not the deployment branch.')
    for name in ('.htaccess', '.gitignore', '.env.example', 'index.php', 'zaidimas/index.html'):
        if not (directory / name).is_file():
            raise SystemExit(f'Missing runtime file: {name}')
    if any((directory / name).exists() for name in ('.git', '.env', 'env.local.php', 'tests', 'node_modules')):
        raise SystemExit('Refusing to publish credentials, Git state, tests, or build dependencies.')
    source_sha = git(repository, 'rev-parse', 'HEAD', capture=True).strip()
    remote_ref = git(repository, 'ls-remote', '--heads', remote, 'refs/heads/' + branch, capture=True).strip()
    parent = Path(tempfile.mkdtemp(prefix='blynine-deploy-'))
    checkout = parent / 'checkout'
    orphan = None
    try:
        if remote_ref:
            git(repository, 'fetch', '--no-tags', remote, 'refs/heads/' + branch)
            git(repository, 'worktree', 'add', '--detach', str(checkout), 'FETCH_HEAD')
        else:
            git(repository, 'worktree', 'add', '--detach', str(checkout), 'HEAD')
            orphan = 'blynine-publish-' + uuid.uuid4().hex
            git(checkout, 'checkout', '--orphan', orphan)
        # Remove only files in this newly created temporary worktree. Its .git pointer stays.
        for path in checkout.iterdir():
            if path.name == '.git':
                continue
            if path.is_dir() and not path.is_symlink():
                shutil.rmtree(path)
            else:
                path.unlink()
        shutil.copytree(directory, checkout, dirs_exist_ok=True)
        git(checkout, 'add', '--all')
        expected = {str(path.relative_to(directory)) for path in directory.rglob('*') if path.is_file()}
        staged = set(git(checkout, 'ls-files', '-z', capture=True).split('\0'))
        missing = expected - staged
        if missing:
            raise RuntimeError('Runtime files were ignored: ' + ', '.join(sorted(missing)))
        changed = subprocess.run(['git', '-C', str(checkout), 'diff', '--cached', '--quiet']).returncode
        if changed == 0:
            print('Deployment branch already matches the prepared site.')
            return
        if changed != 1:
            raise RuntimeError('Could not compare deployment content.')
        git(checkout, '-c', 'user.name=github-actions[bot]',
            '-c', 'user.email=41898282+github-actions[bot]@users.noreply.github.com',
            'commit', '-m', 'Deploy ' + source_sha[:12])
        git(checkout, 'push', remote, 'HEAD:refs/heads/' + branch)
        print(f'Published {source_sha[:12]} to {branch}.')
    finally:
        if checkout.exists():
            git(repository, 'worktree', 'remove', '--force', str(checkout))
        if orphan:
            subprocess.run(['git', '-C', str(repository), 'branch', '-D', orphan],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        shutil.rmtree(parent, ignore_errors=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--directory', type=Path, default=Path('dist/hostinger'))
    parser.add_argument('--branch', default='codex/hostinger-deploy')
    options = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    publish(root, options.directory, options.branch)
