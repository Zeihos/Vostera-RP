#!/usr/bin/env python3
"""Unit-тесты проверяющего скрипта игрового мода на искусственных данных."""
from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
file = ROOT / 'Мод/tools/preflight.py'
spec = importlib.util.spec_from_file_location('vostera_preflight', file)
assert spec and spec.loader
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

with tempfile.TemporaryDirectory() as folder:
    root = Path(folder)
    state = module.analyse(root)
    assert not state['ready_for_compile'], 'Отсутствующий PWN должен блокировать компиляцию'
    (root / 'gamemodes').mkdir()
    (root / 'pawno/include').mkdir(parents=True)
    (root / 'gamemodes/vostera.pwn').write_text('#include <a_samp>\nmain() {}\n', encoding='utf-8')
    (root / 'pawno/include/a_samp.inc').write_text('// test stub\n')
    (root / 'server.cfg').write_text('gamemode0 vostera 1\n')
    state = module.analyse(root)
    assert state['ready_for_compile'] and state['includes_total'] == 1
    assert not any('Не найден #include' in x for x in state['warnings'])
    (root / 'gamemodes/vostera.pwn').write_text('#include <unknown>\nmain() {}\n')
    state = module.analyse(root)
    assert any('unknown' in x for x in state['warnings'])
    (root / 'gamemodes/vostera.pwn').write_text('#include "../secret"\n')
    state = module.analyse(root)
    assert not state['ready_for_compile']

print('OK: предварительная проверка Pawn — 4 сценария')
