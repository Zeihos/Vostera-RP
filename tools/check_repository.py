#!/usr/bin/env python3
"""Проверка приватных файлов и структуры репозитория VOSTERA."""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FORBIDDEN_TRACKED = {
    'Форум/private/install.key',
    'Форум/private/store.json',
    'Вики/private/install.key',
    'Вики/private/app.key',
    'Вики/private/data.json',
}
REQUIRED = {
    'Сайт/config/site.php',
    'Форум/tools/prepare-install.php',
    'Вики/tools/prepare-install.php',
    'Вики/private/data.example.json',
    'Мод/README.md',
}


def main() -> int:
    errors: list[str] = []
    tracked = subprocess.run(
        ['git', 'ls-files', '-z'], cwd=ROOT, capture_output=True, check=True
    ).stdout.decode('utf-8', errors='replace').split('\0')
    for path in FORBIDDEN_TRACKED:
        if path in tracked:
            errors.append(f'Секретный или рабочий файл отслеживается Git: {path}')
    for path in REQUIRED:
        if not (ROOT / path).is_file():
            errors.append(f'Отсутствует обязательный файл: {path}')
    docs = [('Форум', 'INSTALL.txt'), ('Вики', 'INSTALL.txt')]
    for folder, file in docs:
        body = (ROOT / folder / file).read_text(encoding='utf-8')
        if 'prepare-install.php' not in body:
            errors.append(f'{folder}/{file}: отсутствует инструкция генерации ключа')
    try:
        data = json.loads((ROOT / 'Вики/private/data.example.json').read_text(encoding='utf-8'))
        if data.get('installed') or data.get('users'):
            errors.append('data.example.json должен содержать только пустое состояние')
    except (OSError, json.JSONDecodeError) as exc:
        errors.append(f'Некорректный шаблон данных Wiki: {exc}')
    if errors:
        for error in errors:
            print('ERROR:', error, file=sys.stderr)
        return 1
    print('OK: секреты не отслеживаются, структура проекта корректна')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
