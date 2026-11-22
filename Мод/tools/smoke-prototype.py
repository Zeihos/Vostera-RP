#!/usr/bin/env python3
"""CI smoke: genuine open.mp server + VOSTERA AMX + SA-MP info query."""
from __future__ import annotations
import json
import secrets
import socket
import struct
import subprocess
import sys
import time
from pathlib import Path

IP = "127.0.0.1"

def packet(port: int) -> bytes:
    return b"SAMP" + socket.inet_aton(IP) + struct.pack("<H", port) + b"i"

def parse_info(data: bytes, request: bytes) -> dict:
    if len(request) != 11 or len(data) < 28 or data[:11] != request:
        raise ValueError("Некорректный заголовок/длина пакета")
    if data[11] not in (0, 1):
        raise ValueError("Некорректный флаг пароля")
    online, maximum = struct.unpack_from("<HH", data, 12)
    if not 1 <= maximum <= 1000 or online > maximum:
        raise ValueError("Некорректное число игроков")
    pos, strings = 16, []
    for _ in range(3):
        if len(data) - pos < 4:
            raise ValueError("Не хватает байт для длины строки")
        size = struct.unpack_from("<I", data, pos)[0]
        pos += 4
        if size > len(data) - pos:
            raise ValueError("Длина строки за пределами пакета")
        strings.append(data[pos:pos+size].decode("utf-8", "replace"))
        pos += size
    return {"players": online, "max_players": maximum, "name": strings[0],
            "mode": strings[1], "language": strings[2]}

def self_test() -> None:
    q = packet(17777)
    data = q + b"\x00" + struct.pack("<HH", 0, 10)
    for field in (b"VOSTERA", b"VOSTERA | Prototype", b"Russian"):
        data += struct.pack("<I", len(field)) + field
    assert parse_info(data, q)["mode"] == "VOSTERA | Prototype"
    corrupted = [b"", data[:10], data[:-1],
                 data[:11] + b"\x02" + data[12:],
                 data[:12] + struct.pack("<HH", 11, 10) + data[16:],
                 data[:16] + struct.pack("<I", 99999) + data[20:]]
    for wrong in corrupted:
        try:
            parse_info(wrong, q)
        except ValueError:
            continue
        raise AssertionError("Повреждённый ответ принят")
    print("OK: корректный UDP info + 6 повреждённых пакетов отклонены")

def log_tail(path: Path) -> str:
    if not path.is_file():
        return "(файл не создан)"
    return "\n".join(path.read_text(encoding="utf-8", errors="replace").splitlines()[-65:])

def run(server: Path, directory: Path) -> None:
    if not server.is_file():
        raise RuntimeError("Не найден бинарный файл omp-server")
    amx = directory / "gamemodes" / "vostera_starter.amx"
    if not amx.is_file() or amx.stat().st_size < 100:
        raise RuntimeError("Отсутствует тестовый AMX")
    with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as udp:
        udp.bind((IP, 0))
        port = udp.getsockname()[1]
    config = {"name": "VOSTERA Local Smoke", "language": "Russian",
              "announce": False, "enable_query": True, "max_players": 10,
              "network": {"bind": IP, "port": port},
              "pawn": {"main_scripts": ["vostera_starter 1"],
                       "side_scripts": [], "legacy_plugins": []},
              "rcon": {"enable": False, "password": secrets.token_urlsafe(32)},
              "logging": {"enable": True, "file": "vostera-smoke.log"}}
    (directory / "config.json").write_text(json.dumps(config, indent=2) + "\n", encoding="utf-8")
    stdout = directory / "vostera-smoke.stdout.log"
    with stdout.open("wb") as log:
        process = subprocess.Popen([str(server)], cwd=directory, stdin=subprocess.PIPE,
                                   stdout=log, stderr=subprocess.STDOUT, start_new_session=True)
        try:
            result = None
            request = packet(port)
            started = time.monotonic()
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as udp:
                udp.settimeout(0.4)
                while time.monotonic() - started < 22:
                    if process.poll() is not None:
                        raise RuntimeError("open.mp завершился раньше запуска UDP")
                    try:
                        udp.sendto(request, (IP, port))
                        response, source = udp.recvfrom(2048)
                        if source == (IP, port):
                            result = parse_info(response, request)
                            break
                    except socket.timeout:
                        time.sleep(0.15)
            if result is None:
                raise RuntimeError("За 22 секунды сервер не ответил на UDP-запрос")
            if result["players"] != 0 or result["max_players"] != 10:
                raise RuntimeError(f"Некорректная статистика: {result}")
            if "VOSTERA" not in result["mode"].upper():
                raise RuntimeError(f"Не запустился VOSTERA AMX: {result}")
            print(f"OK: настоящий open.mp отвечает; режим={result['mode']}; онлайн=0/10")
        finally:
            if process.poll() is None:
                try:
                    process.stdin.write(b"exit\n")
                    process.stdin.flush()
                    process.wait(timeout=4)
                except (OSError, subprocess.TimeoutExpired):
                    process.terminate()
                    try:
                        process.wait(timeout=3)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait()
            print("=== stdout ===\n" + log_tail(stdout))
            print("=== server log ===\n" + log_tail(directory / "vostera-smoke.log"))

if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        self_test()
    elif len(sys.argv) == 3:
        run(Path(sys.argv[1]).resolve(), Path(sys.argv[2]).resolve())
    else:
        raise SystemExit("Использование: smoke-prototype.py <omp-server> <server-dir> | --self-test")
