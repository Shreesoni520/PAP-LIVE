<?php
session_start();
include './config.php';

// Bloquear acesso se não tiver login
if (empty($_SESSION['public_user_id'])) {
    header('Location: index.php?evora_p=login');
    exit;
}

$user_id = (int) $_SESSION['public_user_id'];

// Mensagens de contacto deste utilizador
$mensagens = [];
$stmt = $conn->prepare("
    SELECT id, name, email, subject, message, created_at
    FROM contact
    WHERE user_id = ?
    ORDER BY id DESC
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $mensagens[] = $row;
    }
    $stmt->close();
}

// Comentários em notícias deste utilizador
$comentarios = [];
$stmt2 = $conn->prepare("
    SELECT c.id, c.nome, c.texto, c.criado_em, n.titulo AS titulo_noticia
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    WHERE c.user_id = ?
    ORDER BY c.criado_em DESC
");
if ($stmt2) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $comentarios[] = $row;
    }
    $stmt2->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>As Minhas Mensagens</title>

    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Poppins:wght@500;600&display=swap" rel="stylesheet">

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        html, body { max-width: 100%; overflow-x: hidden; }
        .main, #hero, #my-messages { width: 100%; max-width: 100vw; overflow-x: hidden; }
        .profile-shell {
            border-radius: 18px;
            background: radial-gradient(circle at top left, #0d6efd15, transparent 55%),
                        radial-gradient(circle at bottom right, #f9731615, transparent 55%),
                        #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.12);
            padding: 24px;
        }
        .pill-nav .nav-link {
            border-radius: 999px;
            padding: 0.45rem 1rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        /* AQUI: azul tipo my ocorrencias */
        .pill-nav .nav-link.active {
            background: #0d6efd; /* azul bootstrap */
            color: #fff;
            box-shadow: 0 6px 18px rgba(13, 110, 253, 0.4);
        }
        .msg-item {
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            padding: 16px;
            position: relative;
        }
        .msg-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: #22c55e;
            position: absolute;
            left: -4px;
            top: 20px;
        }
        .timeline {
            border-left: 2px solid #e5e7eb;
            padding-left: 18px;
            margin-left: 6px;
        }
        .empty-state {
            border-radius: 18px;
            border: 1px dashed #cbd5f5;
            background: #f9fafb;
            padding: 24px;
            text-align: center;
        }
        .empty-icon {
            width: 46px;
            height: 46px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .msg-meta { font-size: 0.8rem; color: #6b7280; }

        @media (max-width: 767.98px) {
          body.index-page {
            padding-top: 60px; /* pequeno espaço extra para o header fixo */
            background-color: #37517e;
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
             style="height: 260px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
        <div class="container h-100 d-flex align-items-center justify-content-center">
            <div class="row w-100 gy-0 align-items-center justify-content-center">
                <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
                    <h2>As Minhas Mensagens</h2>
                    <p>Consulte o histórico de mensagens de contacto e comentários em notícias.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="my-messages" class="section">
        <div class="container" data-aos="fade-up" data-aos-delay="100">

            <div class="profile-shell">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Histórico de participação</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">
                            Veja o que já comunicou através da plataforma.
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <a href="contact.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-envelope-open me-1"></i> Nova mensagem de contacto
                        </a>
                    </div>
                </div>

                <ul class="nav nav-pills pill-nav mb-3" id="msg-pills-tab" role="tablist">
                    <li class="nav-item me-1" role="presentation">
                        <button class="nav-link active" id="pill-contact-tab" data-bs-toggle="pill"
                                data-bs-target="#pill-contact" type="button" role="tab"
                                aria-controls="pill-contact" aria-selected="true">
                            <i class="bi bi-envelope me-1"></i> Contacto
                            <span class="badge bg-light text-dark ms-1">
                                <?php echo count($mensagens); ?>
                            </span>
                        </button>
                    </li>
                    <li class="nav-item me-1" role="presentation">
                        <button class="nav-link" id="pill-comments-tab" data-bs-toggle="pill"
                                data-bs-target="#pill-comments" type="button" role="tab"
                                aria-controls="pill-comments" aria-selected="false">
                            <i class="bi bi-chat-dots me-1"></i> Comentários em notícias
                            <span class="badge bg-light text-dark ms-1">
                                <?php echo count($comentarios); ?>
                            </span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="msg-pills-tabContent">
                    <!-- Mensagens de contacto -->
                    <div class="tab-pane fade show active" id="pill-contact" role="tabpanel" aria-labelledby="pill-contact-tab">
                        <?php if (empty($mensagens)): ?>
                            <div class="empty-state mt-2">
                                <div class="empty-icon">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <h6 class="mb-1">Ainda não enviou mensagens de contacto.</h6>
                                <p class="text-muted mb-2" style="font-size: 0.9rem;">
                                    Quando enviar mensagens pelo formulário de contacto, elas aparecerão aqui.
                                </p>
                                <a href="contact.php" class="btn btn-primary btn-sm">
                                    Enviar primeira mensagem
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline mt-2">
                                <?php foreach ($mensagens as $m): ?>
                                    <div class="mb-3 position-relative">
                                        <span class="msg-dot"></span>
                                        <div class="msg-item">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-envelope-open me-1 text-primary"></i>
                                                    <?php echo htmlspecialchars($m['subject']); ?>
                                                </h6>
                                                <span class="msg-meta">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php
                                                    $data = !empty($m['created_at']) ? $m['created_at'] : '';
                                                    echo $data ? date('d/m/Y H:i', strtotime($data)) : '';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="msg-meta mb-1">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($m['name']); ?>
                                                <?php if (!empty($m['email'])): ?>
                                                    · <i class="bi bi-at me-1"></i><?php echo htmlspecialchars($m['email']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0" style="font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comentários em notícias -->
                    <div class="tab-pane fade" id="pill-comments" role="tabpanel" aria-labelledby="pill-comments-tab">
                        <?php if (empty($comentarios)): ?>
                            <div class="empty-state mt-2">
                                <div class="empty-icon" style="background:#fdf2ff;color:#db2777;">
                                    <i class="bi bi-chat-dots"></i>
                                </div>
                                <h6 class="mb-1">Ainda não comentou notícias.</h6>
                                <p class="text-muted mb-2" style="font-size: 0.9rem;">
                                    Quando comentar notícias, os registos aparecerão aqui.
                                </p>
                                <a href="noticias.php" class="btn btn-primary btn-sm">
                                    Ver notícias
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline mt-2">
                                <?php foreach ($comentarios as $c): ?>
                                    <div class="mb-3 position-relative">
                                        <span class="msg-dot" style="background:#6366f1;"></span>
                                        <div class="msg-item">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-chat-left-quote me-1 text-secondary"></i>
                                                    Comentário em:
                                                    <span class="text-primary">
                                                        <?php echo htmlspecialchars($c['titulo_noticia']); ?>
                                                    </span>
                                                </h6>
                                                <span class="msg-meta">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php
                                                    $data = !empty($c['criado_em']) ? $c['criado_em'] : '';
                                                    echo $data ? date('d/m/Y H:i', strtotime($data)) : '';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="msg-meta mb-1">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($c['nome']); ?>
                                            </div>
                                            <p class="mb-0" style="font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars($c['texto'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
<script src="assets/js/main.js"></script>

</body>
</html>
