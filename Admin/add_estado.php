<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

if (isset($_POST['add_estado'])) {
    $name       = trim($_POST['name'] ?? '');
    $color_input = trim($_POST['color_name'] ?? '');

    if ($name !== '') {
        $color_css = $color_input !== '' ? $color_input : null;

        $stmt = $conn->prepare("INSERT INTO states (name, color_name) VALUES (?, ?)");
        if ($stmt === false) {
            $error = "Erro na preparação da query: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $name, $color_css);

            if ($stmt->execute()) {
                $success = "Tarefa adicionada com sucesso!";
                $novo_id = $stmt->insert_id;

                regista_log(
                    $conn,
                    $_SESSION['user_id'],
                    "adicionar",
                    "estado",
                    $novo_id,
                    "Tarefa $name adicionada com cor '$color_css'."
                );

                $userId  = $_SESSION['user_id'];
                $acao    = 'Nova tarefa criada';
                $detalhe = "Tarefa: $name · Cor: $color_css";

                $stmtAt = $conn->prepare("
                    INSERT INTO atividade (user_id, acao, detalhe)
                    VALUES (?, ?, ?)
                ");
                if ($stmtAt) {
                    $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }

            } else {
                $error = "Erro ao adicionar tarefa: " . $stmt->error;
            }

            $stmt->close();
        }
    } else {
        $error = "O campo de nome da tarefa é obrigatório!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Tarefa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">

    <style>
        body, .sidebar, .card, .btn, h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }

        .page-content {
            background-color: transparent;
        }

        .card-main {
            background-color: #fff !important;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.08);
            border: 1px solid #e5e7eb;
        }

        .field-label {
            font-weight: 600;
            color: #4b5563;
            font-size: 0.9rem;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }
        .page-heading { margin-bottom: 20px; }

        .hc-color-card {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.55rem 0.85rem;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15,23,42,0.08);
            cursor: pointer;
            transition: box-shadow 0.16s ease, transform 0.16s ease,
                        border-color 0.16s ease, background-color 0.16s ease;
        }
        .hc-color-card:hover {
            box-shadow: 0 18px 36px rgba(15,23,42,0.14);
            transform: translateY(-1px);
            border-color: #d1d5db;
            background-color: #fff5f1;
        }
        .hc-color-preview {
            width: 40px;
            height: 32px;
            border-radius: 999px;
            border: 1px solid rgba(15,23,42,0.12);
            box-shadow: 0 4px 10px rgba(15,23,42,0.25);
            background: #ffffff;
            flex-shrink: 0;
        }
        .hc-color-main {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
            margin-left: 0.75rem;
        }
        .hc-color-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
        }
        .hc-color-value {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #111827;
        }
        .hc-color-input {
            max-width: 0;
            max-height: 0;
            opacity: 0;
            padding: 0;
            border: 0;
        }

        @media (max-width: 992px) {
            #main { margin-left: 0 !important; }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
            }
            #app { overflow-x: hidden; }
            .page-content { position: relative; z-index: 1; }
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

        <div class="page-content d-flex justify-content-center">
            <div style="width: 100%; max-width: 520px;">

                <div class="page-heading">
                    <h3>Adicionar Tarefa</h3>
                    <p class="text-subtitle text-muted mb-0">
                        Crie uma nova tarefa e escolha a cor correspondente.
                    </p>
                </div>

                <section class="section">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card card-main mb-4">
                        <div class="card-header">
                            <h4 class="mb-0">Nova Tarefa</h4>
                        </div>

                        <div class="card-body">
                            <form method="post" autocomplete="off">
                                <div class="mb-3">
                                    <label class="field-label mb-1">
                                        <i class="bi bi-card-text me-1"></i> Nome da Tarefa
                                    </label>
                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <?php
                                    $hexDefault        = '#FFFFFF';
                                    $cssColorForPicker = $hexDefault;
                                ?>

                                <div class="mb-3">
                                    <label class="field-label mb-1">
                                        <i class="bi bi-palette me-1"></i> Cor da Tarefa
                                    </label>

                                    <div class="d-flex flex-column gap-2">
                                        <div id="colorBoxTrigger" class="hc-color-card">
                                            <div class="hc-color-preview" id="colorBoxPreview"></div>

                                            <div class="hc-color-main">
                                                <span class="hc-color-label">HEX</span>
                                                <span class="hc-color-value" id="colorBoxHex">
                                                    <?= strtoupper(htmlspecialchars($cssColorForPicker)) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <input
                                            type="color"
                                            id="corpicker"
                                            name="color_name"
                                            value="<?= htmlspecialchars($cssColorForPicker) ?>"
                                            class="hc-color-input"
                                            title="Escolher cor"
                                        >
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    name="add_estado"
                                    class="btn btn-primary btn-main w-100"
                                >
                                    Adicionar Tarefa
                                </button>
                            </form>
                        </div>
                    </div>

                </section>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputPicker = document.getElementById('corpicker');
    const boxTrigger  = document.getElementById('colorBoxTrigger');
    const boxPreview  = document.getElementById('colorBoxPreview');
    const boxHex      = document.getElementById('colorBoxHex');

    function updateFromPicker() {
        if (!inputPicker) return;
        const val = inputPicker.value || '#FFFFFF';
        boxPreview.style.backgroundColor = val;
        boxHex.textContent = val.toUpperCase();
    }

    if (boxTrigger && inputPicker) {
        boxTrigger.addEventListener('click', function () {
            inputPicker.click();
        });
    }

    if (inputPicker) {
        inputPicker.addEventListener('input', updateFromPicker);
        inputPicker.addEventListener('change', updateFromPicker);
        updateFromPicker();
    }
});
</script>
</body>
</html>
