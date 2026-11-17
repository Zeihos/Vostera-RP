#!/usr/bin/env python3
"""Предварительная проверка игровой сборки. Не заменяет компилятор Pawn."""
from __future__ import annotations

import argparse
import json
import re
from pathlib import Path


def analyse(root: Path) -> dict:
    root = root.resolve()
    problems: list[str] = []
    warnings: list[str] = []
    source = root / 'gamemodes/vostera.pwn'
    if not source.is_file():
        problems.append('Нет gamemodes/vostera.pwn — исходник ещё не импортирован')
        return {'ready_for_compile': False, 'errors': problems, 'warnings': warnings}

    raw = source.read_bytes()
    try:
        text = raw.decode('utf-8')
        encoding = 'UTF-8'
    except UnicodeDecodeError:
        text = raw.decode('cp1251', errors='replace')
        encoding = 'CP1251'
    includes = []
    for m in re.finditer(r'^\s*#include\s*(?:<([^>]+)>|"([^"]+)"|([\w./-]+))', text, re.M):
        name = next(part for part in m.groups() if part)
        includes.append(name)
        relative = Path(name + ('' if Path(name).suffix else '.inc'))
        if relative.is_absolute() or '..' in relative.parts:
            problems.append(f'Недопустимый путь подключаемой библиотеки: {name}')
            continue
        candidates = [root / 'pawno/include' / relative, root / 'gamemodes' / relative]
        if not any(c.is_file() for c in candidates):
            warnings.append(f'Не найден #include: {name} (возможна внешняя зависимость)')

    cfg = root / 'server.cfg'
    if not cfg.is_file():
        warnings.append('Нет server.cfg')
    else:
        try:
            config = cfg.read_text(encoding='utf-8', errors='replace')
            line = next((v for v in config.splitlines() if v.strip().lower().startswith('gamemode0 ')), None)
            if not line or 'vostera' not in line.lower().split():
                warnings.append('server.cfg не запускает gamemode0 vostera')
        except OSError:
            warnings.append('Не удалось прочитать server.cfg')

    if re.search(r'(?im)^\s*(?:rcon_password\s+\S+|.*SendRconCommand\s*\(\s*"rcon_password)', text):
        warnings.append('Проверьте настройки RCON: возможен жёстко заданный пароль')
    if re.search(r'\b(?:md5|MD5|WP_Hash)\s*\(', text):
        warnings.append('Проверьте хранение паролей: обнаружена устаревшая хеш-функция')
    amx = root / 'gamemodes/vostera.amx'
    if not amx.is_file():
        warnings.append('AMX отсутствует — компиляция ещё не подтверждена')
    elif amx.stat().st_mtime < source.stat().st_mtime:
        warnings.append('AMX старше исходника PWN; возможно, его нужно пересобрать')

    return {
        'ready_for_compile': not problems,
        'source': str(source),
        'encoding': encoding,
        'source_bytes': len(raw),
        'includes_total': len(includes),
        'warnings': warnings,
        'errors': problems,
        'compiler_was_run': False,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1],
                        help='Корень каталога игрового мода')
    parser.add_argument('--json', action='store_true', help='Отчёт в JSON')
    args = parser.parse_args()
    result = analyse(args.root)
    if args.json:
        print(json.dumps(result, indent=2, ensure_ascii=False))
    else:
        print('VOSTERA — предварительная проверка Pawn')
        for e in result['errors']:
            print('ОШИБКА:', e)
        for w in result['warnings']:
            print('ВНИМАНИЕ:', w)
        print('Готовность исходников к запуску компилятора:', 'да' if result['ready_for_compile'] else 'нет')
        print('Это не результат компиляции и не подтверждение работы сервера.')
    return 0 if result['ready_for_compile'] else 2


if __name__ == '__main__':
    raise SystemExit(main())
