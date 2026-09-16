<?php
// staff/notifications.php
// Small JSON endpoint backing the notification bell in includes/header.php.
require_once 'includes/auth.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {

    case 'list':
        echo json_encode([
            'ok'     => true,
            'count'  => getUnreadNotificationCount($conn, $staff_id),
            'items'  => array_map(function ($n) {
                $n['time_ago'] = timeAgo($n['created_at']);
                return $n;
            }, getActiveNotifications($conn, $staff_id, 20)),
        ]);
        break;

    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            markNotificationRead($conn, $id, $staff_id);
        }
        echo json_encode(['ok' => true, 'count' => getUnreadNotificationCount($conn, $staff_id)]);
        break;

    case 'mark_all_read':
        markAllNotificationsRead($conn, $staff_id);
        echo json_encode(['ok' => true, 'count' => 0]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
