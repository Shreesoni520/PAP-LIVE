<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Lisbon');

include './config.php';

$username   = $_SESSION['username'] ?? 'Utilizador';
$avatarPath = '';
$userId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isAdmin    = false;

/* =========================================================
   USER / ADMIN INFO
   Always check admin from database, then sync session.
========================================================= */
if ($userId > 0) {
    $stmtUser = $conn->prepare("SELECT photo, name, username, is_admin FROM users WHERE id = ? LIMIT 1");

    if ($stmtUser) {
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();

        if ($userRow) {
            if (!empty($userRow['name'])) {
                $username = $userRow['name'];
            } elseif (!empty($userRow['username'])) {
                $username = $userRow['username'];
            }

            if (!empty($userRow['photo'])) {
                $avatarPath = $userRow['photo'];
            }

            $isAdmin = !empty($userRow['is_admin']) && (int)$userRow['is_admin'] === 1;
            $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
        }
    }
}

/* =========================================================
   AJAX AÇÕES DAS NOTIFICAÇÕES
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action']) && $userId > 0) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['notification_action'];

    if ($action === 'remove_one') {
        $notifId = isset($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0;
        $source  = $_POST['source'] ?? 'db';

        if ($notifId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        if ($source === 'db') {
            $stmt = $conn->prepare("
                DELETE FROM notificacoes
                WHERE id = ?
                  AND user_id = ?
                  AND (enviar_em IS NULL OR enviar_em <= NOW())
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'tree_assignment') {
            $stmt = $conn->prepare("
                UPDATE arvores
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'ocorrencia_assignment') {
            $stmt = $conn->prepare("
                UPDATE ocorrencias
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'ocorrencia_estrada_assignment') {
            $stmt = $conn->prepare("
                UPDATE ocorrencias_estrada
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Origem inválida']);
        exit;
    }

    if ($action === 'clear_all') {
        $ok = true;

        $stmt1 = $conn->prepare("
            DELETE FROM notificacoes
            WHERE user_id = ?
              AND (enviar_em IS NULL OR enviar_em <= NOW())
        ");

        if ($stmt1) {
            $stmt1->bind_param("i", $userId);
            $ok = $ok && $stmt1->execute();
            $stmt1->close();
        }

        $stmt2 = $conn->prepare("
            UPDATE arvores
            SET notification_read = 1
            WHERE assigned_to_user_id = ?
              AND notification_read = 0
              AND (
                    scheduled_for IS NULL
                    OR scheduled_for = '0000-00-00 00:00:00'
                    OR scheduled_for <= NOW()
                  )
        ");

        if ($stmt2) {
            $stmt2->bind_param("i", $userId);
            $ok = $ok && $stmt2->execute();
            $stmt2->close();
        }

        $stmt3 = $conn->prepare("
            UPDATE ocorrencias
            SET notification_read = 1
            WHERE assigned_to_user_id = ?
              AND notification_read = 0
              AND (
                    scheduled_for IS NULL
                    OR scheduled_for = '0000-00-00 00:00:00'
                    OR scheduled_for <= NOW()
                  )
        ");

        if ($stmt3) {
            $stmt3->bind_param("i", $userId);
            $ok = $ok && $stmt3->execute();
            $stmt3->close();
        }

        $stmt4 = $conn->prepare("
            UPDATE ocorrencias_estrada
            SET notification_read = 1
            WHERE assigned_to_user_id = ?
              AND notification_read = 0
              AND (
                    scheduled_for IS NULL
                    OR scheduled_for = '0000-00-00 00:00:00'
                    OR scheduled_for <= NOW()
                  )
        ");

        if ($stmt4) {
            $stmt4->bind_param("i", $userId);
            $ok = $ok && $stmt4->execute();
            $stmt4->close();
        }

        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($action === 'mark_read') {
        $notifId = isset($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0;
        $source  = $_POST['source'] ?? 'db';

        if ($notifId <= 0) {
            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'db') {
            $stmt = $conn->prepare("
                UPDATE notificacoes
                SET lida = 1
                WHERE id = ?
                  AND user_id = ?
                  AND (enviar_em IS NULL OR enviar_em <= NOW())
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'tree_assignment') {
            $stmt = $conn->prepare("
                UPDATE arvores
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'ocorrencia_assignment') {
            $stmt = $conn->prepare("
                UPDATE ocorrencias
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        if ($source === 'ocorrencia_estrada_assignment') {
            $stmt = $conn->prepare("
                UPDATE ocorrencias_estrada
                SET notification_read = 1
                WHERE id = ?
                  AND assigned_to_user_id = ?
                  AND (
                        scheduled_for IS NULL
                        OR scheduled_for = '0000-00-00 00:00:00'
                        OR scheduled_for <= NOW()
                      )
            ");

            if ($stmt) {
                $stmt->bind_param("ii", $notifId, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $ok]);
                exit;
            }

            echo json_encode(['success' => false]);
            exit;
        }

        echo json_encode(['success' => false]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

$iniciais = '';
$partes   = preg_split('/\s+/', trim($username));

if (!empty($partes[0])) {
    $iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1, 'UTF-8'), 'UTF-8');
}

if (count($partes) > 1) {
    $iniciais .= mb_strtoupper(mb_substr(end($partes), 0, 1, 'UTF-8'), 'UTF-8');
}

if ($iniciais === '') {
    $iniciais = 'U';
}

if (!function_exists('tempo_decorrido_menu')) {
    function tempo_decorrido_menu($datetime) {
        if (empty($datetime)) return 'Agora mesmo';

        $timestamp = strtotime($datetime);
        if (!$timestamp) return 'Agora mesmo';

        $diff = time() - $timestamp;

        if ($diff < 60) return 'Agora mesmo';
        if ($diff < 3600) return 'Há ' . floor($diff / 60) . ' min';
        if ($diff < 86400) return 'Há ' . floor($diff / 3600) . ' h';

        return 'Há ' . floor($diff / 86400) . ' dia(s)';
    }
}

if (!function_exists('notif_link_menu')) {
    function notif_link_menu($notif) {
        $origemTipo = $notif['origem_tipo'] ?? '';
        $origemId   = isset($notif['origem_id']) ? (int)$notif['origem_id'] : 0;
        $notifId    = isset($notif['id']) ? (int)$notif['id'] : 0;
        $source     = $notif['notif_source'] ?? 'db';

        if ($origemTipo === 'arvore') {
            if ($source === 'tree_assignment') {
                return "index.php?evora=editarintervencaoarvore&id={$origemId}&notif_id={$origemId}";
            }

            return "index.php?evora=editarintervencaoarvore&id={$origemId}&notif_id={$notifId}";
        }

        if ($origemTipo === 'ocorrencia') {
            if ($source === 'ocorrencia_assignment') {
                return "index.php?evora=editarintervencaoocorrencias&id={$origemId}&notif_id={$origemId}";
            }

            return "index.php?evora=editarintervencaoocorrencias&id={$origemId}&notif_id={$notifId}";
        }

        if ($origemTipo === 'ocorrencia_estrada') {
            if ($source === 'ocorrencia_estrada_assignment') {
                return "index.php?evora=editarintervencaoocorrenciasestrada&id={$origemId}&notif_id={$origemId}";
            }

            return "index.php?evora=editarintervencaoocorrenciasestrada&id={$origemId}&notif_id={$notifId}";
        }

        return "index.php?evora=inicio";
    }
}

/* =========================================================
   NOTIFICAÇÕES REAIS
   - DB notifications are shown only when unread and visible.
   - Assignment fallback is kept for old assignments.
========================================================= */
$notificationCount = 0;
$notifications = [];

if ($userId > 0) {
    $stmtNotif = $conn->prepare("
        SELECT
            id,
            titulo,
            mensagem,
            origem_tipo,
            origem_id,
            enviar_em,
            lida,
            criada_em,
            COALESCE(enviar_em, criada_em) AS sort_time,
            'db' AS notif_source
        FROM notificacoes
        WHERE user_id = ?
          AND lida = 0
          AND (enviar_em IS NULL OR enviar_em <= NOW())
        ORDER BY COALESCE(enviar_em, criada_em) DESC
        LIMIT 20
    ");

    if ($stmtNotif) {
        $stmtNotif->bind_param("i", $userId);
        $stmtNotif->execute();
        $resNotif = $stmtNotif->get_result();

        while ($row = $resNotif->fetch_assoc()) {
            $notifications[] = $row;
        }

        $stmtNotif->close();
    }

    $stmtTreeNotif = $conn->prepare("
        SELECT
            id,
            especie,
            place_name,
            scheduled_for,
            assigned_at
        FROM arvores
        WHERE assigned_to_user_id = ?
          AND notification_read = 0
          AND (
                scheduled_for IS NULL
                OR scheduled_for = '0000-00-00 00:00:00'
                OR scheduled_for <= NOW()
              )
        ORDER BY
            CASE
                WHEN scheduled_for IS NULL OR scheduled_for = '0000-00-00 00:00:00' THEN assigned_at
                ELSE scheduled_for
            END DESC
        LIMIT 20
    ");

    if ($stmtTreeNotif) {
        $stmtTreeNotif->bind_param("i", $userId);
        $stmtTreeNotif->execute();
        $resTreeNotif = $stmtTreeNotif->get_result();

        while ($row = $resTreeNotif->fetch_assoc()) {
            $displayDate = !empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00'
                ? date('Y-m-d H:i', strtotime($row['scheduled_for']))
                : 'Agora';

            $sortTime = (!empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00')
                ? $row['scheduled_for']
                : $row['assigned_at'];

            $notifications[] = [
                'id'           => (int)$row['id'],
                'titulo'       => 'Nova intervenção atribuída',
                'mensagem'     => 'Árvore atribuída para si. Data/Hora: ' . $displayDate,
                'origem_tipo'  => 'arvore',
                'origem_id'    => (int)$row['id'],
                'enviar_em'    => null,
                'lida'         => 0,
                'criada_em'    => $sortTime,
                'sort_time'    => $sortTime,
                'notif_source' => 'tree_assignment'
            ];
        }

        $stmtTreeNotif->close();
    }

    $stmtOccNotif = $conn->prepare("
        SELECT
            id,
            descricao,
            place_name,
            scheduled_for,
            assigned_at
        FROM ocorrencias
        WHERE assigned_to_user_id = ?
          AND notification_read = 0
          AND (
                scheduled_for IS NULL
                OR scheduled_for = '0000-00-00 00:00:00'
                OR scheduled_for <= NOW()
              )
        ORDER BY
            CASE
                WHEN scheduled_for IS NULL OR scheduled_for = '0000-00-00 00:00:00' THEN assigned_at
                ELSE scheduled_for
            END DESC
        LIMIT 20
    ");

    if ($stmtOccNotif) {
        $stmtOccNotif->bind_param("i", $userId);
        $stmtOccNotif->execute();
        $resOccNotif = $stmtOccNotif->get_result();

        while ($row = $resOccNotif->fetch_assoc()) {
            $displayDate = !empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00'
                ? date('Y-m-d H:i', strtotime($row['scheduled_for']))
                : 'Agora';

            $sortTime = (!empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00')
                ? $row['scheduled_for']
                : $row['assigned_at'];

            $notifications[] = [
                'id'           => (int)$row['id'],
                'titulo'       => 'Nova ocorrência atribuída',
                'mensagem'     => 'Ocorrência atribuída para si. Data/Hora: ' . $displayDate,
                'origem_tipo'  => 'ocorrencia',
                'origem_id'    => (int)$row['id'],
                'enviar_em'    => null,
                'lida'         => 0,
                'criada_em'    => $sortTime,
                'sort_time'    => $sortTime,
                'notif_source' => 'ocorrencia_assignment'
            ];
        }

        $stmtOccNotif->close();
    }

    $stmtRoadNotif = $conn->prepare("
        SELECT
            id,
            descricao,
            place_name,
            scheduled_for,
            assigned_at
        FROM ocorrencias_estrada
        WHERE assigned_to_user_id = ?
          AND notification_read = 0
          AND (
                scheduled_for IS NULL
                OR scheduled_for = '0000-00-00 00:00:00'
                OR scheduled_for <= NOW()
              )
        ORDER BY
            CASE
                WHEN scheduled_for IS NULL OR scheduled_for = '0000-00-00 00:00:00' THEN assigned_at
                ELSE scheduled_for
            END DESC
        LIMIT 20
    ");

    if ($stmtRoadNotif) {
        $stmtRoadNotif->bind_param("i", $userId);
        $stmtRoadNotif->execute();
        $resRoadNotif = $stmtRoadNotif->get_result();

        while ($row = $resRoadNotif->fetch_assoc()) {
            $displayDate = !empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00'
                ? date('Y-m-d H:i', strtotime($row['scheduled_for']))
                : 'Agora';

            $sortTime = (!empty($row['scheduled_for']) && $row['scheduled_for'] !== '0000-00-00 00:00:00')
                ? $row['scheduled_for']
                : $row['assigned_at'];

            $notifications[] = [
                'id'           => (int)$row['id'],
                'titulo'       => 'Nova ocorrência de estrada atribuída',
                'mensagem'     => 'Ocorrência de estrada atribuída para si. Data/Hora: ' . $displayDate,
                'origem_tipo'  => 'ocorrencia_estrada',
                'origem_id'    => (int)$row['id'],
                'enviar_em'    => null,
                'lida'         => 0,
                'criada_em'    => $sortTime,
                'sort_time'    => $sortTime,
                'notif_source' => 'ocorrencia_estrada_assignment'
            ];
        }

        $stmtRoadNotif->close();
    }

    usort($notifications, function ($a, $b) {
        return strtotime($b['sort_time'] ?? $b['criada_em'] ?? 'now') <=> strtotime($a['sort_time'] ?? $a['criada_em'] ?? 'now');
    });

    $notifications = array_slice($notifications, 0, 8);

    foreach ($notifications as $n) {
        if (empty($n['lida'])) {
            $notificationCount++;
        }
    }
}
?>

<style>
    .topbar {
        position: fixed;
        top: 14px;
        right: 18px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        z-index: 2000;
        pointer-events: none;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .topbar > .dropdown {
        pointer-events: auto;
    }

    .topbar-icon-btn,
    .topbar-btn {
        height: 40px;
        border-radius: 999px;
        border: none !important;
        outline: none !important;
        background: rgba(248, 250, 252, 0.9);
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.06),
            0 4px 8px rgba(15, 23, 42, 0.04);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0 12px;
        font-size: 0.8rem;
        color: #0f172a;
        transition:
            background-color 0.18s ease,
            box-shadow 0.18s ease,
            transform 0.08s ease;
    }

    .topbar-icon-btn {
        height: 40px;
        width: 40px;
        border-radius: 999px;
        border: none !important;
        outline: none !important;
        background: #ffffff;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.04),
            0 2px 4px rgba(15, 23, 42, 0.04);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        font-size: 0.8rem;
        color: #111827;
        transition:
            background-color 0.15s ease,
            box-shadow 0.15s ease,
            transform 0.05s ease;
    }

    .topbar-icon-btn:focus,
    .topbar-icon-btn:active,
    .topbar-btn:focus,
    .topbar-btn:active,
    .notif-clear-btn:focus,
    .notif-clear-btn:active,
    .notif-remove-btn:focus,
    .notif-remove-btn:active {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .topbar-icon-btn i,
    .topbar-btn i {
        font-size: 16px;
        color: #6b7280;
    }

    .topbar-profile {
        padding-inline: 10px 12px;
        gap: 6px;
    }

    .topbar-icon-btn:hover,
    .topbar-btn:hover {
        background-color: #f1f5f9;
        box-shadow:
            0 3px 6px rgba(15, 23, 42, 0.12),
            0 8px 16px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);
    }

    .topbar-icon-btn:hover {
        background-color: #f9fafb;
        box-shadow:
            0 4px 10px rgba(15, 23, 42, 0.08);
        transform: translateY(-1px);
    }

    .topbar-avatar-wrapper {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.6);
        background: #F2E5FF;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .topbar-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .topbar-avatar-initials {
        font-weight: 600;
        font-size: 0.7rem;
        letter-spacing: 0.04em;
        color: #4b5563;
    }

    .topbar-username {
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.78rem;
        color: #374151;
    }

    .topbar-chevron {
        font-size: 11px;
        color: #9ca3af;
        transition: transform 0.18s ease;
    }

    .topbar-btn[aria-expanded="true"] .topbar-chevron {
        transform: rotate(180deg);
    }

    .notif-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        line-height: 16px;
        text-align: center;
        font-weight: 600;
        box-shadow: 0 0 0 2px #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .notification-dropdown-wrap .dropdown-menu {
        margin-top: 10px !important;
    }

    .notif-dropdown-wide {
        width: 390px;
        padding: 0;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        box-shadow:
            0 20px 40px rgba(15, 23, 42, 0.16),
            0 8px 18px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        font-size: 0.8rem;
    }

    .notif-header {
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #eef2f7;
        background: #fcfcfd;
    }

    .notif-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: #111827;
    }

    .notif-header-left i {
        font-size: 14px;
        color: #6366f1;
    }

    .notif-header-right {
        font-size: 0.72rem;
        color: #6b7280;
    }

    .notif-clear-btn {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        background: #f3f4f6;
        color: #374151;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 6px 10px;
        border-radius: 999px;
        transition: 0.2s ease;
        cursor: pointer;
    }

    .notif-clear-btn:hover {
        background: #e5e7eb;
        color: #111827;
    }

    .notif-list {
        max-height: 360px;
        overflow-y: auto;
        padding: 8px;
        background: #fff;
    }

    .notif-empty {
        padding: 28px 14px;
        text-align: center;
        color: #9ca3af;
        font-size: 0.8rem;
    }

    .notif-card {
        position: relative;
        display: flex;
        align-items: flex-start;
        border-radius: 14px;
        padding-right: 40px;
        transition: background-color 0.18s ease, transform 0.18s ease;
    }

    .notif-card:hover {
        background: #f8fafc;
    }

    .notif-card.is-read {
        opacity: 0.82;
    }

    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        width: 100%;
        transition: background-color 0.15s ease;
        cursor: pointer;
    }

    .notif-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        margin-top: 6px;
        background: #22c55e;
        flex-shrink: 0;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.10);
    }

    .notif-dot.read {
        background: #cbd5e1;
        box-shadow: none;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-text {
        margin: 0 0 4px 0;
        font-size: 0.81rem;
        line-height: 1.35;
        color: #111827;
        word-break: break-word;
        white-space: normal;
        overflow: hidden;
    }

    .notif-time {
        font-size: 0.71rem;
        color: #9ca3af;
    }

    .notif-remove-btn {
        position: absolute;
        top: 10px;
        right: 8px;
        width: 28px;
        height: 28px;
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        border-radius: 999px;
        background: transparent;
        color: #9ca3af;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.18s ease;
        cursor: pointer;
    }

    .notif-remove-btn:hover {
        background: #eef2f7;
        color: #ef4444;
    }

    .notif-remove-btn i {
        font-size: 12px;
    }

    .topbar .dropdown-menu {
        z-index: 2100;
    }

    .topbar .dropdown-item {
        padding: 6px 12px;
    }
</style>

<div class="topbar">
    <div class="dropdown notification-dropdown-wrap">
        <button class="topbar-icon-btn position-relative" type="button"
                id="notificationDropdown"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false">
            <i class="bi bi-bell"></i>

            <?php if ($notificationCount > 0): ?>
                <span class="notif-badge" id="notifBadge"><?= (int)$notificationCount ?></span>
            <?php endif; ?>
        </button>

        <div class="dropdown-menu dropdown-menu-end notif-dropdown-wide p-0" aria-labelledby="notificationDropdown">
            <div class="notif-header">
                <div class="notif-header-left">
                    <i class="bi bi-bell-fill"></i>
                    <span>Notificações</span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="notif-header-right" id="notifCounterText">
                        <?php if ($notificationCount > 0): ?>
                            <?= (int)$notificationCount ?> novas
                        <?php else: ?>
                            Sem novas
                        <?php endif; ?>
                    </span>

                    <?php if (!empty($notifications)): ?>
                        <button type="button" class="notif-clear-btn" id="clearAllNotifications">
                            Limpar tudo
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="notif-list" id="notifList">
                <?php if (empty($notifications)): ?>
                    <div class="notif-empty">Sem novas notificações</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                            $notifUrl    = notif_link_menu($notif);
                            $notifId     = (int)($notif['id'] ?? 0);
                            $notifSource = $notif['notif_source'] ?? 'db';
                            $isRead      = !empty($notif['lida']);
                            $notifTime   = $notif['sort_time'] ?? $notif['criada_em'] ?? null;
                        ?>

                        <div class="notif-card <?= $isRead ? 'is-read' : '' ?>"
                             id="notif-item-<?= $notifId ?>"
                             data-id="<?= $notifId ?>"
                             data-source="<?= htmlspecialchars($notifSource) ?>">

                            <a href="<?= htmlspecialchars($notifUrl) ?>"
                               class="notif-item notif-open-link"
                               data-id="<?= $notifId ?>"
                               data-source="<?= htmlspecialchars($notifSource) ?>">
                                <div class="notif-dot <?= $isRead ? 'read' : '' ?>"></div>

                                <div class="notif-content">
                                    <p class="notif-text">
                                        <strong><?= htmlspecialchars($notif['titulo']) ?></strong><br>
                                        <?= htmlspecialchars($notif['mensagem']) ?>
                                    </p>

                                    <span class="notif-time">
                                        <?= htmlspecialchars(tempo_decorrido_menu($notifTime)) ?>
                                    </span>
                                </div>
                            </a>

                            <button type="button"
                                    class="notif-remove-btn remove-single-notif"
                                    title="Remover notificação"
                                    data-id="<?= $notifId ?>"
                                    data-source="<?= htmlspecialchars($notifSource) ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dropdown">
        <button class="topbar-btn topbar-profile" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
            <div class="topbar-avatar-wrapper">
                <?php if (!empty($avatarPath)): ?>
                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Perfil" class="topbar-avatar-img">
                <?php else: ?>
                    <span class="topbar-avatar-initials"><?= htmlspecialchars($iniciais) ?></span>
                <?php endif; ?>
            </div>

            <span class="topbar-username"><?= htmlspecialchars($username) ?></span>
            <i class="bi bi-chevron-down topbar-chevron"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li class="dropdown-header small text-muted">Conta</li>
            <li><a class="dropdown-item" href="index.php?evora=profile"><i class="bi bi-person me-2"></i>Perfil</a></li>
            <li><a class="dropdown-item" href="index.php?evora=security"><i class="bi bi-shield-lock me-2"></i>Segurança</a></li>

            <?php if ($isAdmin): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="index.php?evora=listarutilizador"><i class="bi bi-people me-2"></i>Admin Users</a></li>
            <?php endif; ?>

            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="index.php?evora=logout"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
        </ul>
    </div>
</div>

<div id="sidebar" class="active">
    <div class="sidebar-wrapper active">
        <div class="sidebar-header">
            <div class="d-flex justify-content-center align-items-center" style="height: 80px;">
                <div class="logo">
                    <a href="index.php?evora=inicio">
                        <img src="assets/images/logo/logo.png" alt="Logo" style="width: 13vw; height: auto;">
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul class="menu">
                <li class="sidebar-title">Menu</li>

                <li class="sidebar-item">
                    <a href="index.php?evora=inicio" class="sidebar-link">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-title">Páginas</li>

                <?php if ($isAdmin): ?>
                    <li class="sidebar-item has-sub">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-tree-fill"></i>
                            <span>Espaço Verdes</span>
                        </a>
                        <ul class="submenu">
                            <li class="submenu-item"><a href="index.php?evora=addtree" class="submenu-link">Adicionar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=listtree" class="submenu-link">Listar / PDF</a></li>
                            <li class="submenu-item"><a href="index.php?evora=editartree" class="submenu-link">Editar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removetree" class="submenu-link">Remover</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <li class="sidebar-item has-sub">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-tags-fill"></i>
                        <span>Intervenção</span>
                    </a>
                    <ul class="submenu">
                        <li class="submenu-item"><a href="index.php?evora=editarintervencaoarvore" class="submenu-link">Espaço Verdes</a></li>
                        <li class="submenu-item"><a href="index.php?evora=editarintervencaoocorrencias" class="submenu-link">Ocorrências Espaço Verdes</a></li>
                        <li class="submenu-item"><a href="index.php?evora=editarintervencaoocorrenciasestrada" class="submenu-link">Ocorrências Estrada</a></li>
                    </ul>
                </li>

                <?php if ($isAdmin): ?>
                    <li class="sidebar-item has-sub">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-tags-fill"></i>
                            <span>Tarefa</span>
                        </a>
                        <ul class="submenu">
                            <li class="submenu-item"><a href="index.php?evora=addestado" class="submenu-link">Adicionar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=listestado" class="submenu-link">Listar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=editarestado" class="submenu-link">Editar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removeestado" class="submenu-link">Remover</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <li class="sidebar-item">
                    <a href="index.php?evora=mapa2d" class="sidebar-link">
                        <i class="bi bi-map-fill"></i>
                        <span>Mapa 2D</span>
                    </a>
                </li>

                <li class="sidebar-item has-sub">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-tree"></i>
                        <span>Ocorrências Espaço Verdes</span>
                    </a>
                    <ul class="submenu">
                        <li class="submenu-item"><a href="index.php?evora=addocorrencias" class="submenu-link">Adicionar</a></li>
                        <li class="submenu-item"><a href="index.php?evora=listocorrencias" class="submenu-link">Listar / PDF</a></li>

                        <?php if ($isAdmin): ?>
                            <li class="submenu-item"><a href="index.php?evora=removeocorrencias" class="submenu-link">Remover</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <li class="sidebar-item has-sub">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-cone-striped"></i>
                        <span>Ocorrência Estrada</span>
                    </a>
                    <ul class="submenu">
                        <li class="submenu-item"><a href="index.php?evora=addocorrencias_estrada" class="submenu-link">Adicionar</a></li>
                        <li class="submenu-item"><a href="index.php?evora=listocorrencias_estrada" class="submenu-link">Listar / PDF</a></li>

                        <?php if ($isAdmin): ?>
                            <li class="submenu-item"><a href="index.php?evora=removeocorrencias_estrada" class="submenu-link">Remover</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if ($isAdmin): ?>
                    <li class="sidebar-item">
                        <a href="index.php?evora=contact" class="sidebar-link">
                            <i class="bi bi-chat-dots-fill"></i>
                            <span>Página de Contacto</span>
                        </a>
                    </li>

                    <li class="sidebar-item has-sub">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-newspaper"></i>
                            <span>Notícias</span>
                        </a>
                        <ul class="submenu">
                            <li class="submenu-item"><a href="index.php?evora=addnoticias" class="submenu-link">Adicionar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=listarnoticias" class="submenu-link">Listar / Email</a></li>
                            <li class="submenu-item"><a href="index.php?evora=editarnoticias" class="submenu-link">Editar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removernoticias" class="submenu-link">Remover</a></li>
                            <li class="submenu-item"><a href="index.php?evora=listarcommentnoticias" class="submenu-link">Lista de Comentários</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removercommentnoticias" class="submenu-link">Remover Comentário</a></li>
                        </ul>
                    </li>

                    <li class="sidebar-item has-sub">
                        <a href="#" class="sidebar-link">
                            <i class="bi bi-person-circle"></i>
                            <span>Utilizadores</span>
                        </a>
                        <ul class="submenu">
                            <li class="submenu-item"><a href="index.php?evora=addutilizador" class="submenu-link">Adicionar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=listarutilizador" class="submenu-link">Lista Admin /Publico | (PDF)</a></li>
                            <li class="submenu-item"><a href="index.php?evora=editarutilizador" class="submenu-link">Editar</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removerutilizador" class="submenu-link">Remover Utilizador Admin</a></li>
                            <li class="submenu-item"><a href="index.php?evora=removerutilizadorpublic" class="submenu-link">Remover Utilizador Público</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <li class="sidebar-item has-sub">
                    <a href="#" class="sidebar-link">
                        <i class="bi bi-person-circle"></i>
                        <span>Conta</span>
                    </a>
                    <ul class="submenu">
                        <li class="submenu-item"><a href="index.php?evora=profile" class="submenu-link">Perfil</a></li>
                        <li class="submenu-item"><a href="index.php?evora=security" class="submenu-link">Segurança da Conta</a></li>
                        <li class="submenu-item"><a href="index.php?evora=logout" class="submenu-link">Terminar sessão</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <button class="sidebar-toggler btn x">
            <i data-feather="x"></i>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const notifList = document.getElementById('notifList');
    const notifCounterText = document.getElementById('notifCounterText');

    function getBadge() {
        return document.getElementById('notifBadge');
    }

    function updateNotificationUI() {
        const items = document.querySelectorAll('.notif-card');
        const unreadItems = document.querySelectorAll('.notif-card:not(.is-read)');
        const totalUnread = unreadItems.length;
        const clearBtn = document.getElementById('clearAllNotifications');
        const badge = getBadge();

        if (badge) {
            if (totalUnread > 0) {
                badge.textContent = totalUnread;
                badge.style.display = 'inline-flex';
            } else {
                badge.remove();
            }
        }

        if (notifCounterText) {
            notifCounterText.textContent = totalUnread > 0 ? `${totalUnread} novas` : 'Sem novas';
        }

        if (items.length === 0) {
            if (notifList) {
                notifList.innerHTML = '<div class="notif-empty">Sem novas notificações</div>';
            }

            if (clearBtn) {
                clearBtn.remove();
            }
        }
    }

    function postNotificationAction(data) {
        return fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(data).toString()
        }).then(res => res.json());
    }

    document.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-single-notif');
        const clearAllBtn = e.target.closest('#clearAllNotifications');
        const notifLink = e.target.closest('.notif-open-link');

        if (removeBtn) {
            e.preventDefault();
            e.stopPropagation();

            const notifId = removeBtn.dataset.id;
            const source = removeBtn.dataset.source;
            const card = document.getElementById(`notif-item-${notifId}`);

            if (card) {
                card.remove();
                updateNotificationUI();
            }

            postNotificationAction({
                notification_action: 'remove_one',
                notif_id: notifId,
                source: source
            }).catch(err => console.error(err));

            return;
        }

        if (clearAllBtn) {
            e.preventDefault();
            e.stopPropagation();

            document.querySelectorAll('.notif-card').forEach(card => card.remove());
            clearAllBtn.remove();

            if (notifList) {
                notifList.innerHTML = '<div class="notif-empty">Sem novas notificações</div>';
            }

            const badge = getBadge();

            if (badge) {
                badge.remove();
            }

            if (notifCounterText) {
                notifCounterText.textContent = 'Sem novas';
            }

            postNotificationAction({
                notification_action: 'clear_all'
            }).catch(err => console.error(err));

            return;
        }

        if (notifLink) {
            const notifId = notifLink.dataset.id;
            const source = notifLink.dataset.source;
            const card = document.getElementById(`notif-item-${notifId}`);

            if (card) {
                card.classList.add('is-read');

                const dot = card.querySelector('.notif-dot');

                if (dot) {
                    dot.classList.add('read');
                }

                updateNotificationUI();
            }

            postNotificationAction({
                notification_action: 'mark_read',
                notif_id: notifId,
                source: source
            }).catch(err => console.error(err));
        }
    });

    updateNotificationUI();
});
</script>
