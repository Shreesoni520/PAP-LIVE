<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Mapa 2D - Espaços Verdes de Évora</title>
  <meta name="description" content="Mapa interativo dos espaços verdes urbanos de Évora.">
  <meta name="keywords" content="Évora, espaços verdes, mapa, árvores">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    html, body {
      max-width: 100%;
      overflow-x: hidden;
    }

    .main,
    #hero,
    #mapa,
    #map {
      width: 100%;
      max-width: 100vw;
      overflow-x: hidden;
    }

    body {
      font-family: 'Open Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .map-filters-card {
      background: #ffffff;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      padding: 16px 18px;
    }

    #map-wrapper {
      position: relative;
    }

    #map {
      height: 420px;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    }

    @media (min-width: 992px) {
      #map {
        height: 480px;
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
      max-width: 210px;
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

    .filters-column {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .filter-widget {
      border-radius: 14px;
      border: 1px solid #e5e7eb;
      background-color: #ffffff;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
      overflow: hidden;
    }

    .filter-widget-header {
      background-color: #f9fafb;
      padding: 0.7rem 0.95rem;
      border-bottom: 1px solid #e5e7eb;
    }

    .filter-widget-header h3 {
      font-size: 1rem;
      margin: 0;
      font-weight: 600;
      color: #111827;
    }

    .filter-widget-body {
      padding: 0.65rem 0.9rem 0.75rem 0.9rem;
    }

    .filter-list {
      list-style: none;
      padding-left: 0;
      margin-bottom: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .filter-list li {
      margin: 0;
    }

    .filter-btn {
      display: inline-block;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      color: #4b5563;
      border-radius: 50px;
      padding: 6px 14px;
      font-size: 0.85rem;
      line-height: 1.2;
      cursor: pointer;
      text-align: left;
      text-decoration: none;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .filter-btn:hover {
      border-color: #0d6efd;
      color: #0d6efd;
      background-color: #eff6ff;
    }

    .filter-btn.active {
      font-weight: 600;
      color: #ffffff;
      background-color: #0d6efd;
      border-color: #0d6efd;
      box-shadow: 0 2px 6px rgba(13, 110, 253, 0.2);
    }

    .leaflet-popup-content {
      font-size: 0.95rem;
    }

    @media (max-width: 768px) {
      #map {
        height: 320px;
      }
      .legend-box {
        font-size: 0.84rem;
        left: 10px;
        bottom: 10px;
        padding: 6px 10px;
      }
    }

    .leaflet-top.leaflet-left {
      top: 0;
    }

    .header {
      z-index: 1030;
    }
    .leaflet-top,
    .leaflet-bottom,
    .leaflet-control {
      z-index: 400;
    }

    .map-mode-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 4px;
      margin-bottom: 10px;
    }
    .map-mode-btn {
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background-color: #f9fafb;
      color: #4b5563;
      padding: 6px 14px;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .map-mode-btn i {
      font-size: 1rem;
    }
    .map-mode-btn:hover {
      border-color: #0d6efd;
      color: #0d6efd;
      background-color: #eff6ff;
    }
    .map-mode-btn.active {
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: #ffffff;
      border-color: transparent;
      box-shadow: 0 8px 22px rgba(37, 99, 235, 0.35);
    }

    .top-info-row {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 8px;
      align-items: flex-start;
    }
    .top-info-left {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .top-info-right-btn {
      display: flex;
      align-items: flex-start;
    }

    .top-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: #111827;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .top-subtitle {
      font-size: 0.85rem;
      color: #6b7280;
      line-height: 1.3;
    }

    .filters-toggle-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background: #ffffff;
      color: #4b5563;
      padding: 6px 14px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .filters-toggle-btn i {
      font-size: 1rem;
    }
    .filters-toggle-btn:hover {
      border-color: #0d6efd;
      color: #0d6efd;
      background-color: #eff6ff;
    }

    .map-filters-row {
      transition: all 0.3s ease;
    }

    .col-map {
      transition: all 0.3s ease;
    }

    .col-filters {
      transition: all 0.3s ease;
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transform: translateX(20px);
    }

    .map-filters-row.filters-open .col-filters {
      max-height: 2000px;
      opacity: 1;
      transform: translateX(0);
    }

    .filters-header {
      margin-bottom: 8px;
      opacity: 0;
      transform: translateY(-6px);
      transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .map-filters-row.filters-open .filters-header {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 767.98px) {
      body.index-page {
        padding-top: 40px;
        background-color: #37517e;
      }

      .top-info-row {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body class="index-page">

<header id="header" class="header d-flex align-items-center fixed-top">
  <?php include "menu.php"; ?>
</header>

<main class="main">

  <section id="hero" class="section dark-background"
           style="height: 300px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
    <div class="container h-100 d-flex align-items-center justify-content-center">
      <div class="row w-100 gy-0 align-items-center justify-content-center">
        <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
          <h2>Mapa 2D Interativo</h2>
          <p>Explore os espaços verdes urbanos de Évora e visualize a tarefa das árvores e ocorrências associadas.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="mapa" class="section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">

      <div class="map-filters-card">
        <div class="row g-4 align-items-stretch map-filters-row" id="mapFiltersRow">

          <!-- COL MAPA -->
          <div class="col-12 col-lg-12 col-map" id="colMap">
            <div id="map-wrapper">

              <div class="top-info-row">
                <div class="top-info-left">
                  <div class="top-title">
                    <span>Mapa de Évora</span>
                  </div>
                  <div class="top-subtitle">
                    Veja árvores, ocorrências em espaços verdes e ocorrências em estrada no mesmo mapa.
                  </div>
                </div>

                <div class="top-info-right-btn">
                  <button type="button" class="filters-toggle-btn" id="toggleFilters">
                    <i class="bi bi-funnel"></i> Filtros
                  </button>
                </div>
              </div>

              <div class="map-mode-bar">
                <button type="button" class="map-mode-btn active" id="btnModeArvores">
                  <i class="bi bi-tree"></i> Árvores / Espaços Verdes
                </button>
                <button type="button" class="map-mode-btn" id="btnModeOccVerde">
                  <i class="bi bi-flag"></i> Ocorrências Espaço Verde
                </button>
                <button type="button" class="map-mode-btn" id="btnModeOccEstrada">
                  <i class="bi bi-signpost-2"></i> Ocorrências Estrada
                </button>
              </div>

              <div id="map">
                <div class="legend-box" id="legend">
                  <span class="legend-title" id="legend-title">Tarefas das árvores</span>
                </div>
              </div>
            </div>
          </div>

          <!-- COL FILTROS -->
          <div class="col-12 col-lg-4 col-filters" id="colFilters">
            <div class="filters-header">
              <div class="top-title">
                <span>Filtros rápidos</span>
              </div>
              <div class="top-subtitle">
                Filtre por espécie ou tipo, intervenção e tarefas.
              </div>
            </div>

            <div class="filters-column" data-aos="fade-up" data-aos-delay="150">

              <div class="filter-widget">
                <div class="filter-widget-header">
                  <h3 id="label-especie">Espécie</h3>
                </div>
                <div class="filter-widget-body">
                  <ul class="filter-list" id="filter-especie"></ul>
                </div>
              </div>

              <div class="filter-widget">
                <div class="filter-widget-header">
                  <h3>Intervenção</h3>
                </div>
                <div class="filter-widget-body">
                  <ul class="filter-list" id="filter-intervencao"></ul>
                </div>
              </div>

              <div class="filter-widget">
                <div class="filter-widget-header">
                  <h3 id="label-tarefa">Tarefa</h3>
                </div>
                <div class="filter-widget-body">
                  <ul class="filter-list" id="filter-tarefa"></ul>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>

    </div>
  </section>

</main>

<?php include "footer.php"; ?>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<div id="preloader"></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<script>
  let map = L.map('map', {
    attributionControl: false,
    scrollWheelZoom: 'ctrl',
    dragging: !L.Browser.mobile,
    tap: !L.Browser.mobile
  }).setView([38.5712, -7.9131], 14);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
  }).addTo(map);

  const markerCluster = L.markerClusterGroup({ disableClusteringAtZoom: 17 });
  map.addLayer(markerCluster);

  let allTrees = [];
  let allOccs = [];
  let allEstrada = [];

  let allMarkers = [];

  let currentMode = 'arvores';

  let activeFilters = {
    especie: null,
    intervencao: null,
    tarefa: null
  };

  const btnModeArvores    = document.getElementById('btnModeArvores');
  const btnModeOccVerde   = document.getElementById('btnModeOccVerde');
  const btnModeOccEstrada = document.getElementById('btnModeOccEstrada');
  const legendTitleEl     = document.getElementById('legend-title');
  const labelEspecieEl    = document.getElementById('label-especie');
  const labelTarefaEl     = document.getElementById('label-tarefa');

  const toggleFiltersBtn  = document.getElementById('toggleFilters');
  const mapFiltersRow     = document.getElementById('mapFiltersRow');
  const colMap            = document.getElementById('colMap');
  const colFilters        = document.getElementById('colFilters');

  let filtersOpen = false;

  function updateMapSize() {
    setTimeout(function() {
      map.invalidateSize();
    }, 350);
  }

  toggleFiltersBtn.addEventListener('click', function () {
    filtersOpen = !filtersOpen;
    mapFiltersRow.classList.toggle('filters-open', filtersOpen);

    const icon = this.querySelector('i');
    if (filtersOpen) {
      colMap.classList.remove('col-lg-12');
      colMap.classList.add('col-lg-8');
      colFilters.style.display = 'block';
      icon.className = 'bi bi-x';
    } else {
      colMap.classList.remove('col-lg-8');
      colMap.classList.add('col-lg-12');
      icon.className = 'bi bi-funnel';

      setTimeout(function () {
        if (!filtersOpen) {
          colFilters.style.display = 'none';
        }
      }, 300);
    }

    updateMapSize();
  });

  colFilters.style.display = 'none';

  btnModeArvores.addEventListener('click', () => setMode('arvores'));
  btnModeOccVerde.addEventListener('click', () => setMode('occ_verde'));
  btnModeOccEstrada.addEventListener('click', () => setMode('occ_estrada'));

  function setMode(mode) {
    currentMode = mode;
    activeFilters = { especie: null, intervencao: null, tarefa: null };

    [btnModeArvores, btnModeOccVerde, btnModeOccEstrada].forEach(btn => btn.classList.remove('active'));
    if (mode === 'arvores')     btnModeArvores.classList.add('active');
    if (mode === 'occ_verde')   btnModeOccVerde.classList.add('active');
    if (mode === 'occ_estrada') btnModeOccEstrada.classList.add('active');

    if (mode === 'arvores') {
      legendTitleEl.textContent = 'Tarefas das árvores';
      labelEspecieEl.textContent = 'Espécie';
      labelTarefaEl.textContent  = 'Tarefa';
    } else if (mode === 'occ_verde') {
      legendTitleEl.textContent = 'Tarefas das ocorrências (espaço verde)';
      labelEspecieEl.textContent = 'Tipo / Categoria';
      labelTarefaEl.textContent  = 'Tarefa';
    } else {
      legendTitleEl.textContent = 'Tarefas das ocorrências (estrada)';
      labelEspecieEl.textContent = 'Tipo / Categoria';
      labelTarefaEl.textContent  = 'Tarefa';
    }

    loadDataAndRender();
  }

  function loadDataAndRender() {
    if (currentMode === 'arvores') {
      if (allTrees.length > 0) {
        renderMarkersForCurrentMode();
      } else {
        $.getJSON('data.php', function (data) {
          allTrees = data || [];
          renderMarkersForCurrentMode();
        });
      }
    } else if (currentMode === 'occ_verde') {
      if (allOccs.length > 0) {
        renderMarkersForCurrentMode();
      } else {
        $.getJSON('data_ocorrencias.php', function (data) {
          allOccs = data || [];
          renderMarkersForCurrentMode();
        });
      }
    } else {
      if (allEstrada.length > 0) {
        renderMarkersForCurrentMode();
      } else {
        $.getJSON('data_ocorrencias_estrada.php', function (data) {
          allEstrada = data || [];
          renderMarkersForCurrentMode();
        });
      }
    }
  }

  function renderMarkersForCurrentMode() {
    markerCluster.clearLayers();
    allMarkers = [];

    let dataArray = [];
    if (currentMode === 'arvores') {
      dataArray = allTrees;
    } else if (currentMode === 'occ_verde') {
      dataArray = allOccs;
    } else {
      dataArray = allEstrada;
    }

    let especiesSet      = new Set();
    let intervencoesSet  = new Set();
    let tarefasSet       = new Set();

    dataArray.forEach(function (item) {
      let especieLabel;
      let tipo_intervencao = item.tipo_intervencao || '';
      let tarefaValue;

      if (currentMode === 'arvores') {
        especieLabel = item.especie || '';
        tarefaValue  = item.tarefa || item.estado || '';
      } else if (currentMode === 'occ_verde') {
        especieLabel = item.tipo || '';
        tarefaValue  = item.tarefa || item.estado || '';
      } else {
        especieLabel = item.tipo || '';
        tarefaValue  = item.tarefa || item.tarefas || item.estado || '';
      }

      if (especieLabel) {
        especiesSet.add(especieLabel);
      }
      if (tipo_intervencao) {
        intervencoesSet.add(tipo_intervencao);
      }
      if (tarefaValue) {
        tarefasSet.add(tarefaValue);
      }

      const color = item.color_name || (currentMode === 'arvores' ? '#10b981' : '#ef4444');

      const markerIcon = L.divIcon({
        className: 'custom-marker',
        html: '<span style="display:inline-block;width:17px;height:17px;background:' + color + ';border-radius:3px;border:1px solid #666;"></span>',
        iconSize: [18, 18]
      });

      let popupHTML = '';

      if (currentMode === 'arvores') {
        popupHTML =
          '<div style="min-width:185px;">' +
          '<strong>Espécie:</strong> <span class="text-success">' + (item.especie || '-') + '</span><br>' +
          '<strong>Intervenção:</strong> <span>' + (item.tipo_intervencao || 'Nenhuma') + '</span><br>' +
          '<strong>Tarefa:</strong> ' +
          '<span style="display:inline-block;width:14px;height:14px;background:' + color + ';border-radius:2px;border:1px solid #aaa;margin-right:4px;vertical-align:middle;"></span> ' +
          (item.tarefa || item.estado || '-') +
          '</div>';
      } else {
        let dataTexto = '-';
        if (item.data_ocorrencia) {
          dataTexto = item.data_ocorrencia.toString().substring(0, 10);
        }
        let imagemHtml = '';
        if (item.imagem) {
          const pasta = (currentMode === 'occ_estrada') ? 'uploads/ocorrencias_estrada/' : 'uploads/ocorrencias/';
          imagemHtml = '<br><strong>Imagem:</strong> <a href="' + pasta + item.imagem + '" target="_blank">ver</a>';
        }

        const tarefaTexto = (currentMode === 'occ_estrada')
          ? (item.tarefa || item.tarefas || item.estado || '-')
          : (item.tarefa || item.estado || '-');

        popupHTML =
          '<div style="min-width:220px;">' +
          '<strong>Descrição:</strong> ' + (item.descricao || '-') + '<br>' +
          '<strong>Tipo de Intervenção:</strong> ' + (item.tipo_intervencao || 'Nenhuma') + '<br>' +
          '<strong>Tarefa:</strong> ' + tarefaTexto + '<br>' +
          '<strong>Data:</strong> ' + dataTexto + '<br>' +
          '<strong>Local:</strong> ' + (item.place_name || '-') +
          imagemHtml +
          '</div>';
      }

      const lat = parseFloat(item.latitude);
      const lng = parseFloat(item.longitude);
      if (isNaN(lat) || isNaN(lng)) return;

      const marker = L.marker([lat, lng], { icon: markerIcon }).bindPopup(popupHTML);

      marker.treeData = {
        especie: especieLabel,
        tipo_intervencao: tipo_intervencao,
        tarefa: tarefaValue
      };

      markerCluster.addLayer(marker);
      allMarkers.push(marker);
    });

    buildLegend(dataArray);
    buildFilterList('filter-especie', 'especie', especiesSet);
    buildFilterList('filter-intervencao', 'intervencao', intervencoesSet);
    buildFilterList('filter-tarefa', 'tarefa', tarefasSet);

    map.setView([38.5712, -7.9131], 14);
  }

  function buildLegend(data) {
    let legend = document.getElementById('legend');
    if (!legend) return;

    Array.from(legend.querySelectorAll('.legend-item')).forEach(el => el.remove());

    let tarefas = {};
    data.forEach(item => {
      const tarefaValue = item.tarefa || item.tarefas || item.estado || '';
      if (tarefaValue && item.color_name) {
        tarefas[tarefaValue] = item.color_name;
      }
    });

    Object.entries(tarefas).forEach(([tarefa, color]) => {
      let item = document.createElement('div');
      item.className = 'legend-item';
      item.innerHTML =
        '<span class="legend-color" style="background:' + color + ';"></span>' +
        '<span>' + tarefa + '</span>';
      legend.appendChild(item);
    });
  }

  function buildFilterList(elementId, filterKey, valuesSet) {
    const container = document.getElementById(elementId);
    if (!container) return;

    container.innerHTML = '';

    const liAll = document.createElement('li');
    const btnAll = document.createElement('button');
    btnAll.type = 'button';
    btnAll.textContent = 'Todos';
    btnAll.className = 'filter-btn active';
    btnAll.dataset.value = '';
    btnAll.addEventListener('click', function () {
      setFilter(filterKey, null);
      updateFilterButtons(elementId, '');
    });
    liAll.appendChild(btnAll);
    container.appendChild(liAll);

    Array.from(valuesSet).sort().forEach(val => {
      const li = document.createElement('li');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'filter-btn';
      btn.textContent = val;
      btn.dataset.value = val;
      btn.addEventListener('click', function () {
        if (activeFilters[filterKey] === val) {
          setFilter(filterKey, null);
          updateFilterButtons(elementId, '');
        } else {
          setFilter(filterKey, val);
          updateFilterButtons(elementId, val);
        }
      });
      li.appendChild(btn);
      container.appendChild(li);
    });
  }

  function updateFilterButtons(elementId, activeValue) {
    const container = document.getElementById(elementId);
    if (!container) return;

    container.querySelectorAll('.filter-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === activeValue);
    });
  }

  function setFilter(key, value) {
    activeFilters[key] = value;
    applyFilters();
  }

  function applyFilters() {
    markerCluster.clearLayers();

    allMarkers.forEach(marker => {
      const t = marker.treeData || {};

      let matchEspecie      = true;
      let matchIntervencao  = true;
      let matchTarefa       = true;

      if (activeFilters.especie) {
        matchEspecie = (t.especie === activeFilters.especie);
      }
      if (activeFilters.intervencao) {
        matchIntervencao = (t.tipo_intervencao === activeFilters.intervencao);
      }
      if (activeFilters.tarefa) {
        matchTarefa = (t.tarefa === activeFilters.tarefa);
      }

      if (matchEspecie && matchIntervencao && matchTarefa) {
        markerCluster.addLayer(marker);
      }
    });

    zoomToVisibleMarkers();
  }

  function zoomToVisibleMarkers() {
    let bounds = markerCluster.getBounds();
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [30, 30] });
    } else {
      map.setView([38.5712, -7.9131], 14);
    }
  }

  $(document).ready(function () {
    setMode('arvores');
  });
</script>

<script src="assets/js/main.js"></script>
</body>
</html>
