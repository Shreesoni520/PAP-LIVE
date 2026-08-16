<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Lisbon');

include './config.php';
include './log.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$error  = '';

/* =========================================================
   CHECK ADMIN DIRECTLY FROM DATABASE
   This avoids problems when $_SESSION['is_admin'] is missing
========================================================= */
$isAdmin = false;

$stmtLoggedUser = $conn->prepare("
    SELECT is_admin
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmtLoggedUser) {
    $stmtLoggedUser->bind_param("i", $userId);
    $stmtLoggedUser->execute();
    $loggedUser = $stmtLoggedUser->get_result()->fetch_assoc();
    $stmtLoggedUser->close();

    if ($loggedUser) {
        $isAdmin = ((int)$loggedUser['is_admin'] === 1);
        $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
    }
}

$id = 0;
if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
}

$notifId = isset($_GET['notif_id']) ? (int)$_GET['notif_id'] : 0;

$intervencoes  = ['Corte', 'Poda'];
$valid_species = ['Carvalho', 'Oliveira', 'Pinheiro', 'Plátano', 'Jacarandá', 'Loureiro'];

function isEstadoConcluido(?string $estado): bool {
    $estado = trim((string)$estado);
    return in_array(mb_strtolower($estado), ['concluída', 'concluido', 'concluído'], true);
}

/* =========================================================
   AUTOMATIC REMINDER EVERY 5 DAYS
========================================================= */
function runAutomaticTreeReminders(mysqli $conn): void {
    $sql = "
        SELECT 
            id,
            especie,
            place_name,
            tipo_intervencao,
            estado,
            assigned_to_user_id,
            assigned_by_user_id,
            next_reminder_at,
            reminder_every_days
        FROM arvores
        WHERE assigned_to_user_id IS NOT NULL
          AND next_reminder_at IS NOT NULL
          AND next_reminder_at <= NOW()
        LIMIT 100
    ";

    $res = $conn->query($sql);

    if (!$res) {
        return;
    }

    while ($tree = $res->fetch_assoc()) {
        $treeId = (int)$tree['id'];
        $estadoAtual = trim((string)($tree['estado'] ?? ''));

        if (isEstadoConcluido($estadoAtual)) {
            $stmtStop = $conn->prepare("
                UPDATE arvores
                SET reminder_every_days = NULL,
                    next_reminder_at = NULL,
                    last_reminder_at = NULL
                WHERE id = ?
            ");

            if ($stmtStop) {
                $stmtStop->bind_param("i", $treeId);
                $stmtStop->execute();
                $stmtStop->close();
            }

            continue;
        }

        $assignedUserId  = (int)$tree['assigned_to_user_id'];
        $createdByUserId = !empty($tree['assigned_by_user_id']) ? (int)$tree['assigned_by_user_id'] : 0;

        $days = !empty($tree['reminder_every_days']) ? (int)$tree['reminder_every_days'] : 5;
        if ($days <= 0) {
            $days = 5;
        }

        $tituloNotif = "Lembrete de intervenção no espaço verde";

        $mensagemNotif = "Lembrete automático: ainda existe uma intervenção pendente no espaço verde: ";
        $mensagemNotif .= ($tree['place_name'] ?? 'Espaço verde') . ". ";
        $mensagemNotif .= "Espécie: " . ($tree['especie'] ?? 'Árvore') . ". ";
        $mensagemNotif .= "Intervenção: " . (($tree['tipo_intervencao'] ?? '') ?: 'Nenhuma') . ". ";
        $mensagemNotif .= "Por favor, verifique a situação e atualize o estado da tarefa.";

        $stmtNotif = $conn->prepare("
            INSERT INTO notificacoes
            (user_id, created_by_user_id, origem_tipo, origem_id, titulo, mensagem, enviar_em, lida, criada_em)
            VALUES (?, ?, 'arvore', ?, ?, ?, NULL, 0, NOW())
        ");

        if ($stmtNotif) {
            $stmtNotif->bind_param(
                "iiiss",
                $assignedUserId,
                $createdByUserId,
                $treeId,
                $tituloNotif,
                $mensagemNotif
            );
            $stmtNotif->execute();
            $stmtNotif->close();
        }

        $next = new DateTime($tree['next_reminder_at']);
        $now = new DateTime();

        do {
            $next->modify("+{$days} days");
        } while ($next <= $now);

        $nextReminderAt = $next->format('Y-m-d H:i:s');

        $stmtNext = $conn->prepare("
            UPDATE arvores
            SET last_reminder_at = NOW(),
                next_reminder_at = ?
            WHERE id = ?
        ");

        if ($stmtNext) {
            $stmtNext->bind_param("si", $nextReminderAt, $treeId);
            $stmtNext->execute();
            $stmtNext->close();
        }
    }
}

/* =========================================================
   HELPERS - ADMIN SINGLE NOTIFICATION
========================================================= */
function getUserDisplayName(mysqli $conn, int $userId): string {
    if ($userId <= 0) {
        return 'Sistema';
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(NULLIF(name, ''), username) AS display_name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return 'Utilizador';
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['display_name']) ? $row['display_name'] : 'Utilizador';
}

function getLatestTreeReport(mysqli $conn, int $treeId): string {
    $stmt = $conn->prepare("
        SELECT mensagem
        FROM arvore_relatorios
        WHERE arvore_id = ?
        ORDER BY criado_em DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param("i", $treeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return trim((string)($row['mensagem'] ?? ''));
}

function upsertSingleTreeNotification(
    mysqli $conn,
    int $adminId,
    int $createdByUserId,
    int $treeId,
    string $mensagem
): void {
    $titulo = 'Atualização da intervenção no espaço verde';

    $stmtClean = $conn->prepare("
        DELETE FROM notificacoes
        WHERE user_id = ?
          AND origem_id = ?
          AND lida = 0
          AND (
                origem_tipo = 'arvore_relatorio'
                OR titulo IN (
                    'Intervenção atualizada',
                    'Intervenção concluída',
                    'Novo relatório do funcionário'
                )
          )
    ");

    if ($stmtClean) {
        $stmtClean->bind_param("ii", $adminId, $treeId);
        $stmtClean->execute();
        $stmtClean->close();
    }

    $stmtFind = $conn->prepare("
        SELECT id
        FROM notificacoes
        WHERE user_id = ?
          AND origem_tipo = 'arvore'
          AND origem_id = ?
          AND titulo = ?
          AND lida = 0
        LIMIT 1
    ");

    $existingId = 0;

    if ($stmtFind) {
        $stmtFind->bind_param("iis", $adminId, $treeId, $titulo);
        $stmtFind->execute();
        $existing = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($existing) {
            $existingId = (int)$existing['id'];
        }
    }

    if ($existingId > 0) {
        $stmtUpdate = $conn->prepare("
            UPDATE notificacoes
            SET created_by_user_id = ?,
                mensagem = ?,
                enviar_em = NULL,
                criada_em = NOW()
            WHERE id = ?
        ");

        if ($stmtUpdate) {
            $stmtUpdate->bind_param("isi", $createdByUserId, $mensagem, $existingId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    } else {
        $stmtInsert = $conn->prepare("
            INSERT INTO notificacoes
            (user_id, created_by_user_id, origem_tipo, origem_id, titulo, mensagem, enviar_em, lida, criada_em)
            VALUES (?, ?, 'arvore', ?, ?, ?, NULL, 0, NOW())
        ");

        if ($stmtInsert) {
            $stmtInsert->bind_param("iiiss", $adminId, $createdByUserId, $treeId, $titulo, $mensagem);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }
}

function notifyAdminsTreeSingleUpdate(
    mysqli $conn,
    int $createdByUserId,
    array $tree,
    string $estadoAtual,
    ?string $reportMessage = null,
    bool $isConclusion = false
): void {
    $treeId = (int)($tree['id'] ?? 0);

    if ($treeId <= 0) {
        return;
    }

    $placeName = trim((string)($tree['place_name'] ?? 'Espaço verde'));
    $especie = trim((string)($tree['especie'] ?? 'Árvore'));

    $tipoIntervencao = trim((string)($tree['tipo_intervencao'] ?? ''));
    if ($tipoIntervencao === '') {
        $tipoIntervencao = 'Nenhuma';
    }

    $assignedUserId = !empty($tree['assigned_to_user_id']) ? (int)$tree['assigned_to_user_id'] : 0;
    $funcionarioNome = $assignedUserId > 0 ? getUserDisplayName($conn, $assignedUserId) : 'Não atribuído';
    $updatedBy = getUserDisplayName($conn, $createdByUserId);

    $latestReport = trim((string)$reportMessage);

    if ($latestReport === '') {
        $latestReport = getLatestTreeReport($conn, $treeId);
    }

    $scheduledText = '';
    if (!empty($tree['scheduled_for'])) {
        $scheduledText = date('d/m/Y H:i', strtotime($tree['scheduled_for']));
    }

    $mensagemFuncionarioTree = trim((string)($tree['mensagem_funcionario'] ?? ''));

    $mensagemNotif  = "Atualização da intervenção no espaço verde.\n\n";
    $mensagemNotif .= "Espaço: {$placeName}\n";
    $mensagemNotif .= "Espécie: {$especie}\n";
    $mensagemNotif .= "Tipo de intervenção: {$tipoIntervencao}\n";
    $mensagemNotif .= "Estado atual: {$estadoAtual}\n";
    $mensagemNotif .= "Funcionário responsável: {$funcionarioNome}\n";

    if ($scheduledText !== '') {
        $mensagemNotif .= "Data/Hora do trabalho: {$scheduledText}\n";
    }

    if ($mensagemFuncionarioTree !== '') {
        $mensagemNotif .= "\nMensagem enviada ao funcionário:\n{$mensagemFuncionarioTree}\n";
    }

    if ($latestReport !== '') {
        $mensagemNotif .= "\nRelatório/Situação encontrada:\n{$latestReport}\n";
    } else {
        $mensagemNotif .= "\nRelatório/Situação encontrada: ainda sem relatório.\n";
    }

    if ($isConclusion) {
        $mensagemNotif .= "\nResultado: intervenção marcada como concluída.\n";
    }

    $mensagemNotif .= "\nAtualizado por: {$updatedBy}.\n";
    $mensagemNotif .= "Clique para abrir os detalhes desta intervenção.";

    $adminsRes = $conn->query("
        SELECT id
        FROM users
        WHERE is_admin = 1
    ");

    if (!$adminsRes) {
        return;
    }

    while ($admin = $adminsRes->fetch_assoc()) {
        $adminId = (int)$admin['id'];

        if ($adminId === $createdByUserId) {
            continue;
        }

        upsertSingleTreeNotification(
            $conn,
            $adminId,
            $createdByUserId,
            $treeId,
            $mensagemNotif
        );
    }
}

/* =========================================================
   LOAD STATES AND WORKERS
========================================================= */
$tarefas = [];
$tarefas_sql = $conn->query("SELECT name FROM states ORDER BY name ASC");
if ($tarefas_sql && $tarefas_sql->num_rows > 0) {
    while ($row = $tarefas_sql->fetch_assoc()) {
        if (!empty($row['name'])) {
            $tarefas[] = $row['name'];
        }
    }
}

$workers = [];
if ($isAdmin) {
    $workersRes = $conn->query("
        SELECT id, COALESCE(NULLIF(name, ''), username) AS display_name
        FROM users
        WHERE is_admin = 0
        ORDER BY display_name ASC
    ");

    if ($workersRes) {
        while ($w = $workersRes->fetch_assoc()) {
            $workers[] = $w;
        }
    }
}

runAutomaticTreeReminders($conn);

/* =========================================================
   MARK NOTIFICATION AS READ
========================================================= */
if ($notifId > 0) {
    $stmtMarkNotif = $conn->prepare("
        UPDATE notificacoes
        SET lida = 1
        WHERE id = ?
          AND user_id = ?
          AND (enviar_em IS NULL OR enviar_em <= NOW())
    ");

    if ($stmtMarkNotif) {
        $stmtMarkNotif->bind_param("ii", $notifId, $userId);
        $stmtMarkNotif->execute();
        $stmtMarkNotif->close();
    }
}

/* =========================================================
   ASSIGN SELECTED TREES + SEND ONLY ONE NOTIFICATION
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_selected']) && $isAdmin) {
    $selectedIds = isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $_POST['selected_ids']))))
        : [];

    $assignedUserId = isset($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : 0;

    $assignDate = trim($_POST['assign_date'] ?? '');
    $assignTime = trim($_POST['assign_time'] ?? '');

    $notifyDate = trim($_POST['notify_date'] ?? '');
    $notifyTime = trim($_POST['notify_time'] ?? '');

    $mensagemFuncionario = trim($_POST['mensagem_funcionario'] ?? '');

    $mensagemFuncionarioGuardar = $mensagemFuncionario !== ''
        ? $mensagemFuncionario
        : 'Deve verificar o local e cuidar da árvore.';

    $scheduledFor = null;
    if ($assignDate !== '' && $assignTime !== '') {
        $scheduledFor = $assignDate . ' ' . $assignTime . ':00';
    }

    $notifyAt = null;
    $notifyTimestamp = null;

    if ($notifyDate !== '' && $notifyTime !== '') {
        $notifyAt = $notifyDate . ' ' . $notifyTime . ':00';
        $notifyTimestamp = strtotime($notifyAt);
    }

    if (empty($selectedIds)) {
        $error = "Selecione pelo menos uma árvore.";
    } elseif ($assignedUserId <= 0) {
        $error = "Selecione um funcionário.";
    } elseif (($assignDate !== '' && $assignTime === '') || ($assignDate === '' && $assignTime !== '')) {
        $error = "Preencha a data e a hora da intervenção ou deixe ambos vazios.";
    } elseif (($notifyDate !== '' && $notifyTime === '') || ($notifyDate === '' && $notifyTime !== '')) {
        $error = "Preencha a data e a hora da notificação ou deixe ambos vazios.";
    } elseif ($notifyAt !== null && ($notifyTimestamp === false || $notifyTimestamp <= time())) {
        $error = "A data/hora da notificação deve ser futura. Se quiser enviar agora, deixe a data e a hora da notificação vazias.";
    } else {
        $nextReminderAt = $notifyAt
            ? date('Y-m-d H:i:s', strtotime($notifyAt . ' +5 days'))
            : date('Y-m-d H:i:s', strtotime('+5 days'));

        $stmtWorker = $conn->prepare("
            SELECT COALESCE(NULLIF(name, ''), username) AS display_name
            FROM users
            WHERE id = ? AND is_admin = 0
            LIMIT 1
        ");

        if (!$stmtWorker) {
            $error = "Erro ao verificar funcionário: " . $conn->error;
        } else {
            $stmtWorker->bind_param("i", $assignedUserId);
            $stmtWorker->execute();
            $workerRow = $stmtWorker->get_result()->fetch_assoc();
            $stmtWorker->close();

            if (!$workerRow) {
                $error = "Funcionário inválido.";
            } else {
                $conn->begin_transaction();

                try {
                    $stmtUpdate = $conn->prepare("
                        UPDATE arvores
                        SET assigned_to_user_id = ?,
                            assigned_by_user_id = ?,
                            scheduled_for = ?,
                            mensagem_funcionario = ?,
                            assigned_at = NOW(),
                            notification_read = 1,
                            reminder_every_days = 5,
                            next_reminder_at = ?,
                            last_reminder_at = NULL
                        WHERE id = ?
                    ");

                    if (!$stmtUpdate) {
                        throw new Exception("Erro ao preparar atualização da árvore: " . $conn->error);
                    }

                    $stmtTree = $conn->prepare("
                        SELECT id, especie, place_name, tipo_intervencao, estado
                        FROM arvores
                        WHERE id = ?
                        LIMIT 1
                    ");

                    if (!$stmtTree) {
                        throw new Exception("Erro ao preparar consulta da árvore: " . $conn->error);
                    }

                    $linhasTarefas = [];
                    $firstTreeId = (int)$selectedIds[0];

                    foreach ($selectedIds as $treeId) {
                        $stmtUpdate->bind_param(
                            "iisssi",
                            $assignedUserId,
                            $userId,
                            $scheduledFor,
                            $mensagemFuncionarioGuardar,
                            $nextReminderAt,
                            $treeId
                        );

                        if (!$stmtUpdate->execute()) {
                            throw new Exception("Erro ao atribuir árvore ID {$treeId}: " . $stmtUpdate->error);
                        }

                        $stmtDeleteOldNotif = $conn->prepare("
                            DELETE FROM notificacoes
                            WHERE user_id = ?
                              AND origem_tipo = 'arvore'
                              AND origem_id = ?
                              AND lida = 0
                              AND titulo IN (
                                  'Nova intervenção atribuída',
                                  'Nova tarefa no espaço verde',
                                  'Novas tarefas no espaço verde'
                              )
                        ");

                        if ($stmtDeleteOldNotif) {
                            $stmtDeleteOldNotif->bind_param("ii", $assignedUserId, $treeId);
                            $stmtDeleteOldNotif->execute();
                            $stmtDeleteOldNotif->close();
                        }

                        $stmtTree->bind_param("i", $treeId);
                        $stmtTree->execute();
                        $treeInfo = $stmtTree->get_result()->fetch_assoc();

                        if (!$treeInfo) {
                            continue;
                        }

                        $especie = $treeInfo['especie'] ?? 'Árvore';
                        $placeName = $treeInfo['place_name'] ?? 'Espaço verde';
                        $tipoIntervencao = !empty($treeInfo['tipo_intervencao']) ? $treeInfo['tipo_intervencao'] : 'Nenhuma';
                        $estado = !empty($treeInfo['estado']) ? $treeInfo['estado'] : 'Tarefa pendente';

                        $numero = count($linhasTarefas) + 1;

                        $linhasTarefas[] =
                            "{$numero}) Espaço: {$placeName}. " .
                            "Espécie: {$especie}. " .
                            "Intervenção: {$tipoIntervencao}. " .
                            "Tarefa: {$estado}.";

                        regista_log(
                            $conn,
                            $userId,
                            "editar",
                            "intervencao_arvore",
                            $treeId,
                            "Intervenção atribuída ao funcionário"
                        );
                    }

                    if (empty($linhasTarefas)) {
                        throw new Exception("Nenhuma árvore válida foi encontrada.");
                    }

                    $quantidade = count($linhasTarefas);

                    if ($quantidade === 1) {
                        $tituloNotif = "Nova tarefa no espaço verde";
                        $mensagemNotif = "Foi-lhe atribuída 1 tarefa no espaço verde.\n\n";
                    } else {
                        $tituloNotif = "Novas tarefas no espaço verde";
                        $mensagemNotif = "Foram-lhe atribuídas {$quantidade} tarefas no espaço verde.\n\n";
                    }

                    if ($scheduledFor) {
                        $mensagemNotif .= "Data/Hora para realizar o trabalho: " . date('d/m/Y H:i', strtotime($scheduledFor)) . ".\n\n";
                    }

                    if ($notifyAt) {
                        $mensagemNotif .= "Data/Hora para receber esta notificação: " . date('d/m/Y H:i', strtotime($notifyAt)) . ".\n\n";
                    }

                    $mensagemNotif .= "Tarefas atribuídas:\n";
                    $mensagemNotif .= implode("\n", $linhasTarefas);
                    $mensagemNotif .= "\n\n";

                    $mensagemNotif .= "Mensagem do administrador:\n{$mensagemFuncionarioGuardar}\n\n";

                    $mensagemNotif .= "O sistema irá enviar um lembrete automático a cada 5 dias até a intervenção ser marcada como concluída.";

                    $stmtInsertOneNotif = $conn->prepare("
                        INSERT INTO notificacoes
                        (user_id, created_by_user_id, origem_tipo, origem_id, titulo, mensagem, enviar_em, lida, criada_em)
                        VALUES (?, ?, 'arvore', ?, ?, ?, ?, 0, NOW())
                    ");

                    if (!$stmtInsertOneNotif) {
                        throw new Exception("Erro ao preparar notificação única: " . $conn->error);
                    }

                    $stmtInsertOneNotif->bind_param(
                        "iiisss",
                        $assignedUserId,
                        $userId,
                        $firstTreeId,
                        $tituloNotif,
                        $mensagemNotif,
                        $notifyAt
                    );

                    if (!$stmtInsertOneNotif->execute()) {
                        throw new Exception("Erro ao criar notificação única: " . $stmtInsertOneNotif->error);
                    }

                    $stmtInsertOneNotif->close();
                    $stmtUpdate->close();
                    $stmtTree->close();

                    $conn->commit();

                    header("Location: index.php?evora=editarintervencaoarvore&assigned=1");
                    exit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = "Erro ao atribuir intervenções: " . $e->getMessage();
                }
            }
        }
    }
}

/* =========================================================
   FUNCIONÁRIO SEND REPORT TO ADMIN
   Fixed: this separate button also saves the current tarefa
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_tree_report']) && $id > 0) {
    $reportMessage = trim($_POST['report_message'] ?? '');
    $tarefaFromReportForm = trim($_POST['tarefa_from_report_form'] ?? '');

    if ($reportMessage === '') {
        $error = "Escreva uma mensagem antes de enviar o relatório.";
    } else {
        $stmt = $conn->prepare("
            SELECT *
            FROM arvores
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $treeReport = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$treeReport) {
            $error = "Árvore não encontrada.";
        } elseif ((int)$treeReport['assigned_to_user_id'] !== $userId) {
            $error = "Só o funcionário responsável pode enviar relatório desta intervenção.";
        } elseif ($tarefaFromReportForm !== '' && !in_array($tarefaFromReportForm, $tarefas, true)) {
            $error = "Tarefa inválida.";
        } else {
            $reportMessage = mb_substr($reportMessage, 0, 1500);

            $estadoAnterior = trim((string)($treeReport['estado'] ?? ''));
            $estadoFinal = $tarefaFromReportForm !== '' ? $tarefaFromReportForm : $estadoAnterior;
            $isConclusao = isEstadoConcluido($estadoFinal);
            $wasAlreadyDone = isEstadoConcluido($estadoAnterior);

            $conn->begin_transaction();

            try {
                if ($tarefaFromReportForm !== '' && $tarefaFromReportForm !== $estadoAnterior) {
                    if ($isConclusao) {
                        $stmtUpdTarefa = $conn->prepare("
                            UPDATE arvores
                            SET estado = ?,
                                completed_by_user_id = ?,
                                completed_at = NOW(),
                                reminder_every_days = NULL,
                                next_reminder_at = NULL,
                                last_reminder_at = NULL
                            WHERE id = ?
                        ");

                        if (!$stmtUpdTarefa) {
                            throw new Exception("Erro ao preparar atualização da tarefa: " . $conn->error);
                        }

                        $stmtUpdTarefa->bind_param("sii", $estadoFinal, $userId, $id);
                    } else {
                        $stmtUpdTarefa = $conn->prepare("
                            UPDATE arvores
                            SET estado = ?,
                                completed_by_user_id = NULL,
                                completed_at = NULL
                            WHERE id = ?
                        ");

                        if (!$stmtUpdTarefa) {
                            throw new Exception("Erro ao preparar atualização da tarefa: " . $conn->error);
                        }

                        $stmtUpdTarefa->bind_param("si", $estadoFinal, $id);
                    }

                    if (!$stmtUpdTarefa->execute()) {
                        throw new Exception("Erro ao atualizar tarefa: " . $stmtUpdTarefa->error);
                    }

                    $stmtUpdTarefa->close();

                    regista_log(
                        $conn,
                        $userId,
                        "editar",
                        "intervencao_arvore",
                        $id,
                        "Tarefa atualizada para: {$estadoFinal} juntamente com o relatório"
                    );
                }

                $stmtSaveReport = $conn->prepare("
                    INSERT INTO arvore_relatorios
                    (arvore_id, funcionario_id, mensagem, criado_em)
                    VALUES (?, ?, ?, NOW())
                ");

                if (!$stmtSaveReport) {
                    throw new Exception("Erro ao preparar relatório: " . $conn->error);
                }

                $stmtSaveReport->bind_param("iis", $id, $userId, $reportMessage);

                if (!$stmtSaveReport->execute()) {
                    throw new Exception("Erro ao guardar relatório: " . $stmtSaveReport->error);
                }

                $stmtSaveReport->close();

                regista_log(
                    $conn,
                    $userId,
                    "mensagem",
                    "intervencao_arvore",
                    $id,
                    "Funcionário enviou relatório ao administrador"
                );

                $conn->commit();

                $treeReport['estado'] = $estadoFinal;

                if ($isConclusao) {
                    $treeReport['completed_by_user_id'] = $userId;
                    $treeReport['completed_at'] = date('Y-m-d H:i:s');
                }

                notifyAdminsTreeSingleUpdate(
                    $conn,
                    $userId,
                    $treeReport,
                    $estadoFinal !== '' ? $estadoFinal : '—',
                    $reportMessage,
                    $isConclusao && !$wasAlreadyDone
                );

                header("Location: index.php?evora=editarintervencaoarvore&id={$id}&saved=1&report_sent=1");
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Erro ao enviar relatório: " . $e->getMessage();
            }
        }
    }
}

/* =========================================================
   UPDATE DETAIL
   Fixed: Guardar Alterações can also save report_message
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_intervencao']) && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM arvores WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $tree = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tree) {
        $error = "Árvore não encontrada.";
    } else {
        $canEdit = $isAdmin || ((int)$tree['assigned_to_user_id'] === $userId);

        if (!$canEdit) {
            $error = "Não tem permissão para editar esta intervenção.";
        } else {
            $tarefa = trim($_POST['tarefa'] ?? '');

            if ($tarefa === '') {
                $error = "A tarefa é obrigatória.";
            } elseif (!in_array($tarefa, $tarefas, true)) {
                $error = "Tarefa inválida.";
            } else {
                $reportMessageFromSave = '';

                if (!$isAdmin && (int)$tree['assigned_to_user_id'] === $userId) {
                    $reportMessageFromSave = trim($_POST['report_message'] ?? '');

                    if ($reportMessageFromSave !== '') {
                        $reportMessageFromSave = mb_substr($reportMessageFromSave, 0, 1500);
                    }
                }

                $isConclusao = isEstadoConcluido($tarefa);
                $wasAlreadyDone = isEstadoConcluido($tree['estado'] ?? '');

                if ($isConclusao) {
                    $stmtUpd = $conn->prepare("
                        UPDATE arvores
                        SET estado = ?,
                            completed_by_user_id = ?,
                            completed_at = NOW(),
                            reminder_every_days = NULL,
                            next_reminder_at = NULL,
                            last_reminder_at = NULL
                        WHERE id = ?
                    ");

                    if ($stmtUpd) {
                        $stmtUpd->bind_param("sii", $tarefa, $userId, $id);
                    }
                } else {
                    $stmtUpd = $conn->prepare("
                        UPDATE arvores
                        SET estado = ?,
                            completed_by_user_id = NULL,
                            completed_at = NULL
                        WHERE id = ?
                    ");

                    if ($stmtUpd) {
                        $stmtUpd->bind_param("si", $tarefa, $id);
                    }
                }

                if (!$stmtUpd) {
                    $error = "Erro ao preparar atualização: " . $conn->error;
                } elseif ($stmtUpd->execute()) {
                    regista_log(
                        $conn,
                        $userId,
                        "editar",
                        "intervencao_arvore",
                        $id,
                        "Tarefa atualizada para: $tarefa"
                    );

                    $reportWasSaved = false;

                    if ($reportMessageFromSave !== '') {
                        $stmtSaveReport = $conn->prepare("
                            INSERT INTO arvore_relatorios
                            (arvore_id, funcionario_id, mensagem, criado_em)
                            VALUES (?, ?, ?, NOW())
                        ");

                        if ($stmtSaveReport) {
                            $stmtSaveReport->bind_param("iis", $id, $userId, $reportMessageFromSave);
                            $stmtSaveReport->execute();
                            $stmtSaveReport->close();

                            $reportWasSaved = true;

                            regista_log(
                                $conn,
                                $userId,
                                "mensagem",
                                "intervencao_arvore",
                                $id,
                                "Funcionário enviou relatório juntamente com a atualização da tarefa"
                            );
                        }
                    }

                    if (!$isAdmin && (int)$tree['assigned_to_user_id'] === $userId) {
                        $tree['estado'] = $tarefa;

                        if ($isConclusao) {
                            $tree['completed_by_user_id'] = $userId;
                            $tree['completed_at'] = date('Y-m-d H:i:s');
                        }

                        if ($reportWasSaved || ($isConclusao && !$wasAlreadyDone)) {
                            notifyAdminsTreeSingleUpdate(
                                $conn,
                                $userId,
                                $tree,
                                $tarefa,
                                $reportWasSaved ? $reportMessageFromSave : null,
                                $isConclusao
                            );
                        }
                    }

                    $stmtUpd->close();

                    $extraReport = !empty($reportWasSaved) ? '&report_sent=1' : '';
                    header("Location: index.php?evora=editarintervencaoarvore&id={$id}&saved=1{$extraReport}");
                    exit();
                } else {
                    $error = "Erro ao atualizar intervenção: " . $stmtUpd->error;
                    $stmtUpd->close();
                }
            }
        }
    }
}

/* =========================================================
   LIST PAGE
========================================================= */
if ($id <= 0) {
    $whereSql = '';

    if (!$isAdmin) {
        $whereSql = "WHERE a.assigned_to_user_id = {$userId}";
    }

    $sqlArvores = "
        SELECT a.*,
               u1.username AS assigned_to_username,
               u1.name AS assigned_to_name
        FROM arvores a
        LEFT JOIN users u1 ON u1.id = a.assigned_to_user_id
        {$whereSql}
        ORDER BY
            CASE WHEN a.assigned_to_user_id IS NULL THEN 0 ELSE 1 END ASC,
            a.criado_em DESC,
            a.scheduled_for ASC
    ";

    $arvores = $conn->query($sqlArvores);
    $per_page = 6;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Intervenção - Espaço Verdes</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

        <style>
            body, .sidebar, .card, .btn, h4, h3, h2 { font-family: 'Nunito', sans-serif !important; }
            .page-content { background: linear-gradient(135deg, #eef2ff, #f9fafb); }
            .card-main { border-radius: 20px; border: 0; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12); }
            .card-header-flex { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
            .card-header { border-bottom: 1px solid #e5e7eb; }
            #openFilterPanel, #toggleSelectMode, #openAssignModalBtn { border-radius: 999px; padding: 0.4rem 1rem; font-size: 0.9rem; }
            .filter-panel { position: absolute; top: 72px; right: 24px; width: 320px; background: #ffffff; padding: 22px 18px 16px 18px; border-radius: 18px; z-index: 1001; border: 1px solid #e5e7eb; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18); }
            .filter-panel .form-label { font-size: 0.8rem; font-weight: 600; color: #6b7280; }
            .filter-panel .form-control, .filter-panel .form-select { font-size: 0.85rem; border-radius: 10px; }
            .filter-panel-actions { padding-top: 8px; display: flex; justify-content: flex-end; gap: 0.5rem; }
            .especie-wrapper { position: relative; }
            #especieList { position: absolute; top: 100%; left: 0; right: 0; max-height: 180px; overflow-y: auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; margin-top: 4px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.16); list-style: none; padding: 4px 0; z-index: 1100; display: none; }
            #especieList li { padding: 6px 10px; font-size: 0.85rem; cursor: pointer; }
            #especieList li:hover { background: #f3f4f6; }
            .tree-card-container { display: flex; margin-bottom: 16px; }
            .tree-card { border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08); overflow: hidden; background-color: #ffffff; display: flex; flex-direction: column; width: 100%; transition: transform 0.15s ease, box-shadow 0.15s ease; }
            .tree-card:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12); }
            .tree-card-body { padding: 0.9rem 1.1rem 0.6rem 1.1rem; flex-grow: 1; display: flex; flex-direction: column; gap: 0.15rem; }
            .tree-card-footer { background-color: #f9fafb; padding: 0.45rem 1.1rem 0.55rem 1.1rem; font-size: 0.78rem; color: #6b7280; border-top: 1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap: nowrap; }
            .tree-title { font-weight: 800; font-size: 1rem; margin-bottom: 0.15rem; color: #111827; }
            .tree-label { font-weight: 600; font-size: 13px; color: #6b7280; }
            .tree-value { font-size: 14px; color: #111827; }
            .tree-line { margin-bottom: 0.15rem; }
            .status-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 0.25rem 0.7rem; font-size: 0.75rem; font-weight: 700; }
            .status-done { background: #dcfce7; color: #166534; }
            .status-pending { background: #fef3c7; color: #92400e; }
            .multi-checkbox-wrapper { display: none; margin-left: 10px; flex-shrink: 0; }
            .select-mode-on .multi-checkbox-wrapper { display: block; }
            .footer-actions-inline { display: flex; align-items: center; gap: 10px; margin-left: auto; flex-wrap: nowrap; }
            .ts-wrapper .ts-control { background-color: #ffffff !important; border-radius: 10px; padding: 0.375rem 0.75rem; border-color: #d1d5db; }
            .ts-wrapper .ts-control:focus { box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25); border-color: #2563eb; }
            .ts-dropdown { background-color: #ffffff !important; }
            .quick-status-btn.active { font-weight: 700; box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12); }
            .message-help-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 0.75rem; font-size: 0.82rem; color: #64748b; }
            @media (max-width: 768px) { .filter-panel { position: fixed; top: 88px; right: 12px; width: 260px; max-width: 80%; padding: 14px 12px 10px 12px; border-radius: 14px; } .card-header-flex { flex-direction: column; align-items: flex-start; gap: 0.6rem; } }
            @media (max-width: 992px) { #main { margin-left: 0 !important; } .sidebar-wrapper { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1050; } #app { overflow-x: hidden; } }
        </style>
    </head>
    <body>
    <div id="app">
        <?php include "menu.php"; ?>
        <div id="main">
            <header class="mb-3 d-flex align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none me-2"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading-custom mb-3">
                <h3 class="mb-1"><?= $isAdmin ? 'Gerir intervenções de árvores' : 'As minhas intervenções de árvores' ?></h3>
                <p class="text-subtitle text-muted mb-0"><?= $isAdmin ? 'Selecione uma ou várias árvores e atribua ao funcionário.' : 'Veja apenas as intervenções atribuídas a si.' ?></p>
            </div>

            <div class="page-content">
                <section class="section">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Árvores disponíveis</h4>
                                    <small class="text-muted">Use os filtros ou o modo seleção.</small>
                                </div>
                                <div class="d-flex justify-content-end flex-grow-1 gap-2 flex-wrap">
                                    <?php if ($isAdmin): ?>
                                        <button id="toggleSelectMode" class="btn btn-outline-secondary d-flex align-items-center" type="button"><i class="bi bi-check2-square me-1"></i><span>Modo seleção</span></button>
                                        <button id="openAssignModalBtn" class="btn btn-primary d-none" type="button" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-send me-1"></i><span>Atribuir selecionadas</span></button>
                                    <?php endif; ?>
                                    <button id="openFilterPanel" class="btn btn-outline-secondary d-flex align-items-center" type="button"><i class="bi bi-funnel-fill me-1"></i><span>Filtrar</span></button>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($_GET['assigned'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Intervenções atribuídas, notificação única criada e lembrete automático ativado.</div></div><?php endif; ?>
                        <?php if (!empty($_GET['saved'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Intervenção atualizada com sucesso.</div></div><?php endif; ?>
                        <?php if (!empty($_GET['report_sent'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Relatório enviado ao administrador com sucesso.</div></div><?php endif; ?>
                        <?php if ($error): ?><div class="px-3 pt-3"><div class="alert alert-danger mb-0"><?= htmlspecialchars($error) ?></div></div><?php endif; ?>

                        <div id="filterPanel" class="filter-panel d-none">
                            <form id="treeFilterForm">
                                <div class="mb-2 especie-wrapper">
                                    <label for="filterEspecie" class="form-label">Espécie</label>
                                    <input type="text" class="form-control" id="filterEspecie" placeholder="Nome da espécie">
                                    <ul id="especieList"></ul>
                                </div>
                                <div class="mb-2">
                                    <label for="filterTarefa" class="form-label">Tarefa</label>
                                    <select id="filterTarefa" class="form-select js-nice-select"><option value="">Todas</option><?php foreach ($tarefas as $tarefa_opt): ?><option value="<?= htmlspecialchars(strtolower($tarefa_opt)) ?>"><?= htmlspecialchars($tarefa_opt) ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="mb-2">
                                    <label for="filterTipoIntervencao" class="form-label">Tipo de Intervenção</label>
                                    <select id="filterTipoIntervencao" class="form-select js-nice-select"><option value="">Todos</option><?php foreach ($intervencoes as $interv): ?><option value="<?= htmlspecialchars(strtolower($interv)) ?>"><?= htmlspecialchars($interv) ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Estado rápido</label>
                                    <div class="d-flex gap-2 flex-wrap" id="statusQuickButtons">
                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-status-btn active" data-status="">Todos</button>
                                        <button type="button" class="btn btn-sm btn-outline-success quick-status-btn" data-status="feito">Feitos</button>
                                        <button type="button" class="btn btn-sm btn-outline-warning quick-status-btn" data-status="pendente">Pendentes</button>
                                    </div>
                                    <input type="hidden" id="filterStatus" value="">
                                </div>
                                <div class="filter-panel-actions">
                                    <button type="button" class="btn btn-light btn-sm" id="clearFilters">Limpar</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                                </div>
                            </form>
                        </div>

                        <div class="card-body pt-3">
                            <?php if ($arvores && $arvores->num_rows > 0): ?>
                                <div class="container-fluid"><div class="row" id="treeList">
                                <?php while ($tree = $arvores->fetch_assoc()): ?>
                                    <?php
                                        $criadoText = !empty($tree['criado_em']) ? date('Y-m-d H:i', strtotime($tree['criado_em'])) : '—';
                                        $scheduledText = !empty($tree['scheduled_for']) ? date('Y-m-d H:i', strtotime($tree['scheduled_for'])) : '';
                                        $assignedName = !empty($tree['assigned_to_name']) ? $tree['assigned_to_name'] : ($tree['assigned_to_username'] ?? '');
                                        $estadoAtual = trim((string)($tree['estado'] ?? ''));
                                        $isDone = isEstadoConcluido($estadoAtual);
                                    ?>
                                    <div class="col-12 col-md-6 col-xl-4 tree-card-container">
                                        <div class="tree-card">
                                            <div class="tree-card-body">
                                                <div class="d-flex align-items-start justify-content-between gap-2">
                                                    <div class="tree-title tree-search-especie"><?= htmlspecialchars($tree['especie']) ?></div>
                                                    <?php if ($isDone): ?><span class="status-chip status-done tree-status-search">Feito</span><?php else: ?><span class="status-chip status-pending tree-status-search">Pendente</span><?php endif; ?>
                                                </div>
                                                <div class="tree-line"><span class="tree-label">Nome do Espaço:</span> <span class="tree-value"><?= htmlspecialchars($tree['place_name']) ?></span></div>
                                                <div class="tree-line"><span class="tree-label">Latitude/Longitude:</span> <span class="tree-value"><?= htmlspecialchars($tree['latitude']) ?>, <?= htmlspecialchars($tree['longitude']) ?></span></div>
                                                <div class="tree-line"><span class="tree-label">Tipo de Intervenção:</span> <span class="tree-value tree-search-tipo"><?= htmlspecialchars($tree['tipo_intervencao'] ?: 'Nenhuma') ?></span></div>
                                                <div class="tree-line"><span class="tree-label">Tarefa:</span> <span class="tree-value tree-tarefa"><?= htmlspecialchars($tree['estado'] ?: '—') ?></span></div>
                                                <?php if (!empty($assignedName)): ?><div class="tree-line"><span class="tree-label">Funcionário:</span> <span class="tree-value"><?= htmlspecialchars($assignedName) ?></span></div><?php else: ?><div class="tree-line"><span class="tree-label">Funcionário:</span> <span class="tree-value text-muted">Ainda não atribuído</span></div><?php endif; ?>
                                                <?php if (!empty($scheduledText)): ?><div class="tree-line"><span class="tree-label">Data/Hora:</span> <span class="tree-value"><?= htmlspecialchars($scheduledText) ?></span></div><?php endif; ?>
                                            </div>
                                            <div class="tree-card-footer">
                                                <span>Criado em: <?= htmlspecialchars($criadoText) ?></span>
                                                <div class="footer-actions-inline">
                                                    <form method="post" action="index.php?evora=editarintervencaoarvore" class="d-inline"><input type="hidden" name="id" value="<?= (int)$tree['id'] ?>"><button type="submit" class="btn btn-sm btn-primary">Editar</button></form>
                                                    <?php if ($isAdmin): ?><div class="form-check mb-0 multi-checkbox-wrapper"><input class="form-check-input tree-checkbox" type="checkbox" value="<?= (int)$tree['id'] ?>" id="treeChk<?= (int)$tree['id'] ?>"><label class="form-check-label" for="treeChk<?= (int)$tree['id'] ?>">Selecionar</label></div><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </div></div>
                                <nav id="paginationNav" aria-label="Paginação de árvores" class="mt-3 mb-1"><ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul></nav>
                            <?php else: ?>
                                <div class="alert alert-info text-center mb-0"><?= $isAdmin ? 'Nenhuma árvore registada.' : 'Não tem intervenções atribuídas.' ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px; border:0; box-shadow:0 18px 45px rgba(15,23,42,0.18);"><form method="post" id="assignForm">
            <div class="modal-header border-0 pb-2"><h5 class="modal-title">Enviar intervenção ao funcionário</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body pt-2">
                <div class="mb-3"><label class="form-label fw-semibold">Funcionário responsável</label><select name="assigned_to_user_id" class="form-select js-nice-select" required><option value="">Selecione o funcionário</option><?php foreach ($workers as $worker): ?><option value="<?= (int)$worker['id'] ?>"><?= htmlspecialchars($worker['display_name']) ?></option><?php endforeach; ?></select><small class="text-muted">Escolha quem vai receber esta tarefa.</small></div>
                <div class="alert alert-primary py-2 mb-3" style="font-size: 0.85rem;"><strong>1. Data/Hora para realizar o trabalho</strong><br>Esta data indica quando o funcionário deve ir ao local para verificar, podar, cortar, cuidar da árvore ou avaliar se é necessário plantar uma nova árvore.</div>
                <div class="row"><div class="col-6 mb-3"><label class="form-label fw-semibold">Data para realizar o trabalho</label><input type="date" name="assign_date" class="form-control"></div><div class="col-6 mb-3"><label class="form-label fw-semibold">Hora para realizar o trabalho</label><input type="time" name="assign_time" class="form-control"></div></div>
                <div class="alert alert-warning py-2 mb-3" style="font-size: 0.85rem;"><strong>2. Data/Hora para enviar a notificação</strong><br>Esta data indica quando a notificação deve aparecer para o funcionário. Se deixar vazio, a notificação aparece imediatamente.</div>
                <div class="row"><div class="col-6 mb-3"><label class="form-label fw-semibold">Data para enviar notificação</label><input type="date" name="notify_date" class="form-control"></div><div class="col-6 mb-3"><label class="form-label fw-semibold">Hora para enviar notificação</label><input type="time" name="notify_time" class="form-control"></div></div>
                <div class="alert alert-info py-2 mb-3" style="font-size: 0.85rem;"><strong>Lembrete automático:</strong><br>Depois da primeira notificação, o sistema irá enviar um novo lembrete ao funcionário a cada 5 dias, até a intervenção ser marcada como concluída.</div>
                <div class="mb-3"><label class="form-label fw-semibold">Mensagem para o funcionário</label><textarea name="mensagem_funcionario" class="form-control" rows="3" placeholder="Ex: Verificar esta árvore, fazer poda, avaliar o local ou plantar uma nova árvore."></textarea><small class="text-muted">Esta mensagem será enviada juntamente com a notificação e ficará visível nos detalhes da intervenção.</small></div>
                <div class="message-help-box mb-3"><strong>Exemplo:</strong><br>Trabalho: 30/04/2026 às 10:00<br>Notificação: 29/04/2026 às 18:00<br>Resultado: o funcionário recebe o aviso no dia 29/04 às 18:00 para realizar o trabalho no dia 30/04 às 10:00.</div>
                <div class="small text-muted mb-2" id="selectedSummaryModal">0 cartões selecionados.</div>
                <div id="selectedHiddenInputs"></div>
                <div class="small text-muted">Se deixar a data/hora da intervenção vazia, a intervenção fica disponível imediatamente.</div>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="assign_selected" class="btn btn-primary">Enviar intervenção</button></div>
        </form></div></div>
    </div>
    <?php endif; ?>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-nice-select').forEach(function (el) {
            if (!el.tomselect) {
                new TomSelect(el, { maxItems: 1, allowEmptyOption: true, create: false, plugins: { clear_button: { title: 'Limpar seleção' } } });
            }
        });

        const openFilterPanelBtn  = document.getElementById('openFilterPanel');
        const filterPanel         = document.getElementById('filterPanel');
        const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
        const clearFiltersBtn     = document.getElementById('clearFilters');
        const filterForm          = document.getElementById('treeFilterForm');
        const especieInput = document.getElementById('filterEspecie');
        const especieList  = document.getElementById('especieList');
        const filterStatusEl = document.getElementById('filterStatus');
        const quickStatusButtons = document.querySelectorAll('.quick-status-btn');
        const perPage         = <?= (int)$per_page ?>;
        const cards           = Array.from(document.querySelectorAll('.tree-card-container'));
        const paginationNav   = document.getElementById('paginationNav');
        const paginationLinks = document.getElementById('paginationLinks');
        let currentPage = 1;

        function buildEspecieList() {
            const especies = new Set();
            document.querySelectorAll('.tree-search-especie').forEach(function (el) { const name = el.textContent.trim(); if (name) especies.add(name); });
            especieList.innerHTML = '';
            Array.from(especies).sort().forEach(function (nome) {
                const li = document.createElement('li');
                li.textContent = nome;
                li.addEventListener('click', function () { especieInput.value = nome; especieList.style.display = 'none'; applyFilters(); });
                especieList.appendChild(li);
            });
        }
        function getFilteredCards() { return cards.filter(card => card.dataset.filtered !== 'true'); }
        function renderPagination(totalPages, page) {
            if (!paginationNav || !paginationLinks) return;
            if (totalPages <= 1) { paginationNav.style.display = 'none'; paginationLinks.innerHTML = ''; return; }
            paginationNav.style.display = '';
            let html = '';
            html += `<li class="page-item ${page === 1 ? 'disabled' : ''}"><a href="#" class="page-link" data-page="${page - 1}">« Anterior</a></li>`;
            for (let p = 1; p <= totalPages; p++) html += `<li class="page-item ${p === page ? 'active' : ''}"><a href="#" class="page-link" data-page="${p}">${p}</a></li>`;
            html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}"><a href="#" class="page-link" data-page="${page + 1}">Próxima »</a></li>`;
            paginationLinks.innerHTML = html;
        }
        function showPage(page) {
            const filteredCards = getFilteredCards();
            const totalVisible  = filteredCards.length;
            const totalPages    = Math.ceil(totalVisible / perPage) || 1;
            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;
            currentPage = page;
            cards.forEach(function (card) { card.style.display = 'none'; });
            const start = (page - 1) * perPage;
            const end   = start + perPage;
            filteredCards.forEach(function (card, index) { if (index >= start && index < end) card.style.display = ''; });
            renderPagination(totalPages, currentPage);
        }
        function applyFilters() {
            const especie = (document.getElementById('filterEspecie')?.value || '').trim().toLowerCase();
            const tarefa = (document.getElementById('filterTarefa')?.value || '').trim().toLowerCase();
            const tipoIntervencao = (document.getElementById('filterTipoIntervencao')?.value || '').trim().toLowerCase();
            const statusRapido = (document.getElementById('filterStatus')?.value || '').trim().toLowerCase();
            cards.forEach(function (card) {
                const textEspecie = (card.querySelector('.tree-search-especie')?.textContent || '').trim().toLowerCase();
                const textTarefa  = (card.querySelector('.tree-tarefa')?.textContent || '').trim().toLowerCase();
                const textTipo    = (card.querySelector('.tree-search-tipo')?.textContent || '').trim().toLowerCase();
                const textStatus  = (card.querySelector('.tree-status-search')?.textContent || '').trim().toLowerCase();
                const matchesEspecie = !especie || textEspecie.includes(especie);
                const matchesTarefa  = !tarefa || textTarefa.includes(tarefa);
                const matchesTipo    = !tipoIntervencao || textTipo.includes(tipoIntervencao);
                const matchesStatus  = !statusRapido || textStatus === statusRapido;
                card.dataset.filtered = (matchesEspecie && matchesTarefa && matchesTipo && matchesStatus) ? 'false' : 'true';
            });
            showPage(1);
        }
        openFilterPanelBtn?.addEventListener('click', function () { filterPanel?.classList.toggle('d-none'); });
        closeFilterPanelBtn?.addEventListener('click', function () { filterPanel?.classList.add('d-none'); });
        clearFiltersBtn?.addEventListener('click', function () {
            filterForm?.reset();
            if (especieInput) especieInput.value = '';
            document.querySelectorAll('.js-nice-select').forEach(function (el) { if (el.tomselect) el.tomselect.clear(); });
            if (filterStatusEl) filterStatusEl.value = '';
            quickStatusButtons.forEach(function (b) { b.classList.remove('active'); if ((b.dataset.status || '') === '') b.classList.add('active'); });
            applyFilters();
        });
        filterForm?.addEventListener('submit', function (e) { e.preventDefault(); applyFilters(); filterPanel?.classList.add('d-none'); });
        quickStatusButtons.forEach(function (btn) { btn.addEventListener('click', function () { const selectedStatus = this.dataset.status || ''; filterStatusEl.value = selectedStatus; quickStatusButtons.forEach(function (b) { b.classList.remove('active'); }); this.classList.add('active'); applyFilters(); }); });
        especieInput?.addEventListener('focus', function () { buildEspecieList(); especieList.style.display = 'block'; });
        especieInput?.addEventListener('input', function () { const term = this.value.trim().toLowerCase(); Array.from(especieList.children).forEach(function (li) { const text = li.textContent.toLowerCase(); li.style.display = (!term || text.includes(term)) ? '' : 'none'; }); });
        document.addEventListener('click', function (e) {
            if (especieList && especieInput && !especieList.contains(e.target) && e.target !== especieInput) especieList.style.display = 'none';
            if (filterPanel && openFilterPanelBtn && !filterPanel.classList.contains('d-none')) {
                if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) filterPanel.classList.add('d-none');
            }
        });
        paginationLinks?.addEventListener('click', function (e) {
            const link = e.target.closest('.page-link');
            if (!link) return;
            const parentLi = link.closest('.page-item');
            if (parentLi && parentLi.classList.contains('disabled')) { e.preventDefault(); return; }
            e.preventDefault();
            const page = parseInt(link.dataset.page, 10);
            if (!isNaN(page)) showPage(page);
        });

        <?php if ($isAdmin): ?>
        const toggleSelectModeBtn  = document.getElementById('toggleSelectMode');
        const openAssignModalBtn   = document.getElementById('openAssignModalBtn');
        const selectedSummaryModal = document.getElementById('selectedSummaryModal');
        const selectedHiddenInputs = document.getElementById('selectedHiddenInputs');
        const treeList             = document.getElementById('treeList');
        let selectionMode = false;
        function getCheckedBoxes() { return Array.from(document.querySelectorAll('.tree-checkbox')).filter(chk => chk.checked); }
        function refreshSelectedSummary() {
            const checked = getCheckedBoxes();
            if (checked.length > 0 && selectionMode) openAssignModalBtn.classList.remove('d-none'); else openAssignModalBtn.classList.add('d-none');
            if (selectedSummaryModal) selectedSummaryModal.textContent = checked.length + ' cartões selecionados.';
            if (selectedHiddenInputs) {
                selectedHiddenInputs.innerHTML = '';
                checked.forEach(function (chk) { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'selected_ids[]'; input.value = chk.value; selectedHiddenInputs.appendChild(input); });
            }
        }
        function updateSelectionUI() {
            if (selectionMode) { treeList?.classList.add('select-mode-on'); toggleSelectModeBtn?.classList.add('active'); }
            else { treeList?.classList.remove('select-mode-on'); document.querySelectorAll('.tree-checkbox').forEach(function (chk) { chk.checked = false; }); toggleSelectModeBtn?.classList.remove('active'); openAssignModalBtn?.classList.add('d-none'); }
            refreshSelectedSummary();
        }
        toggleSelectModeBtn?.addEventListener('click', function () { selectionMode = !selectionMode; updateSelectionUI(); });
        document.querySelectorAll('.tree-checkbox').forEach(function (chk) { chk.addEventListener('change', refreshSelectedSummary); });
        openAssignModalBtn?.addEventListener('click', function (e) { const checked = getCheckedBoxes(); if (!checked.length) { e.preventDefault(); alert('Selecione pelo menos um cartão.'); } });
        <?php endif; ?>

        const burgerBtn = document.querySelector('.burger-btn');
        burgerBtn?.addEventListener('click', function () { filterPanel?.classList.add('d-none'); });
        if (cards.length > 0) { cards.forEach(function (card) { card.dataset.filtered = 'false'; }); showPage(1); }
        else if (paginationNav) paginationNav.style.display = 'none';
    });
    </script>
    </body>
    </html>
    <?php
    exit();
}

/* =========================================================
   DETAIL PAGE
========================================================= */
$stmt = $conn->prepare("
    SELECT a.*,
           u1.username AS assigned_to_username,
           u1.name AS assigned_to_name,
           u2.username AS completed_by_username,
           u2.name AS completed_by_name,
           u2.is_admin AS completed_by_is_admin
    FROM arvores a
    LEFT JOIN users u1 ON u1.id = a.assigned_to_user_id
    LEFT JOIN users u2 ON u2.id = a.completed_by_user_id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$tree = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tree) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8" />
        <title>Árvore não encontrada</title>
        <link rel="stylesheet" href="assets/css/bootstrap.css" />
        <link rel="stylesheet" href="assets/css/app.css" />
    </head>
    <body>
    <div class="container mt-4">
        <div class="alert alert-danger mt-5 mx-auto text-center" style="max-width:500px;">
            Árvore não encontrada.
        </div>
    </div>
    </body>
    </html>
    <?php
    exit();
}

$canView = $isAdmin || ((int)$tree['assigned_to_user_id'] === $userId);
if (!$canView) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8" />
        <title>Acesso negado</title>
        <link rel="stylesheet" href="assets/css/bootstrap.css" />
        <link rel="stylesheet" href="assets/css/app.css" />
    </head>
    <body>
    <div class="container mt-4">
        <div class="alert alert-warning mt-5 mx-auto text-center" style="max-width:540px;">
            Não tem permissão para abrir esta intervenção.
        </div>
    </div>
    </body>
    </html>
    <?php
    exit();
}

$relatorios = [];

$stmtRelatorios = $conn->prepare("
    SELECT 
        r.id,
        r.mensagem,
        r.criado_em,
        COALESCE(NULLIF(u.name, ''), u.username) AS funcionario_nome
    FROM arvore_relatorios r
    LEFT JOIN users u ON u.id = r.funcionario_id
    WHERE r.arvore_id = ?
    ORDER BY r.criado_em DESC
");

if ($stmtRelatorios) {
    $stmtRelatorios->bind_param("i", $id);
    $stmtRelatorios->execute();
    $resRelatorios = $stmtRelatorios->get_result();

    while ($rel = $resRelatorios->fetch_assoc()) {
        $relatorios[] = $rel;
    }

    $stmtRelatorios->close();
}

$assignedName = !empty($tree['assigned_to_name']) ? $tree['assigned_to_name'] : ($tree['assigned_to_username'] ?? 'Não atribuído');
$estadoAtual = trim((string)($tree['estado'] ?? ''));
$isDone = isEstadoConcluido($estadoAtual);

$mensagemFuncionarioDetalhe = trim((string)($tree['mensagem_funcionario'] ?? ''));

$completedByName = !empty($tree['completed_by_name'])
    ? $tree['completed_by_name']
    : (!empty($tree['completed_by_username']) ? $tree['completed_by_username'] : '');

$completedByIsAdmin = isset($tree['completed_by_is_admin']) ? (int)$tree['completed_by_is_admin'] === 1 : false;

$statusLabel = 'Ainda não concluído';
$statusClass = 'pending';
$statusIcon  = 'bi-hourglass-split';

if ($isDone) {
    if ($completedByName !== '') {
        if ($completedByIsAdmin) {
            $statusLabel = 'Feito pelo administrador';
        } else {
            $statusLabel = 'Feito pelo funcionário';
        }
    } else {
        $statusLabel = 'Intervenção concluída';
    }

    $statusClass = 'done';
    $statusIcon  = 'bi-check-circle-fill';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Intervenção</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body { height: 100%; }

        body {
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
        }

        #app, #main { min-height: 100%; }

        .page-content {
            min-height: calc(100vh - 56px);
            padding: 24px 12px 32px 12px;
        }

        .edit-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .page-heading-custom { margin-bottom: 16px; }

        .page-heading-custom h3 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 4px;
        }

        .page-heading-custom p {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 0;
        }

        .edit-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 20px 20px 18px 20px;
            border: 1px solid #e5e7eb;
        }

        .section-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            margin-top: 4px;
            margin-bottom: 6px;
        }

        .field-label {
            font-weight: 600;
            color: #4b5563;
            font-size: 0.9rem;
        }

        .form-control,
        .form-select { border-radius: 10px; }

        .readonly-input {
            background-color: #f9fafb !important;
            color: #374151;
        }

        .edit-two-cols {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr;
            gap: 18px;
        }

        .edit-map-wrapper {
            margin-top: 8px;
            border-radius: 14px;
            background: #f9fafb;
            padding: 10px;
            border: 1px solid #e5e7eb;
        }

        #map {
            height: 330px;
            width: 100%;
            border-radius: 10px;
        }

        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }

        .worker-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 0.45rem 0.9rem;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .worker-chip.done { background: #dcfce7; color: #166534; }
        .worker-chip.pending { background: #fef3c7; color: #92400e; }

        .admin-message-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 18px;
            color: #1e3a8a;
            text-align: left;
        }

        .admin-message-box-title {
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e3a8a;
            text-align: left;
        }

        .admin-message-box-title i {
            font-size: 1rem;
        }

        .admin-message-box-text {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 12px 14px;
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.55;
            color: #111827;
            text-align: left !important;
            white-space: normal;
            width: 100%;
        }

        .reports-section {
            margin-top: 10px;
        }

        .reports-header-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .reports-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reports-header-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #435ebe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .reports-header-title h5 {
            margin: 0;
            font-weight: 800;
            color: #111827;
            font-size: 1rem;
        }

        .reports-header-title small {
            color: #6b7280;
            font-size: 0.84rem;
        }

        .reports-count-badge {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            color: #374151;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: stretch;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.07);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            min-height: 240px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.11);
        }

        .report-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            min-height: 46px;
            flex-shrink: 0;
        }

        .report-user {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .report-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eef2ff;
            color: #435ebe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .report-author {
            font-weight: 800;
            color: #111827;
            font-size: 0.92rem;
            line-height: 1.2;
            word-break: break-word;
        }

        .report-subtitle {
            font-size: 0.78rem;
            color: #6b7280;
            margin-top: 2px;
            line-height: 1.2;
        }

        .report-date {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            border-radius: 999px;
            padding: 0.32rem 0.65rem;
            font-size: 0.74rem;
            font-weight: 700;
            white-space: nowrap;
            line-height: 1.1;
            flex-shrink: 0;
        }

        .report-message-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 13px 14px;
            flex: 1;
            height: 150px;
            min-height: 150px;
            max-height: 150px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            text-align: left;
        }

        .report-message-label {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 7px;
            font-size: 0.76rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .035em;
            margin: 0 0 10px 0;
            padding: 0;
            line-height: 1.2;
            flex-shrink: 0;
            text-align: left;
        }

        .report-message {
            width: 100%;
            margin: 0;
            padding: 0;
            font-size: 0.92rem;
            color: #111827;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: normal;
            line-height: 1.55;
            text-align: left;
            align-self: flex-start;
        }

        .empty-report-box {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .empty-report-box i {
            font-size: 1.4rem;
            color: #94a3b8;
        }

        .ts-wrapper .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px;
            padding: 0.375rem 0.75rem;
            border-color: #d1d5db;
        }

        .ts-wrapper .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25);
            border-color: #2563eb;
        }

        .ts-dropdown { background-color: #ffffff !important; }

        @media (max-width: 1200px) {
            .report-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 992px) {
            #main { margin-left: 0 !important; }
            .sidebar-wrapper { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1050; }
            #app { overflow-x: hidden; }
        }

        @media (max-width: 768px) {
            .edit-card { padding: 16px 14px 16px 14px; }
            .edit-two-cols { grid-template-columns: 1fr; }
            .report-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 576px) {
            .report-top { flex-direction: column; }
            .report-date { width: fit-content; }
            .report-message-box { height: 160px; min-height: 160px; max-height: 160px; }
        }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>

    <div id="main">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="page-content">
            <div class="edit-container">
                <div class="page-heading-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3>Espaço Verde</h3>
                        <p>Atualize a tarefa da intervenção da árvore selecionada.</p>
                    </div>

                    <span class="worker-chip <?= htmlspecialchars($statusClass) ?>">
                        <i class="bi <?= htmlspecialchars($statusIcon) ?>"></i>
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>

                <div class="edit-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($_GET['saved'])): ?>
                        <div class="alert alert-success mb-3">
                            Intervenção atualizada com sucesso.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_GET['report_sent'])): ?>
                        <div class="alert alert-success mb-3">
                            Relatório enviado ao administrador com sucesso.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <span class="badge bg-light text-dark border">
                            Funcionário: <?= htmlspecialchars($assignedName) ?>
                        </span>
                    </div>

                    <?php if ($mensagemFuncionarioDetalhe !== ''): ?>
                        <div class="admin-message-box">
                            <div class="admin-message-box-title">
                                <i class="bi bi-chat-left-text-fill"></i>
                                Mensagem do administrador
                            </div>
                            <div class="admin-message-box-text"><?= nl2br(htmlspecialchars($mensagemFuncionarioDetalhe)) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isDone && $completedByName !== ''): ?>
                        <div class="mb-3">
                            <span class="badge bg-light text-dark border">
                                Concluído por: <?= htmlspecialchars($completedByName) ?>
                                <?php if ($completedByIsAdmin): ?>
                                    (Administrador)
                                <?php else: ?>
                                    (Funcionário)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off" id="editIntervencaoForm">
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <textarea name="report_message" id="report_message_hidden_for_save" class="d-none"></textarea>

                        <div class="edit-two-cols mb-3">
                            <div>
                                <div class="section-label">Dados principais</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Espécie da Árvore</label>
                                    <select id="species" class="form-select js-nice-select" disabled>
                                        <option value="">Selecione a espécie</option>
                                        <?php foreach ($valid_species as $sp): ?>
                                            <option value="<?= htmlspecialchars($sp) ?>" <?= ($tree['especie'] == $sp) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sp) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Nome do Espaço</label>
                                    <input type="text" id="place_name" class="form-control readonly-input" value="<?= htmlspecialchars($tree['place_name']) ?>" readonly>
                                </div>

                                <div class="section-label">Intervenção e tarefa</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Tipo de Intervenção</label>
                                    <select class="form-select js-nice-select" disabled>
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($intervencoes as $interv): ?>
                                            <option value="<?= htmlspecialchars($interv) ?>" <?= ($tree['tipo_intervencao'] == $interv ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($interv) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-0">
                                    <label class="field-label mb-1">Tarefa</label>
                                    <select name="tarefa" id="tarefa_select_visible" class="form-select js-nice-select" required>
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($tarefas as $tarefa_opt): ?>
                                            <option value="<?= htmlspecialchars($tarefa_opt) ?>" <?= ($tree['estado'] == $tarefa_opt ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($tarefa_opt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="edit_intervencao" class="btn btn-primary btn-main w-100">
                                        <i class="bi bi-check-lg me-1"></i> Guardar Alterações
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="section-label">Mapa e coordenadas</div>

                                <div class="row mb-2">
                                    <div class="col-6">
                                        <label class="field-label mb-1">Latitude</label>
                                        <input type="text" id="latitude" class="form-control readonly-input" value="<?= htmlspecialchars($tree['latitude']) ?>" readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="field-label mb-1">Longitude</label>
                                        <input type="text" id="longitude" class="form-control readonly-input" value="<?= htmlspecialchars($tree['longitude']) ?>" readonly>
                                    </div>
                                </div>

                                <div class="edit-map-wrapper">
                                    <div id="map"></div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="reports-section">
                        <div class="reports-header-box">
                            <div class="reports-header-title">
                                <div class="reports-header-icon">
                                    <i class="bi bi-chat-square-text-fill"></i>
                                </div>

                                <div>
                                    <h5>Relatórios do funcionário</h5>
                                    <small>Mensagens enviadas pelo funcionário sobre esta intervenção.</small>
                                </div>
                            </div>

                            <div class="reports-count-badge">
                                <?= count($relatorios) ?> relatório(s)
                            </div>
                        </div>

                        <?php if (!empty($relatorios)): ?>
                            <div class="report-grid">
                                <?php foreach ($relatorios as $relatorio): ?>
                                    <div class="report-card">
                                        <div class="report-top">
                                            <div class="report-user">
                                                <div class="report-avatar">
                                                    <i class="bi bi-person-circle"></i>
                                                </div>

                                                <div>
                                                    <div class="report-author">
                                                        <?= htmlspecialchars($relatorio['funcionario_nome'] ?? 'Funcionário') ?>
                                                    </div>
                                                    <div class="report-subtitle">
                                                        Funcionário responsável
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="report-date">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= !empty($relatorio['criado_em']) ? date('d/m/Y H:i', strtotime($relatorio['criado_em'])) : '—' ?>
                                            </div>
                                        </div>

                                        <div class="report-message-box">
                                            <div class="report-message-label">
                                                <i class="bi bi-pencil-square"></i>
                                                Situação encontrada
                                            </div>

                                            <div class="report-message"><?= nl2br(htmlspecialchars(trim((string)$relatorio['mensagem']))) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-report-box">
                                <i class="bi bi-inbox"></i>
                                <div>
                                    <strong>Ainda não existem relatórios.</strong><br>
                                    <span>Quando o funcionário enviar uma mensagem, ela irá aparecer aqui.</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isAdmin && (int)$tree['assigned_to_user_id'] === $userId): ?>
                        <hr class="my-4">

                        <form method="post" autocomplete="off" id="sendReportForm">
                            <input type="hidden" name="id" value="<?= (int)$id ?>">
                            <input type="hidden" name="tarefa_from_report_form" id="tarefa_hidden_for_report" value="">

                            <div class="section-label">Mensagem para o administrador</div>

                            <div class="mb-3">
                                <label class="field-label mb-1">Relatório / Situação encontrada</label>
                                <textarea
                                    name="report_message"
                                    id="report_message_visible"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1500"
                                    required
                                    placeholder="Ex: A árvore está danificada, precisa de poda urgente, o local não tem árvore, ou existe outro problema no espaço verde."></textarea>
                                <small class="text-muted">
                                    Escreva aqui o que encontrou no local. O administrador irá receber esta mensagem por notificação.
                                </small>
                            </div>

                            <button type="submit" name="send_tree_report" class="btn btn-warning w-100">
                                <i class="bi bi-chat-dots me-1"></i> Enviar relatório ao administrador
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        if (!el.tomselect) {
            new TomSelect(el, {
                maxItems: 1,
                allowEmptyOption: true,
                create: false,
                plugins: {
                    clear_button: { title: 'Limpar seleção' }
                }
            });
        }
    });

    const editIntervencaoForm = document.getElementById('editIntervencaoForm');
    const sendReportForm = document.getElementById('sendReportForm');
    const visibleReportBox = document.getElementById('report_message_visible');
    const hiddenReportBox = document.getElementById('report_message_hidden_for_save');
    const tarefaSelectVisible = document.getElementById('tarefa_select_visible');
    const tarefaHiddenForReport = document.getElementById('tarefa_hidden_for_report');

    function syncReportToSaveForm() {
        if (visibleReportBox && hiddenReportBox) {
            hiddenReportBox.value = visibleReportBox.value;
        }
    }

    function syncTarefaToReportForm() {
        if (tarefaSelectVisible && tarefaHiddenForReport) {
            tarefaHiddenForReport.value = tarefaSelectVisible.value;
        }
    }

    visibleReportBox?.addEventListener('input', syncReportToSaveForm);
    tarefaSelectVisible?.addEventListener('change', syncTarefaToReportForm);

    editIntervencaoForm?.addEventListener('submit', function () {
        syncReportToSaveForm();
    });

    sendReportForm?.addEventListener('submit', function () {
        syncTarefaToReportForm();
    });
});

const evora = [
    <?= is_numeric($tree['latitude']) ? (float)$tree['latitude'] : 38.5667 ?>,
    <?= is_numeric($tree['longitude']) ? (float)$tree['longitude'] : -7.9 ?>
];

const map = L.map('map', { attributionControl: false }).setView(evora, 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

L.marker(evora, {
    draggable: false
}).addTo(map);
</script>
</body>
</html>
