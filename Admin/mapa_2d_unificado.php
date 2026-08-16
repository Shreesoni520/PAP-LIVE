<?php
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mapa 2D - Espaços Verdes e Ocorrências</title>

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/css/app.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">

    <style>
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Nunito', sans-serif !important;
            background: linear-gradient(135deg, #eef2ff, #f9fafb) !important;
            margin: 0;
        }
        #app, #main {
            min-height: 100%;
        }
        .page-content {
            min-height: calc(100vh - 56px);
            padding: 24px 12px 32px 12px;
        }
        .map-container-custom {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }
        .page-heading-custom {
            margin-bottom: 16px;
            text-align: center;
        }
        .page-heading-custom h2 {
            font-weight: 700;
            font-size: 1.7rem;
            margin-bottom: 4px;
        }
        .page-heading-custom p {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 0;
        }
        .info-wrap {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 20px 20px 18px 20px;
            box-shadow: 0 10px 30px rgba(15,23,42,0.08);
        }

        .map-shell {
            position: relative;
        }
        #mapTrees,
        #mapOccs,
        #mapEstrada {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
        }
        @media (min-width: 992px) {
            #mapTrees,
            #mapOccs,
            #mapEstrada {
                height: 450px;
            }
        }
        .legend-box {
            position: absolute;
            left: 16px;
            bottom: 16px;
            font-size: 0.9rem;
            color: #111827;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(148, 163, 184, 0.6);
            border-radius: 10px;
            padding: 6px 14px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
            max-width: 230px;
            z-index: 999;
        }
        .legend-title {
            font-weight: 700;
            font-size: 0.78rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .legend-color {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 4px;
            border: 1px solid #4b5563;
        }
        .leaflet-popup-content {
            font-size: 0.95rem;
        }

        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .search-bar {
            margin-bottom: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            flex: 1 1 auto;
        }
        .search-input-wrapper {
            position: relative;
            flex: 1 1 260px;
            max-width: 420px;
        }
        .search-input-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.9rem;
            color: #9ca3af;
        }
        .search-bar input {
            border-radius: 999px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
            padding: 8px 14px 8px 30px;
            width: 100%;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .search-bar input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.25);
            outline: none;
        }
        .search-bar button {
            border-radius: 999px;
            font-weight: 600;
            padding-inline: 16px;
        }

        .map-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
        }
        .map-tab {
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 6px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            background-color: #f3f4f6;
            white-space: nowrap;
        }
        .map-tab.active {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 8px 18px rgba(37,99,235,0.24);
        }

        a, a:hover, a:focus, a:active,
        .btn, h1, h2, h3, h4, h5, h6,
        th, td, label, .sidebar, .menu, .navbar {
            text-decoration: none !important;
            border-bottom: none !important;
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
            .page-content {
                position: relative;
                z-index: 1;
            }
            #app {
                overflow-x: hidden;
            }

            #mapTrees,
            #mapOccs,
            #mapEstrada {
                width: 100%;
                height: 320px;
            }

            .info-wrap {
                padding: 16px 14px 16px 14px;
            }

            .header-row {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
            }
            .map-tabs {
                order: 1;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            .search-bar {
                order: 2;
                width: 100%;
            }
            .search-input-wrapper {
                flex: 1 1 auto;
                max-width: 100%;
            }
            .search-bar button {
                width: 100%;
            }

            .legend-box {
                font-size: 0.84rem;
                left: 10px;
                bottom: 10px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 576px) {
            #mapTrees,
            #mapOccs,
            #mapEstrada {
                height: 280px;
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

        <section class="section" style="padding-top: 0;">
            <div class="container-fluid">
                <div class="row gy-0 align-items-center justify-content-center">
                    <div class="map-container-custom">
                        <div class="page-heading-custom mb-1">
                            <h2>Mapa 2D Interativo</h2>
                            <p class="text-muted">
                                Explore os espaços verdes de Évora e as ocorrências associadas num só ecrã.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="page-content">
            <section class="section">
                <div class="container-fluid">
                    <div class="row gy-4">
                        <div class="col-12">
                            <div class="map-container-custom">
                                <div class="info-wrap">

                                    <!-- PANEL: ESPAÇOS VERDES -->
                                    <div id="panelTrees">
                                        <div class="header-row">
                                            <div class="search-bar">
                                                <div class="search-input-wrapper">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        type="text"
                                                        id="searchTrees"
                                                        placeholder="Pesquisar por espécie, tarefa, intervenção ou local..."
                                                        aria-label="Pesquisar no mapa de espaços verdes"
                                                    >
                                                </div>
                                                <button class="btn btn-primary" id="btnSearchTrees" type="button">Procurar</button>
                                                <button class="btn btn-primary" id="btnResetTrees" type="button">Repor mapa</button>
                                            </div>

                                            <div class="map-tabs">
                                                <button type="button"
                                                        class="map-tab active"
                                                        id="tabTrees">
                                                    <i class="bi bi-tree"></i> Espaços Verdes
                                                </button>
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabOccs">
                                                    <i class="bi bi-flag"></i> Ocorrências
                                                </button>
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabEstrada">
                                                    <i class="bi bi-truck"></i> Ocorrências Estrada
                                                </button>
                                            </div>
                                        </div>

                                        <div class="map-shell">
                                            <div id="mapTrees">
                                                <div class="legend-box" id="legendTrees">
                                                    <span class="legend-title">Tarefas das árvores</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 mb-3 d-flex flex-wrap gap-2">
                                            <a href="add_arvore.php" class="btn btn-success">
                                                Adicionar Novo Espaço Verde
                                            </a>
                                            <a href="add_intervencao.php" class="btn btn-primary">
                                                Nova Intervenção
                                            </a>
                                            <a href="add_estado.php" class="btn btn-primary">
                                                Nova Tarefa
                                            </a>
                                        </div>
                                    </div>

                                    <!-- PANEL: OCORRÊNCIAS -->
                                    <div id="panelOccs" style="display:none;">
                                        <div class="header-row">
                                            <div class="search-bar">
                                                <div class="search-input-wrapper">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        type="text"
                                                        id="searchOccs"
                                                        placeholder="Pesquisar por descrição, local, tarefa ou tipo..."
                                                        aria-label="Pesquisar no mapa de ocorrências"
                                                    >
                                                </div>
                                                <button class="btn btn-primary" id="btnSearchOccs" type="button">Procurar</button>
                                                <button class="btn btn-primary" id="btnResetOccs" type="button">Repor mapa</button>
                                            </div>

                                            <div class="map-tabs">
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabTrees2">
                                                    <i class="bi bi-tree"></i> Espaços Verdes
                                                </button>
                                                <button type="button"
                                                        class="map-tab active"
                                                        id="tabOccs2">
                                                    <i class="bi bi-flag"></i> Ocorrências
                                                </button>
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabEstrada2">
                                                    <i class="bi bi-truck"></i> Ocorrências Estrada
                                                </button>
                                            </div>
                                        </div>

                                        <div class="map-shell">
                                            <div id="mapOccs">
                                                <div class="legend-box" id="legendOccs">
                                                    <span class="legend-title">Tarefas de ocorrências</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 mb-3 d-flex flex-wrap gap-2">
                                            <a href="index.php?evora=addocorrencias" class="btn btn-success">
                                                Adicionar Nova Ocorrência
                                            </a>
                                            <a href="index.php?evora=listocorrencias" class="btn btn-primary">
                                                Listar Ocorrências
                                            </a>
                                        </div>
                                    </div>

                                    <!-- PANEL: OCORRÊNCIAS ESTRADA -->
                                    <div id="panelEstrada" style="display:none;">
                                        <div class="header-row">
                                            <div class="search-bar">
                                                <div class="search-input-wrapper">
                                                    <i class="bi bi-search"></i>
                                                    <input
                                                        type="text"
                                                        id="searchEstrada"
                                                        placeholder="Pesquisar por descrição, local, tarefa ou tipo..."
                                                        aria-label="Pesquisar no mapa de ocorrências de estrada"
                                                    >
                                                </div>
                                                <button class="btn btn-primary" id="btnSearchEstrada" type="button">Procurar</button>
                                                <button class="btn btn-primary" id="btnResetEstrada" type="button">Repor mapa</button>
                                            </div>

                                            <div class="map-tabs">
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabTrees3">
                                                    <i class="bi bi-tree"></i> Espaços Verdes
                                                </button>
                                                <button type="button"
                                                        class="map-tab"
                                                        id="tabOccs3">
                                                    <i class="bi bi-flag"></i> Ocorrências
                                                </button>
                                                <button type="button"
                                                        class="map-tab active"
                                                        id="tabEstrada3">
                                                    <i class="bi bi-truck"></i> Ocorrências Estrada
                                                </button>
                                            </div>
                                        </div>

                                        <div class="map-shell">
                                            <div id="mapEstrada">
                                                <div class="legend-box" id="legendEstrada">
                                                    <span class="legend-title">Tarefas ocorrências estrada</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 mb-3 d-flex flex-wrap gap-2">
                                            <a href="index.php?evora=addocorrencias_estrada" class="btn btn-success">
                                                Adicionar Nova Ocorrência Estrada
                                            </a>
                                            <a href="index.php?evora=listocorrencias_estrada" class="btn btn-primary">
                                                Listar Ocorrências Estrada
                                            </a>
                                        </div>
                                    </div>

                                </div><!-- .info-wrap -->
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // ====== TABS ======
    const panelTrees   = document.getElementById('panelTrees');
    const panelOccs    = document.getElementById('panelOccs');
    const panelEstrada = document.getElementById('panelEstrada');

    const tabTrees   = document.getElementById('tabTrees');
    const tabOccs    = document.getElementById('tabOccs');
    const tabEstrada = document.getElementById('tabEstrada');

    const tabTrees2   = document.getElementById('tabTrees2');
    const tabOccs2    = document.getElementById('tabOccs2');
    const tabEstrada2 = document.getElementById('tabEstrada2');

    const tabTrees3   = document.getElementById('tabTrees3');
    const tabOccs3    = document.getElementById('tabOccs3');
    const tabEstrada3 = document.getElementById('tabEstrada3');

    let mapTrees, mapOccs, mapEstrada;
    let markerClusterTrees, markerClusterOccs, markerClusterEstrada;
    let allTrees = [];
    let allOccs  = [];
    let allEstrada = [];
    let allMarkersTrees = [];
    let allMarkersOccs  = [];
    let allMarkersEstrada = [];

    function showTreesPanel() {
        panelTrees.style.display   = '';
        panelOccs.style.display    = 'none';
        panelEstrada.style.display = 'none';

        tabTrees.classList.add('active');
        tabOccs.classList.remove('active');
        tabEstrada.classList.remove('active');

        tabTrees2.classList.add('active');
        tabOccs2.classList.remove('active');
        tabEstrada2.classList.remove('active');

        tabTrees3.classList.add('active');
        tabOccs3.classList.remove('active');
        tabEstrada3.classList.remove('active');

        if (mapTrees) {
            setTimeout(function () { mapTrees.invalidateSize(); }, 150);
        }
    }

    function showOccsPanel() {
        panelTrees.style.display   = 'none';
        panelOccs.style.display    = '';
        panelEstrada.style.display = 'none';

        tabTrees.classList.remove('active');
        tabOccs.classList.add('active');
        tabEstrada.classList.remove('active');

        tabTrees2.classList.remove('active');
        tabOccs2.classList.add('active');
        tabEstrada2.classList.remove('active');

        tabTrees3.classList.remove('active');
        tabOccs3.classList.add('active');
        tabEstrada3.classList.remove('active');

        if (!mapOccs) {
            initOccsMap();
        } else {
            setTimeout(function () { mapOccs.invalidateSize(); }, 150);
        }
    }

    function showEstradaPanel() {
        panelTrees.style.display   = 'none';
        panelOccs.style.display    = 'none';
        panelEstrada.style.display = '';

        tabTrees.classList.remove('active');
        tabOccs.classList.remove('active');
        tabEstrada.classList.add('active');

        tabTrees2.classList.remove('active');
        tabOccs2.classList.remove('active');
        tabEstrada2.classList.add('active');

        tabTrees3.classList.remove('active');
        tabOccs3.classList.remove('active');
        tabEstrada3.classList.add('active');

        if (!mapEstrada) {
            initEstradaMap();
        } else {
            setTimeout(function () { mapEstrada.invalidateSize(); }, 150);
        }
    }

    tabTrees.addEventListener('click', showTreesPanel);
    tabTrees2.addEventListener('click', showTreesPanel);
    tabTrees3.addEventListener('click', showTreesPanel);

    tabOccs.addEventListener('click', showOccsPanel);
    tabOccs2.addEventListener('click', showOccsPanel);
    tabOccs3.addEventListener('click', showOccsPanel);

    tabEstrada.addEventListener('click', showEstradaPanel);
    tabEstrada2.addEventListener('click', showEstradaPanel);
    tabEstrada3.addEventListener('click', showEstradaPanel);

    // ====== MAPA ESPAÇOS VERDES ======
    function initTreesMap() {
        mapTrees = L.map('mapTrees', {
            attributionControl: false,
            scrollWheelZoom: 'ctrl',
            dragging: !L.Browser.mobile,
            tap: !L.Browser.mobile
        }).setView([38.5712, -7.9131], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(mapTrees);

        markerClusterTrees = L.markerClusterGroup({ disableClusteringAtZoom: 17 });
        mapTrees.addLayer(markerClusterTrees);

        loadAllTrees(function () {
            renderTreeMarkers();
            loadTreesLegend();
        });
    }

    function loadAllTrees(callback) {
        if (allTrees.length > 0) {
            if (callback) callback();
            return;
        }
        $.getJSON('data.php', function (data) {
            allTrees = data || [];
            if (callback) callback();
        });
    }

    function renderTreeMarkers(searchTerm = "") {
        if (!markerClusterTrees) return;
        markerClusterTrees.clearLayers();
        allMarkersTrees = [];

        const term = (searchTerm || "").trim().toLowerCase();

        allTrees.forEach(function (tree) {
            if (term) {
                const matches =
                    (tree.place_name && tree.place_name.toLowerCase().includes(term)) ||
                    (tree.especie && tree.especie.toLowerCase().includes(term)) ||
                    (tree.estado && tree.estado.toLowerCase().includes(term)) ||
                    (tree.tipo_intervencao && tree.tipo_intervencao.toLowerCase().includes(term));

                if (!matches) return;
            }

            const color = tree.color_name || '#888';

            const markerIcon = L.divIcon({
                className: 'custom-marker',
                html: '<span style="display:inline-block;width:17px;height:17px;background:' + color + ';border-radius:3px;border:1px solid #666;"></span>',
                iconSize: [18, 18]
            });

            const popupHTML =
                '<div style="min-width:185px;">' +
                '<strong>Espécie:</strong> <span class="text-success">' + (tree.especie || '-') + '</span><br>' +
                '<strong>Intervenção:</strong> <span>' + (tree.tipo_intervencao || 'Nenhuma') + '</span><br>' +
                '<strong>Tarefa:</strong> ' +
                '<span style="display:inline-block;width:14px;height:14px;background:' + color + ';border-radius:2px;border:1px solid #aaa;margin-right:4px;vertical-align:middle;"></span> ' +
                (tree.estado || '-') +
                '</div>';

            const lat = parseFloat(tree.latitude);
            const lng = parseFloat(tree.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = L.marker([lat, lng], { icon: markerIcon }).bindPopup(popupHTML);
            markerClusterTrees.addLayer(marker);
            allMarkersTrees.push(marker);
        });
    }

    function loadTreesLegend() {
        $.getJSON('data.php', function (data) {
            const legend = document.getElementById('legendTrees');
            if (!legend) return;

            const estados = {};
            data.forEach(function (tree) {
                if (tree.estado && tree.color_name) {
                    estados[tree.estado] = tree.color_name;
                }
            });

            Array.from(legend.querySelectorAll('.legend-item')).forEach(function (el) { el.remove(); });

            Object.entries(estados).forEach(function ([estado, color]) {
                const item = document.createElement('div');
                item.className = 'legend-item';
                item.innerHTML =
                    '<span class="legend-color" style="background:' + color + ';"></span>' +
                    '<span>' + estado + '</span>';
                legend.appendChild(item);
            });
        });
    }

    // ====== MAPA OCORRÊNCIAS ======
    function initOccsMap() {
        mapOccs = L.map('mapOccs', {
            attributionControl: false,
            scrollWheelZoom: 'ctrl',
            dragging: !L.Browser.mobile,
            tap: !L.Browser.mobile
        }).setView([38.5712, -7.9131], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(mapOccs);

        markerClusterOccs = L.markerClusterGroup({ disableClusteringAtZoom: 17 });
        mapOccs.addLayer(markerClusterOccs);

        loadAllOccs(function () {
            renderOccMarkers();
            loadOccsLegend();
        });
    }

    function loadAllOccs(callback) {
        if (allOccs.length > 0) {
            if (callback) callback();
            return;
        }
        $.getJSON('data_ocorrencias.php', function (data) {
            allOccs = data || [];
            if (callback) callback();
        });
    }

    function renderOccMarkers(searchTerm = "") {
        if (!markerClusterOccs) return;
        markerClusterOccs.clearLayers();
        allMarkersOccs = [];

        const term = (searchTerm || "").trim().toLowerCase();

        allOccs.forEach(function (o) {
            if (term) {
                const matches =
                    (o.descricao && o.descricao.toLowerCase().includes(term)) ||
                    (o.place_name && o.place_name.toLowerCase().includes(term)) ||
                    (o.tipo_intervencao && o.tipo_intervencao.toLowerCase().includes(term)) ||
                    (o.estado && o.estado.toLowerCase().includes(term));

                if (!matches) return;
            }

            const color = o.color_name || '#e63946';

            const markerIcon = L.divIcon({
                className: 'custom-marker',
                html: '<span style="display:inline-block;width:17px;height:17px;background:' + color + ';border-radius:3px;border:1px solid #666;"></span>',
                iconSize: [18, 18]
            });

            let dataTexto = '-';
            if (o.data_ocorrencia) {
                dataTexto = o.data_ocorrencia.toString().substring(0, 10);
            }

            let imagemHtml = '';
            if (o.imagem) {
                imagemHtml = '<br><strong>Imagem:</strong> <a href="uploads/ocorrencias/' + o.imagem + '" target="_blank">ver</a>';
            }

            const popupHTML =
                '<div style="min-width:220px;">' +
                '<strong>Descrição:</strong> ' + (o.descricao || '-') + '<br>' +
                '<strong>Tipo de Intervenção:</strong> ' + (o.tipo_intervencao || 'Nenhuma') + '<br>' +
                '<strong>Tarefa:</strong> ' + (o.estado || '-') + '<br>' +
                '<strong>Data:</strong> ' + dataTexto + '<br>' +
                '<strong>Local:</strong> ' + (o.place_name || '-') +
                imagemHtml +
                '</div>';

            const lat = parseFloat(o.latitude);
            const lng = parseFloat(o.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = L.marker([lat, lng], { icon: markerIcon }).bindPopup(popupHTML);
            markerClusterOccs.addLayer(marker);
            allMarkersOccs.push(marker);
        });
    }

    function loadOccsLegend() {
        $.getJSON('data_ocorrencias.php', function (data) {
            const legend = document.getElementById('legendOccs');
            if (!legend) return;

            const estados = {};
            data.forEach(function (o) {
                if (o.estado && o.color_name) {
                    estados[o.estado] = o.color_name;
                }
            });

            Array.from(legend.querySelectorAll('.legend-item')).forEach(function (el) { el.remove(); });

            Object.entries(estados).forEach(function ([estado, color]) {
                const item = document.createElement('div');
                item.className = 'legend-item';
                item.innerHTML =
                    '<span class="legend-color" style="background:' + color + ';"></span>' +
                    '<span>' + estado + '</span>';
                legend.appendChild(item);
            });
        });
    }

    // ====== MAPA OCORRÊNCIAS ESTRADA ======
    function initEstradaMap() {
        mapEstrada = L.map('mapEstrada', {
            attributionControl: false,
            scrollWheelZoom: 'ctrl',
            dragging: !L.Browser.mobile,
            tap: !L.Browser.mobile
        }).setView([38.5712, -7.9131], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(mapEstrada);

        markerClusterEstrada = L.markerClusterGroup({ disableClusteringAtZoom: 17 });
        mapEstrada.addLayer(markerClusterEstrada);

        loadAllEstrada(function () {
            renderEstradaMarkers();
            loadEstradaLegend();
        });
    }

    function loadAllEstrada(callback) {
        if (allEstrada.length > 0) {
            if (callback) callback();
            return;
        }
        $.getJSON('data_ocorrencias_estrada.php', function (data) {
            allEstrada = data || [];
            if (callback) callback();
        });
    }

    function renderEstradaMarkers(searchTerm = "") {
        if (!markerClusterEstrada) return;
        markerClusterEstrada.clearLayers();
        allMarkersEstrada = [];

        const term = (searchTerm || "").trim().toLowerCase();

        allEstrada.forEach(function (o) {
            if (term) {
                const matches =
                    (o.descricao && o.descricao.toLowerCase().includes(term)) ||
                    (o.place_name && o.place_name.toLowerCase().includes(term)) ||
                    (o.tipo_intervencao && o.tipo_intervencao.toLowerCase().includes(term)) ||
                    (o.estado && o.estado.toLowerCase().includes(term));

                if (!matches) return;
            }

            const color = o.color_name || '#f97316';

            const markerIcon = L.divIcon({
                className: 'custom-marker',
                html:
                    '<span style="display:inline-block;width:17px;height:17px;background:' +
                    color +
                    ';border-radius:3px;border:1px solid #666;"></span>',
                iconSize: [18, 18]
            });

            let dataTexto = '-';
            if (o.data_ocorrencia) {
                dataTexto = o.data_ocorrencia.toString().substring(0, 10);
            }

            let imagemHtml = '';
            if (o.imagem) {
                imagemHtml =
                    '<br><strong>Imagem:</strong> <a href="uploads/ocorrencias_estrada/' +
                    o.imagem +
                    '" target="_blank">ver</a>';
            }

            const popupHTML =
                '<div style="min-width:220px;">' +
                '<strong>Descrição:</strong> ' +
                (o.descricao || '-') +
                '<br>' +
                '<strong>Tipo de Intervenção:</strong> ' +
                (o.tipo_intervencao || 'Nenhuma') +
                '<br>' +
                '<strong>Tarefa:</strong> ' +
                (o.estado || '-') +
                '<br>' +
                '<strong>Data:</strong> ' +
                dataTexto +
                '<br>' +
                '<strong>Local:</strong> ' +
                (o.place_name || '-') +
                imagemHtml +
                '</div>';

            const lat = parseFloat(o.latitude);
            const lng = parseFloat(o.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = L.marker([lat, lng], { icon: markerIcon }).bindPopup(popupHTML);
            markerClusterEstrada.addLayer(marker);
            allMarkersEstrada.push(marker);
        });
    }

    function loadEstradaLegend() {
        $.getJSON('data_ocorrencias_estrada.php', function (data) {
            const legend = document.getElementById('legendEstrada');
            if (!legend) return;

            const estados = {};
            data.forEach(function (o) {
                if (o.estado && o.color_name) {
                    estados[o.estado] = o.color_name;
                }
            });

            Array.from(legend.querySelectorAll('.legend-item')).forEach(function (el) {
                el.remove();
            });

            Object.entries(estados).forEach(function ([estado, color]) {
                const item = document.createElement('div');
                item.className = 'legend-item';
                item.innerHTML =
                    '<span class="legend-color" style="background:' +
                    color +
                    ';"></span>' +
                    '<span>' +
                    estado +
                    '</span>';
                legend.appendChild(item);
            });
        });
    }

    // ====== SEARCH BARS ======
    const inputTrees     = document.getElementById('searchTrees');
    const btnSearchTrees = document.getElementById('btnSearchTrees');
    const btnResetTrees  = document.getElementById('btnResetTrees');

    btnSearchTrees.addEventListener('click', function () {
        renderTreeMarkers(inputTrees.value);
    });
    btnResetTrees.addEventListener('click', function () {
        inputTrees.value = '';
        renderTreeMarkers();
    });
    inputTrees.addEventListener('keyup', function () {
        renderTreeMarkers(this.value);
    });

    const inputOccs     = document.getElementById('searchOccs');
    const btnSearchOccs = document.getElementById('btnSearchOccs');
    const btnResetOccs  = document.getElementById('btnResetOccs');

    btnSearchOccs.addEventListener('click', function () {
        renderOccMarkers(inputOccs.value);
    });
    btnResetOccs.addEventListener('click', function () {
        inputOccs.value = '';
        renderOccMarkers();
    });
    inputOccs.addEventListener('keyup', function () {
        renderOccMarkers(this.value);
    });

    const inputEstrada     = document.getElementById('searchEstrada');
    const btnSearchEstrada = document.getElementById('btnSearchEstrada');
    const btnResetEstrada  = document.getElementById('btnResetEstrada');

    btnSearchEstrada.addEventListener('click', function () {
        renderEstradaMarkers(inputEstrada.value);
    });
    btnResetEstrada.addEventListener('click', function () {
        inputEstrada.value = '';
        renderEstradaMarkers();
    });
    inputEstrada.addEventListener('keyup', function () {
        renderEstradaMarkers(this.value);
    });

    // Inicialização do mapa principal
    document.addEventListener('DOMContentLoaded', function () {
        initTreesMap();
    });
</script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
