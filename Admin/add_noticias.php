<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success       = '';
$error         = '';
$last_news_id  = null;

$uploadDir = dirname(__DIR__) . '/uploads/noticias/';
$uploadUrl = 'uploads/noticias/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

if (isset($_POST['add_noticia'])) {
    $titulo          = trim($_POST['titulo'] ?? '');
    $resumo          = trim($_POST['resumo'] ?? '');
    $conteudo        = trim($_POST['conteudo'] ?? '');
    $autor           = trim($_POST['autor'] ?? '');
    $data_publicacao = trim($_POST['data_publicacao'] ?? '');
    $categoria       = isset($_POST['categoria']) ? $_POST['categoria'] : null;

    // todos os campos obrigatórios
    if (
        $titulo === '' ||
        $resumo === '' ||
        $conteudo === '' ||
        $autor === '' ||
        $data_publicacao === '' ||
        $categoria === null || $categoria === ''
    ) {
        $error = "Todos os campos são obrigatórios.";
    } else {

        // aceitar plataforma, estrada e outros
        if (
            $categoria !== 'plataforma' &&
            $categoria !== 'estrada' &&
            $categoria !== 'outros'
        ) {
            $error = "Tipo de notícia inválido.";
        }

        // validar data
        if (!$error) {
            $dt = DateTime::createFromFormat('Y-m-d', $data_publicacao);
            if ($dt === false) {
                $error = "Data de publicação inválida. Use o formato AAAA-MM-DD.";
            } else {
                $hoje = new DateTime('today');
                if ($dt > $hoje) {
                    $data_publicacao = $hoje->format('Y-m-d');
                } else {
                    $data_publicacao = $dt->format('Y-m-d');
                }
            }
        }

        $imagem_lista     = null;
        $imagens_detalhe  = [];
        $imagem_detalheDB = null;

        if (!$error) {
            $stmt = $conn->prepare("
                INSERT INTO noticias
                    (titulo, resumo, conteudo, categoria, imagem_lista, imagem_detalhe, autor, data_publicacao)
                VALUES
                    (?, ?, ?, ?, NULL, NULL, ?, ?)
            ");
            if ($stmt === false) {
                $error = "Erro na preparação da query: " . $conn->error;
            } else {
                $autorFinal = $autor !== '' ? $autor : null;
                $dataFinal  = $data_publicacao !== '' ? $data_publicacao : null;

                $stmt->bind_param(
                    "ssssss",
                    $titulo,
                    $resumo,
                    $conteudo,
                    $categoria,
                    $autorFinal,
                    $dataFinal
                );

                if (!$stmt->execute()) {
                    $error = "Erro ao inserir notícia: " . $stmt->error;
                } else {
                    $novo_id = $stmt->insert_id;
                    $stmt->close();

                    // imagem da lista (single) – sem GIF
                    if (isset($_FILES['imagem_lista_file']) && $_FILES['imagem_lista_file']['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['imagem_lista_file']['name'], PATHINFO_EXTENSION);
                        $ext = strtolower($ext);
                        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                            $nomeFile = 'noticia_' . $novo_id . '_lista_' . time() . '.' . $ext;
                            $dest     = $uploadDir . $nomeFile;
                            if (move_uploaded_file($_FILES['imagem_lista_file']['tmp_name'], $dest)) {
                                $imagem_lista = $uploadUrl . $nomeFile;
                            } else {
                                $error = "Falha ao mover ficheiro de imagem (lista).";
                            }
                        } else {
                            $error = "Formato de imagem da lista inválido. Use JPG, PNG ou WEBP.";
                        }
                    }

                    // imagens de detalhe (até 3) – sem GIF
                    if (!$error && isset($_FILES['imagem_detalhe_files'])) {
                        $files = $_FILES['imagem_detalhe_files'];

                        $total = 0;
                        foreach ($files['name'] as $idx => $name) {
                            if ($files['error'][$idx] === UPLOAD_ERR_OK && $name !== '') {
                                $total++;
                            }
                        }
                        if ($total > 3) {
                            $error = "Só pode enviar no máximo 3 imagens de detalhe.";
                        } else {
                            foreach ($files['name'] as $idx => $name) {
                                if ($files['error'][$idx] === UPLOAD_ERR_OK && $name !== '') {
                                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                                    $ext = strtolower($ext);
                                    if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                                        $error = "Formato de imagem de detalhe inválido. Use JPG, PNG ou WEBP.";
                                        break;
                                    }

                                    $nomeFile = 'noticia_' . $novo_id . '_det_' . $idx . '_' . time() . '.' . $ext;
                                    $dest     = $uploadDir . $nomeFile;
                                    if (move_uploaded_file($files['tmp_name'][$idx], $dest)) {
                                        $imagens_detalhe[] = $uploadUrl . $nomeFile;
                                    } else {
                                        $error = "Falha ao mover ficheiro de imagem (detalhe).";
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if (!$error && !empty($imagens_detalhe)) {
                        $imagem_detalheDB = implode('|', $imagens_detalhe);
                    }

                    if (!$error && ($imagem_lista || $imagem_detalheDB)) {
                        $stmtUp = $conn->prepare("
                            UPDATE noticias
                            SET imagem_lista = ?, imagem_detalhe = ?
                            WHERE id = ?
                        ");
                        if ($stmtUp) {
                            $imgLista   = $imagem_lista     ?: null;
                            $imgDetalhe = $imagem_detalheDB ?: null;
                            $stmtUp->bind_param("ssi", $imgLista, $imgDetalhe, $novo_id);
                            $stmtUp->execute();
                            $stmtUp->close();
                        }
                    }

                    regista_log(
                        $conn,
                        $_SESSION['user_id'],
                        "adicionar",
                        "noticias",
                        $novo_id,
                        "Título: $titulo (categoria: $categoria)"
                    );

                    if ($stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ")) {
                        $userId  = $_SESSION['user_id'];
                        $acao    = 'Nova notícia adicionada';
                        $detalhe = "Título: $titulo (categoria: $categoria)";
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    if (!$error) {
                        $success      = "Notícia adicionada com sucesso!";
                        $last_news_id = $novo_id;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Notícias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonts / tema -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap / template -->
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">

    <!-- Tom Select -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body {
            height: 100%;
        }
        body {
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
        }
        #app, #main {
            min-height: 100%;
        }
        .page-content {
            min-height: calc(100vh - 56px);
            padding: 24px 12px 32px 12px;
        }
        .edit-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }
        .page-heading-custom {
            margin-bottom: 16px;
        }
        .page-heading-custom h3 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 4px;
        }
        .page-heading-custom p {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        .edit-card {
            background-color: #ffffff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.09);
            padding: 20px 20px 18px 20px;
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
            color: #374151;
            font-size: 0.9rem;
        }
        .form-control,
        .form-select {
            border-radius: 10px;
        }
        .edit-two-cols {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 18px;
        }
        .upload-hint {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .upload-box {
            border: 1px dashed #cbd5f5;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            background-color: #f9fafb;
        }
        .upload-box label {
            margin-bottom: 0.25rem;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }
        .actions-row-desktop {
            margin-top: 16px;
        }
        .alert {
            border-radius: 12px;
        }

        .ts-wrapper.single .ts-control,
        .ts-wrapper.single.input-active .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px;
            padding: 0.375rem 0.75rem;
            border-color: #d1d5db;
        }
        .ts-wrapper.single .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25);
            border-color: #2563eb;
        }
        .ts-dropdown {
            background-color: #ffffff !important;
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
        }
        @media (max-width: 768px) {
            .edit-card {
                padding: 16px 14px 16px 14px;
            }
            .edit-two-cols {
                grid-template-columns: 1fr;
            }
            .actions-row-desktop {
                margin-top: 10px;
            }
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

                <div class="page-heading-custom">
                    <h3>Adicionar Notícias</h3>
                    <p>Registe novas notícias para a plataforma, estrada ou outros em Évora.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success mb-3" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="edit-card">
                    <form method="post" enctype="multipart/form-data" autocomplete="off">
                        <div class="edit-two-cols mb-3">

                            <div>
                                <div class="section-label">Dados principais</div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                                        <label class="field-label mb-1">Título da Notícia</label>
                                        <input
                                            type="text"
                                            name="titulo"
                                            class="form-control"
                                            required
                                            placeholder="Ex.: Atualização do mapa 3D dos espaços verdes"
                                        >
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="field-label mb-1">Autor</label>
                                        <input
                                            type="text"
                                            name="autor"
                                            class="form-control"
                                            placeholder="Ex.: Equipa SIG Évora"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                                        <label class="field-label mb-1">Tipo de notícia</label>
                                        <select
                                            name="categoria"
                                            id="categoria"
                                            class="form-select js-nice-select"
                                            required
                                        >
                                            <option value="" selected disabled>Selecione uma opção</option>
                                            <option value="plataforma">Plataforma (espaços verdes)</option>
                                            <option value="estrada">Estrada</option>
                                            <option value="outros">Outros</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="field-label mb-1">Data de publicação</label>
                                        <input
                                            type="date"
                                            name="data_publicacao"
                                            class="form-control"
                                            max="<?php echo date('Y-m-d'); ?>"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Resumo curto</label>
                                    <textarea
                                        name="resumo"
                                        class="form-control"
                                        rows="3"
                                        required
                                        placeholder="Breve descrição que será mostrada na lista pública de notícias."
                                    ></textarea>
                                </div>

                                <div class="mb-0">
                                    <label class="field-label mb-1">Conteúdo completo</label>
                                    <textarea
                                        name="conteudo"
                                        class="form-control"
                                        rows="7"
                                        required
                                        placeholder="Texto completo da notícia, com todos os detalhes relevantes."
                                    ></textarea>
                                    <div class="upload-hint mt-1">
                                        Pode incluir parágrafos, listas e explicações detalhadas sobre intervenções, mapas, etc.
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="section-label">Imagens</div>

                                <div class="mb-3">
                                    <div class="upload-box">
                                        <label class="field-label mb-1">Imagem para lista</label>
                                        <input
                                            type="file"
                                            name="imagem_lista_file"
                                            class="form-control"
                                            accept="image/*"
                                        >
                                        <div class="upload-hint mt-1">
                                            Será utilizada na grelha pública de notícias (tamanho recomendado: 800x500px).
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="upload-box">
                                        <label class="field-label mb-1">Imagens de detalhe (máx. 3)</label>
                                        <input
                                            type="file"
                                            name="imagem_detalhe_files[]"
                                            class="form-control"
                                            accept="image/*"
                                            multiple
                                        >
                                        <div class="upload-hint mt-1">
                                            Serão mostradas em slider na página de detalhes da notícia.
                                        </div>
                                    </div>
                                </div>

                                <div class="actions-row-desktop">
                                    <button type="submit" name="add_noticia" class="btn btn-primary btn-main w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Adicionar Notícia
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/vendors/apexcharts/apexcharts.js"></script>
<script src="assets/js/main.js"></script>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: true,
            create: false,
            plugins: {
                clear_button: { title: 'Limpar seleção' }
            }
        });
    });
});
</script>
</body>
</html>
