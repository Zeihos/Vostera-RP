#!/usr/bin/env python3
"""Проверка структуры, документации и файлов, которым не место в публичном Git."""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[1]
REQUIRED = {
    'README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'SECURITY.md', 'NOTICE.md',
    'docs/structure.md', 'docs/deploy.md', 'docs/roadmap.md',
    'Сайт/config/site.php', 'Сайт/tests/test_samp_query.php',
    'Форум/tools/prepare-install.php', 'Вики/tools/prepare-install.php',
    'Вики/private/data.example.json', 'Мод/README.md',
    '.github/workflows/quality.yml', '.github/PULL_REQUEST_TEMPLATE.md',
}
DOCS = [
    'README.md', 'CONTRIBUTING.md', 'SECURITY.md', 'NOTICE.md',
    'docs/structure.md', 'docs/deploy.md', 'docs/roadmap.md', 'CHANGELOG.md',
]


def forbidden_tracked(path: str) -> bool:
    """Опасные для открытой публикации файлы, независимо от .gitignore."""
    p = path.replace('\\', '/')
    name = p.rsplit('/', 1)[-1]
    if name in {'.DS_Store', 'Thumbs.db'} or name.endswith(('.log', '.tmp', '.bak', '.pyc')):
        return True
    if name == '.env' or (name.startswith('.env.') and name not in {'.env.example', '.env.sample'}):
        return True
    if '/private/' in '/' + p and name in {'install.key', 'app.key', 'store.json', 'data.json', 'store.lock', 'data.lock'}:
        return True
    if '/private/' in '/' + p and name.endswith(('.sqlite', '.sqlite3', '.db')):
        return True
    if p.startswith(('Форум/public/uploads/', 'Вики/public/uploads/')):
        return True
    if p == 'Мод/scriptfiles/serverconfig.ini':
        return True
    if p.startswith('Мод/') and (name.endswith(('.amx', '.dll', '.so', '.exe')) or name.endswith('.sql.gz')):
        return True
    return False


def missing_links(file_path: Path, body: str) -> list[str]:
    """Проверяем относительные ссылки в Markdown (ссылки на сеть не трогаем)."""
    missing: list[str] = []
    for match in re.finditer(r'(?<!!)\[[^\]]+\]\(([^)]+)\)', body):
        target = match.group(1).strip().split('#', 1)[0].split('?', 1)[0]
        if not target or target.startswith(('http://', 'https://', 'mailto:', '/')):
            continue
        target = unquote(target)
        if not (file_path.parent / target).exists():
            missing.append(f'{file_path}: неверная ссылка {target}')
    return missing


def main() -> int:
    errors: list[str] = []
    proc = subprocess.run(['git', 'ls-files', '-z'], cwd=ROOT, capture_output=True)
    if proc.returncode != 0:
        print('ERROR: для проверки необходимо выполнить git внутри репозитория', file=sys.stderr)
        return 1
    tracked = proc.stdout.decode('utf-8', errors='replace').split('\0')
    for path in tracked:
        if path and forbidden_tracked(path):
            errors.append(f'Нельзя отслеживать в Git: {path}')
    for path in REQUIRED:
        if not (ROOT / path).is_file():
            errors.append(f'Отсутствует обязательный файл: {path}')

    for folder in ('Форум', 'Вики'):
        install_path = ROOT / folder / 'INSTALL.txt'
        if not install_path.is_file() or 'prepare-install.php' not in install_path.read_text(encoding='utf-8'):
            errors.append(f'{folder}/INSTALL.txt: не описана безопасная генерация ключа')

    for path in DOCS:
        f = ROOT / path
        if f.exists():
            errors.extend(missing_links(f, f.read_text(encoding='utf-8')))

    try:
        data = json.loads((ROOT / 'Вики/private/data.example.json').read_text(encoding='utf-8'))
        if data.get('installed') or data.get('users'):
            errors.append('Пример данных Wiki должен быть без активных аккаунтов')
    except (OSError, ValueError) as exc:
        errors.append(f'Некорректный шаблон данных Wiki: {exc}')

    if errors:
        for error in errors:
            print('ERROR:', error, file=sys.stderr)
        return 1
    print('OK: структура, документация и приватные файлы проверены')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
