<?php
require_once __DIR__.'/lib.php';
function layout_begin(string $title, array $db, ?array $user): void {
    $config=cfg(); $full=$title==='Форум'?$config['name'].' — Форум':$title.' — '.$config['name'];
    $uri=$_SERVER['REQUEST_URI']??'/';
    $flashes=$_SESSION['flash']??[];unset($_SESSION['flash']);
    header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="theme-color" content="#12101b">
<meta name="description" content="<?=h(cfg()['description'])?>"><title><?=h($full)?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="/assets/css/forum.css?v=10"><script defer src="/assets/js/forum.js?v=6"></script>
</head><body>
<div class="topline"></div>
<header class="topnav"><div class="nav-inner container">
 <a class="brand" href="/" aria-label="VOSTERA — главная">
  <img src="/assets/img/vostera-avatar.jpg" alt="VOSTERA" class="brand-mark">
  <span class="brand-name">VOSTERA <em>ROLEPLAY</em><small>ОФИЦИАЛЬНОЕ СООБЩЕСТВО</small></span>
 </a>
 <nav class="desktop-links" aria-label="Основная навигация">
  <a class="<?=($uri==='/'?'active':'')?>" href="/">Главная</a><a href="/search">Поиск тем</a><a href="/rules">Правила</a>
  <?php if($user && staff($user)):?><a href="/reports">Жалобы</a><?php endif;?><?php if(admin($user)):?><a href="/admin">Управление</a><?php endif;?>
 </nav>
 <div class="nav-actions">
  <?php if($user):?><a href="/user/<?=$user['id']?>" class="user-pill"><?=avatar($user)?><span><?=h($user['name'])?></span></a><form method="post" action="/logout" class="inlineform"><?=csrf_field()?><button class="icon-btn" title="Выйти" aria-label="Выйти">↪</button></form><?php else:?><a class="login-link" href="/login">Войти</a><a class="btn btn-small" href="/register">Регистрация</a><?php endif;?>
  <button class="menu-toggle" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-nav">☰</button>
 </div></div>
 <nav class="mobile-nav" id="mobile-nav" hidden aria-label="Мобильная навигация"><a href="/">Главная</a><a href="/search">Поиск</a><a href="/rules">Правила</a><?php if(staff($user)):?><a href="/reports">Жалобы</a><?php endif;?><?php if(admin($user)):?><a href="/admin">Управление</a><?php endif;?><?php if($user):?><a href="/settings">Настройки профиля</a><form method="post" action="/logout"><?=csrf_field()?><button type="submit" class="mobile-signout">Выйти из аккаунта</button></form><?php else:?><a href="/login">Войти</a><a href="/register">Регистрация</a><?php endif;?></nav>
</header>
<main class="container page-content">
<?php foreach($flashes as $f):?><div class="notice notice-<?=h($f['type'])?>" role="status"><?=h($f['message'])?></div><?php endforeach;?>
<?php
}
function layout_end():void {?>
</main><footer class="footer"><div class="container foot-grid"><div class="foot-brand"><strong>VOSTERA <span>ROLEPLAY</span></strong><p>Истории создают игроки. Сообщество объединяет всех.</p><small>© <?=date('Y')?> VOSTERA ROLEPLAY. Форум сообщества.</small></div><div class="foot-links"><a href="/rules">Правила форума</a><a href="<?=h(cfg()['vk'])?>" rel="noopener" target="_blank">ВКонтакте ↗</a><a href="<?=h(cfg()['telegram'])?>" rel="noopener" target="_blank">Telegram ↗</a><?php if(cfg()['game_site']):?><a href="<?=h(cfg()['game_site'])?>">Игровой сайт ↗</a><?php endif;?></div></div></footer>
</body></html><?php }
function breadcrumbs(array $items): void {echo '<nav class="crumbs" aria-label="Навигационная цепочка"><a href="/">Форум</a>';foreach($items as $item){echo '<span>›</span>';if(!empty($item[1]))echo '<a href="'.h($item[1]).'">'.h($item[0]).'</a>';else echo '<b>'.h($item[0]).'</b>';} echo '</nav>';}
function show_home(array $db, ?array $u): void {
    $users=count($db['users']); $topics=count($db['topics']);$posts=count($db['posts']);
    ?><section class="home-hero"><div class="hero-bg"></div><div class="hero-shade"></div><div class="hero-content"><div class="eyebrow"><span class="live-dot"></span> МИР ТВОИХ ИСТОРИЙ</div><h1>ДОБРО ПОЖАЛОВАТЬ<br>НА <span>VOSTERA ROLEPLAY</span></h1><p>Знакомься с игроками, обсуждай события, делись идеями и стань частью нашего игрового сообщества.</p><div class="hero-actions"><a class="btn" href="#forum-sections">Открыть разделы <span>↗</span></a><a class="btn btn-outline" href="<?=h(cfg()['vk'])?>" target="_blank" rel="noopener">Наше сообщество VK</a></div></div><div class="hero-bottom">РОЛЕВАЯ ИГРА <span>✦</span> СООБЩЕСТВО <span>✦</span> ВОЗМОЖНОСТИ</div></section>
    <div class="statbar"><div><b><?=number_format($users,0,'.',' ')?></b><span>Участников</span></div><div><b><?=number_format($topics,0,'.',' ')?></b><span>Обсуждений</span></div><div><b><?=number_format($posts,0,'.',' ')?></b><span>Сообщений</span></div><div class="statbar-info"><span class="pulse"></span><span>ФОРУМ СООБЩЕСТВА</span></div></div>
    <div class="main-grid"><div><div class="section-heading" id="forum-sections"><div><div class="overline">ЦЕНТР СООБЩЕСТВА</div><h2>Разделы форума</h2></div><span class="section-index">01 / VOSTERA</span></div>
    <?php foreach($db['categories'] as $group):?><section class="forum-group"><header class="group-header"><span class="group-diamond">◆</span><div><h3><?=h($group['title'])?></h3><p><?=h($group['subtitle'])?></p></div></header><div class="forum-list">
    <?php $groupForums=array_values(array_filter($db['forums'],fn($f)=>$f['category_id']===$group['id'])); usort($groupForums,fn($a,$b)=>$a['sort']<=>$b['sort']);foreach($groupForums as $f):?>
    <?php $ts=topics_in_forum($db,$f['id']);$postCount=0;foreach($ts as $tt)$postCount+=count(posts_in_topic($db,$tt['id']));$last=$ts[0]??null;?>
    <a href="/forum/<?=$f['id']?>" class="forum-row"><div class="forum-icon"><?=h($f['icon'])?></div><div class="forum-detail"><strong><?=h($f['title'])?></strong><small><?=h($f['description'])?></small></div><div class="forum-numbers"><strong><?=count($ts)?></strong><small>Тем</small></div><div class="forum-numbers posts"><strong><?=$postCount?></strong><small>Сообщений</small></div><div class="forum-arrow">↗</div></a>
    <?php endforeach;?></div></section><?php endforeach;?></div>
    <aside class="sidebar"><section class="side-panel"><header class="side-title"><span>◈</span> БЫСТРЫЙ СТАРТ</header><div class="side-body"><p>Впервые на форуме? Прочитай правила и представься нашему сообществу.</p><a class="quick-link" href="/rules"><span>01</span> Ознакомиться с правилами <b>↗</b></a><a class="quick-link" href="/forum/3"><span>02</span> Общение игроков <b>↗</b></a><a class="quick-link" href="/forum/10"><span>03</span> Задать вопрос <b>↗</b></a></div></section>
    <section class="side-panel"><header class="side-title"><span>◈</span> СООБЩЕСТВО</header><div class="side-body social-buttons"><a href="<?=h(cfg()['vk'])?>" target="_blank" rel="noopener"><span class="social-ic">VK</span><div><strong>ВКонтакте</strong><small>Новости и жизнь проекта</small></div><b>↗</b></a><a href="<?=h(cfg()['telegram'])?>" target="_blank" rel="noopener"><span class="social-ic telegram-ic">✈</span><div><strong>Telegram</strong><small>Анонсы и общение</small></div><b>↗</b></a></div></section>
    <section class="side-panel"><header class="side-title"><span>◈</span> НОВЫЕ ОБСУЖДЕНИЯ</header><div class="side-body recent-threads"><?php $recent=$db['topics'];usort($recent,fn($a,$b)=>$b['updated_at']<=>$a['updated_at']);foreach(array_slice($recent,0,5) as $t):?><a href="/topic/<?=$t['id']?>"><b><?=h($t['title'])?></b><small><?=h(forum_by_id($db,$t['forum_id'])['title']??'Раздел')?> · <?=js_date($t['updated_at'])?></small></a><?php endforeach;?><?php if(!$recent):?><div class="empty-side">Первые обсуждения скоро появятся.</div><?php endif;?></div></section>
    </aside></div>
    <?php
}
function topic_card(array $db,array $t):void {
    $author=user_by_id($db,$t['author_id']); $n=topic_replies($db,$t['id']);
    ?><a class="thread-row" href="/topic/<?=$t['id']?>"><div class="thread-symbol"><?=($t['pinned']?'♛':'▤')?></div><div class="thread-main"><strong><?php if($t['pinned']):?><span class="pin-label">ВАЖНО</span><?php endif;?><?=h($t['title'])?><?php if($t['locked']):?> <span class="lock-label">🔒</span><?php endif;?></strong><small><?=h($author['name']??'Удалённый пользователь')?> · <?=js_date($t['created_at'])?></small></div><div class="thread-metric"><b><?=$n?></b><small>Ответов</small></div><div class="thread-metric views"><b><?=($t['views']??0)?></b><small>Просмотров</small></div><div class="arrow-end">↗</div></a><?php
}
function editor(string $action,string $button, string $body='',string $name='body'):void { ?><form method="post" action="<?=h($action)?>" class="editor-form"><?=csrf_field()?><label for="editor">Ваше сообщение</label><textarea name="<?=h($name)?>" id="editor" rows="7" required maxlength="12000" placeholder="Напишите сообщение... Не публикуйте чужие персональные данные."><?=h($body)?></textarea><div class="editor-foot"><span>Поддерживаются обычный текст и ссылки. Максимум 12 000 символов.</span><button class="btn" type="submit"><?=h($button)?></button></div></form><?php }
