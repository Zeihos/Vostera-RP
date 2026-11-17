<?php
declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data.json';
const UPLOAD_PATH = __DIR__ . '/../public/uploads';

function boot(): void {
    $settings = require __DIR__ . '/config.php';
    date_default_timezone_set($settings['timezone'] ?? 'Europe/Moscow');
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('vostera_wiki');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
function cfg(): array { static $c=null; return $c ??= require __DIR__ . '/config.php'; }
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function lower(string $text): string {
    if (function_exists('mb_strtolower')) return mb_strtolower($text,'UTF-8');
    return strtolower(strtr($text, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя'));
}
function slug(string $value): string {
    $trans=['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s=strtr(lower(trim($value)),$trans);
    $s=preg_replace('/[^a-z0-9]+/','-',$s);
    return substr(trim($s,'-'),0,84);
}
function db_read(): array {
    $fp=@fopen(DATA_FILE,'rb'); if (!$fp) { http_response_code(503); exit('Хранилище Wiki недоступно. Проверьте private/data.json.'); }
    if (!flock($fp,LOCK_SH)) { fclose($fp); http_response_code(503); exit('Хранилище заблокировано.'); }
    $raw=stream_get_contents($fp); flock($fp,LOCK_UN); fclose($fp);
    $data=json_decode($raw,true); if (!is_array($data)) {http_response_code(503); exit('Ошибка чтения данных Wiki.');}
    return $data;
}
function db_change(callable $fn): mixed {
    $fp=@fopen(DATA_FILE,'c+b'); if (!$fp || !flock($fp,LOCK_EX)) {http_response_code(503); exit('Не удалось открыть хранилище для записи.');}
    rewind($fp);$data=json_decode(stream_get_contents($fp),true);
    if (!is_array($data)) {flock($fp,LOCK_UN);fclose($fp);http_response_code(503);exit('Ошибка формата базы.');}
    try {$result=$fn($data);} catch(Throwable $x) {flock($fp,LOCK_UN);fclose($fp);throw $x;}
    $serialized=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    if ($serialized===false) {flock($fp,LOCK_UN);fclose($fp);http_response_code(500);exit('Ошибка сериализации.');}
    rewind($fp);ftruncate($fp,0);$written=fwrite($fp,$serialized."\n"); fflush($fp);
    flock($fp,LOCK_UN);fclose($fp);
    if ($written===false || $written<strlen($serialized)) {http_response_code(500);exit('Ошибка записи базы.');}
    return $result;
}
function setting(array $db,string $key): string {
    return (string)($db['settings'][$key] ?? cfg()[$key] ?? '');
}
function to_date(?string $iso): string { if (!$iso) return '—'; try {return (new DateTimeImmutable($iso))->format('d.m.Y');} catch(Throwable){return '—';} }
function now(): string {return date(DATE_ATOM);}
function redirect(string $target): never { header('Location: '.$target, true,303); exit; }
function flash(string $message,string $type='ok'): void {$_SESSION['flash']=['message'=>$message,'type'=>$type];}
function csrf(): string {if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function csrf_field(): string {return '<input type="hidden" name="csrf" value="'.e(csrf()).'">';}
function check_csrf(): void { if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(419);exit('Ошибка безопасности: обновите страницу и повторите действие.');} }
function method_post(): bool {return ($_SERVER['REQUEST_METHOD']??'GET')==='POST';}
function user(array $db): ?array {
    $id=(int)($_SESSION['uid']??0); if(!$id)return null;
    foreach($db['users'] as $u)if((int)$u['id']===$id && ($u['active']??true))return $u;
    return null;
}
function can_edit(?array $u): bool {return $u!==null && in_array($u['role'],['owner','admin','editor'],true);}
function can_admin(?array $u): bool {return $u!==null && in_array($u['role'],['owner','admin'],true);}
function is_owner(?array $u): bool {return $u!==null && $u['role']==='owner';}
function require_editor(array $db): array { $u=user($db);if(!can_edit($u)) redirect('/login'); return $u; }
function require_admin(array $db): array { $u=user($db);if(!can_admin($u)){http_response_code(403);exit('Недостаточно прав.');}return $u;}
function find_article(array $db,string $slug,bool $includeDraft=false): ?array {
    foreach($db['articles'] as $a)if($a['slug']===$slug && ($includeDraft || $a['status']==='published'))return $a;
    return null;
}
function category_for(array $db,int $id): ?array {foreach($db['categories'] as $c)if((int)$c['id']===$id)return $c;return null;}
function category_slug(array $db,string $s): ?array {foreach($db['categories'] as $c)if($c['slug']===$s)return $c;return null;}
function article_path(array $a): string {return '/article/'.rawurlencode($a['slug']);}
function category_path(array $c): string {return '/category/'.rawurlencode($c['slug']);}
function published(array $db, ?int $cat=null): array {
    $a=array_values(array_filter($db['articles'],fn($a)=>$a['status']==='published' && ($cat===null || (int)$a['category_id']===$cat)));
    usort($a,fn($x,$y)=>strcmp($y['updated_at'],$x['updated_at']) ?: strcmp($x['title'],$y['title'])); return $a;
}
function search_articles(array $db,string $query,int $max=30): array {
    $query=trim($query); if($query==='')return [];
    $terms=preg_split('/\s+/u',lower($query)); $terms=array_filter($terms,fn($v)=>$v!=='');
    $matches=[];
    foreach(published($db) as $a){
        $title=lower($a['title']);$keywords=lower($a['keywords']??'');$excerpt=lower($a['excerpt']);$body=lower($a['body']);$score=0;
        foreach($terms as $term){$found=0;foreach([[$title,9],[$keywords,5],[$excerpt,3],[$body,1]] as [$value,$weight]){if(str_contains($value,$term))$found+=$weight;}if($found===0){$score=0;break;}$score+=$found;}
        if($score>0)$matches[]=['item'=>$a,'score'=>$score];
    }
    usort($matches,fn($x,$y)=>$y['score']<=>$x['score'] ?: strcmp($y['item']['updated_at'],$x['item']['updated_at']));
    return array_slice(array_map(fn($m)=>$m['item'],$matches),0,$max);
}
function safe_url(string $url): ?string {
    $url=html_entity_decode(trim($url),ENT_QUOTES,'UTF-8');
    if(preg_match('~^https?://[^\s<>"\x00-\x1f]+$~i',$url))return $url;
    if(preg_match('~^/(?:[a-zA-Z0-9_./%?=&+-]*)$~',$url) && !str_starts_with($url,'//'))return $url;
    return null;
}
function md_inline(string $line): string {
    $placeholders=[];
    $new=function(string $html)use(&$placeholders):string{$key='WIKITOKEN'.count($placeholders).'END';$placeholders[$key]=$html;return $key;};
    $line=preg_replace_callback('/!\[([^\]\n]*)\]\(([^\)\n]+)\)/u',function($m)use($new){$url=trim($m[2]);if(!preg_match('~^/uploads/[a-zA-Z0-9._-]+\.(?:png|jpg|jpeg|webp)$~i',$url))return e($m[0]);return $new('<img class="article-image" src="'.e($url).'" alt="'.e($m[1]).'" loading="lazy">');},$line);
    $line=preg_replace_callback('/\[([^\]\n]+)\]\(([^\)\n]+)\)/u',function($m)use($new){$url=safe_url($m[2]); if($url===null)return e($m[0]);$ext=str_starts_with($url,'http');return $new('<a href="'.e($url).'"'.($ext?' target="_blank" rel="noopener noreferrer"':'').'>'.e($m[1]).'</a>');},$line);
    $line=preg_replace_callback('/`([^`\n]+)`/u',fn($m)=>$new('<code>'.e($m[1]).'</code>'),$line);
    $line=e($line);
    $line=preg_replace('/\*\*(.+?)\*\*/u','<strong>$1</strong>',$line);
    $line=preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u','<em>$1</em>',$line);
    $line=preg_replace('/~~(.+?)~~/u','<del>$1</del>',$line);
    foreach($placeholders as $key=>$html)$line=str_replace($key,$html,$line);
    return $line;
}
function article_blocks(string $markdown): array {
    $lines=explode("\n",str_replace("\r",'', $markdown));$html='';$toc=[];$paragraph=[];$list=null;$quote=[];$code=[];$codeOpen=false;
    $flush=function()use(&$html,&$paragraph,&$list,&$quote){
        if($paragraph){$html.='<p>'.md_inline(implode(' ',$paragraph)).'</p>'; $paragraph=[];}
        if($list!==null){$html.='</'.$list.'>'; $list=null;}
        if($quote){$html.='<aside class="article-note"><span class="note-symbol">i</span><div>'.md_inline(implode(' ',$quote)).'</div></aside>'; $quote=[];}
    };
    foreach($lines as $line){$trim=trim($line);
        if(str_starts_with($trim,'```')){if($codeOpen){$html.='<pre><code>'.e(implode("\n",$code)).'</code></pre>';$code=[];$codeOpen=false;}else{$flush();$codeOpen=true;}continue;}
        if($codeOpen){$code[]=$line;continue;}
        if($trim===''){$flush();continue;}
        if(preg_match('/^(#{1,3})\s+(.+)$/u',$trim,$m)){$flush();$level=min(3,strlen($m[1])+1);$name=trim($m[2]);$id=slug($name) ?: 'part-'.(count($toc)+1);$id.='-'.(count($toc)+1);$html.="<h{$level} id=\"".e($id).'">'.md_inline($name)."</h{$level}>";$toc[]=['id'=>$id,'name'=>$name,'level'=>$level];continue;}
        if(preg_match('/^>\s*(.+)$/u',$trim,$m)){$flush();$quote[]=$m[1];continue;}
        if(preg_match('/^[-*]\s+(.+)$/u',$trim,$m)){if($paragraph||$quote)$flush();if($list==='ol')$flush();if($list!=='ul'){$list='ul';$html.='<ul>';} $html.='<li>'.md_inline($m[1]).'</li>';continue;}
        if(preg_match('/^\d+[.)]\s+(.+)$/u',$trim,$m)){if($paragraph||$quote)$flush();if($list==='ul')$flush();if($list!=='ol'){$list='ol';$html.='<ol>';} $html.='<li>'.md_inline($m[1]).'</li>';continue;}
        if(preg_match('/^(-{3,}|\*{3,})$/',$trim)){$flush();$html.='<hr>';continue;}
        if($list!==null){$flush();}
        $paragraph[]=$trim;
    }
    if($codeOpen)$html.='<pre><code>'.e(implode("\n",$code)).'</code></pre>';
    $flush();return [$html,$toc];
}
function document_url(): string {
    $configured=trim(cfg()['site_url'] ?? '');
    $https=!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off';
    $host=(string)($_SERVER['HTTP_HOST']??'localhost');
    if(!preg_match('/^[a-zA-Z0-9.:-]{1,240}$/',$host))$host='localhost';
    return ($https?'https://':'http://').$host;
}
