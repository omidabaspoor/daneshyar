<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) json_response(['ok'=>false,'error'=>'دسترسی نامعتبر'], 401);

if (!csrf_check($_POST['csrf'] ?? '')) {
    json_response(['ok'=>false,'error'=>'توکن نامعتبر']);
}

$action  = $_POST['action']  ?? '';
$chat_id = (int)($_POST['chat_id'] ?? 0);

// مالکیت چت رو چک کن
$chat = get_chat($chat_id, $user['id']);
if (!$chat) json_response(['ok'=>false,'error'=>'چت یافت نشد']);

switch ($action) {
    case 'toggle_pin':
        $newPinned = $chat['is_pinned'] ? 0 : 1;
        db()->prepare("UPDATE chats SET is_pinned=? WHERE id=? AND user_id=?")
            ->execute([$newPinned, $chat_id, $user['id']]);
        json_response(['ok'=>true, 'pinned'=>$newPinned===1]);
        break;

    case 'rename':
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 100) {
            json_response(['ok'=>false,'error'=>'عنوان نامعتبر']);
        }
        db()->prepare("UPDATE chats SET title=? WHERE id=? AND user_id=?")
            ->execute([$title, $chat_id, $user['id']]);
        json_response(['ok'=>true]);
        break;

    case 'delete':
        // حذف پیام‌های مرتبط
        db()->prepare("DELETE FROM chat_history WHERE chat_id=? AND user_id=?")
            ->execute([$chat_id, $user['id']]);
        db()->prepare("DELETE FROM chats WHERE id=? AND user_id=?")
            ->execute([$chat_id, $user['id']]);
        json_response(['ok'=>true]);
        break;

    default:
        json_response(['ok'=>false,'error'=>'دستور نامعتبر']);
}
