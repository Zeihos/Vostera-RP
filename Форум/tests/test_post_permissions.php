<?php
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib.php';
function ensure(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$now = time();
$db = [
    'users' => [
        ['id'=>1,'role'=>'admin','banned_until'=>0],
        ['id'=>2,'role'=>'user','banned_until'=>0],
        ['id'=>3,'role'=>'moderator','banned_until'=>0],
    ],
    'forums' => [
        ['id'=>4,'write_role'=>'user'],
        ['id'=>5,'write_role'=>'admin'],
    ],
    'topics' => [
        ['id'=>7,'forum_id'=>4,'locked'=>false],
        ['id'=>8,'forum_id'=>5,'locked'=>false],
    ],
    'posts' => [
        ['id'=>11,'topic_id'=>7,'user_id'=>2,'created_at'=>$now-100,'hidden'=>false],
        ['id'=>12,'topic_id'=>7,'user_id'=>2,'created_at'=>$now-2000,'hidden'=>false],
    ],
];
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    ensure($ok, $message);
    $checks++;
}
check(forum_can_create_topic($db,2,4),'Обычный игрок создаёт тему в открытом разделе');
check(!forum_can_create_topic($db,2,5),'Нельзя создавать тему в разделе администрации');
check(forum_can_create_topic($db,1,5),'Администратор имеет доступ к своему разделу');
check(forum_can_reply($db,2,7),'Ответ в открытой теме');
check(!forum_can_reply($db,2,8),'Обычный игрок не пишет в закрытом разделе');
check(forum_can_reply($db,3,8),'Модератор может ответить в служебном разделе (существующее правило)');
check(forum_can_edit_post($db,2,11),'Своё сообщение можно редактировать первые полчаса');
check(!forum_can_edit_post($db,2,12),'30-минутный срок редактирования истёк');
check(!forum_can_edit_post($db,3,99),'Нельзя редактировать отсутствующее сообщение');
check(forum_can_edit_post($db,3,11),'Модератор может редактировать чужое сообщение');
check(!forum_can_edit_post($db,2,999),'Отсутствующий ID сообщения');
check(!forum_can_reply($db,2,999),'Ответ в отсутствующую тему');
check(!forum_can_create_topic($db,2,999),'Несуществующий раздел');

$old = $db;
$db['topics'][0]['locked'] = true;
check(!forum_can_reply($db,2,7),'После закрытия темы старое разрешение не действует');
check(forum_can_reply($db,3,7),'Модератор может ответить в закрытую тему');
$db = $old;
$db['topics'] = array_values(array_filter($db['topics'],fn($t)=>$t['id']!==7));
check(!forum_can_reply($db,2,7),'Ответ нельзя записать после удаления темы');
check(!forum_can_edit_post($db,2,11),'Редактирование невозможно после удаления темы');
$db = $old;
$db['forums'] = array_values(array_filter($db['forums'],fn($f)=>$f['id']!==4));
check(!forum_can_create_topic($db,2,4),'Нельзя создать тему после удаления раздела');
check(!forum_can_reply($db,2,7),'Нельзя ответить, если раздел удалён');
$db = $old;
$db['users'][1]['banned_until'] = $now+1800;
check(!forum_can_create_topic($db,2,4),'Заблокированный игрок не создаёт тему');
check(!forum_can_reply($db,2,7),'Заблокированный игрок не отвечает');
check(!forum_can_edit_post($db,2,11),'Заблокированный игрок не редактирует');
$db = $old;
$db['forums'][0]['write_role']='admin';
check(!forum_can_create_topic($db,2,4),'Изменение прав на раздел вступает в силу');
check(!forum_can_reply($db,2,7),'Изменение прав на ответ вступает в силу');
$db = $old;
$db['users'][2]['role']='user';
check(!forum_can_reply($db,3,8),'Отозванные права модератора учитываются');
$db = $old;
$db['posts'][0]['hidden']=true;
check(!forum_can_edit_post($db,2,11),'Скрытое сообщение нельзя редактировать');
$db = $old;
$db['users']=array_values(array_filter($db['users'],fn($u)=>$u['id']!==2));
check(!forum_can_create_topic($db,2,4),'Удалённый аккаунт не создаёт темы');
check(!forum_can_reply($db,2,7),'Удалённый аккаунт не отвечает');
check(!forum_can_edit_post($db,2,11),'Удалённый аккаунт не меняет пост');
$db = $old;
$db['users'][1]['password'] = password_hash('correct-current-password', PASSWORD_DEFAULT);
check(forum_can_update_profile($db,2,'',''),'Обновление описания доступно');
check(forum_can_update_profile($db,2,'correct-current-password','new-password-123'),'Смена с правильным старым паролем');
check(!forum_can_update_profile($db,2,'wrong','new-password-123'),'Нельзя менять пароль без подтверждения');
check(!forum_can_update_profile($db,2,'correct-current-password','short'),'Слишком короткий новый пароль');
$db['users'][1]['password'] = password_hash('already-changed-password', PASSWORD_DEFAULT);
check(!forum_can_update_profile($db,2,'correct-current-password','next-password-123'),'Старый запрос не заменит уже сменённый пароль');
$db['users'][1]['banned_until']=$now+1800;
check(!forum_can_update_profile($db,2,'',''),'Заблокированному аккаунту запрещено менять профиль');

echo "OK: права форума и состояние транзакции — {$checks} проверок\n";
