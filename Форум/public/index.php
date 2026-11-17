<?php
declare(strict_types=1);
ini_set('display_errors','0');
error_reporting(E_ALL);
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
session_start();
require dirname(__DIR__).'/private/views.php';
date_default_timezone_set(cfg()['timezone'] ?? 'Europe/Moscow');
$path=trim(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'','/');
$seg=$path===''?[]:explode('/',$path);
$route=$seg[0]??'home';
$entityId=(int)($seg[1]??0);
if(!installed() && $route!=='install') redirect('/install');
$db=installed()?state_read():[];
$user=installed()?current_user($db):null;
$isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
if($isPost) check_csrf();

if($route==='install'){
    if(installed()) redirect('/');
    $error='';
    if($isPost){
        $key=trim((string)@file_get_contents(dirname(__DIR__).'/private/install.key'));
        $provided=clean('install_key',100);
        $name=clean('name',32); $email=lc(clean('email',200));$pass=(string)($_POST['password']??'');
        if(!$key || !hash_equals($key,$provided)) $error='Неверный установочный ключ.';
        elseif(!preg_match('/^[\p{L}\p{N}_.-]{3,24}$/u',$name)) $error='Логин: 3–24 символа, буквы, цифры, точки, дефисы или подчёркивания.';
        elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Укажите правильный email.';
        elseif(strlen($pass)<10 || strlen($pass)>256) $error='Пароль администратора должен быть от 10 символов.';
        else {
            try { state_write(function (&$s) use ($name,$email,$pass) { if($s!==null) throw new RuntimeException('Форум уже установлен'); $s=seeded_state($name,$email,$pass); });
                @unlink(dirname(__DIR__).'/private/install.key');$_SESSION['uid']=1;session_regenerate_id(true);note('Форум установлен! Вы вошли как администратор.');redirect('/admin');
            } catch(Throwable $e) {$error='Ошибка установки: '. $e->getMessage();}
        }
    }
    layout_begin('Установка',[] ,null);
    ?><section class="install-page"><div class="install-brand"><img src="/assets/img/vostera-avatar.jpg" alt="VOSTERA"><div><h1>Установка VOSTERA</h1><p>Первый запуск форума сообщества</p></div></div><div class="panel auth-panel"><div class="overline">ШАГ 01 / СОЗДАНИЕ АДМИНИСТРАТОРА</div><h2>Добро пожаловать!</h2><p>Установочный ключ указан в файле <code>INSTALL.txt</code>. После установки он будет удалён автоматически.</p><?php if($error):?><div class="notice notice-bad"><?=h($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><label>Установочный ключ<input name="install_key" required autocomplete="off" type="password"></label><label>Логин администратора<input name="name" maxlength="24" required placeholder="Admin_Vostera" autocomplete="username"></label><label>Email<input name="email" type="email" required autocomplete="email"></label><label>Пароль администратора (от 10 символов)<input name="password" type="password" minlength="10" required autocomplete="new-password"></label><button class="btn full" type="submit">Установить форум ↗</button></form></div></section><?php layout_end();exit;
}

if($isPost){
    if($route==='logout'){unset($_SESSION['uid']);session_regenerate_id(true);note('Вы вышли из аккаунта.');redirect('/');}
    if($route==='login'){
        $login=lc(clean('login',200));$pass=(string)($_POST['password']??'');
        $tooMuch=state_write(function(&$s){return throttled($s,'login',15,900);});
        if($tooMuch){note('Слишком много попыток входа. Повторите позже.','bad');redirect('/login');}
        $found=null;
        foreach($db['users'] as $x) if(lc($x['name'])===$login || lc($x['email'])===$login){$found=$x;break;}
        if($found && password_verify($pass,$found['password'])){
            if(banned($found)){note('Вход недоступен: аккаунт заблокирован до '.js_date($found['banned_until']),'bad');redirect('/login');}
            session_regenerate_id(true);$_SESSION['uid']=$found['id'];note('С возвращением, '.$found['name'].'!');redirect(safe_next());
        }
        note('Неверные данные для входа.','bad');redirect('/login');
    }
    if($route==='register'){
        if(!cfg()['registration_open']) fail(403,'Регистрация закрыта.');
        $name=clean('name',32);$email=lc(clean('email',200));$pass=(string)($_POST['password']??'');
        if(!preg_match('/^[\p{L}\p{N}_.-]{3,24}$/u',$name)){note('Логин должен содержать от 3 до 24 символов: буквы, цифры, точки или подчёркивания.','bad');redirect('/register');}
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){note('Неверный адрес электронной почты.','bad');redirect('/register');}
        if(strlen($pass)<10 || strlen($pass)>256){note('Пароль должен содержать не менее 10 символов.','bad');redirect('/register');}
        $result=state_write(function(&$s)use($name,$email,$pass){
            if(throttled($s,'register',4,3600))return ['error'=>'Слишком много регистраций. Повторите позже.'];
            foreach($s['users'] as $u)if(lc($u['name'])===lc($name) || lc($u['email'])===$email)return ['error'=>'Логин или email уже используется.'];
            $new=next_id($s,'users');$s['users'][]=['id'=>$new,'name'=>$name,'email'=>$email,'password'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>'user','created_at'=>now(),'about'=>'','banned_until'=>0];return ['id'=>$new];
        });
        if(isset($result['error'])){note($result['error'],'bad');redirect('/register');}
        session_regenerate_id(true);$_SESSION['uid']=$result['id'];note('Добро пожаловать на форум VOSTERA!');redirect('/');
    }
    if($route==='new'){
        require_user($user);$forumId=id(clean('forum',20));$forum=forum_by_id($db,$forumId);
        if(!$forum) fail(404,'Раздел не найден.');if(!can_write($user,$forum)) fail(403,'В этом разделе нельзя создавать темы.');
        $title=clean('title',150);$body=clean('body',12000);
        if(text_len($title)<5 || text_len($title)>120 || text_len($body)<10 || text_len($body)>12000){note('Название темы: 5–120 символов. Сообщение: 10–12000 символов.','bad');redirect('/new?forum='.$forumId);}
        $result=state_write(function(&$s)use($user,$forumId,$title,$body){
            if(throttled($s,'new',10,3600)) return ['error'=>'Слишком много тем за короткое время.'];
            $tid=next_id($s,'topics');$pid=next_id($s,'posts');
            $s['topics'][]=['id'=>$tid,'forum_id'=>$forumId,'author_id'=>$user['id'],'title'=>$title,'created_at'=>now(),'updated_at'=>now(),'views'=>0,'pinned'=>false,'locked'=>false];
            $s['posts'][]=['id'=>$pid,'topic_id'=>$tid,'user_id'=>$user['id'],'body'=>$body,'created_at'=>now(),'updated_at'=>null,'hidden'=>false];
            return ['id'=>$tid];
        });
        if(isset($result['error'])){note($result['error'],'bad');redirect('/forum/'.$forumId);}
        note('Тема создана.');redirect('/topic/'.$result['id']);
    }
    if($route==='reply'){
        require_user($user);$tid=id(clean('topic',12));$topic=topic_by_id($db,$tid);if(!$topic)fail(404,'Тема не найдена.');
        $forum=forum_by_id($db,$topic['forum_id']);if(!$forum || !can_write($user,$forum) && !staff($user))fail(403,'Вы не можете отвечать в этом разделе.');
        if($topic['locked']&&!staff($user))fail(403,'Тема закрыта для ответов.');
        $body=clean('body',12000);if(text_len($body)<2 || text_len($body)>12000){note('Ответ должен содержать от 2 до 12000 символов.','bad');redirect('/topic/'.$tid);}
        $result=state_write(function(&$s)use($user,$tid,$body){
            if(throttled($s,'reply',25,3600))return false;
            foreach($s['topics'] as &$t) if($t['id']===$tid) {$t['updated_at']=now();break;}unset($t);
            $id=next_id($s,'posts');$s['posts'][]=['id'=>$id,'topic_id'=>$tid,'user_id'=>$user['id'],'body'=>$body,'created_at'=>now(),'updated_at'=>null,'hidden'=>false];return $id;
        });
        if(!$result){note('Слишком много сообщений за короткое время.','bad');redirect('/topic/'.$tid);}
        note('Ответ опубликован.');redirect('/topic/'.$tid.'?page='.max(1,(int)ceil((count(posts_in_topic($db,$tid))+1)/15)).'#post-'.$result);
    }
    if($route==='edit-post'){
        require_user($user);$post=post_by_id($db,$entityId);if(!$post || $post['hidden']) fail(404,'Сообщение не найдено.');
        if(!self_or_staff($user,$post['user_id'])) fail(403,'Нет права на редактирование.');
        if(!staff($user) && now()-$post['created_at']>1800)fail(403,'Время редактирования истекло (30 минут).');
        $body=clean('body',12000);if(text_len($body)<2||text_len($body)>12000){note('Сообщение должно содержать от 2 до 12000 символов.','bad');redirect('/edit-post/'.$entityId);}
        state_write(function(&$s)use($entityId,$body,$user){foreach($s['posts'] as &$p)if($p['id']===$entityId){$p['body']=$body;$p['updated_at']=now();break;}unset($p);log_action($s,$user['id'],'Редактирование сообщения #'.$entityId);});
        note('Изменения сохранены.');redirect('/topic/'.$post['topic_id'].'#post-'.$entityId);
    }
    if($route==='report'){
        require_user($user);$pid=id(clean('post',12));$post=post_by_id($db,$pid);if(!$post)fail(404,'Сообщение не найдено.');
        $reason=clean('reason',600);if(text_len($reason)<10||text_len($reason)>600){note('Опишите причину жалобы (10–600 символов).','bad');redirect('/report/'.$pid);}
        state_write(function(&$s)use($user,$pid,$reason){$id=next_id($s,'reports');$s['reports'][]=['id'=>$id,'post_id'=>$pid,'author_id'=>$user['id'],'reason'=>$reason,'status'=>'open','created_at'=>now(),'resolved_by'=>null];});
        note('Жалоба отправлена администрации.');redirect('/topic/'.$post['topic_id']);
    }
    if($route==='profile'){
        require_user($user);$about=clean('about',500);if(text_len($about)>500)fail(400,'Слишком длинное описание.');
        $old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');
        if($new!=='' && (strlen($new)<10 || !password_verify($old,$user['password']))){note('Новый пароль должен быть длиной от 10 символов; для смены укажите текущий пароль.','bad');redirect('/settings');}
        state_write(function(&$s)use($user,$about,$new){foreach($s['users']as &$u)if($u['id']===$user['id']){$u['about']=$about;if($new!=='')$u['password']=password_hash($new,PASSWORD_DEFAULT);break;}unset($u);});
        note('Настройки профиля обновлены.');redirect('/settings');
    }
    if($route==='mod'){
        must_staff($user);$operation=clean('action',35);$tid=id(clean('topic',12));$pid=id(clean('post',12));
        if(in_array($operation,['pin','lock','delete_topic','rename'],true)){
            if(!topic_by_id($db,$tid))fail(404,'Тема не найдена.');
            $title=clean('title',150);
            if($operation==='rename' && (text_len($title)<5||text_len($title)>120))fail(400,'Название должно содержать 5–120 символов.');
            state_write(function(&$s)use($operation,$tid,$user,$title){
                foreach($s['topics'] as $i=>&$t)if($t['id']===$tid){
                    if($operation==='pin')$t['pinned']=!$t['pinned'];
                    if($operation==='lock')$t['locked']=!$t['locked'];
                    if($operation==='rename')$t['title']=$title;
                    break;
                }unset($t);
                if($operation==='delete_topic'){
                    $s['topics']=array_values(array_filter($s['topics'],fn($t)=>$t['id']!==$tid));
                    $ids=array_column(array_filter($s['posts'],fn($p)=>$p['topic_id']===$tid),'id');
                    $s['posts']=array_values(array_filter($s['posts'],fn($p)=>$p['topic_id']!==$tid));
                    $s['reports']=array_values(array_filter($s['reports'],fn($r)=>!in_array($r['post_id'],$ids,true)));
                }
                log_action($s,$user['id'],$operation.' тема #'.$tid);
            });note('Действие выполнено.');redirect($operation==='delete_topic'?'/':'/topic/'.$tid);
        }
        if($operation==='hide_post'){
            $post=post_by_id($db,$pid);if(!$post)fail(404,'Сообщение не найдено.');
            $topic=topic_by_id($db,$post['topic_id']);if(!$topic)fail(404,'Тема не найдена.');
            $first=posts_in_topic($db,$topic['id'])[0]['id']??0;
            if($pid===$first)fail(400,'Первое сообщение можно удалить только вместе с темой.');
            state_write(function(&$s)use($pid,$user){foreach($s['posts']as &$p)if($p['id']===$pid){$p['hidden']=!$p['hidden'];break;}unset($p);log_action($s,$user['id'],'Скрытие сообщения #'.$pid);});
            note('Сообщение скрыто или восстановлено.');redirect('/topic/'.$post['topic_id']);
        }
    }
    if($route==='admin'){
        must_admin($user);$action=clean('action',40);
        if($action==='create_category'){
            $title=clean('title',100);$subtitle=clean('subtitle',120);
            if(text_len($title)<3)fail(400,'Название слишком короткое.');
            state_write(function(&$s)use($title,$subtitle){$id=next_id($s,'categories');$s['categories'][]=['id'=>$id,'title'=>$title,'subtitle'=>$subtitle];});note('Категория создана.');redirect('/admin?tab=forums');
        }
        if($action==='create_forum'){
            $title=clean('title',100);$desc=clean('description',200);$icon=clean('icon',20);$cat=id(clean('category_id',10));$write=clean('write_role',20);
            if(text_len($title)<3 || !in_array($write,['user','moderator','admin'],true))fail(400,'Неправильные данные раздела.');
            if(!in_array($cat,array_column($db['categories'],'id'),true))fail(400,'Выберите категорию.');
            state_write(function(&$s)use($title,$desc,$icon,$cat,$write){$fid=next_id($s,'forums');$s['forums'][]=['id'=>$fid,'category_id'=>$cat,'title'=>$title,'description'=>$desc,'icon'=>$icon?:'◆','write_role'=>$write,'sort'=>$fid];});note('Раздел добавлен.');redirect('/admin?tab=forums');
        }
        if($action==='edit_forum'){
            $fid=id(clean('forum_id',10));$title=clean('title',100);$desc=clean('description',200);$write=clean('write_role',20);
            if(!forum_by_id($db,$fid) || text_len($title)<3 || !in_array($write,['user','moderator','admin'],true))fail(400,'Некорректные данные раздела.');
            state_write(function(&$s)use($fid,$title,$desc,$write){foreach($s['forums']as &$f)if($f['id']===$fid){$f['title']=$title;$f['description']=$desc;$f['write_role']=$write;break;}unset($f);});note('Раздел обновлён.');redirect('/admin?tab=forums');
        }
        if($action==='user_role'||$action==='user_ban'){
            $role='user';$days=0;
            $uid=id(clean('user_id',12));$target=user_by_id($db,$uid);if(!$target)fail(404,'Пользователь не найден.');
            if($uid===$user['id'])fail(403,'Нельзя менять собственные права или блокировку.');
            if($action==='user_role'){$role=clean('role',20);if(!in_array($role,['user','moderator','admin'],true))fail(400,'Неверная роль.');}
            else {$days=max(0,min(3650,(int)clean('days',10)));}
            state_write(function(&$s)use($uid,$action,$role,$days,$user){foreach($s['users']as &$u)if($u['id']===$uid){if($action==='user_role')$u['role']=$role;else $u['banned_until']=$days?now()+$days*86400:0;break;}unset($u);log_action($s,$user['id'],$action.' пользователь #'.$uid);});note('Права пользователя обновлены.');redirect('/admin?tab=users');
        }
        if($action==='resolve_report'){
            $rid=id(clean('report_id',10));state_write(function(&$s)use($rid,$user){foreach($s['reports']as &$r)if($r['id']===$rid){$r['status']='resolved';$r['resolved_by']=$user['id'];break;}unset($r);});note('Жалоба закрыта.');redirect('/reports');
        }
        fail(400,'Неизвестное действие администратора.');
    }
    if($route==='reports'){
        must_staff($user);$rid=id(clean('report_id',10));
        state_write(function(&$s)use($rid,$user){foreach($s['reports']as &$r)if($r['id']===$rid){$r['status']='resolved';$r['resolved_by']=$user['id'];break;}unset($r);log_action($s,$user['id'],'Рассмотрение жалобы #'.$rid);});
        note('Жалоба обработана.');redirect('/reports');
    }
    fail(405,'Операция не поддерживается.');
}

switch($route){
    case 'home': layout_begin('Форум',$db,$user);show_home($db,$user);layout_end();break;
    case 'forum':
        $f=forum_by_id($db,$entityId);if(!$f)fail(404,'Раздел не найден.');
        $threads=topics_in_forum($db,$f['id']);$p=page_int();$per=15;
        layout_begin($f['title'],$db,$user);breadcrumbs([[$f['title'],'']]);
        ?><div class="page-header"><div><div class="overline">РАЗДЕЛ СООБЩЕСТВА</div><h1><?=h($f['title'])?></h1><p><?=h($f['description'])?></p></div><?php if(can_write($user,$f)):?><a class="btn" href="/new?forum=<?=$f['id']?>">+ Создать тему</a><?php elseif(!$user):?><a class="btn" href="/login">Войти для публикации</a><?php endif;?></div>
        <div class="content-frame"><div class="list-header"><strong>Темы раздела</strong><span><?=count($threads)?> обсуждений</span></div><?php foreach(array_slice($threads,($p-1)*$per,$per) as $t)topic_card($db,$t);?><?php if(!$threads):?><div class="empty-list"><span>✧</span><h2>Пока нет обсуждений</h2><p>Станьте первым, кто откроет тему в этом разделе.</p></div><?php endif;?></div><?=page_links(count($threads),$per,$p,'/forum/'.$f['id'])?>
        <?php layout_end();break;
    case 'topic':
        $t=topic_by_id($db,$entityId);if(!$t)fail(404,'Тема не найдена.');$f=forum_by_id($db,$t['forum_id']);if(!$f)fail(404,'Раздел удалён.');
        $posts=posts_in_topic($db,$t['id']);$p=page_int();$per=15;$shown=array_slice($posts,($p-1)*$per,$per);
        if(!isset($_SESSION['viewed'][$entityId]) || $_SESSION['viewed'][$entityId]<now()-3600){$_SESSION['viewed'][$entityId]=now();state_write(function(&$s)use($entityId){foreach($s['topics']as &$thread)if($thread['id']===$entityId){$thread['views']++;break;}unset($thread);});}
        layout_begin($t['title'],$db,$user);breadcrumbs([[$f['title'],'/forum/'.$f['id']],[$t['title'],'']]);
        ?><div class="page-header topic-title"><div><div class="overline">ОБСУЖДЕНИЕ #<?=$t['id']?> <?php if($t['pinned']):?>· ЗАКРЕПЛЕНО<?php endif;?></div><h1><?=h($t['title'])?></h1><p><?=topic_replies($db,$t['id'])?> ответов · <?=$t['views']?> просмотров <?php if($t['locked']):?>· Тема закрыта<?php endif;?></p></div><a href="/forum/<?=$f['id']?>" class="btn btn-outline">← К разделу</a></div>
        <?php if(staff($user)):?><details class="admin-tools"><summary>⚙ Управление темой</summary><div class="admin-tool-grid"><form method="post" action="/mod"><?=csrf_field()?><input type="hidden" name="topic" value="<?=$t['id']?>"><button class="btn btn-subtle" name="action" value="pin"><?=$t['pinned']?'Открепить':'Закрепить'?></button><button class="btn btn-subtle" name="action" value="lock"><?=$t['locked']?'Открыть ответы':'Закрыть ответы'?></button><button class="btn btn-danger" name="action" value="delete_topic" data-confirm="Удалить всю тему и сообщения без восстановления?">Удалить тему</button></form><form method="post" action="/mod"><?=csrf_field()?><input type="hidden" name="topic" value="<?=$t['id']?>"><input type="hidden" name="action" value="rename"><input name="title" value="<?=h($t['title'])?>" required maxlength="120"><button class="btn btn-subtle">Переименовать</button></form></div></details><?php endif;?>
        <div class="posts-list"><?php foreach($shown as $post):$author=user_by_id($db,$post['user_id']);if(!$author)continue;?><article class="post-card" id="post-<?=$post['id']?>"><aside class="post-author"><a href="/user/<?=$author['id']?>" class="large-avatar"><?=avatar($author)?></a><a href="/user/<?=$author['id']?>" class="poster-name"><?=h($author['name'])?></a><?=user_badge($author['role'])?><small>С нами с <?=date('d.m.Y',$author['created_at'])?></small></aside><div class="post-main"><header class="post-meta"><span><?=js_date($post['created_at'])?><?php if($post['updated_at']):?> · изменено <?=js_date($post['updated_at'])?><?php endif;?></span><a href="#post-<?=$post['id']?>">#<?=$post['id']?></a></header><div class="post-text"><?=format_post($post['body'])?></div><div class="post-actions"><?php if($user && self_or_staff($user,$author['id']) && (staff($user)||now()-$post['created_at']<=1800)):?><a href="/edit-post/<?=$post['id']?>">✎ Изменить</a><?php endif;?><?php if($user):?><a href="/report/<?=$post['id']?>">⚑ Пожаловаться</a><?php endif;?><?php if(staff($user) && $post['id']!==($posts[0]['id']??0)):?><form method="post" action="/mod" class="inlineform"><?=csrf_field()?><input type="hidden" name="action" value="hide_post"><input type="hidden" name="post" value="<?=$post['id']?>"><button class="text-btn" data-confirm="Скрыть сообщение?">Скрыть</button></form><?php endif;?></div></div></article><?php endforeach;?></div>
        <?=page_links(count($posts),$per,$p,'/topic/'.$t['id'])?>
        <section class="panel reply-box"><h2>Оставить ответ</h2><?php if(!$user):?><p>Для участия в обсуждении необходимо войти или зарегистрироваться.</p><a class="btn" href="/login?next=<?=urlencode('/topic/'.$t['id'])?>">Войти</a><?php elseif($t['locked']&&!staff($user)):?><p>Тема закрыта для новых ответов.</p><?php elseif(!can_write($user,$f)&&!staff($user)):?><p>У вас нет прав на ответ в этом разделе.</p><?php else:?><form method="post" action="/reply" class="editor-form"><?=csrf_field()?><input type="hidden" name="topic" value="<?=$t['id']?>"><textarea name="body" required minlength="2" maxlength="12000" rows="5" placeholder="Присоединиться к обсуждению..."></textarea><div class="editor-foot"><small>Уважайте других участников сообщества.</small><button type="submit" class="btn">Опубликовать ответ ↗</button></div></form><?php endif;?></section>
        <?php layout_end();break;
    case 'new':
        require_user($user);$fid=(int)($_GET['forum']??0);$f=forum_by_id($db,$fid);if(!$f)fail(404,'Раздел не найден.');if(!can_write($user,$f))fail(403,'Создание тем ограничено.');
        layout_begin('Новая тема',$db,$user);breadcrumbs([[$f['title'],'/forum/'.$fid],['Новая тема','']]);
        ?><div class="form-shell panel"><div class="overline">НОВОЕ ОБСУЖДЕНИЕ</div><h1>Создать тему</h1><p class="muted">Раздел: <?=h($f['title'])?></p><form method="post" action="/new" class="editor-form"><?=csrf_field()?><input type="hidden" name="forum" value="<?=$fid?>"><label>Название темы<input name="title" required minlength="5" maxlength="120" placeholder="Коротко и понятно опишите тему"></label><label>Текст сообщения<textarea name="body" required minlength="10" maxlength="12000" rows="11" placeholder="Напишите подробности..."></textarea></label><div class="editor-foot"><small>Убедитесь, что тема соответствует правилам.</small><button class="btn" type="submit">Опубликовать тему ↗</button></div></form></div><?php layout_end();break;
    case 'login': case 'register':
        if($user)redirect('/');$reg=$route==='register';layout_begin($reg?'Регистрация':'Вход в форум',$db,$user);
        ?><div class="auth-wrap"><div class="auth-aside"><img src="/assets/img/vostera-avatar.jpg" alt="Vostera"><div class="overline">VOSTERA COMMUNITY</div><h1>ТВОЯ ИСТОРИЯ<br><span>НАЧИНАЕТСЯ ЗДЕСЬ.</span></h1><p>Стань частью игрового сообщества, обсуждай новости и создавай истории вместе с другими.</p></div><div class="panel auth-panel"><div class="overline">ЛИЧНЫЙ КАБИНЕТ</div><h2><?=$reg?'Создать аккаунт':'С возвращением!'?></h2><p><?=$reg?'Присоединяйся к сообществу VOSTERA.':'Войди, чтобы продолжить общение на форуме.'?></p><form method="post"><?=csrf_field()?><?php if($reg):?><label>Игровой никнейм<input type="text" name="name" minlength="3" maxlength="24" required autocomplete="username" placeholder="Player_Vostera"></label><label>Электронная почта<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><?php else:?><input type="hidden" name="next" value="<?=h(safe_next())?>"><label>Логин или email<input name="login" required autocomplete="username" placeholder="Введите логин или email"></label><?php endif;?><label>Пароль<input type="password" name="password" required minlength="<?=$reg?'10':'1'?>" autocomplete="<?=$reg?'new-password':'current-password'?>" placeholder="Введите пароль"></label><button class="btn full" type="submit"><?=$reg?'Зарегистрироваться':'Войти в аккаунт'?> ↗</button></form><p class="auth-switch"><?=$reg?'Уже есть аккаунт? <a href="/login">Войти</a>':'Нет аккаунта? <a href="/register">Зарегистрироваться</a>'?></p></div></div><?php layout_end();break;
    case 'user':
        $target=user_by_id($db,$entityId);if(!$target)fail(404,'Профиль не найден.');$topics=array_values(array_filter($db['topics'],fn($t)=>$t['author_id']===$target['id']));usort($topics,fn($a,$b)=>$b['created_at']<=>$a['created_at']);
        $postsCount=count(array_filter($db['posts'],fn($p)=>$p['user_id']===$target['id']));
        layout_begin('Профиль '.$target['name'],$db,$user);breadcrumbs([['Участники',''],[$target['name'],'']]);
        ?><div class="profile-cover"></div><div class="profile-box panel"><div class="profile-heading"><?=avatar($target,'profile-avatar')?><div><div class="overline">УЧАСТНИК VOSTERA</div><h1><?=h($target['name'])?></h1><?=user_badge($target['role'])?></div><?php if($user && $user['id']===$target['id']):?><a class="btn btn-outline" href="/settings">Настройки профиля</a><?php endif;?></div><div class="profile-stats"><span><b><?=count($topics)?></b> Тем</span><span><b><?=$postsCount?></b> Сообщений</span><span><b><?=date('d.m.Y',$target['created_at'])?></b> Дата регистрации</span></div><?php if($target['about']):?><p><?=nl2br(h($target['about']))?></p><?php endif;?></div><div class="content-frame"><div class="list-header"><strong>Темы пользователя</strong></div><?php foreach(array_slice($topics,0,30) as $t)topic_card($db,$t);?><?php if(!$topics):?><div class="empty-list"><p>Тем пока нет.</p></div><?php endif;?></div><?php layout_end();break;
    case 'settings':
        require_user($user);layout_begin('Настройки профиля',$db,$user);breadcrumbs([['Настройки','']]);?><section class="form-shell panel"><div class="overline">ЛИЧНЫЙ КАБИНЕТ</div><h1>Настройки профиля</h1><p class="muted"><?=h($user['name'])?> · <?=h($user['email'])?></p><form method="post" action="/profile" class="editor-form"><?=csrf_field()?><label>О себе<textarea name="about" maxlength="500" rows="4" placeholder="Расскажите о себе..."><?=h($user['about'])?></textarea></label><h3>Изменить пароль (необязательно)</h3><label>Текущий пароль<input type="password" name="current_password" autocomplete="current-password"></label><label>Новый пароль<input type="password" name="new_password" minlength="10" autocomplete="new-password"></label><button class="btn">Сохранить изменения</button></form></section><?php layout_end();break;
    case 'edit-post':
        require_user($user);$post=post_by_id($db,$entityId);if(!$post||($post['hidden']??false))fail(404,'Сообщение не найдено.');if(!self_or_staff($user,$post['user_id']) || (!staff($user)&&now()-$post['created_at']>1800))fail(403,'Нет права на изменение.');
        layout_begin('Редактирование сообщения',$db,$user);breadcrumbs([['Обсуждение','/topic/'.$post['topic_id']],['Изменить сообщение','']]);?><section class="panel form-shell"><h1>Изменить сообщение</h1><?php editor('/edit-post/'.$entityId,'Сохранить изменения',$post['body']);?></section><?php layout_end();break;
    case 'report':
        require_user($user);$post=post_by_id($db,$entityId);if(!$post)fail(404,'Сообщение не найдено.');layout_begin('Жалоба на сообщение',$db,$user);breadcrumbs([['Обсуждение','/topic/'.$post['topic_id']],['Жалоба','']]);?><section class="panel form-shell"><div class="overline">ОБРАТНАЯ СВЯЗЬ</div><h1>Сообщить о нарушении</h1><p>Опишите, какое правило нарушено. Ваша жалоба будет видна только модераторам.</p><form method="post" action="/report" class="editor-form"><?=csrf_field()?><input type="hidden" name="post" value="<?=$post['id']?>"><label>Причина жалобы<textarea name="reason" rows="6" minlength="10" maxlength="600" required placeholder="Опишите ситуацию..."></textarea></label><button class="btn">Отправить жалобу</button></form></section><?php layout_end();break;
    case 'search':
        $q=trim(substr((string)($_GET['q']??''),0,160));$hits=[];if(text_len($q)>=2){foreach($db['topics'] as $t){$text=$t['title'];foreach($db['posts']as $post)if($post['topic_id']===$t['id'] && !($post['hidden']??false)){$text.=' '.$post['body'];}if(str_contains(lc($text),lc($q)))$hits[]=$t;}usort($hits,fn($a,$b)=>$b['updated_at']<=>$a['updated_at']);}
        layout_begin('Поиск',$db,$user);?><section class="panel search-panel"><div class="overline">ПОИСК ПО ФОРУМУ</div><h1>Найти обсуждение</h1><form method="get" action="/search" class="search-form"><input aria-label="Поисковая фраза" type="search" name="q" value="<?=h($q)?>" minlength="2" maxlength="160" placeholder="Название темы или текст сообщения" required><button class="btn">Найти ↗</button></form></section><?php if($q):?><div class="content-frame"><div class="list-header"><strong>Результаты: <?=h($q)?></strong><span><?=count($hits)?> тем</span></div><?php foreach(array_slice($hits,(page_int()-1)*20,20)as $t)topic_card($db,$t);?><?php if(!$hits):?><div class="empty-list"><p>Ничего не найдено. Попробуйте другой запрос.</p></div><?php endif;?></div><?=page_links(count($hits),20,page_int(),'/search?q='.urlencode($q))?><?php endif;layout_end();break;
    case 'rules':
        layout_begin('Правила',$db,$user);breadcrumbs([['Правила','']]);?><section class="panel rules-page"><div class="overline">ПРАВИЛА СООБЩЕСТВА</div><h1>Правила форума</h1><p>Уважайте игроков и сотрудников проекта. Администрация может обновлять правила, публикуя изменения в разделе объявлений.</p><div class="rule"><b>01</b><div><h3>Взаимное уважение</h3><p>Запрещены оскорбления, угрозы, дискриминация, травля и провокации конфликтов.</p></div></div><div class="rule"><b>02</b><div><h3>Содержание публикаций</h3><p>Не публикуйте спам, рекламу без разрешения и материалы, нарушающие закон либо права третьих лиц.</p></div></div><div class="rule"><b>03</b><div><h3>Безопасность</h3><p>Не публикуйте пароли, токены, личные переписки и чужие персональные данные без согласия.</p></div></div><div class="rule"><b>04</b><div><h3>Темы и жалобы</h3><p>Выбирайте подходящий раздел, давайте темам понятные названия и описывайте обращения подробно.</p></div></div><div class="rule"><b>05</b><div><h3>Решения модерации</h3><p>За нарушения сообщения могут скрываться, темы закрываться, а доступ к форуму временно ограничиваться.</p></div></div><p class="muted">Это базовые правила форума. Игровые правила должны быть отдельно утверждены администрацией VOSTERA.</p></section><?php layout_end();break;
    case 'reports':
        must_staff($user);$reports=$db['reports'];usort($reports,fn($a,$b)=>(($a['status']==='resolved')<=>($b['status']==='resolved'))?:$b['created_at']<=>$a['created_at']);layout_begin('Жалобы',$db,$user);breadcrumbs([['Модерация','']]);?><div class="page-header"><div><div class="overline">МОДЕРАЦИЯ</div><h1>Жалобы пользователей</h1><p>Обращения сообщества, доступные сотрудникам.</p></div></div><div class="content-frame"><?php foreach($reports as $r):$reporter=user_by_id($db,$r['author_id']);$p=post_by_id($db,$r['post_id']);?><div class="report-item"><div><span class="pill <?=$r['status']==='open'?'pill-open':''?>"><?=$r['status']==='open'?'ОТКРЫТА':'ЗАКРЫТА'?></span><h3>Жалоба #<?=$r['id']?> · <?=h($reporter['name']??'Пользователь')?></h3><p><?=nl2br(h($r['reason']))?></p><small><?=js_date($r['created_at'])?></small><?php if($p):?> <a href="/topic/<?=$p['topic_id']?>#post-<?=$p['id']?>">Перейти к сообщению ↗</a><?php endif;?></div><?php if($r['status']==='open'):?><form method="post" action="/reports"><?=csrf_field()?><input type="hidden" name="report_id" value="<?=$r['id']?>"><button class="btn btn-subtle">Отметить обработанной</button></form><?php endif;?></div><?php endforeach;?><?php if(!$reports):?><div class="empty-list">Жалоб пока нет.</div><?php endif;?></div><?php layout_end();break;
    case 'admin':
        must_admin($user);$tab=(string)($_GET['tab']??'overview');layout_begin('Администрирование',$db,$user);breadcrumbs([['Панель управления','']]);?><div class="page-header"><div><div class="overline">VOSTERA / CONTROL PANEL</div><h1>Панель администратора</h1><p>Управление сообществом и модерация форума.</p></div></div><nav class="tabbar"><a class="<?=$tab==='overview'?'selected':''?>" href="/admin">Обзор</a><a class="<?=$tab==='forums'?'selected':''?>" href="/admin?tab=forums">Разделы</a><a class="<?=$tab==='users'?'selected':''?>" href="/admin?tab=users">Участники</a><a href="/reports">Жалобы (<?=count(array_filter($db['reports'],fn($r)=>$r['status']==='open'))?>)</a></nav>
        <?php if($tab==='overview'):?><div class="admin-kpis"><div><strong><?=count($db['users'])?></strong>Пользователей</div><div><strong><?=count($db['topics'])?></strong>Тем</div><div><strong><?=count($db['posts'])?></strong>Сообщений</div><div><strong><?=count($db['forums'])?></strong>Разделов</div></div><section class="panel"><h2>Последние действия</h2><?php foreach(array_reverse(array_slice($db['audit'],-15))as $entry):?><p class="audit-line"><span><?=js_date($entry['time'])?></span><?=h($entry['action'])?></p><?php endforeach;?><?php if(!$db['audit']):?><p class="muted">Пока нет записей журнала.</p><?php endif;?></section>
        <?php elseif($tab==='forums'):?><div class="admin-layout"><section class="panel"><h2>Создать категорию</h2><form action="/admin" method="post" class="editor-form"><?=csrf_field()?><input type="hidden" name="action" value="create_category"><label>Название<input name="title" required maxlength="100" placeholder="НОВАЯ КАТЕГОРИЯ"></label><label>Подзаголовок<input name="subtitle" maxlength="120"></label><button class="btn">Добавить категорию</button></form></section><section class="panel"><h2>Создать раздел</h2><form action="/admin" method="post" class="editor-form"><?=csrf_field()?><input type="hidden" name="action" value="create_forum"><label>Название<input name="title" required maxlength="100"></label><label>Описание<input name="description" maxlength="200"></label><label>Иконка (символ)<input name="icon" value="◆" maxlength="20"></label><label>Категория<select name="category_id"><?php foreach($db['categories'] as $c):?><option value="<?=$c['id']?>"><?=h($c['title'])?></option><?php endforeach;?></select></label><label>Кто может создавать темы<select name="write_role"><option value="user">Игроки</option><option value="moderator">Модераторы</option><option value="admin">Только администраторы</option></select></label><button class="btn">Добавить раздел</button></form></section></div><section class="panel admin-list"><h2>Существующие разделы</h2><?php foreach($db['forums']as $f):?><details><summary><?=h($f['title'])?> <span>#<?=$f['id']?></span></summary><form method="post" action="/admin" class="editor-form"><?=csrf_field()?><input name="action" type="hidden" value="edit_forum"><input name="forum_id" type="hidden" value="<?=$f['id']?>"><label>Название<input name="title" value="<?=h($f['title'])?>" required></label><label>Описание<input name="description" value="<?=h($f['description'])?>"></label><label>Создание тем<select name="write_role"><?php foreach(['user'=>'Игроки','moderator'=>'Модераторы','admin'=>'Администраторы']as $r=>$label):?><option value="<?=$r?>" <?=$f['write_role']===$r?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><button class="btn">Сохранить</button></form></details><?php endforeach;?></section>
        <?php elseif($tab==='users'):?><section class="panel admin-list"><h2>Участники</h2><?php foreach($db['users']as $entry):?><details><summary><span><?=h($entry['name'])?> · <?=h($entry['email'])?></span> <span><?=h($entry['role'])?><?php if(banned($entry)):?> · ЗАБЛОКИРОВАН<?php endif;?></span></summary><?php if($entry['id']===$user['id']):?><p>Это ваша учётная запись.</p><?php else:?><div class="admin-layout"><form method="post" action="/admin" class="editor-form"><?=csrf_field()?><input type="hidden" name="action" value="user_role"><input type="hidden" name="user_id" value="<?=$entry['id']?>"><label>Роль<select name="role"><?php foreach(['user'=>'Игрок','moderator'=>'Модератор','admin'=>'Администратор']as $r=>$label):?><option value="<?=$r?>" <?=$entry['role']===$r?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><button class="btn btn-subtle">Изменить роль</button></form><form method="post" action="/admin" class="editor-form"><?=csrf_field()?><input type="hidden" name="action" value="user_ban"><input type="hidden" name="user_id" value="<?=$entry['id']?>"><label>Блокировка (дней, 0 = снять)<input type="number" name="days" value="<?=$entry['banned_until']>now()?max(1,(int)ceil(($entry['banned_until']-now())/86400)):0?>" min="0" max="3650"></label><button class="btn btn-subtle">Применить</button></form></div><?php endif;?></details><?php endforeach;?></section><?php endif;?>
        <?php layout_end();break;
    default:fail(404,'Страница не найдена.');
}
