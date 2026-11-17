<p align="center">
  <img src="./Оформление/cover.jpg" width="100%" alt="VOSTERA ROLEPLAY — фирменная обложка">
</p>

<h1 align="center">VOSTERA ROLEPLAY</h1>
<p align="center">
  <strong>Твоя история начинается здесь.</strong><br>
  Многопользовательский RP-проект на базе SA-MP / CR:MP.
</p>

<p align="center">
  <a href="https://github.com/Zeihos/Vostera-RP/actions/workflows/quality.yml"><img src="https://github.com/Zeihos/Vostera-RP/actions/workflows/quality.yml/badge.svg" alt="Состояние проверок"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Статус-в%20разработке-B63459?style=flat-square" alt="Статус: в разработке">
</p>

<p align="center">
  <a href="https://vk.ru/vostera_rp"><b>ВКонтакте</b></a> &nbsp;·&nbsp;
  <a href="https://t.me/vostera_rp"><b>Telegram</b></a> &nbsp;·&nbsp;
  <a href="https://github.com/Zeihos/Vostera-RP/issues"><b>Обсуждение разработки</b></a>
</p>

<p align="center">
  <a href="#о-проекте">О проекте</a> &nbsp;•&nbsp;
  <a href="#состав-проекта">Разделы</a> &nbsp;•&nbsp;
  <a href="#быстрый-старт">Запуск</a> &nbsp;•&nbsp;
  <a href="#план-и-документация">Документация</a>
</p>

---

## О проекте

**VOSTERA ROLEPLAY** — игровой RP-проект с собственным оформлением и сервисами для сообщества. Здесь собираются сайт, форум, база знаний, инструменты контроля качества и материалы для будущего игрового сервера.

В основе стиля — тёмные оттенки, красные акценты и атмосфера автомобильной RP-игры. Все основные пользовательские материалы и документация ведутся на **русском языке**.

> [!NOTE]
> **Текущий этап — разработка.** Исходник игрового мода `vostera.pwn` и проверенный `vostera.amx` **пока не опубликованы**. Публичный игровой сервер и дата релиза не подтверждены. Сайт, форум и Wiki имеют исходный код и автоматические тесты, но перед публичным запуском требуют настройки и проверки на хостинге.

## Состав проекта

<p align="center">
  <img src="./docs/assets/ecosystem.svg" alt="Четыре части VOSTERA: сайт, форум, Wiki и игровой сервер">
</p>

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🌐 <a href="./Сайт/">Сайт</a></h3>
      <p>Новости, информация о проекте, инструкция для новичков и мониторинг серверов SA-MP / open.mp.</p>
      <p><sub>PHP · CSS · JavaScript</sub></p>
      <a href="./Сайт/">Исходники сайта</a>
    </td>
    <td width="50%" valign="top">
      <h3>💬 <a href="./Форум/">Форум</a></h3>
      <p>Обсуждения, регистрация, профили, модерация, разделы сообщества и административная панель.</p>
      <p><sub>PHP 8.1+ · JSON-хранилище</sub></p>
      <a href="./Форум/INSTALL.txt">Инструкция установки</a>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <h3>📚 <a href="./Вики/">VOSTERA WIKI</a></h3>
      <p>База знаний: категории, поиск, статьи, редактор и разграничение прав доступа.</p>
      <p><sub>PHP 8.1+ · JSON-хранилище</sub></p>
      <a href="./Вики/INSTALL.txt">Инструкция установки</a>
    </td>
    <td width="50%" valign="top">
      <h3>🎮 <a href="./Мод/">Игровой мод</a></h3>
      <p>Подготовка компиляции Pawn, диагностика зависимостей, проверка безопасности и будущие игровые испытания.</p>
      <p><sub>Pawn · SA-MP / CR:MP · пока без опубликованного AMX</sub></p>
      <a href="https://github.com/Zeihos/Vostera-RP/issues/2">Текущая задача разработки</a>
    </td>
  </tr>
</table>

Фирменные аватарки, обложки и материалы публикаций находятся в каталоге **[Оформление](./Оформление/)**.

## Быстрый старт

Для локальной работы с веб-сервисами требуется **PHP 8.1+**. Для запуска проверок — **Python 3.10+**. Форум и Wiki не требуют отдельного MySQL-сервера.

<details>
<summary><b>Открыть сайт локально</b></summary>

Из корня репозитория:

~~~bash
php -S 127.0.0.1:8786 -t Сайт
~~~

Откройте http://127.0.0.1:8786. Чтобы отображался реальный онлайн, задайте адреса своих серверов в `Сайт/config/site.php`.
</details>

<details>
<summary><b>Установить форум или Wiki</b></summary>

У каждого сервиса должен быть **свой** виртуальный хост с корнем `public/`. Каталог `private/` не должен быть доступен из интернета.

В каталоге соответствующего сервиса:

~~~bash
php tools/prepare-install.php
~~~

Сохраните сгенерированный ключ **только у себя**, затем откройте `/install` и создайте владельца. Не добавляйте рабочие файлы и секреты в Git.

Подробности: [форум](./Форум/INSTALL.txt) · [Wiki](./Вики/INSTALL.txt) · [развёртывание](./docs/deploy.md).
</details>

<details>
<summary><b>Запустить автоматические проверки</b></summary>

Из корня репозитория:

~~~bash
python3 tools/check_repository.py
python3 tools/test_repository_policy.py
python3 tools/test_install_bootstrap.py
python3 tools/test_mod_preflight.py
python3 tools/test_web_smoke.py
php Сайт/tests/test_samp_query.php
~~~

Проверки PHP и веб-сервисов также выполняются через [GitHub Actions](https://github.com/Zeihos/Vostera-RP/actions). Они **не заменяют** компиляцию Pawn и игровые тесты.
</details>

## План и документация

| Документ | Для чего нужен |
|:--|:--|
| [План разработки](./docs/roadmap.md) | Приоритеты и условия подготовки к релизу |
| [Архитектура](./docs/structure.md) | Связи и назначение каталогов проекта |
| [Развёртывание](./docs/deploy.md) | Установка, безопасность и резервное копирование |
| [История изменений](./CHANGELOG.md) | Только фактически внесённые изменения |
| [Участие в разработке](./CONTRIBUTING.md) | Как предлагать исправления и Pull Request |
| [Безопасность](./SECURITY.md) · [Права на материалы](./NOTICE.md) | Ответственное раскрытие уязвимостей и условия использования файлов |

**Ближайшая цель:** получить исходники игрового мода, разрешённые к публикации, воспроизводимую компиляцию Pawn, рабочий AMX и результаты тестирования сервера с MariaDB. [Посмотреть задачу #2](https://github.com/Zeihos/Vostera-RP/issues/2).

---

<p align="center">
  <b>VOSTERA ROLEPLAY</b><br>
  <sub>Твоя история начинается здесь.</sub><br><br>
  <a href="https://vk.ru/vostera_rp">VK</a> &nbsp;·&nbsp;
  <a href="https://t.me/vostera_rp">Telegram</a> &nbsp;·&nbsp;
  <a href="https://github.com/Zeihos/Vostera-RP/issues">GitHub Issues</a>
</p>
