<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success       = '';
$error         = '';
$intervencoes  = ['Corte', 'Poda'];
$valid_species = ['Carvalho', 'Oliveira', 'Pinheiro', 'Plátano', 'Jacarandá', 'Loureiro'];

$tarefas = [];
$res_tarefa = $conn->query("SELECT name FROM states ORDER BY name");
if ($res_tarefa && $res_tarefa->num_rows > 0) {
    while ($row = $res_tarefa->fetch_assoc()) {
        if (!empty($row['name'])) {
            $tarefas[] = $row['name'];
        }
    }
}

if (isset($_POST['add_tree'])) {
    $species          = $_POST['species'] ?? null;
    $latitude         = $_POST['latitude'] ?? null;
    $longitude        = $_POST['longitude'] ?? null;
    $tipo_intervencao = $_POST['tipo_intervencao'] ?? null;
    $tarefa           = $_POST['tarefa'] ?? null;
    $place_name       = $_POST['place_name'] ?? null;

    if ($species && $latitude && $longitude && $tarefa && $place_name) {

        foreach ($species as $sp) {
            if (!in_array($sp, $valid_species)) {
                $error = "Espécie da Árvore inválida!";
                break;
            }
        }

        if (!$error) {
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                $error = "Latitude e Longitude devem ser números válidos!";
            } elseif ($tipo_intervencao && !in_array($tipo_intervencao, $intervencoes)) {
                $error = "Tipo de intervenção inválido!";
            } elseif (!in_array($tarefa, $tarefas)) {
                $error = "Tarefa inválida!";
            } else {

                if (!$tipo_intervencao) {
                    $tipo_intervencao = "Nenhuma";
                }

                $completedAt = null;
                if ($tarefa === 'Concluída') {
                    $completedAt = date('Y-m-d H:i:s');
                }

                $stmt = $conn->prepare("
                    INSERT INTO arvores (
                        especie,
                        latitude,
                        longitude,
                        place_name,
                        tipo_intervencao,
                        estado,
                        criado_em,
                        completed_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                ");

                if ($stmt === false) {
                    $error = "Erro na preparação da query: " . $conn->error;
                } else {
                    foreach ($species as $sp) {
                        $stmt->bind_param(
                            "sddssss",
                            $sp,
                            $latitude,
                            $longitude,
                            $place_name,
                            $tipo_intervencao,
                            $tarefa,
                            $completedAt
                        );

                        if (!$stmt->execute()) {
                            $error = "Erro ao inserir árvore: " . $stmt->error;
                            break;
                        } else {
                            $novo_id = $stmt->insert_id;

                            regista_log(
                                $conn,
                                $_SESSION['user_id'],
                                "adicionar",
                                "arvore",
                                $novo_id,
                                "Espécie: $sp"
                            );

                            $userId  = $_SESSION['user_id'];
                            $acao    = 'Nova árvore registada';
                            $detalhe = "Espécie: $sp · Local: $place_name";

                            $stmtAt = $conn->prepare("
                                INSERT INTO atividade (user_id, acao, detalhe)
                                VALUES (?, ?, ?)
                            ");
                            if ($stmtAt) {
                                $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                                $stmtAt->execute();
                                $stmtAt->close();
                            }
                        }
                    }

                    $stmt->close();

                    if (!$error) {
                        $success = "Árvore(s) inserida(s) com sucesso!";
                    }
                }
            }
        }
    } else {
        $error = "Todos os campos são obrigatórios!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Espaço Verde</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
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
        .form-select {
            border-radius: 10px;
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
        .edit-map-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .edit-map-info {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }
        .actions-row-desktop { margin-top: 16px; }
        .btn-search-round {
            border-radius: 999px;
            padding-inline: 0.7rem;
            border-color: #d1d5db;
            background-color: #f9fafb;
        }
        .btn-search-round i { font-size: 1rem; }

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

        .bootstrap-select .dropdown-menu { z-index: 1100 !important; }

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
        }
        @media (max-width: 768px) {
            .edit-card { padding: 16px 14px 16px 14px; }
            .edit-two-cols { grid-template-columns: 1fr; }
            .actions-row-desktop { margin-top: 10px; }
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

                <div class="page-heading-custom mb-3">
                    <h3>Novo Espaço Verde</h3>
                    <p>Registe uma nova árvore urbana, o local e a posição no mapa.</p>
                </div>

                <div class="edit-card">

                    <?php if ($success): ?>
                        <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off">
                        <div class="edit-two-cols mb-3">
                            <div>
                                <div class="section-label">Dados principais</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Espécie da Árvore</label>
                                    <select
                                        name="species[]"
                                        id="species"
                                        class="form-select js-nice-select"
                                        multiple
                                        required
                                    >
                                        <?php foreach ($valid_species as $sp) { ?>
                                            <option value="<?= htmlspecialchars($sp) ?>">
                                                <?= htmlspecialchars($sp) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <small class="text-muted">
                                        Pode selecionar várias espécies para o mesmo local.
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Nome do Espaço</label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            id="place_name"
                                            name="place_name"
                                            class="form-control"
                                            required
                                            placeholder="Clique no mapa ou escreva nome, código postal, cidade, país..."
                                        >
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-search-round"
                                            id="search-location"
                                            title="Pesquisar localização"
                                        >
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        Pode pesquisar por nome, rua, código postal, cidade ou país. O marcador e coordenadas serão atualizados.
                                    </small>
                                </div>

                                <div class="section-label">Intervenção e tarefa</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Tipo de Intervenção</label>
                                    <select
                                        name="tipo_intervencao"
                                        id="tipo_intervencao"
                                        class="form-select js-nice-select"
                                    >
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($intervencoes as $interv): ?>
                                            <option value="<?= htmlspecialchars($interv) ?>"><?= htmlspecialchars($interv) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-0">
                                    <label class="field-label mb-1">Tarefa</label>
                                    <select
                                        name="tarefa"
                                        id="tarefa"
                                        class="form-select js-nice-select"
                                        required
                                    >
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($tarefas as $tarefa_opt): ?>
                                            <option value="<?= htmlspecialchars($tarefa_opt) ?>"><?= htmlspecialchars($tarefa_opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="actions-row-desktop">
                                    <button type="submit" name="add_tree" class="btn btn-primary btn-main w-100">
                                        <i class="bi bi-plus-lg me-1"></i> Adicionar Árvore(s)
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="section-label">Mapa e coordenadas</div>

                                <div class="row mb-2">
                                    <div class="col-6">
                                        <label class="field-label mb-1">Latitude</label>
                                        <input
                                            type="text"
                                            id="latitude"
                                            name="latitude"
                                            class="form-control"
                                            required
                                            placeholder="Clique no mapa ou use a pesquisa"
                                            readonly
                                        >
                                    </div>
                                    <div class="col-6">
                                        <label class="field-label mb-1">Longitude</label>
                                        <input
                                            type="text"
                                            id="longitude"
                                            name="longitude"
                                            class="form-control"
                                            required
                                            placeholder="Clique no mapa ou use a pesquisa"
                                            readonly
                                        >
                                    </div>
                                </div>

                                <div class="edit-map-wrapper">
                                    <div class="edit-map-top">
                                        <span class="edit-map-info">
                                            Clique no mapa ou arraste o marcador para definir a posição.
                                        </span>
                                    </div>
                                    <div id="map"></div>
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
<script src="assets/js/main.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: el.id === 'species' ? null : 1,
            allowEmptyOption: true,
            create: false,
            plugins: { clear_button: { title: 'Limpar seleção' } }
        });
    });
});

const evora = [38.5667, -7.9];
const map = L.map('map', { attributionControl: false }).setView(evora, 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

let marker;

function setMarkerPos(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function() {
            const pos = marker.getLatLng();
            setFormFields(pos.lat.toFixed(6), pos.lng.toFixed(6));
            fetchPlaceName(pos.lat, pos.lng);
        });
    }
    map.setView([lat, lng], 16);
}

function setFormFields(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}

function fetchPlaceName(lat, lng) {
    fetch(`reverse_proxy.php?lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('place_name').value = data.display_name || '';
        })
        .catch(() => alert("Erro ao pedir ao servidor (reverse_proxy.php)!"));
}

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(6);
    const lng = e.latlng.lng.toFixed(6);
    setFormFields(lat, lng);
    fetchPlaceName(lat, lng);
    setMarkerPos(lat, lng);
});

document.getElementById('search-location').addEventListener('click', function() {
    const name = document.getElementById('place_name').value;
    if (!name.trim()) {
        alert('Escreva um nome, cidade ou código postal.');
        return;
    }
    fetch(`nominatim_proxy.php?q=${encodeURIComponent(name)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat).toFixed(6);
                const lng = parseFloat(data[0].lon).toFixed(6);
                setFormFields(lat, lng);
                setMarkerPos(lat, lng);
            } else {
                alert('Localização não encontrada!');
            }
        })
        .catch(() => alert('Erro ao pesquisar localização!'));
});

document.getElementById('place_name').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('search-location').click();
        e.preventDefault();
    }
});
</script>
</body>
</html>
