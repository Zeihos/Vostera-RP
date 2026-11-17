#!/usr/bin/env python3
"""Проверяет подготовку установки форума/Wiki без публикации секретов."""
from __future__ import annotations

import json
import re
import shutil
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def run_php(cwd: Path, *args: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(['php', 'tools/prepare-install.php', *args],
                          cwd=cwd, capture_output=True, text=True)


def check(project: str) -> None:
    with tempfile.TemporaryDirectory(prefix='vostera-test-') as dirname:
        work = Path(dirname)
        (work / 'tools').mkdir()
        (work / 'private').mkdir()
        shutil.copy2(ROOT / project / 'tools/prepare-install.php', work / 'tools')
        if project == 'Вики':
            template = ROOT / project / 'private/data.example.json'
            shutil.copy2(template, work / 'private/data.example.json')
        first = run_php(work)
        assert first.returncode == 0, (project, first.stderr)
        key = (work / 'private/install.key').read_text().strip()
        assert re.fullmatch(r'[\w-]{43}', key), project
        second = run_php(work)
        assert second.returncode != 0, 'Нельзя перезаписывать ключ без --rotate'
        assert (work / 'private/install.key').read_text().strip() == key
        rotated = run_php(work, '--rotate')
        assert rotated.returncode == 0, rotated.stderr
        assert (work / 'private/install.key').read_text().strip() != key
        if project == 'Вики':
            assert (work / 'private/app.key').exists()
            db = json.loads((work / 'private/data.json').read_text())
            assert db['installed'] is False
            assert db['users'] == []
            db['installed'] = True
            (work / 'private/data.json').write_text(json.dumps(db))
        else:
            (work / 'private/store.json').write_text('{}')
        assert run_php(work, '--rotate').returncode != 0, 'Установленный проект не должен переустанавливаться'
        if project == 'Вики':
            (work / 'private/app.key').unlink()
            migration = run_php(work, '--migrate')
            assert migration.returncode == 0, migration.stderr
            assert (work / 'private/app.key').exists()
            assert not (work / 'private/install.key').exists()
            assert json.loads((work / 'private/data.json').read_text())['installed'] is True
        print(f'OK: генерация, запрет повторения, ротация, блокировка после установки: {project}')


if __name__ == '__main__':
    check('Форум')
    check('Вики')
