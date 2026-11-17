<?php
declare(strict_types=1);

const VOSTERA_ROOT = __DIR__ . '/..';
$SITE = require VOSTERA_ROOT . '/config/site.php';

function site(): array
{
    global $SITE;
    return $SITE;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function extlink(string $key): string
{
    $value = site()[$key] ?? '';
    return is_string($value) && preg_match('~^https://~i', $value) ? $value : '';
}

function current_page(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path === '/') return 'index.php';
    return basename($path);
}

function nav_link(string $href, string $label): void
{
    $active = current_page() === basename($href);
    echo '<a class="nav-link'.($active ? ' is-active' : '').'" href="'.e($href).'"'.($active ? ' aria-current="page"' : '').'>'.e($label).'</a>';
}

function render_header(string $title, string $description = ''): void
{
    $site = site();
    $title = $title === '' ? 'VOSTERA ROLEPLAY — Твоя история начинается здесь' : $title . ' — VOSTERA ROLEPLAY';
    if ($description === '') $description = 'VOSTERA ROLEPLAY — многопользовательский ролевой проект. Создай свою историю, стань частью сообщества и следи за развитием проекта.';
    $base = rtrim((string)($site['domain'] ?? ''), '/');
    $canonical = $base ? $base . ($_SERVER['SCRIPT_NAME'] ?? '/index.php') : '';
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#100b15">
    <meta name="description" content="<?=e($description)?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?=e($title)?>">
    <meta property="og:description" content="<?=e($description)?>">
    <meta property="og:image" content="<?=e($base ? $base . asset('img/og.jpg') : asset('img/og.jpg'))?>">
    <?php if ($canonical): ?><meta property="og:url" content="<?=e($canonical)?>"><link rel="canonical" href="<?=e($canonical)?>"><?php endif; ?>
    <title><?=e($title)?></title>
    <link rel="icon" sizes="32x32" href="<?=asset('img/favicon-32.png')?>">
    <link rel="apple-touch-icon" href="<?=asset('img/favicon-180.png')?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="<?=asset('css/site.css')?>?v=1.0.0">
    <script src="<?=asset('js/site.js')?>?v=1.0.0" defer></script>
</head>
<body>
    <div class="site-glow site-glow--one" aria-hidden="true"></div>
    <div class="site-glow site-glow--two" aria-hidden="true"></div>
    <header class="site-header" id="top">
       <div class="container header-row">
         <a class="brand" href="/index.php" aria-label="VOSTERA ROLEPLAY — главная">
            <span class="brand-icon">V<span class="brand-dot">.</span></span>
            <span class="brand-word"><strong>VOSTERA</strong><small>ROLEPLAY</small></span>
         </a>
         <nav class="desktop-nav" aria-label="Главное меню">
           <?php nav_link('/index.php', 'Главная'); ?>
           <?php nav_link('/about.php', 'О проекте'); ?>
           <?php nav_link('/play.php', 'Как начать'); ?>
           <?php nav_link('/servers.php', 'Серверы'); ?>
           <?php nav_link('/shop.php', 'Магазин'); ?>
           <?php nav_link('/news.php', 'Новости'); ?>
         </nav>
         <div class="header-actions">
            <a class="social-icon" href="<?=e($site['vk'])?>" rel="noopener noreferrer" target="_blank" aria-label="ВКонтакте"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.8 17.5h1.3s.4 0 .7-.2c.3-.2.3-.7.3-.7s0-2.1 1-2.4c1-.3 2.3 2 3.6 2.8.9.6 1.6.5 1.6.5l3-.1s1.6-.1.9-1.4c0-.1-.5-1-2.5-2.9-2.1-2-1.8-1.7.7-5.2 1.5-2.1 2.1-3.4 1.9-3.9-.1-.4-1.1-.3-1.1-.3l-3.4.1s-.2 0-.4.1c-.2.1-.3.4-.3.4s-.6 1.7-1.5 3.2c-1.7 3-2.4 3.1-2.7 2.9-.7-.5-.5-2.1-.5-3.2 0-3.6.5-5-.8-5.4-.4-.1-.7-.2-1.7-.2H9.5c-.6 0-1.1 0-1.4.2-.3.3-.3.7 0 1.3 1.5.3 2.8.9 3.8 1.9.8 3.4 1.9 5.5 3.8 6.8 2 1.5 4.1 1.4 4.1 1.4z" transform="translate(-4 -1) scale(.98)"/></svg></a>
            <a class="social-icon" href="<?=e($site['telegram'])?>" rel="noopener noreferrer" target="_blank" aria-label="Telegram"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21.9 4.6c.3-1.4-.5-1.9-1.6-1.5L2.8 9.8c-1.3.5-1.3 1.3-.2 1.7l4.5 1.4L17.6 6c.5-.3.9-.1.5.3L9.6 14l-.3 4.3c.5 0 .7-.2 1-.5l2.3-2.2 4.7 3.4c.8.5 1.4.3 1.7-.7z"/></svg></a>
            <a class="button button-small header-start" href="/play.php">Начать играть <span aria-hidden="true">↗</span></a>
         </div>
         <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-menu"><span></span><span></span><span></span></button>
       </div>
       <nav class="mobile-nav" id="mobile-menu" aria-label="Мобильное меню" hidden>
           <?php nav_link('/index.php', 'Главная'); ?>
           <?php nav_link('/about.php', 'О проекте'); ?>
           <?php nav_link('/play.php', 'Как начать играть'); ?>
           <?php nav_link('/servers.php', 'Серверы'); ?>
           <?php nav_link('/shop.php', 'Магазин'); ?>
           <?php nav_link('/news.php', 'Новости'); ?>
           <?php if(extlink('forum_url')): ?><a class="nav-link" href="<?=e(extlink('forum_url'))?>" target="_blank" rel="noopener noreferrer">Форум ↗</a><?php endif; ?>
           <div class="mobile-social"><a href="<?=e($site['vk'])?>" target="_blank" rel="noopener noreferrer">VK ↗</a><a href="<?=e($site['telegram'])?>" target="_blank" rel="noopener noreferrer">Telegram ↗</a></div>
       </nav>
    </header>
    <main id="content">
<?php
}

function render_footer(): void
{
    $site = site(); ?>
    </main>
    <footer class="site-footer">
      <div class="container">
         <div class="footer-top">
           <div class="footer-brand"><div class="footer-logo">VOSTERA<span>.</span></div><p>Твоя история начинается здесь. Создаём пространство для уникальных историй и сильного сообщества.</p></div>
           <div class="footer-links"><span>Навигация</span><a href="/about.php">О проекте</a><a href="/play.php">Как начать</a><a href="/servers.php">Наши серверы</a><a href="/shop.php">Магазин</a></div>
           <div class="footer-links"><span>Сообщество</span><a href="<?=e($site['vk'])?>" target="_blank" rel="noopener noreferrer">ВКонтакте ↗</a><a href="<?=e($site['telegram'])?>" target="_blank" rel="noopener noreferrer">Telegram ↗</a><a href="/news.php">Новости</a><?php if(extlink('forum_url')): ?><a href="<?=e(extlink('forum_url'))?>" target="_blank" rel="noopener noreferrer">Форум ↗</a><?php endif; ?></div>
         </div>
         <div class="footer-bottom"><span>© <?=date('Y')?> VOSTERA ROLEPLAY. Все права защищены.</span><span>Независимый игровой проект.</span></div>
      </div>
    </footer>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
<?php
}
