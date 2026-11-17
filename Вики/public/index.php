<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/lib.php';
require dirname(__DIR__).'/private/views.php';
require dirname(__DIR__).'/private/admin.php';
boot();
$db=db_read();
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$path='/'.trim(rawurldecode($path),'/');
if ($path==='//')$path='/';
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','HEAD','POST'],true)) {http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
function fail(string $message,string $url): never {flash($message,'error');redirect($url);}
function post_string(string $key,int $max=300): string {return substr(trim((string)($_POST[$key]??'')),0,$max);}
function clean_link(string $key): string {
    $v=post_string($key,350); if($v==='' || ($url=safe_url($v))===null || !preg_match('~^https://~i',$v))return '';return $v;
}
if ($method==='POST') {
    check_csrf();
    if ($path==='/install') {
        if($db['installed'])redirect('/admin');
        $key=post_string('key',150);$login=post_string('login',40);$pwd=(string)($_POST['password']??'');$repeat=(string)($_POST['password_repeat']??'');
        $expected=trim((string)@file_get_contents(dirname(__DIR__).'/private/install.key'));
        if($expected==='' || !hash_equals($expected,$key))fail('Неверный установочный ключ.','/install');
        if(!preg_match('/^[A-Za-z0-9_]{3,40}$/D',$login))fail('Логин: от 3 до 40 латинских символов, цифр или _.','/install');
        if(strlen($pwd)<12 || strlen($pwd)>256 || $pwd!==$repeat)fail('Пароль должен содержать 12–256 символов и совпадать с подтверждением.','/install');
        $id=db_change(function(&$data)use($login,$pwd){
            if($data['installed'])return 0;
            $id=$data['next_user_id']++;
            $data['users'][]=['id'=>$id,'login'=>$login,'password'=>password_hash($pwd,PASSWORD_DEFAULT),'role'=>'owner','active'=>true,'created_at'=>now()];
            $data['installed']=true;
            return $id;
        });
        if(!$id)redirect('/login');
        @unlink(dirname(__DIR__).'/private/install.key');
        session_regenerate_id(true);$_SESSION['uid']=$id;flash('Wiki установлена. Вы вошли как владелец.');redirect('/admin');
    }
    if ($path==='/login') {
        $login=post_string('login',40);$pwd=(string)($_POST['password']??'');
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
        $secret=trim((string)@file_get_contents(dirname(__DIR__).'/private/app.key'));
        if(strlen($secret)<64){http_response_code(503);exit('Wiki не подготовлена. Выполните php tools/prepare-install.php');}
        $bucket=hash_hmac('sha256',$ip,$secret);
        $status=db_change(function(&$data)use($bucket,$login,$pwd){
            $now=time(); foreach($data['login_guard'] as $k=>$v){if(($v['until']??0)<$now)unset($data['login_guard'][$k]);}
            $attempt=$data['login_guard'][$bucket]??['count'=>0,'until'=>$now+900];
            if(($attempt['count']??0)>=8)return ['state'=>'limited'];
            foreach($data['users'] as $user){
                if(($user['active']??true) && hash_equals(lower($user['login']),lower($login)) && password_verify($pwd,$user['password'])){unset($data['login_guard'][$bucket]);return ['state'=>'ok','id'=>$user['id']];}
            }
            $attempt['count']++;$attempt['until']=$now+900;$data['login_guard'][$bucket]=$attempt;
            return ['state'=>'bad'];
        });
        if($status['state']==='limited')fail('Слишком много попыток. Повторите вход через 15 минут.','/login');
        if($status['state']==='bad')fail('Неверный логин или пароль.','/login');
        session_regenerate_id(true);$_SESSION['uid']=$status['id'];flash('Вы вошли в панель Wiki.');redirect('/admin');
    }
    if ($path==='/logout') {
        $_SESSION=[];session_regenerate_id(true);flash('Вы вышли из системы.');redirect('/');
    }
    if ($path==='/vote') {
        $id=(int)($_POST['article']??0);$vote=post_string('vote',4);$article=null;
        foreach($db['articles'] as $a){if($a['id']===$id && $a['status']==='published')$article=$a;}
        if(!$article || !in_array($vote,['yes','no'],true))redirect('/');
        $voteId=$id.':'.hash_hmac('sha256',session_id(),csrf());
        db_change(function(&$data)use($voteId,$vote){$data['votes'][$voteId]=$vote;});
        flash('Спасибо за отзыв!');redirect(article_path($article).'#feedback');
    }
    if (!str_starts_with($path,'/admin/')) {http_response_code(404);exit('Неизвестный адрес.');}
    $me=require_editor($db);
    if ($path==='/admin/preview') {
        header('Content-Type: application/json; charset=utf-8');
        [$html]=article_blocks(post_string('body',40000));echo json_encode(['html'=>$html],JSON_UNESCAPED_UNICODE);exit;
    }
    if ($path==='/admin/article/save') {
        $id=(int)($_POST['id']??0);$title=post_string('title',140);$slug=slug(post_string('slug',100)?:$title);
        $excerpt=post_string('excerpt',260);$body=post_string('body',40000);$status=post_string('status',20);
        $category=(int)($_POST['category_id']??0);$keywords=post_string('keywords',300);
        $featured=isset($_POST['featured']);
        if($title==='' || $slug==='' || $excerpt==='' || trim($body)==='' || !in_array($status,['published','draft'],true) || !category_for($db,$category))fail('Заполните заголовок, описание, категорию и содержимое.','/admin/articles');
        foreach($db['articles'] as $a)if($a['slug']===$slug && $a['id']!==$id)fail('Статья с таким адресом уже существует.','/admin/articles');
        $saved=db_change(function(&$data)use($id,$title,$slug,$excerpt,$body,$status,$category,$keywords,$featured,$me){
            $found=false;
            foreach($data['articles'] as &$article){if($article['id']===$id){$article=array_merge($article,['title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'body'=>$body,'status'=>$status,'category_id'=>$category,'keywords'=>$keywords,'featured'=>$featured,'updated_at'=>now()]);$found=true;break;}}
            unset($article);
            if($found)return $id;
            if($id!==0)return 0;
            $new=$data['next_article_id']++;
            $data['articles'][]=['id'=>$new,'category_id'=>$category,'title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'body'=>$body,'keywords'=>$keywords,'featured'=>$featured,'status'=>$status,'created_at'=>now(),'updated_at'=>now(),'author_id'=>$me['id']];
            return $new;
        });
        if(!$saved)fail('Статья не найдена.','/admin/articles');
        flash('Статья сохранена.');redirect('/admin/articles/edit/'.$saved);
    }
    if ($path==='/admin/article/delete') {
        require_admin($db);$id=(int)($_POST['id']??0);
        db_change(function(&$data)use($id){$data['articles']=array_values(array_filter($data['articles'],fn($a)=>$a['id']!==$id));foreach($data['votes'] as $key=>$v){if(str_starts_with($key,$id.':'))unset($data['votes'][$key]);}});
        flash('Статья удалена.');redirect('/admin/articles');
    }
    if ($path==='/admin/category/save') {
        require_admin($db);$id=(int)($_POST['id']??0);$name=post_string('name',90);$slug=slug(post_string('slug',90)?:$name);
        $desc=post_string('description',240);$order=max(1,min(999,(int)($_POST['sort']??10)));
        $icon=post_string('icon',20);if(!in_array($icon,['book','rocket','gamepad','shield','wrench','messages','wallet','bell'],true))$icon='book';
        if(!$slug || !$name)fail('Заполните название и адрес.','/admin/categories');
        foreach($db['categories'] as $c)if($c['slug']===$slug && $c['id']!==$id)fail('Адрес раздела уже используется.','/admin/categories');
        db_change(function(&$data)use($id,$name,$slug,$desc,$order,$icon){
            foreach($data['categories'] as &$c)if($c['id']===$id){$c=array_merge($c,['name'=>$name,'slug'=>$slug,'description'=>$desc,'sort'=>$order,'icon'=>$icon]);return;}unset($c);
            $data['categories'][]=['id'=>$data['next_category_id']++,'name'=>$name,'slug'=>$slug,'description'=>$desc,'sort'=>$order,'icon'=>$icon];
        });flash('Раздел сохранён.');redirect('/admin/categories');
    }
    if ($path==='/admin/category/delete') {
        require_admin($db);$id=(int)($_POST['id']??0);
        foreach($db['articles'] as $a)if($a['category_id']===$id)fail('Нельзя удалить раздел со статьями. Сначала перенесите материалы.','/admin/categories');
        db_change(function(&$data)use($id){$data['categories']=array_values(array_filter($data['categories'],fn($c)=>$c['id']!==$id));});
        flash('Категория удалена.');redirect('/admin/categories');
    }
    if ($path==='/admin/settings') {
        require_admin($db);
        $values=['hero_notice'=>post_string('hero_notice',220),'announcement'=>post_string('announcement',500),'site_url'=>clean_link('site_url'),'forum_url'=>clean_link('forum_url'),'download_url'=>clean_link('download_url')];
        db_change(function(&$data)use($values){$data['settings']=array_merge($data['settings'],$values);});flash('Настройки обновлены.');redirect('/admin/settings');
    }
    if ($path==='/admin/media') {
        if(!isset($_FILES['image']) || !is_array($_FILES['image']) || ($_FILES['image']['error']??1)!==UPLOAD_ERR_OK)fail('Не удалось загрузить файл.','/admin/media');
        $file=$_FILES['image'];$max=(int)(cfg()['upload_max_bytes']??3145728);
        if($file['size']<=0 || $file['size']>$max || !is_uploaded_file($file['tmp_name']))fail('Слишком большой или некорректный файл.','/admin/media');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime]) || !@getimagesize($file['tmp_name']))fail('Разрешены только изображения JPG, PNG или WEBP.','/admin/media');
        $filename=bin2hex(random_bytes(14)).'.'.$allowed[$mime];$dest=UPLOAD_PATH.'/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$dest))fail('Не удалось сохранить изображение. Проверьте права доступа.','/admin/media');
        @chmod($dest,0644);$media=['url'=>'/uploads/'.$filename,'alt'=>post_string('alt',110),'date'=>now(),'by'=>$me['id']];
        db_change(function(&$data)use($media){$data['media'][]=$media;});flash('Изображение загружено. Скопируйте код из медиатеки.');redirect('/admin/media');
    }
    if ($path==='/admin/user/create') {
        if(!is_owner($me)){http_response_code(403);exit('Только владелец может управлять сотрудниками.');}
        $login=post_string('login',40);$password=(string)($_POST['password']??'');$role=post_string('role',20);
        if(!preg_match('/^[A-Za-z0-9_]{3,40}$/D',$login) || strlen($password)<12 || strlen($password)>256 || !in_array($role,['admin','editor'],true))fail('Проверьте логин, роль и пароль от 12 символов.','/admin/users');
        foreach($db['users'] as $u)if(lower($u['login'])===lower($login))fail('Пользователь с таким логином уже существует.','/admin/users');
        db_change(function(&$data)use($login,$password,$role){$data['users'][]=['id'=>$data['next_user_id']++,'login'=>$login,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'active'=>true,'created_at'=>now()];});
        flash('Сотрудник добавлен.');redirect('/admin/users');
    }
    if ($path==='/admin/user/update') {
        if(!is_owner($me)){http_response_code(403);exit('Недостаточно прав.');}
        $id=(int)($_POST['id']??0);$role=post_string('role',20);$pwd=(string)($_POST['password']??'');$active=isset($_POST['active']);
        if(!in_array($role,['admin','editor'],true) || ($pwd!=='' && (strlen($pwd)<12 || strlen($pwd)>256)))fail('Проверьте роль и новый пароль.','/admin/users');
        db_change(function(&$data)use($id,$role,$pwd,$active){foreach($data['users'] as &$u)if($u['id']===$id && $u['role']!=='owner'){$u['role']=$role;$u['active']=$active;if($pwd!=='')$u['password']=password_hash($pwd,PASSWORD_DEFAULT);return;}});
        flash('Доступ сотрудника обновлён.');redirect('/admin/users');
    }
    http_response_code(404);exit('Неизвестное действие.');
}
// GET / HEAD
if ($path==='/robots.txt') {header('Content-Type: text/plain; charset=utf-8');echo "User-agent: *\nDisallow: /admin\nDisallow: /install\nDisallow: /login\nSitemap: ".document_url()."/sitemap.xml\n";exit;}
if ($path==='/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');$url=document_url();
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    echo '<url><loc>'.e($url.'/').'</loc></url>';
    foreach($db['categories'] as $c)echo '<url><loc>'.e($url.category_path($c)).'</loc></url>';
    foreach(published($db) as $a)echo '<url><loc>'.e($url.article_path($a)).'</loc><lastmod>'.e(substr($a['updated_at'],0,10)).'</lastmod></url>';
    echo '</urlset>';exit;
}
if ($path==='/api/search') {
    header('Content-Type: application/json; charset=utf-8');$q=trim(substr((string)($_GET['q']??''),0,100));
    $results=array_map(fn($a)=>['title'=>$a['title'],'excerpt'=>$a['excerpt'],'url'=>article_path($a)],search_articles($db,$q,6));
    echo json_encode(['results'=>$results],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if ($path==='/') {view_home($db);exit;}
if ($path==='/search') {$q=trim(substr((string)($_GET['q']??''),0,100));view_search($db,$q);exit;}
if ($path==='/login') {if(user($db))redirect('/admin');view_login($db);exit;}
if ($path==='/install') {if($db['installed'])redirect('/admin');view_install($db);exit;}
if (preg_match('~^/category/([a-z0-9-]+)$~',$path,$matches)) {$c=category_slug($db,$matches[1]);if($c){view_category($db,$c);exit;}view_error($db,404,'Раздел не найден');exit;}
if (preg_match('~^/article/([a-z0-9-]+)$~',$path,$matches)) {$a=find_article($db,$matches[1]);if($a){view_article($db,$a);exit;}view_error($db,404,'Статья не найдена');exit;}
if ($path==='/admin') {require_editor($db);view_admin_home($db);exit;}
if ($path==='/admin/articles') {require_editor($db);view_admin_articles($db);exit;}
if ($path==='/admin/articles/new') {require_editor($db);view_admin_edit($db,null);exit;}
if(preg_match('~^/admin/articles/edit/([0-9]+)$~',$path,$matches)){
    require_editor($db);foreach($db['articles'] as $a){if($a['id']===(int)$matches[1]){view_admin_edit($db,$a);exit;}}
    view_error($db,404,'Статья не найдена');exit;
}
if ($path==='/admin/categories') {require_admin($db);view_admin_categories($db);exit;}
if ($path==='/admin/settings') {require_admin($db);view_admin_settings($db);exit;}
if ($path==='/admin/media') {require_editor($db);view_admin_media($db);exit;}
if ($path==='/admin/feedback') {require_admin($db);view_admin_feedback($db);exit;}
if ($path==='/admin/users') {$me=require_admin($db);if(!is_owner($me)){http_response_code(403);exit('Только владелец может управлять сотрудниками.');}view_admin_users($db);exit;}
view_error($db,404,'Страница не найдена');
