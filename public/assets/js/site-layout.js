(function () {
  function formatDisplayName(raw) {
    var nome = String(raw || "").trim();
    if (!nome) return "Utilizador";
    var parts = nome.split(/\s+/);
    var first = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    if (parts.length > 1) {
      var last = parts[parts.length - 1];
      return first + " " + last.charAt(0).toUpperCase() + last.slice(1).toLowerCase();
    }
    return first;
  }

  function initialsFromName(raw) {
    var nome = formatDisplayName(raw);
    var parts = nome.split(/\s+/);
    if (parts.length >= 2) {
      return ((parts[0][0] || "") + (parts[1][0] || "")).toUpperCase();
    }
    return (parts[0] || "U").slice(0, 2).toUpperCase();
  }

  function headerHtml(user) {
    var account;
    if (user) {
      var avatarInner = user.avatar
        ? '<img src="' + user.avatar + '" alt="Avatar" class="nav-avatar-img">'
        : '<span class="nav-avatar-initials">' + initialsFromName(user.nome) + "</span>";
      account =
        '<li class="dropdown ms-2">' +
        '<a href="#" class="d-flex align-items-center nav-avatar-toggle">' +
        '<div class="nav-avatar">' +
        avatarInner +
        "</div>" +
        '<span class="nav-avatar-name ms-2 d-none d-lg-inline">' +
        formatDisplayName(user.nome) +
        "</span>" +
        '<i class="bi bi-chevron-down toggle-dropdown ms-1"></i></a>' +
        "<ul>" +
        '<li><hr class="dropdown-divider"></li>' +
        '<li><a href="/profile.html">Perfil</a></li>' +
        '<li><a href="/myocorrencias.html">Minhas Ocorrências</a></li>' +
        '<li><a href="/mymensagens.html">Minhas Mensagens</a></li>' +
        '<li><a href="/seguranca.html">Segurança</a></li>' +
        '<li><a href="/api/auth/logout">Terminar sessão</a></li>' +
        "</ul></li>";
    } else {
      account =
        '<li class="ms-2"><a href="/login.html" class="btn-getstarted btn-sm text-white px-3 rounded-pill">Iniciar sessão</a></li>';
    }

    return (
      '<style>.nav-avatar-toggle{text-decoration:none;color:#f9fafb}.nav-avatar{width:34px;height:34px;border-radius:999px;overflow:hidden;box-shadow:0 4px 10px rgba(15,23,42,.45);flex-shrink:0;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:flex;align-items:center;justify-content:center}.nav-avatar-img{width:100%;height:100%;object-fit:cover;display:block}.nav-avatar-initials{font-weight:600;font-size:.85rem;letter-spacing:.04em;color:#e5e7eb}.nav-avatar-name{font-size:.8rem;color:#e5e7eb;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;opacity:.9}</style>' +
      '<div class="container-fluid container-xl position-relative d-flex align-items-center">' +
      '<a href="/" class="logo d-flex align-items-center me-auto"><img src="/Admin/assets/images/logo/logo.png" alt="Logo"></a>' +
      '<nav id="navmenu" class="navmenu"><ul>' +
      '<li><a href="/">Início</a></li>' +
      '<li><a href="/information.html">Informação</a></li>' +
      '<li><a href="/noticias.html">Notícias</a></li>' +
      '<li><a href="/mapa.html">Mapa</a></li>' +
      '<li class="dropdown"><a href="#"><span>Ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a><ul>' +
      '<li class="dropdown"><a href="/ocorrencias.html"><span>Registar Ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a><ul>' +
      '<li><a href="/ocorrencias.html">Espaço verde</a></li>' +
      '<li><a href="/ocorrencias-estrada.html">Estrada</a></li>' +
      "</ul></li>" +
      '<li class="dropdown"><a href="/listar-ocorrencias.html"><span>Lista ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a><ul>' +
      '<li><a href="/listar-ocorrencias.html">Espaço verde</a></li>' +
      '<li><a href="/listar-ocorrencias-estrada.html">Estrada</a></li>' +
      "</ul></li></ul></li>" +
      '<li><a href="/contact.html">Contacto</a></li>' +
      account +
      '</ul><i class="mobile-nav-toggle d-xl-none bi bi-list"></i></nav></div>'
    );
  }

  function footerHtml(loggedIn) {
    var year = new Date().getFullYear();
    var conta = loggedIn
      ? '<li class="mb-2"><a href="/profile.html" class="text-decoration-none footer-link">Perfil</a></li>' +
        '<li class="mb-2"><a href="/myocorrencias.html" class="text-decoration-none footer-link">Minhas ocorrências</a></li>' +
        '<li class="mb-2"><a href="/mymensagens.html" class="text-decoration-none footer-link">Minhas mensagens</a></li>' +
        '<li class="mb-2"><a href="/api/auth/logout" class="text-decoration-none footer-link">Terminar sessão</a></li>'
      : '<li class="mb-2"><a href="/login.html" class="text-decoration-none footer-link">Iniciar sessão</a></li>' +
        '<li class="mb-2"><a href="/signup.html" class="text-decoration-none footer-link">Criar conta</a></li>' +
        '<li class="mb-2"><a href="/forgot-password.html" class="text-decoration-none footer-link">Recuperar password</a></li>';

    return (
      '<footer id="footer" class="footer text-white pt-5" style="background:radial-gradient(circle at top,#111827 0%,#020617 45%,#000 100%);border-top:1px solid rgba(255,255,255,.08)">' +
      '<div class="container">' +
      '<div class="row justify-content-center text-center pb-5"><div class="col-lg-8">' +
      '<span style="letter-spacing:.12em;color:#6b7280;font-size:.75rem;text-transform:uppercase">Fica a par das novidades</span>' +
      '<h4 class="mt-2 mb-2" style="font-weight:700">Subscreve a nossa newsletter</h4>' +
      '<p class="mb-4" style="color:#9ca3af;font-size:.95rem">Recebe notícias, alertas e informação útil sobre Évora diretamente no teu email.</p>' +
      '<form id="newsletter-form">' +
      '<div class="d-flex flex-column flex-sm-row align-items-stretch gap-2 mx-auto" style="max-width:560px">' +
      '<div class="d-flex align-items-center flex-grow-1 px-3 py-2" style="background:rgba(15,23,42,.95);border-radius:999px;border:1px solid rgba(148,163,184,.35)">' +
      '<span class="me-2" style="color:#9ca3af"><i class="bi bi-envelope-fill"></i></span>' +
      '<input type="email" name="email" class="form-control border-0 bg-transparent text-white" placeholder="O teu email" required style="box-shadow:none">' +
      "</div>" +
      '<button type="submit" class="btn" style="border-radius:999px;padding:10px 24px;font-weight:600;background:linear-gradient(135deg,#0ea5e9,#22c55e);border:none;color:#fff">Subscrever</button>' +
      "</div>" +
      '<div id="newsletter-msg" class="mt-3" style="font-size:.85rem"></div>' +
      "</form></div></div>" +
      '<div class="row gy-4 pb-4">' +
      '<div class="col-lg-4 col-md-6"><a href="/" class="text-decoration-none d-inline-block mb-3"><span style="font-weight:700;font-size:1.5rem;color:#fff">Reporta Évora</span></a>' +
      '<p style="color:#9ca3af;font-size:.92rem;line-height:1.7">Plataforma digital para consulta de informação útil e registo de ocorrências urbanas em Évora.</p>' +
      '<div class="mt-3" style="font-size:.9rem"><p class="mb-1" style="color:#e5e7eb">Av. Dinis Miranda</p><p class="mb-2" style="color:#e5e7eb">Évora, 7005-140</p>' +
      '<p class="mb-1" style="color:#9ca3af"><strong>Telefone:</strong> +351 920 263 262</p>' +
      '<p class="mb-0" style="color:#9ca3af"><strong>Email:</strong> shreesoni520@gmail.com</p></div></div>' +
      '<div class="col-lg-2 col-md-6"><h5 class="mb-3" style="font-size:.95rem;text-transform:uppercase;letter-spacing:.08em;color:#d1d5db">Navegação</h5><ul class="list-unstyled mb-0">' +
      '<li class="mb-2"><a href="/" class="text-decoration-none footer-link">Início</a></li>' +
      '<li class="mb-2"><a href="/information.html" class="text-decoration-none footer-link">Informação útil</a></li>' +
      '<li class="mb-2"><a href="/noticias.html" class="text-decoration-none footer-link">Notícias</a></li>' +
      '<li class="mb-2"><a href="/mapa.html" class="text-decoration-none footer-link">Mapa</a></li>' +
      '<li class="mb-2"><a href="/contact.html" class="text-decoration-none footer-link">Contactos</a></li></ul></div>' +
      '<div class="col-lg-2 col-md-6"><h5 class="mb-3" style="font-size:.95rem;text-transform:uppercase;letter-spacing:.08em;color:#d1d5db">Ocorrências</h5><ul class="list-unstyled mb-0">' +
      '<li class="mb-2"><a href="/ocorrencias.html" class="text-decoration-none footer-link">Ocorrências urbanas</a></li>' +
      '<li class="mb-2"><a href="/ocorrencias-estrada.html" class="text-decoration-none footer-link">Ocorrências estrada</a></li>' +
      '<li class="mb-2"><a href="/listar-ocorrencias.html" class="text-decoration-none footer-link">Listar ocorrências</a></li>' +
      '<li class="mb-2"><a href="/listar-ocorrencias-estrada.html" class="text-decoration-none footer-link">Listar ocorrências estrada</a></li></ul></div>' +
      '<div class="col-lg-2 col-md-6"><h5 class="mb-3" style="font-size:.95rem;text-transform:uppercase;letter-spacing:.08em;color:#d1d5db">Conta</h5><ul class="list-unstyled mb-0">' +
      conta +
      "</ul></div>" +
      '<div class="col-lg-2 col-md-6"><h5 class="mb-3" style="font-size:.95rem;text-transform:uppercase;letter-spacing:.08em;color:#d1d5db">Mais</h5><ul class="list-unstyled mb-0">' +
      '<li class="mb-2"><a href="/seguranca.html" class="text-decoration-none footer-link">Segurança</a></li>' +
      '<li class="mb-2"><a href="/unsubscribe.html" class="text-decoration-none footer-link">Cancelar newsletter</a></li></ul></div>' +
      "</div>" +
      '<div class="text-center py-3" style="border-top:1px solid rgba(255,255,255,.08);color:#9ca3af;font-size:.85rem">© ' +
      year +
      " Reporta Évora</div></div></footer>" +
      '<style>.footer-link{color:#9ca3af}.footer-link:hover{color:#fff}</style>'
    );
  }

  function vendorHead() {
    /* pages include their own head */
  }

  async function initLayout() {
    var headerEl = document.getElementById("site-header");
    var footerMount = document.getElementById("site-footer");
    var user = null;
    try {
      var res = await fetch("/api/auth/me", { credentials: "same-origin" });
      var data = await res.json();
      if (data.ok && data.loggedIn) user = data.user;
    } catch (e) {
      /* offline */
    }

    if (headerEl) headerEl.innerHTML = headerHtml(user);
    if (footerMount) footerMount.innerHTML = footerHtml(Boolean(user));

    var ctaTitle = document.getElementById("cta-title");
    var ctaText = document.getElementById("cta-text");
    var ctaBtn = document.getElementById("cta-btn");
    if (ctaTitle && ctaText && ctaBtn) {
      if (user) {
        ctaTitle.textContent = "Bem-vindo de volta";
        ctaText.textContent =
          "Aceda ao seu perfil para consultar e atualizar os seus dados, gerir as suas preferências e acompanhar a sua atividade na plataforma.";
        ctaBtn.textContent = "Ver perfil";
        ctaBtn.setAttribute("href", "/profile.html");
      } else {
        ctaTitle.textContent = "Crie a sua conta";
        ctaText.textContent =
          "Com uma conta pode aceder à área reservada, gerir o seu perfil e ter uma experiência mais personalizada na plataforma.";
        ctaBtn.textContent = "Criar conta";
        ctaBtn.setAttribute("href", "/signup.html");
      }
    }

    var form = document.getElementById("newsletter-form");
    if (form) {
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        var msg = document.getElementById("newsletter-msg");
        var email = form.email.value;
        try {
          var r = await fetch("/api/newsletter", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ email: email }),
          });
          var d = await r.json();
          if (msg) {
            msg.style.color = d.ok ? "#bbf7d0" : "#fca5a5";
            msg.textContent = d.message || (d.ok ? "OK" : "Erro");
          }
          if (d.redirect) window.location.href = d.redirect;
        } catch (err) {
          if (msg) {
            msg.style.color = "#fca5a5";
            msg.textContent = "Erro de rede.";
          }
        }
      });
    }

    if (window.AOS) {
      window.AOS.init({ duration: 600, easing: "ease-in-out", once: true, mirror: false });
    }
  }

  window.ReportaLayout = { initLayout: initLayout, formatDisplayName: formatDisplayName };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initLayout);
  } else {
    initLayout();
  }
})();
