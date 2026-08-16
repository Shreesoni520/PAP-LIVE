<?php

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

if (isset($_POST['update_info'])) {
    $address = trim($_POST['address']);
    $phone   = trim($_POST['phone']);
    $email   = trim($_POST['email']);

    $current    = $conn->query("SELECT * FROM contact_info LIMIT 1")->fetch_assoc();
    $newAddress = $address !== '' ? $address : ($current['address'] ?? '');
    $newPhone   = $phone   !== '' ? $phone   : ($current['phone']   ?? '');
    $newEmail   = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ($current['email'] ?? '');

    if ($newAddress && $newPhone && $newEmail) {
        if ($current) {
            $stmt = $conn->prepare("UPDATE contact_info SET address=?, phone=?, email=? LIMIT 1");
            $stmt->bind_param("sss", $newAddress, $newPhone, $newEmail);
            if ($stmt->execute()) {
                $success = "Informações de contacto atualizadas com sucesso!";

                $userId  = (int)$_SESSION['user_id'];
                $acao    = 'Contacto atualizado';
                $detalhe = "Morada: $newAddress · Tel: $newPhone · Email: $newEmail";

                $stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)");
                if ($stmtAt) {
                    $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }
            } else {
                $error = "Erro ao atualizar informações de contacto: ".$stmt->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO contact_info (address, phone, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $newAddress, $newPhone, $newEmail);
            if ($stmt->execute()) {
                $success = "Informações de contacto guardadas com sucesso!";

                $userId  = (int)$_SESSION['user_id'];
                $acao    = 'Contacto criado';
                $detalhe = "Morada: $newAddress · Tel: $newPhone · Email: $newEmail";

                $stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)");
                if ($stmtAt) {
                    $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }
            } else {
                $error = "Erro ao guardar informações de contacto: ".$stmt->error;
            }
        }
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    } else {
        $error = "Todos os campos são obrigatórios.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_info'])) {
    $mode = $_POST['mode'] ?? 'single';

    // 1) Exportar PDF (botão btn_pdf)
    if (isset($_POST['btn_pdf']) && $mode === 'multi'
        && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {

        $ids    = array_map('intval', $_POST['delete_ids']);
        $idsStr = implode(',', $ids);

        header('Location: export_pdf.php?tipo=contact&ids=' . urlencode($idsStr));
        exit();
    }

    // 2) Decidir ação de delete
    if (isset($_POST['btn_delete_multi']) && $mode === 'multi'
        && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {

        $action = 'delete_multi';
    } elseif (isset($_POST['delete_id'])) {
        $action = 'delete_single';
    } else {
        $action = '';
    }

    // 2A) Delete múltiplo
    if ($action === 'delete_multi' && $mode === 'multi'
        && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {

        $ids = array_map('intval', $_POST['delete_ids']);
        if (count($ids) > 0) {
            $in    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $stmtInfo = $conn->prepare("SELECT id, name, email, subject FROM contact WHERE id IN ($in)");
            $msgsById = [];
            if ($stmtInfo) {
                $stmtInfo->bind_param($types, ...$ids);
                $stmtInfo->execute();
                $resInfo = $stmtInfo->get_result();
                while ($row = $resInfo->fetch_assoc()) {
                    $msgsById[(int)$row['id']] = $row;
                }
                $stmtInfo->close();
            }

            $stmt = $conn->prepare("DELETE FROM contact WHERE id IN ($in)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $success = "Mensagens apagadas com sucesso!";

                    $userId = (int)$_SESSION['user_id'];
                    foreach ($ids as $idDel) {
                        $acao    = 'Mensagens apagadas (multi)';
                        $detalhe = "ID: $idDel";
                        if (!empty($msgsById[$idDel])) {
                            $mi = $msgsById[$idDel];
                            $detalhe .= " · Nome: ".$mi['name']." · Email: ".$mi['email']." · Assunto: ".$mi['subject'];
                        }

                        $stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)");
                        if ($stmtAt) {
                            $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                            $stmtAt->execute();
                            $stmtAt->close();
                        }
                    }
                } else {
                    $error = "Falha ao apagar mensagens selecionadas: ".$stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Erro na preparação da query de remoção múltipla.";
            }
        }
    }
    // 2B) Delete single
    elseif ($action === 'delete_single' && isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];

        $msgInfo   = null;
        $stmtInfo  = $conn->prepare("SELECT name, email, subject FROM contact WHERE id=?");
        if ($stmtInfo) {
            $stmtInfo->bind_param("i", $delId);
            $stmtInfo->execute();
            $resultInfo = $stmtInfo->get_result();
            $msgInfo    = $resultInfo->fetch_assoc();
            $stmtInfo->close();
        }

        $stmt = $conn->prepare("DELETE FROM contact WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("i", $delId);
            if ($stmt->execute()) {
                $success = "Mensagem apagada com sucesso!";

                $userId  = (int)$_SESSION['user_id'];
                $acao    = 'Mensagem apagada';
                $detalhe = "ID: $delId";
                if ($msgInfo) {
                    $detalhe .= " · Nome: ".$msgInfo['name']." · Email: ".$msgInfo['email']." · Assunto: ".$msgInfo['subject'];
                }

                $stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)");
                if ($stmtAt) {
                    $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }
            } else {
                $error = "Falha ao apagar mensagem: ".$stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Erro na preparação da query de remoção.";
        }
    }

    // Paginação após delete
    $currentPage  = isset($_POST['current_page']) ? max(1, (int)$_POST['current_page']) : 1;
    $itemsOnPage  = isset($_POST['items_on_page']) ? (int)$_POST['items_on_page'] : 0;
    $deletedCount = 0;

    if ($action === 'delete_multi' && !empty($_POST['delete_ids'])) {
        $deletedCount = count($_POST['delete_ids']);
    } elseif ($action === 'delete_single' && isset($_POST['delete_id'])) {
        $deletedCount = 1;
    }

    if ($deletedCount > 0) {
        $resCountAfter = $conn->query("SELECT COUNT(*) AS total FROM contact");
        $rowAfter      = $resCountAfter ? $rowAfter = $resCountAfter->fetch_assoc() : ['total' => 0];
        $total_after   = (int)$rowAfter['total'];

        $per_page    = 6;
        $total_pages = max(1, (int)ceil($total_after / $per_page));

        if ($currentPage > $total_pages || $itemsOnPage === $deletedCount) {
            $goTo = max(1, $currentPage - 1);
        } else {
            $goTo = $currentPage;
        }

        header('Location: ?evora=contact&page=' . $goTo);
        exit();
    }
}

$firstPageTake = 3;
$perPageAfter  = 6;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$totalRes = $conn->query("SELECT COUNT(*) AS total FROM contact");
$totalRow = $totalRes->fetch_assoc();
$total    = (int)$totalRow['total'];

if ($page === 1) {
    $limit  = min($firstPageTake, $total);
    $offset = 0;
} else {
    $limit  = $perPageAfter;
    $offset = $firstPageTake + ($page - 2) * $perPageAfter;
}

$stmtMsg = $conn->prepare("SELECT * FROM contact ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmtMsg->bind_param("ii", $limit, $offset);
$stmtMsg->execute();
$messages = $stmtMsg->get_result();

$remaining       = max(0, $total - $firstPageTake);
$pagesAfterFirst = $remaining > 0 ? ceil($remaining / $perPageAfter) : 0;
$totalPages      = 1 + $pagesAfterFirst;

$infoResult  = $conn->query("SELECT * FROM contact_info LIMIT 1");
$contactInfo = $infoResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contacto - Dashboard</title>

<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/bootstrap.css">
<link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
<link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/app.css">
<link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">

<style>
body,
.sidebar,
.card,
.btn,
h4, h3, h2 {
    font-family: 'Nunito', sans-serif !important;
}
.page-content {
    background-color: #f3f4f6;
}
.card-main {
    border-radius: 18px;
    border: 0;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}
.page-heading h3 {
    font-weight: 800;
}
.page-heading p {
    color: #6b7280;
}
.section-subtitle {
    font-size: 0.85rem;
    color: #9ca3af;
}
#toggleSelectMode,
#pdfButton {
    border-radius: 999px;
    padding: 0.4rem 1rem;
    font-size: 0.9rem;
}
.msg-card-container {
    display: flex;
    margin-bottom: 16px;
}
.msg-card {
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    overflow: hidden;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
    width: 100%;
}
.msg-card-body {
    padding: 0.85rem 1.1rem 0.6rem 1.1rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.msg-card-footer {
    background-color: #f9fafb;
    padding: 0.45rem 1.1rem 0.55rem 1.1rem;
    font-size: 0.78rem;
    color: #6b7280;
    border-top: 1px solid #e5e7eb;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
}
.msg-label {
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
}
.msg-value {
    font-size: 14px;
    color: #111827;
}
.msg-message {
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
@media (max-width: 992px) {
    #main {
        margin-left: 0 !important;
    }
    .sidebar-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1050;
    }
    #app {
        overflow-x: hidden;
    }
    .page-content {
        position: relative;
        z-index: 1;
    }
}
</style>
</head>
<body>
<div id="app">
<?php include "menu.php"; ?>
<div id="main">
<header class="mb-3">
  <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
</header>

<div class="page-heading mb-3">
  <h3 class="mb-1">Contacto</h3>
  <p class="text-subtitle text-muted">Gerir mensagens e informações de contacto.</p>
</div>

<div class="page-content">
  <section class="section">

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($page === 1): ?>
      <div class="card card-main mb-4">
        <div class="card-header">
          <h4 class="mb-1">Editar Informações de Contacto</h4>
          <span class="section-subtitle">Atualize os dados que aparecem na página pública de contacto.</span>
        </div>
        <div class="card-body">
          <form method="post" onsubmit="return validateEmail()">
           <div class="form-group mb-3">
             <label>Morada</label>
             <input type="text" name="address" class="form-control"
                    value="<?= htmlspecialchars($contactInfo['address'] ?? '') ?>">
           </div>
           <div class="form-group mb-3">
             <label>Telefone</label>
             <input type="text" name="phone" class="form-control"
                    value="<?= htmlspecialchars($contactInfo['phone'] ?? '') ?>">
           </div>
           <div class="form-group mb-3">
             <label>Email</label>
             <input type="email" name="email" id="email" class="form-control"
                    value="<?= htmlspecialchars($contactInfo['email'] ?? '') ?>" required>
             <small class="text-muted">Só será guardado um email válido.</small>
           </div>
           <button type="submit" name="update_info" class="btn btn-primary">Guardar Alterações</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <div class="card card-main">
      <div class="card-header border-0 pb-0">
        <div class="card-header-flex d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h4 class="mb-1">Mensagens</h4>
            <small class="text-muted">Use o modo de seleção múltipla para apagar ou exportar mensagens.</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button
                id="toggleSelectMode"
                class="btn btn-outline-secondary d-flex align-items-center"
                type="button"
                style="border-radius: 999px; padding: 0.4rem 1rem; font-size: 0.9rem;"
            >
                <i class="bi bi-check2-square me-1"></i>
                <span>Modo seleção</span>
            </button>

            <!-- PDF: submit próprio -->
            <button
                type="submit"
                class="btn btn-outline-secondary"
                id="pdfButton"
                name="btn_pdf"
                style="border-radius: 999px; padding: 0.4rem 1rem; font-size: 0.9rem;"
                form="messagesForm"
            >
                Exportar PDF
            </button>

            <!-- Remover selecionadas: submit próprio -->
            <button
                type="submit"
                class="btn btn-outline-secondary d-none"
                id="removeButton"
                name="btn_delete_multi"
                onclick="return confirm('Tem certeza que deseja remover as mensagens selecionadas?');"
                form="messagesForm"
                style="border-radius: 999px; padding: 0.4rem 1rem; font-size: 0.9rem;"
            >
                Remover selecionadas
            </button>
          </div>
        </div>
      </div>
      <div class="card-body pt-3">
        <?php if ($messages && $messages->num_rows > 0): ?>
          <form method="post" id="messagesForm">
            <input type="hidden" name="mode" id="deleteModeInput" value="single">
            <input type="hidden" name="delete_id" id="singleDeleteId" value="">
            <input type="hidden" name="current_page" value="<?= (int)$page ?>">
            <input type="hidden" name="items_on_page" value="<?= (int)($messages ? $messages->num_rows : 0) ?>">

            <div class="container-fluid">
              <div class="row">
                <?php while ($row = $messages->fetch_assoc()): ?>
                  <?php
                    $createdRaw  = $row['created_at'] ?? null;
                    $createdText = $createdRaw ? date('d/m/Y H:i', strtotime($createdRaw)) : 'Sem data';
                  ?>
                  <div class="col-12 col-md-4 msg-card-container">
                    <div class="msg-card">
                      <div class="msg-card-body">
                        <div>
                          <span class="msg-label">Nome:</span>
                          <span class="msg-value">
                            <?= htmlspecialchars($row['name']) ?>
                          </span>
                        </div>
                        <div>
                          <span class="msg-label">Email:</span>
                          <span class="msg-value">
                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>">
                              <?= htmlspecialchars($row['email']) ?>
                            </a>
                          </span>
                        </div>
                        <div>
                          <span class="msg-label">Assunto:</span>
                          <span class="msg-value">
                            <?= htmlspecialchars($row['subject']) ?>
                          </span>
                        </div>
                        <div>
                          <span class="msg-label">Mensagem:</span>
                          <span class="msg-value msg-message">
                            <?= nl2br(htmlspecialchars($row['message'])) ?>
                          </span>
                        </div>
                      </div>
                      <div class="msg-card-footer">
                        <span>Criado em: <?= $createdText ?></span>
                        <div class="d-flex align-items-center gap-2">
                          <button
                              type="button"
                              class="btn btn-danger btn-sm single-delete-btn"
                              data-id="<?= (int)$row['id'] ?>"
                          >
                              Apagar
                          </button>

                          <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                              <input
                                  class="form-check-input msg-checkbox"
                                  type="checkbox"
                                  name="delete_ids[]"
                                  value="<?= (int)$row['id'] ?>"
                                  id="msgChk<?= (int)$row['id'] ?>"
                              >
                              <label class="form-check-label" for="msgChk<?= (int)$row['id'] ?>">
                                  Selecionar
                              </label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </form>
        <?php else: ?>
          <div class="alert alert-info text-center mb-0">
            Nenhuma mensagem encontrada.
          </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
          <nav aria-label="Paginação" class="mt-3">
            <ul class="pagination justify-content-center">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="?evora=contact&page=<?= max(1, $page - 1) ?>">Anterior</a>
              </li>

              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                  <a class="page-link"
                     href="?evora=contact&page=<?= $p ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="?evora=contact&page=<?= min($totalPages, $page + 1) ?>">Seguinte</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>

  </section>
</div>
</div>
</div>

<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
function validateEmail() {
    var emailField = document.getElementById('email');
    var email = emailField.value;
    var validEmail = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!validEmail.test(email)) {
        alert("Por favor insira um endereço de email válido.");
        return false;
    }
    return true;
}

const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const deleteModeInput     = document.getElementById('deleteModeInput');
const mainForm            = document.getElementById('messagesForm');
const singleDeleteIdInput = document.getElementById('singleDeleteId');
const pdfButton           = document.getElementById('pdfButton');
const removeButton        = document.getElementById('removeButton');

let selectionMode = false;

function updateSelectionUI() {
    const singleButtons    = document.querySelectorAll('.single-delete-btn');
    const checkboxWrappers = document.querySelectorAll('.multi-checkbox-wrapper');

    if (selectionMode) {
        deleteModeInput.value = 'multi';
        removeButton.classList.remove('d-none');
        singleButtons.forEach(btn => btn.style.display = 'none');
        checkboxWrappers.forEach(w => w.style.display = 'block');
        toggleSelectModeBtn.classList.add('active');
    } else {
        deleteModeInput.value = 'single';
        removeButton.classList.add('d-none');
        document.querySelectorAll('.msg-checkbox').forEach(chk => chk.checked = false);
        singleButtons.forEach(btn => btn.style.display = 'inline-block');
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn.classList.remove('active');
    }
}

if (toggleSelectModeBtn) {
    toggleSelectModeBtn.addEventListener('click', function () {
        selectionMode = !selectionMode;
        updateSelectionUI();
    });
}

document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (selectionMode) return;

        const id = this.getAttribute('data-id');
        if (!id) return;

        if (confirm('Tem a certeza que pretende apagar esta mensagem?')) {
            deleteModeInput.value = 'single';
            singleDeleteIdInput.value = id;
            mainForm.submit();
        }
    });
});

// PDF button: valida se há seleção, mas não mexe em campos escondidos
if (pdfButton && mainForm) {
    pdfButton.addEventListener('click', function (e) {
        const anyChecked = Array.from(document.querySelectorAll('.msg-checkbox'))
            .some(chk => chk.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Selecione pelo menos uma mensagem para exportar o PDF.');
        }
    });
}
</script>
</body>
</html>
