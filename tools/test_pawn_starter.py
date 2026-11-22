#!/usr/bin/env python3
"""Static, dependency-free checks for the independently authored Pawn starter."""
from pathlib import Path

root=Path(__file__).resolve().parents[1]
src=(root/'Мод/prototypes/vostera_starter.pwn').read_text()
build=(root/'Мод/tools/build-prototype.sh').read_text()
ci=(root/'.github/workflows/pawn-starter.yml').read_text()
checks={
    'No proprietary code': 'independently written gameplay prototype' in src,
    'Standard open.mp include': '#include <open.mp>' in src,
    'Reset on connect': 'public OnPlayerConnect(playerid)' in src,
    'Reset on disconnect': 'public OnPlayerDisconnect(playerid, reason)' in src,
    'No player database': all(keyword not in src.lower() for keyword in ('mysql_', 'db_open', 'password_hash', 'password_verify')),
    'Roleplay chat radius': 'GetPlayerDistanceFromPoint(target, x, y, z)' in src,
    'Same dimension': 'GetPlayerVirtualWorld(target) != world' in src and 'GetPlayerInterior(target) != interior' in src,
    'Suppress default global chat': 'return 0;' in src,
    'Jobs and cooldown': '/job courier' in src and '/work' in src and 'VOSTERA_WORK_COOLDOWN' in src,
    'Session state reset': src.count('g_WorkCount[playerid] = 0;') >= 2,
    'Compiler version pinned': 'v3.10.11' in build and '13977433acb1dfb23e3ec6354f622115d5073e97aced9395058a9db59d2564c2' in build,
    'Dependencies pinned': '17c1b0191195cb0b59a820135fbca622c84129dd' in build,
    'AMX validation': 'if [ ! -s "$OUTPUT" ]' in build,
    'Warning-free compilation': "grep -Eiq" in build and "compile.log" in build,
    'CI artifact': 'vostera-starter-amx' in ci,
    'No release claim': 'prototype' in ci.lower() or 'прототип' in ci.lower(),
}
for name,passed in checks.items():
    print(f'{"OK" if passed else "FAIL"}: {name}')
assert all(checks.values()), 'Pawn starter source-level checks failed'
print(f'OK: {len(checks)} source/CI consistency checks')
