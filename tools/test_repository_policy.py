#!/usr/bin/env python3
"""Регрессионные тесты защитных правил публичного репозитория."""
from __future__ import annotations

import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory

SCRIPT = Path(__file__).with_name('check_repository.py')
spec = importlib.util.spec_from_file_location('repo_policy', SCRIPT)
assert spec and spec.loader
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)

BLOCKED = [
    'Форум/private/install.key', 'Вики/private/app.key',
    'Вики/private/data.json', 'Форум/private/store.json',
    'Вики/private/data.lock', 'Форум/private/store.lock',
    'Форум/public/uploads/secret.jpg', 'Вики/public/uploads/private.png',
    'Сайт/.env', '.env.local', 'Мод/scriptfiles/serverconfig.ini',
    'Мод/gamemodes/vostera.amx', 'Мод/plugins/unknown.dll',
    'Форум/private/app.sqlite', 'runtime.log',
]
ALLOWED = [
    'Вики/private/data.example.json', 'Сайт/config/site.php',
    '.env.example', 'Мод/gamemodes/vostera.pwn',
    'Оформление/ava.jpg', 'Форум/public/assets/img/favicon.png',
]
for path in BLOCKED:
    assert policy.forbidden_tracked(path), f'Не заблокирован: {path}'
for path in ALLOWED:
    assert not policy.forbidden_tracked(path), f'Ложная блокировка: {path}'

with TemporaryDirectory() as tmp:
    root = Path(tmp)
    docs = root / 'docs'
    docs.mkdir()
    guide = docs / 'guide.md'
    (root / 'README.md').write_text('OK', encoding='utf-8')
    assert policy.missing_links(guide, '[Корень](../README.md)') == []
    assert policy.missing_links(guide, '[Нет](../UNKNOWN.md)') != []
    assert policy.missing_links(guide, '[Внешняя](https://example.org/docs)') == []

print(f'OK: {len(BLOCKED)} запрещённых + {len(ALLOWED)} разрешённых путей, Markdown-ссылки')
