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

$userId  = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

/* =========================================================
   CHECK ADMIN DIRECTLY FROM DATABASE
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

$intervencoes = ['Corte', 'Poda'];

function isEstadoConcluido(?string $estado): bool {
    $estado = trim((string)$estado);
    return in_array(mb_strtolower($estado), ['concluída', 'concluido', 'concluído'], true);
}

/* =========================================================
   AUTOMATIC REMINDER EVERY 5 DAYS
========================================================= */
function runAutomaticOccurrenceReminders(mysqli $conn): void {
    $sql = "
        SELECT
            id,
            descricao,
            place_name,
            tipo_intervencao,
            estado,
            assigned_to_user_id,
            assigned_by_user_id,
            next_reminder_at,
            reminder_every_days
        FROM ocorrencias
        WHERE assigned_to_user_id IS NOT NULL
          AND next_reminder_at IS NOT NULL
          AND next_reminder_at <= NOW()
        LIMIT 100
    ";

    $res = $conn->query($sql);

    if (!$res) {
        return;
    }

    while ($ocor = $res->fetch_assoc()) {
        $occId = (int)$ocor['id'];
        $estadoAtual = trim((string)($ocor['estado'] ?? ''));

        if (isEstadoConcluido($estadoAtual)) {
            $stmtStop = $conn->prepare("
                UPDATE ocorrencias
                SET reminder_every_days = NULL,
                    next_reminder_at = NULL,
                    last_reminder_at = NULL
                WHERE id = ?
            ");

            if ($stmtStop) {
                $stmtStop->bind_param("i", $occId);
                $stmtStop->execute();
                $stmtStop->close();
            }

            continue;
        }

        $assignedUserId  = (int)$ocor['assigned_to_user_id'];
        $createdByUserId = !empty($ocor['assigned_by_user_id']) ? (int)$ocor['assigned_by_user_id'] : 0;

        $days = !empty($ocor['reminder_every_days']) ? (int)$ocor['reminder_every_days'] : 5;
        if ($days <= 0) {
            $days = 5;
        }

        $tituloNotif = "Lembrete de ocorrência";

        $mensagemNotif = "Lembrete automático: ainda existe uma ocorrência pendente. ";
        $mensagemNotif .= "Local: " . (($ocor['place_name'] ?? '') ?: 'Sem nome') . ". ";
        $mensagemNotif .= "Descrição: " . mb_substr(trim((string)($ocor['descricao'] ?? '')), 0, 220) . ". ";
        $mensagemNotif .= "Intervenção: " . (($ocor['tipo_intervencao'] ?? '') ?: 'Nenhuma') . ". ";
        $mensagemNotif .= "Por favor, verifique a situação e atualize o estado da tarefa.";

        $stmtNotif = $conn->prepare("
            INSERT INTO notificacoes
            (user_id, created_by_user_id, origem_tipo, origem_id, titulo, mensagem, enviar_em, lida, criada_em)
            VALUES (?, ?, 'ocorrencia', ?, ?, ?, NULL, 0, NOW())
        ");

        if ($stmtNotif) {
            $stmtNotif->bind_param("iiiss", $assignedUserId, $createdByUserId, $occId, $tituloNotif, $mensagemNotif);
            $stmtNotif->execute();
            $stmtNotif->close();
        }

        $next = new DateTime($ocor['next_reminder_at']);
        $now = new DateTime();

        do {
            $next->modify("+{$days} days");
        } while ($next <= $now);

        $nextReminderAt = $next->format('Y-m-d H:i:s');

        $stmtNext = $conn->prepare("
            UPDATE ocorrencias
            SET last_reminder_at = NOW(),
                next_reminder_at = ?
            WHERE id = ?
        ");

        if ($stmtNext) {
            $stmtNext->bind_param("si", $nextReminderAt, $occId);
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

function getLatestOccurrenceReport(mysqli $conn, int $occId): string {
    $stmt = $conn->prepare("
        SELECT mensagem
        FROM ocorrencia_relatorios
        WHERE ocorrencia_id = ?
        ORDER BY criado_em DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param("i", $occId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return trim((string)($row['mensagem'] ?? ''));
}

function upsertSingleOccurrenceNotification(
    mysqli $conn,
    int $adminId,
    int $createdByUserId,
    int $occId,
    string $mensagem
): void {
    $titulo = 'Atualização da ocorrência';

    $stmtClean = $conn->prepare("
        DELETE FROM notificacoes
        WHERE user_id = ?
          AND origem_id = ?
          AND lida = 0
          AND origem_tipo = 'ocorrencia'
          AND titulo IN (
              'Ocorrência atualizada',
              'Ocorrência concluída',
              'Novo relatório do funcionário'
          )
    ");

    if ($stmtClean) {
        $stmtClean->bind_param("ii", $adminId, $occId);
        $stmtClean->execute();
        $stmtClean->close();
    }

    $stmtFind = $conn->prepare("
        SELECT id
        FROM notificacoes
        WHERE user_id = ?
          AND origem_tipo = 'ocorrencia'
          AND origem_id = ?
          AND titulo = ?
          AND lida = 0
        LIMIT 1
    ");

    $existingId = 0;

    if ($stmtFind) {
        $stmtFind->bind_param("iis", $adminId, $occId, $titulo);
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
            VALUES (?, ?, 'ocorrencia', ?, ?, ?, NULL, 0, NOW())
        ");

        if ($stmtInsert) {
            $stmtInsert->bind_param("iiiss", $adminId, $createdByUserId, $occId, $titulo, $mensagem);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }
}

function notifyAdminsOccurrenceSingleUpdate(
    mysqli $conn,
    int $createdByUserId,
    array $ocorrencia,
    string $estadoAtual,
    ?string $reportMessage = null,
    bool $isConclusion = false
): void {
    $occId = (int)($ocorrencia['id'] ?? 0);

    if ($occId <= 0) {
        return;
    }

    $placeName = trim((string)($ocorrencia['place_name'] ?? ''));
    if ($placeName === '') {
        $placeName = 'Sem nome';
    }

    $descricao = trim((string)($ocorrencia['descricao'] ?? ''));
    if ($descricao === '') {
        $descricao = 'Sem descrição';
    }

    $tipoIntervencao = trim((string)($ocorrencia['tipo_intervencao'] ?? ''));
    if ($tipoIntervencao === '') {
        $tipoIntervencao = 'Nenhuma';
    }

    $assignedUserId = !empty($ocorrencia['assigned_to_user_id']) ? (int)$ocorrencia['assigned_to_user_id'] : 0;
    $funcionarioNome = $assignedUserId > 0 ? getUserDisplayName($conn, $assignedUserId) : 'Não atribuído';
    $updatedBy = getUserDisplayName($conn, $createdByUserId);

    $latestReport = trim((string)$reportMessage);

    if ($latestReport === '') {
        $latestReport = getLatestOccurrenceReport($conn, $occId);
    }

    $scheduledText = '';
    if (!empty($ocorrencia['scheduled_for'])) {
        $scheduledText = date('d/m/Y H:i', strtotime($ocorrencia['scheduled_for']));
    }

    $mensagemFuncionario = trim((string)($ocorrencia['mensagem_funcionario'] ?? ''));

    $mensagemNotif  = "Atualização da ocorrência.\n\n";
    $mensagemNotif .= "Local: {$placeName}\n";
    $mensagemNotif .= "Descrição: {$descricao}\n";
    $mensagemNotif .= "Tipo de intervenção: {$tipoIntervencao}\n";
    $mensagemNotif .= "Estado atual: {$estadoAtual}\n";
    $mensagemNotif .= "Funcionário responsável: {$funcionarioNome}\n";

    if ($scheduledText !== '') {
        $mensagemNotif .= "Data/Hora do trabalho: {$scheduledText}\n";
    }

    if ($mensagemFuncionario !== '') {
        $mensagemNotif .= "\nMensagem enviada ao funcionário:\n{$mensagemFuncionario}\n";
    }

    if ($latestReport !== '') {
        $mensagemNotif .= "\nRelatório/Situação encontrada:\n{$latestReport}\n";
    } else {
        $mensagemNotif .= "\nRelatório/Situação encontrada: ainda sem relatório.\n";
    }

    if ($isConclusion) {
        $mensagemNotif .= "\nResultado: ocorrência marcada como concluída.\n";
    }

    $mensagemNotif .= "\nAtualizado por: {$updatedBy}.\n";
    $mensagemNotif .= "Clique para abrir os detalhes desta ocorrência.";

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

        upsertSingleOccurrenceNotification($conn, $adminId, $createdByUserId, $occId, $mensagemNotif);
    }
}

/* ========= TAREFAS ========= */
$tarefas = [];
$res_tarefa = $conn->query("SELECT name FROM states ORDER BY name");
if ($res_tarefa && $res_tarefa->num_rows > 0) {
    while ($row = $res_tarefa->fetch_assoc()) {
        if (!empty($row['name'])) {
            $tarefas[] = $row['name'];
        }
    }
}

/* ========= FUNCIONÁRIOS ========= */
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

runAutomaticOccurrenceReminders($conn);

/* ========= MARCAR NOTIFICAÇÃO COMO LIDA ========= */
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
   ASSIGN SELECTED OCCURRENCES + ONE NOTIFICATION
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
        : 'Deve verificar o local e resolver a ocorrência atribuída.';

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
        $error = "Selecione pelo menos uma ocorrência.";
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
                        UPDATE ocorrencias
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
                        throw new Exception("Erro ao preparar atualização da ocorrência: " . $conn->error);
                    }

                    $stmtOcc = $conn->prepare("
                        SELECT id, descricao, place_name, tipo_intervencao, estado
                        FROM ocorrencias
                        WHERE id = ?
                        LIMIT 1
                    ");

                    if (!$stmtOcc) {
                        throw new Exception("Erro ao preparar consulta da ocorrência: " . $conn->error);
                    }

                    $linhasTarefas = [];
                    $firstOccId = (int)$selectedIds[0];

                    foreach ($selectedIds as $occId) {
                        $stmtUpdate->bind_param(
                            "iisssi",
                            $assignedUserId,
                            $userId,
                            $scheduledFor,
                            $mensagemFuncionarioGuardar,
                            $nextReminderAt,
                            $occId
                        );

                        if (!$stmtUpdate->execute()) {
                            throw new Exception("Erro ao atribuir ocorrência ID {$occId}: " . $stmtUpdate->error);
                        }

                        $stmtDeleteOldNotif = $conn->prepare("
                            DELETE FROM notificacoes
                            WHERE user_id = ?
                              AND origem_tipo = 'ocorrencia'
                              AND origem_id = ?
                              AND lida = 0
                              AND titulo IN (
                                  'Nova intervenção atribuída',
                                  'Nova tarefa de ocorrência',
                                  'Novas tarefas de ocorrência'
                              )
                        ");

                        if ($stmtDeleteOldNotif) {
                            $stmtDeleteOldNotif->bind_param("ii", $assignedUserId, $occId);
                            $stmtDeleteOldNotif->execute();
                            $stmtDeleteOldNotif->close();
                        }

                        $stmtOcc->bind_param("i", $occId);
                        $stmtOcc->execute();
                        $occInfo = $stmtOcc->get_result()->fetch_assoc();

                        if (!$occInfo) {
                            continue;
                        }

                        $placeName = $occInfo['place_name'] ?: 'Sem nome';
                        $descricao = mb_substr(trim((string)($occInfo['descricao'] ?? '')), 0, 220);
                        $tipoIntervencao = !empty($occInfo['tipo_intervencao']) ? $occInfo['tipo_intervencao'] : 'Nenhuma';
                        $estado = !empty($occInfo['estado']) ? $occInfo['estado'] : 'Tarefa pendente';

                        $numero = count($linhasTarefas) + 1;

                        $linhasTarefas[] =
                            "{$numero}) Local: {$placeName}. " .
                            "Descrição: {$descricao}. " .
                            "Intervenção: {$tipoIntervencao}. " .
                            "Tarefa: {$estado}.";

                        regista_log(
                            $conn,
                            $userId,
                            "editar",
                            "intervencao_ocorrencia",
                            $occId,
                            "Ocorrência atribuída ao funcionário"
                        );
                    }

                    if (empty($linhasTarefas)) {
                        throw new Exception("Nenhuma ocorrência válida foi encontrada.");
                    }

                    $quantidade = count($linhasTarefas);

                    if ($quantidade === 1) {
                        $tituloNotif = "Nova tarefa de ocorrência";
                        $mensagemNotif = "Foi-lhe atribuída 1 tarefa de ocorrência.\n\n";
                    } else {
                        $tituloNotif = "Novas tarefas de ocorrência";
                        $mensagemNotif = "Foram-lhe atribuídas {$quantidade} tarefas de ocorrência.\n\n";
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
                        VALUES (?, ?, 'ocorrencia', ?, ?, ?, ?, 0, NOW())
                    ");

                    if (!$stmtInsertOneNotif) {
                        throw new Exception("Erro ao preparar notificação única: " . $conn->error);
                    }

                    $stmtInsertOneNotif->bind_param(
                        "iiisss",
                        $assignedUserId,
                        $userId,
                        $firstOccId,
                        $tituloNotif,
                        $mensagemNotif,
                        $notifyAt
                    );

                    if (!$stmtInsertOneNotif->execute()) {
                        throw new Exception("Erro ao criar notificação única: " . $stmtInsertOneNotif->error);
                    }

                    $stmtInsertOneNotif->close();
                    $stmtUpdate->close();
                    $stmtOcc->close();

                    $conn->commit();

                    header("Location: index.php?evora=editarintervencaoocorrencias&assigned=1");
                    exit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = "Erro ao atribuir ocorrências: " . $e->getMessage();
                }
            }
        }
    }
}

/* =========================================================
   FUNCIONÁRIO SEND REPORT TO ADMIN
   Fixed: this separate button also saves the current tarefa
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_occurrence_report']) && $id > 0) {
    $reportMessage = trim($_POST['report_message'] ?? '');
    $tarefaFromReportForm = trim($_POST['estado_from_report_form'] ?? '');

    if ($reportMessage === '') {
        $error = "Escreva uma mensagem antes de enviar o relatório.";
    } else {
        $stmt = $conn->prepare("
            SELECT *
            FROM ocorrencias
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $ocorReport = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ocorReport) {
            $error = "Ocorrência não encontrada.";
        } elseif ((int)$ocorReport['assigned_to_user_id'] !== $userId) {
            $error = "Só o funcionário responsável pode enviar relatório desta intervenção.";
        } elseif ($tarefaFromReportForm !== '' && !in_array($tarefaFromReportForm, $tarefas, true)) {
            $error = "Tarefa inválida.";
        } else {
            $reportMessage = mb_substr($reportMessage, 0, 1500);

            $estadoAnterior = trim((string)($ocorReport['estado'] ?? ''));
            $estadoFinal = $tarefaFromReportForm !== '' ? $tarefaFromReportForm : $estadoAnterior;
            $isConclusao = isEstadoConcluido($estadoFinal);
            $wasAlreadyDone = isEstadoConcluido($estadoAnterior);

            $conn->begin_transaction();

            try {
                if ($tarefaFromReportForm !== '' && $tarefaFromReportForm !== $estadoAnterior) {
                    if ($isConclusao) {
                        $stmtUpdTarefa = $conn->prepare("
                            UPDATE ocorrencias
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
                            UPDATE ocorrencias
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
                        "intervencao_ocorrencia",
                        $id,
                        "Tarefa atualizada para: {$estadoFinal} juntamente com o relatório"
                    );
                }

                $stmtSaveReport = $conn->prepare("
                    INSERT INTO ocorrencia_relatorios
                    (ocorrencia_id, funcionario_id, mensagem, criado_em)
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
                    "intervencao_ocorrencia",
                    $id,
                    "Funcionário enviou relatório ao administrador"
                );

                $acao = 'Relatório de ocorrência enviado';
                $detalhe = "Local: " . (($ocorReport['place_name'] ?? '') ?: 'Sem nome') . " · Tarefa: {$estadoFinal}";
                $stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)");
                if ($stmtAt) {
                    $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }

                $conn->commit();

                $ocorReport['estado'] = $estadoFinal;

                if ($isConclusao) {
                    $ocorReport['completed_by_user_id'] = $userId;
                    $ocorReport['completed_at'] = date('Y-m-d H:i:s');
                }

                notifyAdminsOccurrenceSingleUpdate(
                    $conn,
                    $userId,
                    $ocorReport,
                    $estadoFinal !== '' ? $estadoFinal : '—',
                    $reportMessage,
                    $isConclusao && !$wasAlreadyDone
                );

                header("Location: index.php?evora=editarintervencaoocorrencias&id={$id}&saved=1&report_sent=1");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_intervencao_ocorrencia']) && $id > 0) {
    $stmtCheck = $conn->prepare("SELECT * FROM ocorrencias WHERE id = ?");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $ocorrenciaCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$ocorrenciaCheck) {
        $error = "Ocorrência não encontrada.";
    } else {
        $canEdit = $isAdmin || ((int)$ocorrenciaCheck['assigned_to_user_id'] === $userId);

        if (!$canEdit) {
            $error = "Não tem permissão para editar esta intervenção.";
        } else {
            $tarefa = trim($_POST['estado'] ?? '');

            if ($tarefa === '') {
                $error = "A tarefa é obrigatória!";
            } elseif (!in_array($tarefa, $tarefas, true)) {
                $error = "Tarefa inválida!";
            } else {
                $reportMessageFromSave = '';

                if (!$isAdmin && (int)$ocorrenciaCheck['assigned_to_user_id'] === $userId) {
                    $reportMessageFromSave = trim($_POST['report_message'] ?? '');

                    if ($reportMessageFromSave !== '') {
                        $reportMessageFromSave = mb_substr($reportMessageFromSave, 0, 1500);
                    }
                }

                $isConclusao = isEstadoConcluido($tarefa);
                $wasAlreadyDone = isEstadoConcluido($ocorrenciaCheck['estado'] ?? '');

                if ($isConclusao) {
                    $stmt = $conn->prepare("
                        UPDATE ocorrencias
                        SET estado = ?,
                            completed_by_user_id = ?,
                            completed_at = NOW(),
                            reminder_every_days = NULL,
                            next_reminder_at = NULL,
                            last_reminder_at = NULL
                        WHERE id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("sii", $tarefa, $userId, $id);
                    }
                } else {
                    $stmt = $conn->prepare("
                        UPDATE ocorrencias
                        SET estado = ?,
                            completed_by_user_id = NULL,
                            completed_at = NULL
                        WHERE id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param("si", $tarefa, $id);
                    }
                }

                if (!$stmt) {
                    $error = "Erro ao preparar atualização: " . $conn->error;
                } elseif ($stmt->execute()) {
                    regista_log($conn, $userId, "editar", "intervencao_ocorrencia", $id, "Intervenção atualizada para: $tarefa");

                    $acao = 'Intervenção de ocorrência atualizada';
                    $detalhe = "Local: " . ($ocorrenciaCheck['place_name'] ?: 'Sem nome') .
                              " · Tipo: " . ($ocorrenciaCheck['tipo_intervencao'] ?: 'Nenhuma') .
                              " · Nova tarefa: $tarefa";

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    $reportWasSaved = false;

                    if ($reportMessageFromSave !== '') {
                        $stmtSaveReport = $conn->prepare("
                            INSERT INTO ocorrencia_relatorios
                            (ocorrencia_id, funcionario_id, mensagem, criado_em)
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
                                "intervencao_ocorrencia",
                                $id,
                                "Funcionário enviou relatório juntamente com a atualização da tarefa"
                            );
                        }
                    }

                    if (!$isAdmin && (int)$ocorrenciaCheck['assigned_to_user_id'] === $userId) {
                        $ocorrenciaCheck['estado'] = $tarefa;

                        if ($isConclusao) {
                            $ocorrenciaCheck['completed_by_user_id'] = $userId;
                            $ocorrenciaCheck['completed_at'] = date('Y-m-d H:i:s');
                        }

                        if ($reportWasSaved || ($isConclusao && !$wasAlreadyDone)) {
                            notifyAdminsOccurrenceSingleUpdate(
                                $conn,
                                $userId,
                                $ocorrenciaCheck,
                                $tarefa,
                                $reportWasSaved ? $reportMessageFromSave : null,
                                $isConclusao
                            );
                        }
                    }

                    $stmt->close();

                    $extraReport = !empty($reportWasSaved) ? '&report_sent=1' : '';
                    header("Location: index.php?evora=editarintervencaoocorrencias&id={$id}&saved=1{$extraReport}");
                    exit();
                } else {
                    $error = "Erro ao atualizar intervenção: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}

/* =========================================================
   PARTE 1 – LISTAR OCORRÊNCIAS PARA INTERVENÇÃO
========================================================= */
if ($id <= 0) {
    $whereSql = '';
    if (!$isAdmin) {
        $whereSql = "WHERE o.assigned_to_user_id = {$userId}";
    }

    $sqlOcorrencias = "
        SELECT o.*,
               u1.username AS assigned_to_username,
               u1.name AS assigned_to_name,
               u2.username AS completed_by_username,
               u2.name AS completed_by_name,
               u2.is_admin AS completed_by_is_admin
        FROM ocorrencias o
        LEFT JOIN users u1 ON u1.id = o.assigned_to_user_id
        LEFT JOIN users u2 ON u2.id = o.completed_by_user_id
        {$whereSql}
        ORDER BY
            CASE WHEN o.assigned_to_user_id IS NULL THEN 0 ELSE 1 END ASC,
            o.criado_em DESC,
            o.scheduled_for ASC,
            o.id DESC
    ";
    $ocorrencias = $conn->query($sqlOcorrencias);

    $tipos_result = $conn->query("
        SELECT DISTINCT tipo_intervencao
        FROM ocorrencias
        WHERE tipo_intervencao IS NOT NULL AND tipo_intervencao <> ''
        ORDER BY tipo_intervencao ASC
    ");

    $estados_result = $conn->query("
        SELECT DISTINCT estado
        FROM ocorrencias
        WHERE estado IS NOT NULL AND estado <> ''
        ORDER BY estado ASC
    ");

    $per_page = 6;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Intervenção - Ocorrências</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

        <style>
        body, .sidebar, .card, .btn, h4, h3, h2 { font-family: 'Nunito', sans-serif !important; }
        .page-content { background: radial-gradient(circle at top, #e0f2fe, #eef2ff 40%, #f9fafb 80%); }
        .card-main { border-radius: 20px; border: 0; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12); }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .card-header { border-bottom: 1px solid #e5e7eb; }
        .page-heading-custom h3 { font-weight: 800; }
        .page-heading-custom p { color: #6b7280; }
        #openFilterPanel, #toggleSelectMode, #openAssignModalBtn { border-radius: 999px; padding: 0.4rem 1rem; font-size: 0.9rem; }
        .top-action-btn { line-height: 1.2; }
        .filter-panel { position: absolute; top: 72px; right: 24px; width: 320px; background: #ffffff; padding: 22px 18px 16px 18px; border-radius: 18px; z-index: 2000; border: 1px solid #e5e7eb; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18); }
        .filter-panel .form-label { font-size: 0.8rem; font-weight: 600; color: #6b7280; }
        .filter-panel .form-control, .filter-panel .form-select { font-size: 0.85rem; border-radius: 10px; }
        .filter-panel-actions { padding-top: 8px; display: flex; justify-content: flex-end; gap: 0.5rem; }
        .quick-status-btn.active { font-weight: 700; box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12); }
        .ts-wrapper.single .ts-control, .ts-wrapper.single.input-active .ts-control { background-color: #ffffff !important; border-radius: 10px; padding: 0.375rem 0.75rem; border-color: #d1d5db; }
        .ts-wrapper.single .ts-control:focus { box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25); border-color: #2563eb; }
        .ts-dropdown { background-color: #ffffff !important; }
        .ocor-card-container { display: flex; }
        .ocor-card { border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); overflow: hidden; background-color: #ffffff; display: flex; flex-direction: column; width: 100%; transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .ocor-card:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12); }
        .ocor-card-body { padding: 0.9rem 1.1rem 0.6rem 1.1rem; flex-grow: 1; display: flex; flex-direction: column; gap: 0.2rem; }
        .ocor-card-footer { background-color: #f9fafb; padding: 0.45rem 1.1rem 0.55rem 1.1rem; font-size: 0.78rem; color: #6b7280; border-top: 1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap: nowrap; }
        .ocor-label { font-weight: 600; font-size: 13px; color: #6b7280; }
        .ocor-value { font-size: 14px; color: #111827; }
        .ocor-descricao { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }
        .ocor-line { margin-bottom: 0.15rem; }
        .ocor-image-line{ display:flex; align-items:center; gap:6px; margin-top: 0.15rem; }
        .ocor-image-btn{ padding:0 6px; font-size:0.85rem; line-height:1.2; height:1.4rem; }
        .section-subtitle { font-size: 0.85rem; color: #9ca3af; }
        .ocor-top-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; width: 100%; }
        .ocor-description-wrap { flex: 1; min-width: 0; }
        .ocor-descricao-search { word-break: break-word; overflow-wrap: anywhere; }
        .status-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 0.25rem 0.7rem; font-size: 0.75rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
        .status-done { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .footer-actions-inline { display: flex; align-items: center; gap: 10px; margin-left: auto; flex-wrap: nowrap; }
        .multi-checkbox-wrapper { display: none; margin-left: 10px; flex-shrink: 0; }
        .select-mode-on .multi-checkbox-wrapper { display: block; }
        .message-help-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 0.75rem; font-size: 0.82rem; color: #64748b; }
        @media (max-width: 768px) { .filter-panel { position: fixed; top: 88px; right: 12px; width: 260px; max-width: 80%; padding: 14px 12px 10px 12px; border-radius: 14px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18); } .filter-panel .form-label { font-size: 0.75rem; } .filter-panel .form-control, .filter-panel .form-select, .ts-wrapper.single .ts-control, .ts-wrapper.single.input-active .ts-control { font-size: 0.8rem; padding: 0.25rem 0.6rem; } .filter-panel-actions { gap: 0.35rem; } .filter-panel-actions .btn { font-size: 0.75rem; padding: 0.3rem 0.5rem; border-radius: 999px; } .card-header-flex { flex-direction: column; align-items: flex-start; gap: 0.6rem; } .ocor-card-footer { flex-direction: column; align-items: flex-start; } .ocor-top-row { flex-direction: column; align-items: flex-start; } .status-chip { align-self: flex-start; } }
        @media (max-width: 992px) { #main { margin-left: 0 !important; } .sidebar-wrapper { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1500; } .page-content { position: relative; z-index: 1; } #app { overflow-x: hidden; } }
        .img-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.82); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.18s ease-out, visibility 0.18s ease-out; }
        .img-overlay.show { opacity: 1; visibility: visible; }
        .img-overlay-inner { position: relative; max-width: 92vw; max-height: 92vh; display: flex; align-items: center; justify-content: center; }
        .img-overlay img { max-width: 80vw; max-height: 80vh; border-radius: 12px; box-shadow: 0 16px 36px rgba(0,0,0,0.6); object-fit: contain; }
        .img-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); border-radius: 999px; border: none; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: #0f172a; background: #f9fafb; box-shadow: 0 10px 26px rgba(15,23,42,0.6); cursor: pointer; }
        .img-nav-btn i { font-size: 1rem; }
        .img-nav-btn-left { left: -22px; }
        .img-nav-btn-right { right: -22px; }
        @media (max-width: 576px) { .img-overlay-inner { max-width: 94vw; max-height: 86vh; } .img-overlay img { max-width: 86vw; max-height: 70vh; } .img-nav-btn-left { left: -10px; } .img-nav-btn-right { right: -10px; } }
        </style>
    </head>
    <body>
    <div id="app">
    <?php include "menu.php"; ?>
    <div id="main">
    <header class="mb-3 d-flex align-items-center"><a href="#" class="burger-btn d-block d-xl-none me-2"><i class="bi bi-justify fs-3"></i></a></header>

    <div class="page-heading-custom mb-3">
        <h3 class="mb-1"><?= $isAdmin ? 'Gerir intervenções de ocorrências' : 'As minhas intervenções de ocorrências' ?></h3>
        <p class="text-subtitle text-muted mb-0"><?= $isAdmin ? 'Selecione uma ou várias ocorrências e atribua ao funcionário.' : 'Veja apenas as ocorrências atribuídas a si.' ?></p>
    </div>

    <div class="page-content">
    <section class="section">
        <div class="card card-main position-relative">
            <div class="card-header"><div class="card-header-flex"><div><h4 class="mb-1">Ocorrências registadas</h4><span class="section-subtitle">Use os filtros para encontrar rapidamente tipos ou tarefas específicas.</span></div><div class="d-flex align-items-center gap-2 flex-wrap justify-content-end top-actions"><?php if ($isAdmin): ?><button id="toggleSelectMode" class="btn btn-outline-secondary d-flex align-items-center top-action-btn" type="button"><i class="bi bi-check2-square me-1"></i><span>Modo seleção</span></button><button id="openAssignModalBtn" class="btn btn-primary d-none" type="button" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-send me-1"></i><span>Atribuir selecionadas</span></button><?php endif; ?><button id="openFilterPanel" class="btn btn-outline-secondary d-flex align-items-center top-action-btn" type="button"><i class="bi bi-funnel-fill me-1"></i><span>Filtrar</span></button></div></div></div>
            <?php if (!empty($_GET['assigned'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Ocorrências atribuídas, notificação única criada e lembrete automático ativado.</div></div><?php endif; ?>
            <?php if (!empty($_GET['saved'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Intervenção atualizada com sucesso.</div></div><?php endif; ?>
            <?php if (!empty($_GET['report_sent'])): ?><div class="px-3 pt-3"><div class="alert alert-success mb-0">Relatório enviado ao administrador com sucesso.</div></div><?php endif; ?>
            <?php if ($error): ?><div class="px-3 pt-3"><div class="alert alert-danger mb-0"><?= htmlspecialchars($error) ?></div></div><?php endif; ?>

            <div id="filterPanel" class="filter-panel d-none"><form id="ocorFilterForm"><div class="mb-2"><label for="filterTipo" class="form-label">Tipo de intervenção</label><select id="filterTipo" class="form-select js-nice-select"><option value="">Todos</option><?php if ($tipos_result && $tipos_result->num_rows > 0): ?><?php while ($row = $tipos_result->fetch_assoc()): ?><?php if (!empty($row['tipo_intervencao'])): $val = strtolower($row['tipo_intervencao']); ?><option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($row['tipo_intervencao']) ?></option><?php endif; ?><?php endwhile; ?><?php endif; ?></select></div><div class="mb-2"><label for="filterTarefa" class="form-label">Tarefa</label><select id="filterTarefa" class="form-select js-nice-select"><option value="">Todas</option><?php if ($estados_result && $estados_result->num_rows > 0): ?><?php while ($row = $estados_result->fetch_assoc()): ?><?php if (!empty($row['estado'])): $val = strtolower($row['estado']); ?><option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($row['estado']) ?></option><?php endif; ?><?php endwhile; ?><?php endif; ?></select></div><div class="mb-2"><label class="form-label">Estado rápido</label><div class="d-flex gap-2 flex-wrap" id="statusQuickButtons"><button type="button" class="btn btn-sm btn-outline-secondary quick-status-btn active" data-status="">Todos</button><button type="button" class="btn btn-sm btn-outline-success quick-status-btn" data-status="feito">Feitos</button><button type="button" class="btn btn-sm btn-outline-warning quick-status-btn" data-status="pendente">Pendentes</button></div><input type="hidden" id="filterStatus" value=""></div><div class="mb-2"><label for="filterData" class="form-label">Data da ocorrência</label><input type="date" class="form-control" id="filterData"></div><div class="filter-panel-actions"><button type="button" class="btn btn-light btn-sm" id="clearFilters">Limpar</button><button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">Cancelar</button><button type="submit" class="btn btn-primary btn-sm">Aplicar</button></div></form></div>

            <div class="card-body">
                <?php if ($ocorrencias && $ocorrencias->num_rows > 0): ?>
                    <div class="container-fluid"><div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="ocorList">
                    <?php while ($ocor = $ocorrencias->fetch_assoc()): ?>
                        <?php
                            $dataOcorrenciaRaw = $ocor['data_ocorrencia'];
                            $textoDataOcorrencia = $dataOcorrenciaRaw ? date('d/m/Y', strtotime($dataOcorrenciaRaw)) : 'Sem data';
                            $dataOcorrenciaAttr = $dataOcorrenciaRaw ? date('Y-m-d', strtotime($dataOcorrenciaRaw)) : '';
                            $textoCriadoEm = date('Y-m-d H:i', strtotime($ocor['criado_em']));
                            $scheduledText = !empty($ocor['scheduled_for']) ? date('d/m/Y H:i', strtotime($ocor['scheduled_for'])) : '';
                            $assignedName = !empty($ocor['assigned_to_name']) ? $ocor['assigned_to_name'] : ($ocor['assigned_to_username'] ?? '');
                            $estadoAtual = trim((string)($ocor['estado'] ?? ''));
                            $isDone = isEstadoConcluido($estadoAtual);
                            $completedByName = !empty($ocor['completed_by_name']) ? $ocor['completed_by_name'] : (!empty($ocor['completed_by_username']) ? $ocor['completed_by_username'] : '');
                            $imgs = [];
                            if (!empty($ocor['imagem'])) {
                                $nomeImagem = basename($ocor['imagem']);
                                $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . '/PAP/uploads/ocorrencias/' . $nomeImagem;
                                if (file_exists($caminhoFisico)) {
                                    $imgs[] = '/PAP/uploads/ocorrencias/' . $nomeImagem;
                                }
                            }
                            $imgsJson = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="col ocor-card-container"><div class="ocor-card"><div class="ocor-card-body"><div class="ocor-top-row"><div class="ocor-line ocor-description-wrap"><span class="ocor-label">Descrição:</span> <span class="ocor-value ocor-descricao-search"><?= htmlspecialchars($ocor['descricao']) ?></span></div><?php if ($isDone): ?><span class="status-chip status-done ocor-status-search">Feito</span><?php else: ?><span class="status-chip status-pending ocor-status-search">Pendente</span><?php endif; ?></div><div class="ocor-line"><span class="ocor-label">Local:</span> <span class="ocor-value ocor-local-search"><?= $ocor['place_name'] ? htmlspecialchars($ocor['place_name']) : 'Sem nome' ?></span></div><div class="ocor-line"><span class="ocor-label">Latitude/Longitude:</span> <span class="ocor-value"><?= htmlspecialchars($ocor['latitude']) ?>, <?= htmlspecialchars($ocor['longitude']) ?></span></div><div class="ocor-line"><span class="ocor-label">Tipo de Intervenção:</span> <span class="ocor-value ocor-tipo-search"><?= htmlspecialchars($ocor['tipo_intervencao'] ?: 'Nenhuma') ?></span></div><div class="ocor-line"><span class="ocor-label">Tarefa:</span> <span class="ocor-value ocor-tarefa-search"><?= htmlspecialchars($ocor['estado']) ?></span></div><?php if ($isDone && $completedByName !== ''): ?><div class="ocor-line"><span class="ocor-label">Concluído por:</span> <span class="ocor-value"><?= htmlspecialchars($completedByName) ?><?= (isset($ocor['completed_by_is_admin']) && (int)$ocor['completed_by_is_admin'] === 1) ? ' (Administrador)' : ' (Funcionário)' ?></span></div><?php endif; ?><div class="ocor-line"><span class="ocor-label">Data da ocorrência:</span> <span class="ocor-value ocor-data-search"><?= $textoDataOcorrencia ?></span></div><?php if (!empty($assignedName)): ?><div class="ocor-line"><span class="ocor-label">Funcionário:</span> <span class="ocor-value"><?= htmlspecialchars($assignedName) ?></span></div><?php else: ?><div class="ocor-line"><span class="ocor-label">Funcionário:</span> <span class="ocor-value text-muted">Ainda não atribuído</span></div><?php endif; ?><?php if (!empty($scheduledText)): ?><div class="ocor-line"><span class="ocor-label">Data/Hora:</span> <span class="ocor-value"><?= htmlspecialchars($scheduledText) ?></span></div><?php endif; ?><?php if (!empty($imgs)): ?><div class="ocor-line ocor-image-line"><span class="ocor-label">Imagem da ocorrência:</span><button type="button" class="btn btn-outline-secondary ocor-image-btn d-inline-flex align-items-center js-open-gallery" data-images='<?= $imgsJson ?>' data-start-index="0"><i class="bi bi-card-image me-1"></i><span>Ver imagem</span></button></div><?php endif; ?></div><div class="ocor-card-footer" data-data-ocorrencia="<?= htmlspecialchars($dataOcorrenciaAttr) ?>"><span>Criado em: <?= htmlspecialchars($textoCriadoEm) ?></span><div class="footer-actions-inline"><form method="post" action="index.php?evora=editarintervencaoocorrencias" class="d-inline"><input type="hidden" name="id" value="<?= (int)$ocor['id'] ?>"><button type="submit" class="btn btn-sm btn-primary">Editar</button></form><?php if ($isAdmin): ?><div class="form-check mb-0 multi-checkbox-wrapper"><input class="form-check-input ocor-checkbox" type="checkbox" value="<?= (int)$ocor['id'] ?>" id="ocorChk<?= (int)$ocor['id'] ?>"><label class="form-check-label" for="ocorChk<?= (int)$ocor['id'] ?>">Selecionar</label></div><?php endif; ?></div></div></div></div>
                    <?php endwhile; ?>
                    </div></div>
                    <nav id="paginationNav" aria-label="Paginação de ocorrências" class="mt-3 mb-1"><ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul></nav>
                <?php else: ?>
                    <div class="alert alert-info text-center mb-0"><?= $isAdmin ? 'Nenhuma ocorrência registada.' : 'Não tem ocorrências atribuídas.' ?></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    </div>
    </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px; border:0; box-shadow:0 18px 45px rgba(15,23,42,0.18);"><form method="post" id="assignForm"><div class="modal-header border-0 pb-2"><h5 class="modal-title">Enviar intervenção</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body pt-2"><div class="mb-3"><label class="form-label fw-semibold">Funcionário</label><select name="assigned_to_user_id" class="form-select js-nice-select" required><option value="">Selecione</option><?php foreach ($workers as $worker): ?><option value="<?= (int)$worker['id'] ?>"><?= htmlspecialchars($worker['display_name']) ?></option><?php endforeach; ?></select></div><div class="alert alert-primary py-2 mb-3" style="font-size: 0.85rem;"><strong>1. Data/Hora para realizar o trabalho</strong><br>Esta data indica quando o funcionário deve ir ao local para resolver a ocorrência.</div><div class="row"><div class="col-6 mb-3"><label class="form-label fw-semibold">Data para realizar o trabalho</label><input type="date" name="assign_date" class="form-control"></div><div class="col-6 mb-3"><label class="form-label fw-semibold">Hora para realizar o trabalho</label><input type="time" name="assign_time" class="form-control"></div></div><div class="alert alert-warning py-2 mb-3" style="font-size: 0.85rem;"><strong>2. Data/Hora para enviar a notificação</strong><br>Se deixar vazio, a notificação aparece imediatamente ao funcionário.</div><div class="row"><div class="col-6 mb-3"><label class="form-label fw-semibold">Data para enviar notificação</label><input type="date" name="notify_date" class="form-control"></div><div class="col-6 mb-3"><label class="form-label fw-semibold">Hora para enviar notificação</label><input type="time" name="notify_time" class="form-control"></div></div><div class="alert alert-info py-2 mb-3" style="font-size: 0.85rem;"><strong>Lembrete automático:</strong><br>Depois da primeira notificação, o sistema irá enviar um novo lembrete a cada 5 dias, até a intervenção ser marcada como concluída.</div><div class="mb-3"><label class="form-label fw-semibold">Mensagem para o funcionário</label><textarea name="mensagem_funcionario" class="form-control" rows="3" placeholder="Ex: Verificar o local, resolver a ocorrência, fazer poda/corte ou avaliar a situação."></textarea><small class="text-muted">Esta mensagem será enviada com a notificação e ficará visível nos detalhes.</small></div><div class="message-help-box mb-3"><strong>Exemplo:</strong><br>Trabalho: 30/04/2026 às 10:00<br>Notificação: 29/04/2026 às 18:00<br>Resultado: o funcionário recebe o aviso no dia 29/04 às 18:00 para realizar o trabalho no dia 30/04 às 10:00.</div><div class="small text-muted mb-2" id="selectedSummaryModal">0 cartões selecionados.</div><div id="selectedHiddenInputs"></div><div class="small text-muted">Se deixar data/hora vazias, a intervenção fica disponível imediatamente.</div></div><div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" name="assign_selected" class="btn btn-primary">Enviar intervenção</button></div></form></div></div></div>
    <?php endif; ?>

    <div id="imgOverlay" class="img-overlay" aria-hidden="true"><div class="img-overlay-inner"><button type="button" class="img-nav-btn img-nav-btn-left" id="imgPrevBtn"><i class="bi bi-chevron-left"></i></button><img id="imgOverlayImg" src="" alt="Imagem"><button type="button" class="img-nav-btn img-nav-btn-right" id="imgNextBtn"><i class="bi bi-chevron-right"></i></button></div></div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-nice-select').forEach(function (el) { if (!el.tomselect) { new TomSelect(el, { maxItems: 1, allowEmptyOption: true, create: false, plugins: { clear_button: { title: 'Limpar seleção' } } }); } });
        const openFilterPanelBtn  = document.getElementById('openFilterPanel');
        const filterPanel         = document.getElementById('filterPanel');
        const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
        const clearFiltersBtn     = document.getElementById('clearFilters');
        const filterForm          = document.getElementById('ocorFilterForm');
        const imgOverlay    = document.getElementById('imgOverlay');
        const imgOverlayImg = document.getElementById('imgOverlayImg');
        const imgPrevBtn    = document.getElementById('imgPrevBtn');
        const imgNextBtn    = document.getElementById('imgNextBtn');
        const perPage         = <?= (int)$per_page ?>;
        const cards           = Array.from(document.querySelectorAll('.ocor-card-container'));
        const paginationNav   = document.getElementById('paginationNav');
        const paginationLinks = document.getElementById('paginationLinks');
        const filterTipoEl   = document.getElementById('filterTipo');
        const filterTarefaEl = document.getElementById('filterTarefa');
        const filterDataEl   = document.getElementById('filterData');
        const filterStatusEl = document.getElementById('filterStatus');
        const quickStatusButtons = document.querySelectorAll('.quick-status-btn');
        let currentPage = 1;
        let currentImages = [];
        let currentIndex  = 0;
        function openGallery(images, startIndex) { currentImages = images; currentIndex = startIndex || 0; if (!currentImages.length) return; imgOverlayImg.src = currentImages[currentIndex]; imgOverlay.classList.add('show'); imgOverlay.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
        function closeGallery() { imgOverlay.classList.remove('show'); imgOverlayImg.src = ''; currentImages = []; currentIndex = 0; imgOverlay.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
        function showNext(delta) { if (!currentImages.length) return; currentIndex = (currentIndex + delta + currentImages.length) % currentImages.length; imgOverlayImg.src = currentImages[currentIndex]; }
        document.querySelectorAll('.js-open-gallery').forEach(function (btn) { btn.addEventListener('click', function () { let images = []; try { images = JSON.parse(this.getAttribute('data-images')); } catch (e) { images = []; } const startIndex = parseInt(this.getAttribute('data-start-index') || '0', 10) || 0; openGallery(images, startIndex); }); });
        imgPrevBtn?.addEventListener('click', function (e) { e.stopPropagation(); showNext(-1); });
        imgNextBtn?.addEventListener('click', function (e) { e.stopPropagation(); showNext(1); });
        imgOverlay?.addEventListener('click', function (e) { if (e.target === imgOverlay) closeGallery(); });
        document.addEventListener('keydown', function (e) { if (!imgOverlay?.classList.contains('show')) return; if (e.key === 'Escape') closeGallery(); if (e.key === 'ArrowLeft') showNext(-1); if (e.key === 'ArrowRight') showNext(1); });
        function getFilteredCards() { return cards.filter(card => card.dataset.filtered !== 'true'); }
        function renderPagination(totalPages, page) { if (!paginationNav || !paginationLinks) return; if (totalPages <= 1) { paginationNav.style.display = 'none'; paginationLinks.innerHTML = ''; return; } paginationNav.style.display = ''; let html = ''; html += `<li class="page-item ${page === 1 ? 'disabled' : ''}"><a href="#" class="page-link" data-page="${page - 1}">« Anterior</a></li>`; for (let p = 1; p <= totalPages; p++) html += `<li class="page-item ${p === page ? 'active' : ''}"><a href="#" class="page-link" data-page="${p}">${p}</a></li>`; html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}"><a href="#" class="page-link" data-page="${page + 1}">Próxima »</a></li>`; paginationLinks.innerHTML = html; }
        function showPage(page) { const filteredCards = getFilteredCards(); const totalVisible = filteredCards.length; const totalPages = Math.ceil(totalVisible / perPage) || 1; if (page < 1) page = 1; if (page > totalPages) page = totalPages; currentPage = page; cards.forEach(function (card) { card.style.display = 'none'; }); const start = (page - 1) * perPage; const end = start + perPage; filteredCards.forEach(function (card, index) { if (index >= start && index < end) card.style.display = ''; }); renderPagination(totalPages, currentPage); }
        function applyFilters() { const tipo = (filterTipoEl?.value || '').trim().toLowerCase(); const tarefa = (filterTarefaEl?.value || '').trim().toLowerCase(); const data = (filterDataEl?.value || '').trim(); const status = (filterStatusEl?.value || '').trim().toLowerCase(); cards.forEach(function (card) { const textTipo = (card.querySelector('.ocor-tipo-search')?.textContent || '').trim().toLowerCase(); const textTarefa = (card.querySelector('.ocor-tarefa-search')?.textContent || '').trim().toLowerCase(); const textStatus = (card.querySelector('.ocor-status-search')?.textContent || '').trim().toLowerCase(); const footerEl = card.querySelector('.ocor-card-footer'); const cardData = footerEl ? (footerEl.getAttribute('data-data-ocorrencia') || '') : ''; const matchesTipo = !tipo || textTipo.includes(tipo); const matchesTarefa = !tarefa || textTarefa.includes(tarefa); const matchesData = !data || cardData === data; const matchesStatus = !status || textStatus === status; card.dataset.filtered = (matchesTipo && matchesTarefa && matchesData && matchesStatus) ? 'false' : 'true'; }); showPage(1); }
        openFilterPanelBtn?.addEventListener('click', function () { filterPanel?.classList.toggle('d-none'); });
        closeFilterPanelBtn?.addEventListener('click', function () { filterPanel?.classList.add('d-none'); });
        clearFiltersBtn?.addEventListener('click', function () { filterForm?.reset(); if (filterTipoEl?.tomselect) filterTipoEl.tomselect.clear(); if (filterTarefaEl?.tomselect) filterTarefaEl.tomselect.clear(); if (filterDataEl) filterDataEl.value = ''; if (filterStatusEl) filterStatusEl.value = ''; quickStatusButtons.forEach(function (b) { b.classList.remove('active'); if ((b.dataset.status || '') === '') b.classList.add('active'); }); applyFilters(); });
        quickStatusButtons.forEach(function (btn) { btn.addEventListener('click', function () { const selectedStatus = this.dataset.status || ''; filterStatusEl.value = selectedStatus; quickStatusButtons.forEach(function (b) { b.classList.remove('active'); }); this.classList.add('active'); applyFilters(); }); });
        filterForm?.addEventListener('submit', function (e) { e.preventDefault(); applyFilters(); filterPanel?.classList.add('d-none'); });
        document.addEventListener('click', function (e) { if (filterPanel && openFilterPanelBtn && !filterPanel.classList.contains('d-none')) { if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) filterPanel.classList.add('d-none'); } });
        paginationLinks?.addEventListener('click', function (e) { const link = e.target.closest('.page-link'); if (!link) return; const parentLi = link.closest('.page-item'); if (parentLi && parentLi.classList.contains('disabled')) { e.preventDefault(); return; } e.preventDefault(); const page = parseInt(link.dataset.page, 10); if (!isNaN(page)) showPage(page); });
        <?php if ($isAdmin): ?>
        const toggleSelectModeBtn  = document.getElementById('toggleSelectMode');
        const openAssignModalBtn   = document.getElementById('openAssignModalBtn');
        const selectedSummaryModal = document.getElementById('selectedSummaryModal');
        const selectedHiddenInputs = document.getElementById('selectedHiddenInputs');
        const ocorList             = document.getElementById('ocorList');
        let selectionMode = false;
        function getCheckedBoxes() { return Array.from(document.querySelectorAll('.ocor-checkbox')).filter(chk => chk.checked); }
        function refreshSelectedSummary() { const checked = getCheckedBoxes(); if (checked.length > 0 && selectionMode) openAssignModalBtn.classList.remove('d-none'); else openAssignModalBtn.classList.add('d-none'); if (selectedSummaryModal) selectedSummaryModal.textContent = checked.length + ' cartões selecionados.'; if (selectedHiddenInputs) { selectedHiddenInputs.innerHTML = ''; checked.forEach(function (chk) { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'selected_ids[]'; input.value = chk.value; selectedHiddenInputs.appendChild(input); }); } }
        function updateSelectionUI() { if (selectionMode) { ocorList?.classList.add('select-mode-on'); toggleSelectModeBtn?.classList.add('active'); } else { ocorList?.classList.remove('select-mode-on'); document.querySelectorAll('.ocor-checkbox').forEach(function (chk) { chk.checked = false; }); toggleSelectModeBtn?.classList.remove('active'); openAssignModalBtn?.classList.add('d-none'); } refreshSelectedSummary(); }
        toggleSelectModeBtn?.addEventListener('click', function () { selectionMode = !selectionMode; updateSelectionUI(); });
        document.querySelectorAll('.ocor-checkbox').forEach(function (chk) { chk.addEventListener('change', refreshSelectedSummary); });
        openAssignModalBtn?.addEventListener('click', function (e) { const checked = getCheckedBoxes(); if (!checked.length) { e.preventDefault(); alert('Selecione pelo menos um cartão.'); } });
        <?php endif; ?>
        const burgerBtn = document.querySelector('.burger-btn');
        burgerBtn?.addEventListener('click', function () { filterPanel?.classList.add('d-none'); });
        if (cards.length > 0) { cards.forEach(function (card) { card.dataset.filtered = 'false'; }); showPage(1); } else if (paginationNav) paginationNav.style.display = 'none';
    });
    </script>
    </body>
    </html>
    <?php
    exit();
}

/* =========================================================
   PARTE 2 – EDITAR INTERVENÇÃO DA OCORRÊNCIA
========================================================= */
$stmt = $conn->prepare("
    SELECT o.*,
           u1.username AS assigned_to_username,
           u1.name AS assigned_to_name,
           u2.username AS completed_by_username,
           u2.name AS completed_by_name,
           u2.is_admin AS completed_by_is_admin
    FROM ocorrencias o
    LEFT JOIN users u1 ON u1.id = o.assigned_to_user_id
    LEFT JOIN users u2 ON u2.id = o.completed_by_user_id
    WHERE o.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$ocorrencia = $res->fetch_assoc();
$stmt->close();

if (!$ocorrencia) {
    ?>
    <!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8" /><title>Ocorrência não encontrada</title><link rel="stylesheet" href="assets/css/bootstrap.css" /><link rel="stylesheet" href="assets/css/app.css" /></head><body><div class="container mt-4"><div class="alert alert-danger mt-5 mx-auto text-center" style="max-width:500px;">Ocorrência não encontrada.</div></div></body></html>
    <?php
    exit();
}

$canView = $isAdmin || ((int)$ocorrencia['assigned_to_user_id'] === $userId);
if (!$canView) {
    ?>
    <!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8" /><title>Acesso negado</title><link rel="stylesheet" href="assets/css/bootstrap.css" /><link rel="stylesheet" href="assets/css/app.css" /></head><body><div class="container mt-4"><div class="alert alert-warning mt-5 mx-auto text-center" style="max-width:540px;">Não tem permissão para abrir esta ocorrência.</div></div></body></html>
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
    FROM ocorrencia_relatorios r
    LEFT JOIN users u ON u.id = r.funcionario_id
    WHERE r.ocorrencia_id = ?
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

$dataOcorrenciaText = '';
if (!empty($ocorrencia['data_ocorrencia'])) {
    $ts = strtotime($ocorrencia['data_ocorrencia']);
    $dataOcorrenciaText = $ts ? date('Y-m-d', $ts) : $ocorrencia['data_ocorrencia'];
}

$imagemUrl = '';
if (!empty($ocorrencia['imagem'])) {
    $nomeImagem = basename($ocorrencia['imagem']);
    $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . '/PAP/uploads/ocorrencias/' . $nomeImagem;
    if (file_exists($caminhoFisico)) {
        $imagemUrl = '/PAP/uploads/ocorrencias/' . $nomeImagem;
    }
}

$assignedName = !empty($ocorrencia['assigned_to_name']) ? $ocorrencia['assigned_to_name'] : ($ocorrencia['assigned_to_username'] ?? 'Não atribuído');
$estadoAtual = trim((string)($ocorrencia['estado'] ?? ''));
$isDone = isEstadoConcluido($estadoAtual);
$mensagemFuncionarioDetalhe = trim((string)($ocorrencia['mensagem_funcionario'] ?? ''));

$completedByName = !empty($ocorrencia['completed_by_name']) ? $ocorrencia['completed_by_name'] : (!empty($ocorrencia['completed_by_username']) ? $ocorrencia['completed_by_username'] : '');
$completedByIsAdmin = isset($ocorrencia['completed_by_is_admin']) ? (int)$ocorrencia['completed_by_is_admin'] === 1 : false;

$statusLabel = 'Ainda não concluído';
$statusClass = 'pending';
$statusIcon  = 'bi-hourglass-split';

if ($isDone) {
    if ($completedByName !== '') {
        $statusLabel = $completedByIsAdmin ? 'Feito pelo administrador' : 'Feito pelo funcionário';
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
    <title>Editar Intervenção da Ocorrência</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body { height: 100%; }
        body { font-family: 'Nunito', sans-serif !important; margin: 0; background: linear-gradient(135deg, #eef2ff, #f9fafb); }
        #app, #main { min-height: 100%; }
        .page-content { min-height: calc(100vh - 56px); padding: 24px 12px 32px 12px; }
        .edit-container { width: 100%; max-width: 1180px; margin: 0 auto; }
        .page-heading-custom { margin-bottom: 16px; }
        .page-heading-custom h3 { font-weight: 700; font-size: 1.6rem; margin-bottom: 4px; }
        .page-heading-custom p { font-size: 0.95rem; color: #6b7280; margin-bottom: 0; }
        .edit-card { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); padding: 20px 20px 18px 20px; border: 1px solid #e5e7eb; }
        .section-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-top: 4px; margin-bottom: 6px; }
        .field-label { font-weight: 600; color: #4b5563; font-size: 0.9rem; }
        .form-control, .form-select { border-radius: 10px; }
        .readonly-input { background-color: #f9fafb !important; color: #374151; }
        .edit-two-cols { display: grid; grid-template-columns: 1.1fr 1.1fr; gap: 18px; }
        .edit-map-wrapper { margin-top: 8px; border-radius: 14px; background: #f9fafb; padding: 10px; border: 1px solid #e5e7eb; }
        #map { height: 330px; width: 100%; border-radius: 10px; }
        .edit-map-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .edit-map-info { font-size: 0.8rem; color: #6b7280; }
        .btn-main { font-weight: 600; letter-spacing: .02em; }
        .actions-row-desktop { margin-top: 16px; }
        .btn-search-round { border-radius: 999px; padding-inline: 0.7rem; border-color: #d1d5db; background-color: #f9fafb; }
        .btn-search-round i { font-size: 1rem; }
        .upload-box { border: 1px dashed #cbd5f5; border-radius: 10px; padding: 0.65rem 0.75rem; background-color: #f9fafb; }
        .upload-hint { font-size: 0.8rem; color: #6b7280; }
        .image-preview-box { margin-top: 10px; }
        .image-preview-box img { max-width: 220px; max-height: 220px; border-radius: 8px; border: 1px solid #e5e7eb; display: block; }
        .worker-chip { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 700; }
        .worker-chip.done { background: #dcfce7; color: #166534; }
        .worker-chip.pending { background: #fef3c7; color: #92400e; }
        .ts-wrapper .ts-control { background-color: #ffffff !important; border-radius: 10px; padding: 0.375rem 0.75rem; border-color: #d1d5db; }
        .ts-wrapper .ts-control:focus { box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25); border-color: #2563eb; }
        .ts-dropdown { background-color: #ffffff !important; }
        .admin-message-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 14px; padding: 16px 18px; margin-bottom: 18px; color: #1e3a8a; text-align: left; }
        .admin-message-box-title { font-weight: 800; font-size: 0.95rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: #1e3a8a; text-align: left; }
        .admin-message-box-text { background: #ffffff; border: 1px solid #dbeafe; border-radius: 10px; padding: 12px 14px; margin: 0; font-size: 0.95rem; line-height: 1.55; color: #111827; text-align: left !important; white-space: normal; width: 100%; }
        .reports-section { margin-top: 10px; }
        .reports-header-box { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .reports-header-title { display: flex; align-items: center; gap: 10px; }
        .reports-header-icon { width: 38px; height: 38px; border-radius: 10px; background: #f1f5f9; color: #435ebe; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .reports-header-title h5 { margin: 0; font-weight: 800; color: #111827; font-size: 1rem; }
        .reports-header-title small { color: #6b7280; font-size: 0.84rem; }
        .reports-count-badge { background: #f8fafc; border: 1px solid #e5e7eb; color: #374151; padding: 0.4rem 0.75rem; border-radius: 999px; font-weight: 700; font-size: 0.8rem; white-space: nowrap; }
        .report-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
        .report-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.07); transition: transform 0.15s ease, box-shadow 0.15s ease; min-height: 240px; height: 100%; display: flex; flex-direction: column; }
        .report-card:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(15, 23, 42, 0.11); }
        .report-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; min-height: 46px; flex-shrink: 0; }
        .report-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .report-avatar { width: 40px; height: 40px; border-radius: 50%; background: #eef2ff; color: #435ebe; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
        .report-author { font-weight: 800; color: #111827; font-size: 0.92rem; line-height: 1.2; word-break: break-word; }
        .report-subtitle { font-size: 0.78rem; color: #6b7280; margin-top: 2px; line-height: 1.2; }
        .report-date { background: #f9fafb; border: 1px solid #e5e7eb; color: #6b7280; border-radius: 999px; padding: 0.32rem 0.65rem; font-size: 0.74rem; font-weight: 700; white-space: nowrap; line-height: 1.1; flex-shrink: 0; }
        .report-message-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 13px 14px; flex: 1; height: 150px; min-height: 150px; max-height: 150px; overflow-y: auto; display: flex; flex-direction: column; align-items: stretch; justify-content: flex-start; text-align: left; }
        .report-message-label { display: flex; align-items: center; justify-content: flex-start; gap: 7px; font-size: 0.76rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .035em; margin: 0 0 10px 0; padding: 0; line-height: 1.2; flex-shrink: 0; text-align: left; }
        .report-message { width: 100%; margin: 0; padding: 0; font-size: 0.92rem; color: #111827; white-space: pre-wrap; overflow-wrap: anywhere; word-break: normal; line-height: 1.55; text-align: left; align-self: flex-start; }
        .empty-report-box { background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 16px; color: #64748b; display: flex; align-items: center; gap: 12px; }
        .empty-report-box i { font-size: 1.4rem; color: #94a3b8; }
        @media (max-width: 1200px) { .report-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 992px) { #main { margin-left: 0 !important; } .sidebar-wrapper { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1050; } #app { overflow-x: hidden; } }
        @media (max-width: 768px) { .edit-card { padding: 16px 14px 16px 14px; } .edit-two-cols { grid-template-columns: 1fr; } .actions-row-desktop { margin-top: 10px; } #map { height: 280px; } .report-grid { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { .report-top { flex-direction: column; } .report-date { width: fit-content; } .report-message-box { height: 160px; min-height: 160px; max-height: 160px; } }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>
    <div id="main">
        <header class="mb-3 d-flex align-items-center"><a href="#" class="burger-btn d-block d-xl-none me-2"><i class="bi bi-justify fs-3"></i></a></header>
        <div class="page-content"><div class="edit-container">
            <div class="page-heading-custom d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h3>Ocorrência de Espaço Verde</h3><p>Atualize a tarefa da intervenção da ocorrência selecionada.</p></div><span class="worker-chip <?= htmlspecialchars($statusClass) ?>"><i class="bi <?= htmlspecialchars($statusIcon) ?>"></i><?= htmlspecialchars($statusLabel) ?></span></div>
            <div class="edit-card">
                <?php if ($error): ?><div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if (!empty($_GET['saved'])): ?><div class="alert alert-success mb-3">Intervenção atualizada com sucesso.</div><?php endif; ?>
                <?php if (!empty($_GET['report_sent'])): ?><div class="alert alert-success mb-3">Relatório enviado ao administrador com sucesso.</div><?php endif; ?>
                <div class="mb-3"><span class="badge bg-light text-dark border">Funcionário: <?= htmlspecialchars($assignedName) ?></span></div>

                <?php if ($mensagemFuncionarioDetalhe !== ''): ?><div class="admin-message-box"><div class="admin-message-box-title"><i class="bi bi-chat-left-text-fill"></i>Mensagem do administrador</div><div class="admin-message-box-text"><?= nl2br(htmlspecialchars($mensagemFuncionarioDetalhe)) ?></div></div><?php endif; ?>
                <?php if ($isDone && $completedByName !== ''): ?><div class="mb-3"><span class="badge bg-light text-dark border">Concluído por: <?= htmlspecialchars($completedByName) ?><?= $completedByIsAdmin ? ' (Administrador)' : ' (Funcionário)' ?></span></div><?php endif; ?>

                <form method="post" autocomplete="off" id="editOccurrenceForm">
                    <input type="hidden" name="id" value="<?= (int)$ocorrencia['id'] ?>">
                    <textarea name="report_message" id="report_message_hidden_for_save" class="d-none"></textarea>
                    <div class="edit-two-cols mb-3">
                        <div>
                            <div class="section-label">Detalhes da ocorrência</div>
                            <div class="mb-3"><label class="field-label mb-1">Descrição da Ocorrência</label><textarea id="descricao" rows="4" class="form-control readonly-input" readonly><?= htmlspecialchars($ocorrencia['descricao']) ?></textarea></div>
                            <div class="section-label">Tipologia e tarefa</div>
                            <div class="row"><div class="col-md-6"><div class="mb-3"><label class="field-label mb-1">Tipo de Intervenção</label><select id="tipo_intervencao" class="form-select js-nice-select" disabled><option value="">Selecione uma opção</option><?php foreach ($intervencoes as $interv) { ?><option value="<?= htmlspecialchars($interv) ?>" <?= ($ocorrencia['tipo_intervencao'] == $interv ? 'selected' : '') ?>><?= htmlspecialchars($interv) ?></option><?php } ?><?php if (!in_array($ocorrencia['tipo_intervencao'], $intervencoes, true) && !empty($ocorrencia['tipo_intervencao'])): ?><option value="<?= htmlspecialchars($ocorrencia['tipo_intervencao']) ?>" selected><?= htmlspecialchars($ocorrencia['tipo_intervencao']) ?></option><?php endif; ?></select></div></div><div class="col-md-6"><div class="mb-3"><label class="field-label mb-1">Tarefa</label><select name="estado" id="estado_select_visible" class="form-select js-nice-select" required><option value="">Selecione uma opção</option><?php foreach ($tarefas as $tarefa_opt) { ?><option value="<?= htmlspecialchars($tarefa_opt) ?>" <?= ($ocorrencia['estado'] == $tarefa_opt ? 'selected' : '') ?>><?= htmlspecialchars($tarefa_opt) ?></option><?php } ?></select></div></div></div>
                            <div class="mb-3"><label class="field-label mb-1">Data da Ocorrência</label><input type="date" class="form-control readonly-input" value="<?= htmlspecialchars($dataOcorrenciaText) ?>" readonly></div>
                            <div class="section-label">Fotografia</div><div class="mb-3"><div class="upload-box"><label class="field-label mb-1">Imagem registada</label><?php if ($imagemUrl !== ''): ?><div class="image-preview-box"><img src="<?= htmlspecialchars($imagemUrl) ?>" alt="Imagem da ocorrência"></div><?php else: ?><div class="upload-hint">Sem imagem associada.</div><?php endif; ?></div></div>
                            <div class="actions-row-desktop"><button type="submit" name="edit_intervencao_ocorrencia" class="btn btn-primary btn-main w-100">Guardar Alterações</button></div>
                        </div>
                        <div><div class="section-label">Localização no mapa</div><div class="mb-3"><label class="field-label mb-1">Nome do Local</label><div class="input-group"><input type="text" id="place_name" class="form-control readonly-input" value="<?= htmlspecialchars($ocorrencia['place_name']) ?>" readonly><button type="button" class="btn btn-outline-secondary btn-search-round" id="search-location" title="Centrar localização"><i class="bi bi-search"></i></button></div></div><div class="row mb-2"><div class="col-6"><label class="field-label mb-1">Latitude</label><input type="text" id="latitude" class="form-control readonly-input" value="<?= htmlspecialchars($ocorrencia['latitude']) ?>" readonly></div><div class="col-6"><label class="field-label mb-1">Longitude</label><input type="text" id="longitude" class="form-control readonly-input" value="<?= htmlspecialchars($ocorrencia['longitude']) ?>" readonly></div></div><div class="edit-map-wrapper"><div class="edit-map-top"><span class="edit-map-info">Visualização da localização atual da ocorrência.</span></div><div id="map"></div></div></div>
                    </div>
                </form>

                <hr class="my-4">
                <div class="reports-section"><div class="reports-header-box"><div class="reports-header-title"><div class="reports-header-icon"><i class="bi bi-chat-square-text-fill"></i></div><div><h5>Relatórios do funcionário</h5><small>Mensagens enviadas pelo funcionário sobre esta intervenção.</small></div></div><div class="reports-count-badge"><?= count($relatorios) ?> relatório(s)</div></div><?php if (!empty($relatorios)): ?><div class="report-grid"><?php foreach ($relatorios as $relatorio): ?><div class="report-card"><div class="report-top"><div class="report-user"><div class="report-avatar"><i class="bi bi-person-circle"></i></div><div><div class="report-author"><?= htmlspecialchars($relatorio['funcionario_nome'] ?? 'Funcionário') ?></div><div class="report-subtitle">Funcionário responsável</div></div></div><div class="report-date"><i class="bi bi-calendar3 me-1"></i><?= !empty($relatorio['criado_em']) ? date('d/m/Y H:i', strtotime($relatorio['criado_em'])) : '—' ?></div></div><div class="report-message-box"><div class="report-message-label"><i class="bi bi-pencil-square"></i>Situação encontrada</div><div class="report-message"><?= nl2br(htmlspecialchars(trim((string)$relatorio['mensagem']))) ?></div></div></div><?php endforeach; ?></div><?php else: ?><div class="empty-report-box"><i class="bi bi-inbox"></i><div><strong>Ainda não existem relatórios.</strong><br><span>Quando o funcionário enviar uma mensagem, ela irá aparecer aqui.</span></div></div><?php endif; ?></div>

                <?php if (!$isAdmin && (int)$ocorrencia['assigned_to_user_id'] === $userId): ?>
                    <hr class="my-4">
                    <form method="post" autocomplete="off" id="sendOccurrenceReportForm"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="estado_from_report_form" id="estado_hidden_for_report" value=""><div class="section-label">Mensagem para o administrador</div><div class="mb-3"><label class="field-label mb-1">Relatório / Situação encontrada</label><textarea name="report_message" id="report_message_visible" class="form-control" rows="4" maxlength="1500" required placeholder="Ex: A árvore está danificada, precisa de poda urgente, o local precisa de intervenção, ou a ocorrência já foi resolvida."></textarea><small class="text-muted">Escreva aqui o que encontrou no local. O administrador irá receber esta mensagem por notificação.</small></div><button type="submit" name="send_occurrence_report" class="btn btn-warning w-100"><i class="bi bi-chat-dots me-1"></i> Enviar relatório ao administrador</button></form>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        if (!el.tomselect) {
            new TomSelect(el, { maxItems: 1, allowEmptyOption: true, create: false, plugins: { clear_button: { title: 'Limpar seleção' } } });
        }
    });

    const editOccurrenceForm = document.getElementById('editOccurrenceForm');
    const sendOccurrenceReportForm = document.getElementById('sendOccurrenceReportForm');
    const visibleReportBox = document.getElementById('report_message_visible');
    const hiddenReportBox = document.getElementById('report_message_hidden_for_save');
    const estadoSelectVisible = document.getElementById('estado_select_visible');
    const estadoHiddenForReport = document.getElementById('estado_hidden_for_report');

    function syncReportToSaveForm() {
        if (visibleReportBox && hiddenReportBox) {
            hiddenReportBox.value = visibleReportBox.value;
        }
    }

    function syncEstadoToReportForm() {
        if (estadoSelectVisible && estadoHiddenForReport) {
            estadoHiddenForReport.value = estadoSelectVisible.tomselect
                ? estadoSelectVisible.tomselect.getValue()
                : estadoSelectVisible.value;
        }
    }

    visibleReportBox?.addEventListener('input', syncReportToSaveForm);
    estadoSelectVisible?.addEventListener('change', syncEstadoToReportForm);

    editOccurrenceForm?.addEventListener('submit', function () {
        syncReportToSaveForm();
    });

    sendOccurrenceReportForm?.addEventListener('submit', function () {
        syncEstadoToReportForm();
    });
});

const evora = [
    <?= is_numeric($ocorrencia['latitude']) ? (float)$ocorrencia['latitude'] : 38.5667 ?>,
    <?= is_numeric($ocorrencia['longitude']) ? (float)$ocorrencia['longitude'] : -7.9 ?>
];

const map = L.map('map', { attributionControl: false }).setView(evora, 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

let marker;

function setMarkerPos(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: false }).addTo(map);
    }
    map.setView([lat, lng], 16);
}

setMarkerPos(evora[0], evora[1]);

document.getElementById('search-location').addEventListener('click', function() {
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;

    if (!lat || !lng) {
        alert('Sem coordenadas disponíveis.');
        return;
    }

    map.setView([parseFloat(lat), parseFloat(lng)], 16);
});
</script>
</body>
</html>
