<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require './config.php';

/**
 * Formata o nome para "Primeiro Ultimo".
 */
function formatNomeExibicao(string $nomeBruto): string {
    $nomeBruto = trim($nomeBruto);
    if ($nomeBruto === '') {
        return 'Utilizador';
    }

    $partes   = preg_split('/\s+/', $nomeBruto);
    $primeiro = ucfirst(mb_strtolower($partes[0], 'UTF-8'));

    if (count($partes) > 1) {
        $ultimo = ucfirst(mb_strtolower(end($partes), 'UTF-8'));
        return $primeiro . ' ' . $ultimo;
    }

    return $primeiro;
}

// carrega/garante dados do utilizador público logado
$publicUserNome   = null;
$publicUserAvatar = null;

if (!empty($_SESSION['public_user_id'])) {
    $publicUserNome   = $_SESSION['public_user_nome']    ?? null;
    $publicUserAvatar = $_SESSION['public_user_avatar'] ?? null;

    if ($publicUserNome === null || $publicUserAvatar === null) {
        $stmt = $conn->prepare("
            SELECT nome, photo
            FROM users_public
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $_SESSION['public_user_id']);
        $stmt->execute();
        $res  = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $publicUserNome   = $row['nome'];
            $publicUserAvatar = $row['photo'];     // pode ser NULL se ainda não tiver foto

            $_SESSION['public_user_nome']   = $publicUserNome;
            $_SESSION['public_user_avatar'] = $publicUserAvatar;
        }
        $stmt->close();
    }
}
?>
<style>
  .nav-avatar-toggle { text-decoration: none; color: #f9fafb; }

  .nav-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(15,23,42,0.45);
    flex-shrink: 0;
    transition: transform 0.15s ease-out, box-shadow 0.15s ease-out;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .nav-avatar::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: inherit;
    border: 1px solid rgba(191, 219, 254, 0.5);
    pointer-events: none;
  }

  .nav-avatar-toggle:hover .nav-avatar {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(15,23,42,0.55);
  }

  .nav-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .nav-avatar-initials {
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.04em;
    color: #e5e7eb;
    position: relative;
    z-index: 1;
  }

  .nav-avatar-name {
    font-size: 0.8rem;
    color: #e5e7eb;
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.9;
  }
</style>

<div class="container-fluid container-xl position-relative d-flex align-items-center">
    <a href="index.php?evora_p=inicio" class="logo d-flex align-items-center me-auto">
      <img src="admin/assets/images/logo/logo.png" alt="Logo">
    </a>

    <nav id="navmenu" class="navmenu">
        <ul>
            <li><a href="index.php?evora_p=inicio">Início</a></li>
            <li><a href="index.php?evora_p=information">Informação</a></li>
            <li><a href="index.php?evora_p=noticias">Notícias</a></li>
            <li><a href="index.php?evora_p=mapa">Mapa</a></li>

            <li class="dropdown"><a href="#"><span>Ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                <ul>
                    <li class="dropdown"><a href="index.php?evora_p=ocorrencias"><span>Registar Ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                        <ul>
                            <li><a href="index.php?evora_p=ocorrencias">Espaço verde</a></li>
                            <li><a href="index.php?evora_p=ocorrenciasestrada">Estrada</a></li>
                        </ul>
                    </li>
                    <li class="dropdown"><a href="index.php?evora_p=listocorrencias"><span>Lista ocorrências</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                        <ul>
                            <li><a href="index.php?evora_p=listocorrencias">Espaço verde</a></li>
                            <li><a href="index.php?evora_p=listarocorrenciasestrada">Estrada</a></li>
                        </ul>
                    </li>
                </ul>
            </li>

            <li><a href="index.php?evora_p=contact">Contacto</a></li>

            <?php if (!empty($_SESSION['public_user_id'])): ?>
                <?php
                $nomeBruto     = $publicUserNome   ?? 'Utilizador';
                $nomeFormatado = formatNomeExibicao($nomeBruto);
                $avatarPath    = $publicUserAvatar ?? '';

                $iniciais   = '';
                $partesNome = preg_split('/\s+/', trim($nomeBruto));
                if (!empty($partesNome[0])) {
                    $iniciais = mb_strtoupper(mb_substr($partesNome[0], 0, 1, 'UTF-8'), 'UTF-8');
                }
                if (count($partesNome) > 1) {
                    $iniciais .= mb_strtoupper(mb_substr(end($partesNome), 0, 1, 'UTF-8'), 'UTF-8');
                }
                if ($iniciais === '') {
                    $iniciais = 'U';
                }
                ?>
                <li class="dropdown ms-2">
                    <a href="#" class="d-flex align-items-center nav-avatar-toggle">
                        <div class="nav-avatar">
                            <?php if (!empty($avatarPath)): ?>
                                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="nav-avatar-img">
                            <?php else: ?>
                                <span class="nav-avatar-initials">
                                    <?php echo htmlspecialchars($iniciais); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="nav-avatar-name ms-2 d-none d-lg-inline">
                            <?php echo htmlspecialchars($nomeFormatado); ?>
                        </span>
                        <i class="bi bi-chevron-down toggle-dropdown ms-1"></i>
                    </a>
                    <ul>
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="index.php?evora_p=profile">Perfil</a></li>
                        <li><a href="index.php?evora_p=myocorrencias">Minhas Ocorrências</a></li>
                        <li><a href="index.php?evora_p=mymensagens">Minhas Mensagens</a></li>
                        <li><a href="index.php?evora_p=segurancapublic">Segurança</a></li>
                        <li><a href="index.php?evora_p=logout">Terminar sessão</a></li>
                    </ul>
                </li>
            <?php else: ?>
                <li class="ms-2">
                    <a href="index.php?evora_p=login"
                       class="btn-getstarted btn-sm text-white px-3 rounded-pill">
                       Iniciar sessão
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
    </nav>
</div>
