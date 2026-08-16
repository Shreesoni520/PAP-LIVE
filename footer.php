<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$newsletter_error   = $_SESSION['newsletter_error'] ?? '';
$newsletter_success = $_SESSION['newsletter_success'] ?? '';
unset($_SESSION['newsletter_error'], $_SESSION['newsletter_success']);

$isLoggedIn = !empty($_SESSION['public_user_id']);
?>

<footer id="footer" class="footer text-white pt-5" style="background: radial-gradient(circle at top, #111827 0%, #020617 45%, #000000 100%); border-top: 1px solid rgba(255,255,255,0.08);">

  <div class="container">

    <!-- Newsletter -->
    <div class="row justify-content-center text-center pb-5">
      <div class="col-lg-8">
        <span style="letter-spacing: .12em; color:#6b7280; font-size:.75rem; text-transform:uppercase;">
          Fica a par das novidades
        </span>

        <h4 class="mt-2 mb-2" style="font-weight:700;">Subscreve a nossa newsletter</h4>

        <p class="mb-4" style="color:#9ca3af; font-size:0.95rem;">
          Recebe notícias, alertas e informação útil sobre Évora diretamente no teu email.
        </p>

        <form action="index.php?evora_p=newsletter" method="post">
          <div class="d-flex flex-column flex-sm-row align-items-stretch gap-2 mx-auto" style="max-width:560px;">

            <div class="d-flex align-items-center flex-grow-1 px-3 py-2"
                 style="background:rgba(15,23,42,0.95); border-radius:999px; border:1px solid rgba(148,163,184,0.35); box-shadow:0 18px 35px rgba(0,0,0,0.45);">
              <span class="me-2" style="color:#9ca3af; font-size:1rem;">
                <i class="bi bi-envelope-fill"></i>
              </span>

              <input type="email"
                     name="email"
                     class="form-control border-0 bg-transparent text-white"
                     placeholder="O teu email"
                     required
                     style="font-size:0.95rem; box-shadow:none;">
            </div>

            <button type="submit"
                    class="btn"
                    style="border-radius:999px; padding:10px 24px; font-size:0.95rem; font-weight:600; background:linear-gradient(135deg,#0ea5e9,#22c55e); border:none; color:#fff; white-space:nowrap; box-shadow:0 12px 30px rgba(34,197,94,0.25);">
              Subscrever
            </button>
          </div>

          <?php if ($newsletter_error): ?>
            <div class="mt-3" style="font-size:0.85rem; color:#fca5a5;">
              <?= htmlspecialchars($newsletter_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <?php if ($newsletter_success): ?>
            <div class="mt-3" style="font-size:0.85rem; color:#bbf7d0;">
              <?= htmlspecialchars($newsletter_success, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Main footer content -->
    <div class="row gy-4 pb-4">

      <!-- About -->
      <div class="col-lg-4 col-md-6">
        <a href="index.php?evora_p=inicio" class="text-decoration-none d-inline-block mb-3">
          <span style="font-weight:700; font-size:1.5rem; color:#ffffff;">Reporta Évora</span>
        </a>

        <p style="color:#9ca3af; font-size:0.92rem; line-height:1.7;">
          Plataforma digital para consulta de informação útil e registo de ocorrências urbanas em Évora.
          Um único portal com notícias, mapas, contactos e serviços importantes para a cidade.
        </p>

        <div class="mt-3" style="font-size:0.9rem;">
          <p class="mb-1" style="color:#e5e7eb;">Av. Dinis Miranda</p>
          <p class="mb-2" style="color:#e5e7eb;">Évora, 7005-140</p>
          <p class="mb-1" style="color:#9ca3af;">
            <strong>Telefone:</strong> +351 920 263 262
          </p>
          <p class="mb-0" style="color:#9ca3af;">
            <strong>Email:</strong> shreesoni520@gmail.com
          </p>
        </div>
      </div>

      <!-- Navegação -->
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3" style="font-size:0.95rem; text-transform:uppercase; letter-spacing:.08em; color:#d1d5db;">
          Navegação
        </h5>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="index.php?evora_p=inicio" class="text-decoration-none footer-link">Início</a></li>
          <li class="mb-2"><a href="index.php?evora_p=information" class="text-decoration-none footer-link">Informação útil</a></li>
          <li class="mb-2"><a href="index.php?evora_p=noticias" class="text-decoration-none footer-link">Notícias</a></li>
          <li class="mb-2"><a href="index.php?evora_p=mapa" class="text-decoration-none footer-link">Mapa</a></li>
          <li class="mb-2"><a href="index.php?evora_p=contact" class="text-decoration-none footer-link">Contactos</a></li>
        </ul>
      </div>

      <!-- Ocorrências -->
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3" style="font-size:0.95rem; text-transform:uppercase; letter-spacing:.08em; color:#d1d5db;">
          Ocorrências
        </h5>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="index.php?evora_p=ocorrencias" class="text-decoration-none footer-link">Ocorrências urbanas</a></li>
          <li class="mb-2"><a href="index.php?evora_p=ocorrenciasestrada" class="text-decoration-none footer-link">Ocorrências estrada</a></li>
          <li class="mb-2"><a href="index.php?evora_p=listocorrencias" class="text-decoration-none footer-link">Listar ocorrências</a></li>
          <li class="mb-2"><a href="index.php?evora_p=listarocorrenciasestrada" class="text-decoration-none footer-link">Listar ocorrências estrada</a></li>
        </ul>
      </div>

      <!-- Conta -->
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3" style="font-size:0.95rem; text-transform:uppercase; letter-spacing:.08em; color:#d1d5db;">
          Conta
        </h5>
        <ul class="list-unstyled mb-0">
          <?php if (!$isLoggedIn): ?>
            <li class="mb-2"><a href="index.php?evora_p=login" class="text-decoration-none footer-link">Iniciar sessão</a></li>
            <li class="mb-2"><a href="index.php?evora_p=signup" class="text-decoration-none footer-link">Criar conta</a></li>
            <li class="mb-2"><a href="index.php?evora_p=forgot_password_public" class="text-decoration-none footer-link">Recuperar password</a></li>
          <?php else: ?>
            <li class="mb-2"><a href="index.php?evora_p=profile" class="text-decoration-none footer-link">Perfil</a></li>
            <li class="mb-2"><a href="index.php?evora_p=myocorrencias" class="text-decoration-none footer-link">Minhas ocorrências</a></li>
            <li class="mb-2"><a href="index.php?evora_p=mymensagens" class="text-decoration-none footer-link">Minhas mensagens</a></li>
            <li class="mb-2"><a href="index.php?evora_p=logout" class="text-decoration-none footer-link">Terminar sessão</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Extra -->
      <div class="col-lg-2 col-md-6">
        <h5 class="mb-3" style="font-size:0.95rem; text-transform:uppercase; letter-spacing:.08em; color:#d1d5db;">
          Mais
        </h5>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="index.php?evora_p=segurancapublic" class="text-decoration-none footer-link">Segurança pública</a></li>
          <li class="mb-2"><a href="index.php?evora_p=unsubscribe" class="text-decoration-none footer-link">Cancelar newsletter</a></li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center pt-4 pb-4"
         style="border-top:1px solid rgba(255,255,255,0.08);">

      <div class="mb-3 mb-md-0" style="color:#6b7280; font-size:0.88rem;">
        © <?php echo date('Y'); ?> Reporta Évora. Todos os direitos reservados.
      </div>

      <div class="d-flex align-items-center gap-2">
        <a href="https://github.com/Shreesoni520"
           class="footer-social"
           target="_blank"
           rel="noopener noreferrer">
          <i class="bi bi-github"></i>
        </a>

        <a href="https://www.instagram.com/krishna_soni.52"
           class="footer-social"
           target="_blank"
           rel="noopener noreferrer">
          <i class="bi bi-instagram"></i>
        </a>

        <a href="https://x.com/@Shreessoni520"
           class="footer-social"
           target="_blank"
           rel="noopener noreferrer">
          <i class="bi bi-twitter-x"></i>
        </a>

        <a href="https://www.linkedin.com/in/shree-soni-7751782b1"
           class="footer-social"
           target="_blank"
           rel="noopener noreferrer">
          <i class="bi bi-linkedin"></i>
        </a>
      </div>
    </div>

  </div>
</footer>

<style>
  .footer-link {
    color: #9ca3af;
    font-size: 0.9rem;
    transition: 0.25s ease;
  }

  .footer-link:hover {
    color: #ffffff;
    padding-left: 4px;
  }

  .footer-social {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(15,23,42,0.9);
    color: #e5e7eb;
    border: 1px solid rgba(55,65,81,0.8);
    text-decoration: none;
    transition: 0.25s ease;
  }

  .footer-social:hover {
    color: #ffffff;
    transform: translateY(-2px);
    border-color: rgba(14,165,233,0.6);
  }

  #footer input::placeholder {
    color: #9ca3af;
  }

  #footer input:focus {
    outline: none;
    box-shadow: none;
  }
</style>