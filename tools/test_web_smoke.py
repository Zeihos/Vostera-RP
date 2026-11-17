#!/usr/bin/env python3
"""HTTP smoke: живые PHP-сервисы во временных каталогах, без пользовательских данных."""
from __future__ import annotations

import http.cookiejar
import json
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TIMEOUT = 5


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


class LocalApp:
    def __init__(self, path: Path, *, nested_public: bool) -> None:
        self.root = path
        self.port = free_port()
        self.base = f'http://127.0.0.1:{self.port}'
        cmd = ['php', '-S', f'127.0.0.1:{self.port}']
        if nested_public:
            cmd += ['-t', 'public', 'public/router.php']
        else:
            cmd += ['-t', '.']
        self.log = tempfile.TemporaryFile(mode='w+t')
        self.proc = subprocess.Popen(cmd, cwd=path, stdout=self.log, stderr=subprocess.STDOUT)
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        for _ in range(80):
            if self.proc.poll() is not None:
                self.stop()
                raise RuntimeError('PHP-сервер завершился до запуска')
            try:
                with socket.create_connection(('127.0.0.1', self.port), timeout=.25):
                    break
            except (ConnectionRefusedError, TimeoutError, OSError):
                time.sleep(.05)
        else:
            self.stop()
            raise RuntimeError('PHP-сервер не начал слушать порт')

    def request(self, url: str, fields: dict[str, str] | None = None) -> tuple[int, str]:
        data = urllib.parse.urlencode(fields).encode() if fields is not None else None
        req = urllib.request.Request(self.base + url, data=data)
        try:
            with self.opener.open(req, timeout=TIMEOUT) as resp:
                return resp.status, resp.read().decode('utf-8', errors='replace')
        except urllib.error.HTTPError as exc:
            return exc.code, exc.read().decode('utf-8', errors='replace')

    def stop(self) -> None:
        self.proc.terminate()
        try:
            self.proc.wait(timeout=2)
        except subprocess.TimeoutExpired:
            self.proc.kill()
            self.proc.wait(timeout=2)
        self.log.close()


def csrf(html: str) -> str:
    match = re.search(r'<input\b[^>]*name=["\']csrf["\'][^>]*value=["\']([^"\']+)', html)
    assert match, 'Не найден CSRF-токен в форме'
    return match.group(1)


def check_install(component: str) -> None:
    with tempfile.TemporaryDirectory(prefix='vostera-http-') as dirname:
        path = Path(dirname) / component
        shutil.copytree(ROOT / component, path)
        state_path = path / ('private/store.json' if component == 'Форум' else 'private/data.json')
        assert not state_path.exists(), 'Тест должен запускаться на чистой базе'
        proc = subprocess.run(['php', 'tools/prepare-install.php'], cwd=path, text=True,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
        key = (path / 'private/install.key').read_text().strip()
        assert key and key not in proc.stderr
        app = LocalApp(path, nested_public=True)
        try:
            status, body = app.request('/install')
            assert status == 200 and 'VOSTERA' in body.upper(), (component, status)
            status, body = app.request('/install', {'csrf': 'bad'})
            assert status == 419, (component, 'CSRF не отклонён', status)
            _, body = app.request('/install')
            token = csrf(body)
            if component == 'Форум':
                form = {'csrf': token, 'install_key': key,
                        'name': 'Test_Admin', 'email': 'test@example.invalid',
                        'password': 'Test-Password-12345'}
            else:
                form = {'csrf': token, 'key': key,
                        'login': 'Test_Admin', 'password': 'Test-Password-12345',
                        'password_repeat': 'Test-Password-12345'}
            status, body = app.request('/install', form)
            assert status == 200 and 'VOSTERA' in body.upper(), (component, 'Установка', status, body[:300])
            assert state_path.exists(), (component, 'База после установки не создана')
            assert not (path / 'private/install.key').exists(), (component, 'Ключ установки не удалён')
            status, body = app.request('/admin')
            assert status == 200, (component, 'Админ-панель', status, body[:300])
            status, body = app.request('/')
            assert status == 200 and 'VOSTERA' in body.upper(), (component, 'Главная', status)
            status, _ = app.request('/install')
            assert status == 200, (component, 'Повторный доступ к /install', status)
        finally:
            app.stop()
        print(f'OK: {component}: установка, CSRF, вход в админку и главная')


def check_site() -> None:
    with tempfile.TemporaryDirectory(prefix='vostera-http-') as dirname:
        path = Path(dirname) / 'Сайт'
        shutil.copytree(ROOT / 'Сайт', path)
        app = LocalApp(path, nested_public=False)
        try:
            status, body = app.request('/')
            assert status == 200 and 'VOSTERA' in body.upper()
            status, body = app.request('/api/servers.php')
            assert status == 200 and json.loads(body)['servers'] == []
            status, body = app.request('/assets/css/site.css')
            assert status == 200 and 'font' in body.lower()
        finally:
            app.stop()
        print('OK: Сайт: главная, JSON API, CSS')


if __name__ == '__main__':
    check_site()
    check_install('Форум')
    check_install('Вики')
