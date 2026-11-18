<?php
declare(strict_types=1);

const STORE = __DIR__ . '/store.json';
const STORE_LOCK = __DIR__ . '/store.lock';

function cfg(): array { static $c; return $c ??= require __DIR__ . '/config.php'; }
function installed(): bool { return is_file(STORE); }
function site_url(string $path = '/'): string { return $path; }
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function text_len(string $s): int { return preg_match_all('/./us', $s, $m) ?: 0; }
function lc(string $s): string {
    $from = preg_split('//u', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', -1, PREG_SPLIT_NO_EMPTY);
    $to = preg_split('//u', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя', -1, PREG_SPLIT_NO_EMPTY);
    return strtolower(strtr($s, array_combine($from, $to)));
}
function clean(string $key, int $max=10000): string { return trim(substr((string)($_POST[$key] ?? ''), 0, $max*4)); }
function id(string $str): int { return max(0, (int)$str); }
function now(): int { return time(); }
function current_user(array $db): ?array {
    $uid=(int)($_SESSION['uid'] ?? 0);
    foreach ($db['users'] ?? [] as $u) { if ($u['id'] === $uid) return $u; }
    return null;
}
function user_by_id(array $db, int $id): ?array { foreach ($db['users'] as $u) if($u['id']===$id) return $u; return null; }
function forum_by_id(array $db, int $id): ?array { foreach ($db['forums'] as $f) if($f['id']===$id) return $f; return null; }
function topic_by_id(array $db, int $id): ?array { foreach ($db['topics'] as $t) if($t['id']===$id) return $t; return null; }
function post_by_id(array $db, int $id): ?array { foreach ($db['posts'] as $p) if($p['id']===$id) return $p; return null; }
function role_level(string $role): int { return ['user'=>1,'moderator'=>2,'admin'=>3][$role] ?? 0; }
function staff(?array $u): bool { return $u && role_level($u['role'])>=2; }
function admin(?array $u): bool { return $u && $u['role']==='admin'; }
function can_write(?array $u, array $f): bool { return $u && role_level($u['role'])>=role_level($f['write_role'] ?? 'user') && !(($u['banned_until'] ?? 0)>now()); }
function self_or_staff(?array $u, int $id): bool { return $u && (staff($u) || $u['id']===$id); }
function banned(?array $u): bool { return $u && (int)($u['banned_until']??0)>now(); }
/**
 * Повторная проверка полномочий на живой версии состояния.
 * Использовать ВНУТРИ state_write(), не только перед вызовом.
 * Иначе пост может появиться после закрытия темы, удаления раздела или блокировки.
 */
function forum_live_actor(array $state, int $userId): ?array {
    foreach (($state['users'] ?? []) as $actor) {
        if ((int)$actor['id'] === $userId) {
            return banned($actor) ? null : $actor;
        }
    }
    return null;
}
function forum_can_create_topic(array $state, int $userId, int $forumId): bool {
    $actor = forum_live_actor($state, $userId);
    $forum = forum_by_id($state, $forumId);
    return $actor !== null && $forum !== null && can_write($actor, $forum);
}
function forum_can_reply(array $state, int $userId, int $topicId): bool {
    $actor = forum_live_actor($state, $userId);
    $topic = topic_by_id($state, $topicId);
    if ($actor === null || $topic === null) return false;
    $forum = forum_by_id($state, (int)$topic['forum_id']);
    if ($forum === null) return false;
    if (!can_write($actor, $forum) && !staff($actor)) return false;
    return empty($topic['locked']) || staff($actor);
}
function forum_can_edit_post(array $state, int $userId, int $postId): bool {
    $actor = forum_live_actor($state, $userId);
    $post = post_by_id($state, $postId);
    if ($actor === null || $post === null || !empty($post['hidden'])) return false;
    if (!topic_by_id($state, (int)$post['topic_id'])) return false;
    if (!self_or_staff($actor, (int)$post['user_id'])) return false;
    return staff($actor) || now() - (int)$post['created_at'] <= 1800;
}

/**
 * Проверка актуального пароля под блокировкой state_write().
 * Не разрешаем старому запросу перезаписать пароль, уже сменённый другим запросом.
 */
function forum_can_update_profile(array $state, int $userId, string $currentPassword, string $newPassword): bool {
    $actor = forum_live_actor($state, $userId);
    if ($actor === null) return false;
    if ($newPassword === '') return true;
    return strlen($newPassword) >= 10 && strlen($newPassword) <= 256
        && password_verify($currentPassword, (string)($actor['password'] ?? ''));
}

function js_date(int $timestamp): string { return date('d.m.Y в H:i', $timestamp); }
function summary(string $text, int $len=120): string {
    $text=preg_replace('/\s+/u', ' ',trim($text));
    if (text_len($text)<=$len) return $text;
    return implode('',array_slice(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY),0,$len)).'…';
}
function note(string $message, string $type='good'): void { $_SESSION['flash'][]=['message'=>$message, 'type'=>$type]; }
function redirect(string $to): never { header('Location: '.$to, true, 303); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
function check_csrf(): void { if(!hash_equals(csrf_token(), (string)($_POST['csrf']??''))) fail(419,'Срок действия формы истёк. Обновите страницу.'); }
function fail(int $status, string $message): never { http_response_code($status); layout_begin('Ошибка '.$status, [], null); echo '<section class="panel error-panel"><h1>'.h($status).'</h1><p>'.h($message).'</p><a class="btn" href="/">На главную</a></section>'; layout_end(); exit; }
function require_user(?array $u): void { if (!$u) redirect('/login?next='.urlencode($_SERVER['REQUEST_URI']??'/')); if(banned($u)) fail(403,'Ваш аккаунт временно заблокирован.'); }
function must_staff(?array $u): void { require_user($u); if (!staff($u)) fail(403,'Этот раздел доступен сотрудникам форума.'); }
function must_admin(?array $u): void { require_user($u); if (!admin($u)) fail(403,'Недостаточно прав администратора.'); }
function safe_next(): string { $next=(string)($_GET['next'] ?? $_POST['next'] ?? '/'); return str_starts_with($next,'/') && !str_starts_with($next,'//') && !str_contains($next,"\n") ? $next : '/'; }
function state_read(): array {
    if(!installed()) return [];
    $lock=fopen(STORE_LOCK,'c+'); if($lock===false) throw new RuntimeException('Хранилище недоступно');
    flock($lock, LOCK_SH);
    $raw=file_get_contents(STORE);
    flock($lock, LOCK_UN); fclose($lock);
    $state=json_decode((string)$raw,true);
    if(!is_array($state)) throw new RuntimeException('Хранилище повреждено; восстановите резервную копию.');
    return $state;
}
function state_write(callable $edit): mixed {
    $lock=fopen(STORE_LOCK,'c+'); if($lock===false) throw new RuntimeException('Не удалось открыть lock-файл');
    flock($lock, LOCK_EX);
    try {
        $db=installed() ? json_decode((string)file_get_contents(STORE),true,512,JSON_THROW_ON_ERROR) : null;
        $out=$edit($db);
        if(!is_array($db)) throw new RuntimeException('Ошибка формата базы форума');
        $json=json_encode($db, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
        $tmp=STORE.'.'.bin2hex(random_bytes(5)).'.tmp';
        if(file_put_contents($tmp,$json,LOCK_EX)===false) throw new RuntimeException('Нет права записи в private');
        chmod($tmp,0600);
        if(!rename($tmp,STORE)) throw new RuntimeException('Не удалось сохранить данные');
        return $out;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function next_id(array &$db, string $kind): int { $db['next'][$kind]++; return $db['next'][$kind]; }
function log_action(array &$db, int $actor, string $action): void {
    $db['audit'][]=['actor'=>$actor,'action'=>$action,'time'=>now()];
    if(count($db['audit'])>300) $db['audit']=array_slice($db['audit'],-300);
}
function throttled(array &$db, string $type, int $max, int $window): bool {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $key=hash('sha256',$type.'|'.$ip);
    $times=array_values(array_filter($db['limits'][$key]??[],fn($t)=>$t>now()-$window));
    if(count($times)>=$max) { $db['limits'][$key]=$times; return true; }
    $times[]=now(); $db['limits'][$key]=$times;
    if(count($db['limits'])>3000) $db['limits']=array_slice($db['limits'],-1000,null,true);
    return false;
}
function seeded_state(string $name, string $email, string $password): array {
    $groups=[
        ['id'=>1,'title'=>'ИНФОРМАЦИОННЫЙ ЦЕНТР','subtitle'=>'Начните знакомство с проектом'],
        ['id'=>2,'title'=>'ИГРОВОЕ СООБЩЕСТВО','subtitle'=>'Общайтесь и делитесь историями'],
        ['id'=>3,'title'=>'РАЗДЕЛЫ ИГРОВЫХ СЕРВЕРОВ','subtitle'=>'Организации, лидеры, обращения'],
        ['id'=>4,'title'=>'ПОМОЩЬ И ОБРАТНАЯ СВЯЗЬ','subtitle'=>'Решение вопросов и поддержка'],
    ];
    $rows=[
        [1,'Новости и объявления','Обновления, события и важная информация','✦','admin'],
        [1,'Правила проекта','Правила сообщества и разъяснения','◈','admin'],
        [2,'Общение игроков','Знакомства, вопросы и обсуждения','♟','user'],
        [2,'Медиа и творчество','Скриншоты, видеоролики, истории','▧','user'],
        [2,'Предложения и идеи','Поделитесь своими предложениями','✳','user'],
        [3,'Сервер №1 — Обсуждения','Общие темы и внутриигровая жизнь','◉','user'],
        [3,'Заявления на лидерство','Публикация заявлений и обсуждение кандидатов','♛','user'],
        [3,'Государственные организации','Обсуждение работы организаций','▣','user'],
        [3,'Нелегальные организации','Обсуждения и материалы организаций','◆','user'],
        [4,'Вопросы и помощь','Помощь в запуске и использовании игры','?','user'],
        [4,'Жалобы на нарушения','Обращения и разбор игровых ситуаций','⚑','user'],
        [4,'Технические проблемы','Ошибки, неполадки, рекомендации','⚙','user'],
    ];
    $forums=[]; foreach($rows as $i=>$r) $forums[]=['id'=>$i+1,'category_id'=>$r[0],'title'=>$r[1],'description'=>$r[2],'icon'=>$r[3],'write_role'=>$r[4],'sort'=>$i+1];
    return [
        'version'=>1, 'installed_at'=>now(),
        'users'=>[['id'=>1,'name'=>$name,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'admin','created_at'=>now(),'about'=>'','banned_until'=>0]],
        'categories'=>$groups,'forums'=>$forums,'topics'=>[],'posts'=>[],'reports'=>[],'audit'=>[], 'limits'=>[],
        'next'=>['users'=>1,'categories'=>4,'forums'=>count($forums),'topics'=>0,'posts'=>0,'reports'=>0]
    ];
}
function format_post(string $text): string {
    $safe=h($text);
    $safe=preg_replace_callback('~https?://[^\s&lt;&gt;"\']+~u',function($m){$url=html_entity_decode($m[0],ENT_QUOTES,'UTF-8'); return '<a href="'.h($url).'" rel="ugc nofollow noopener" target="_blank">'.h($url).'</a>';},$safe);
    return nl2br($safe);
}
function target_for_user(array $user): string { return '/user/'.$user['id']; }
function user_badge(string $role): string {
    return match($role) { 'admin'=>'<span class="role role-admin">АДМИНИСТРАТОР</span>', 'moderator'=>'<span class="role role-mod">МОДЕРАТОР</span>', default=>'<span class="role">ИГРОК</span>' };
}
function avatar(array $user, string $extra=''): string {
    $letters=preg_split('//u',$user['name'],-1,PREG_SPLIT_NO_EMPTY);$first=h(strtoupper($letters[0]??'V')); 
    return '<span class="avatar '.h($extra).'">'.$first.'</span>';
}
function initial(string $name): string { return substr($name,0,1); }
function topics_in_forum(array $db, int $forum_id): array {
    $all=array_values(array_filter($db['topics'],fn($t)=>$t['forum_id']===$forum_id));
    usort($all,fn($a,$b)=>($b['pinned']<=>$a['pinned']) ?: ($b['updated_at']<=>$a['updated_at']));
    return $all;
}
function posts_in_topic(array $db,int $topic_id): array { return array_values(array_filter($db['posts'],fn($p)=>$p['topic_id']===$topic_id && !($p['hidden']??false))); }
function topic_replies(array $db,int $topic_id): int { return max(0,count(posts_in_topic($db,$topic_id))-1); }
function page_int(): int { return min(1000,max(1,(int)($_GET['page']??1))); }
function page_links(int $total, int $per, int $page, string $base): string {
    $last=(int)ceil($total/$per); if($last<2) return '';
    $html='<nav class="pagination" aria-label="Страницы">';
    for($i=max(1,$page-2);$i<=min($last,$page+2);$i++) {
        $sep=str_contains($base,'?')?'&':'?';
        $html.='<a class="'.($i===$page?'current':'').'" href="'.h($base.$sep.'page='.$i).'">'.$i.'</a>';
    }
    return $html.'</nav>';
}
