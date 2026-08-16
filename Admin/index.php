<?php
session_start();
include './config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    include "login.php";
    exit();
}

/* refresh is_admin from DB */
$stmtUser = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmtUser->bind_param("i", $_SESSION['user_id']);
$stmtUser->execute();
$userDb = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$_SESSION['is_admin'] = (!empty($userDb['is_admin']) && (int)$userDb['is_admin'] === 1) ? 1 : 0;
$isAdmin = (int)$_SESSION['is_admin'] === 1;

$pagina = $_GET['evora'] ?? 'inicio';

$adminOnly = [
    'addtree',
    'listtree',
    'editartree',
    'removetree',

    'addestado',
    'listestado',
    'editarestado',
    'removeestado',

    'contact',

    'addnoticias',
    'listarnoticias',
    'listarcommentnoticias',
    'editarnoticias',
    'removernoticias',
    'removercommentnoticias',

    'addutilizador',
    'listarutilizador',
    'editarutilizador',
    'removerutilizador',
    'removerutilizadorpublic',
];

if (in_array($pagina, $adminOnly, true) && !$isAdmin) {
    header("Location: index.php?evora=inicio");
    exit();
}

switch ($pagina) {
    case 'inicio':
        include "inicio.php";
    break;

    case 'addtree':
        include "add_arvore.php";
    break;
    case 'listtree':
        include "listar_arvore.php";
    break;
    case 'editartree':
        include "editar_arvore.php";
    break;
    case 'removetree':
        include "remove_arvore.php";
    break;

    case 'editarintervencaoarvore':
        include "editar_intervencao_arvore.php";
    break;
    case 'editarintervencaoocorrencias':
        include "editar_intervencao_ocorrencias.php";
    break;
    case 'editarintervencaoocorrenciasestrada':
        include "editar_intervencao_ocorrencias_estrada.php";
    break;
    case 'notificacoes':
        include "notificacoes.php";
    break;

    case 'addestado':
        include "add_estado.php";
    break;
    case 'listestado':
        include "listar_estado.php";
    break;
    case 'editarestado':
        include "editar_estado.php";
    break;
    case 'removeestado':
        include "remove_estado.php";
    break;

    case 'mapa2d':
        include "mapa_2d_unificado.php";
    break;
    case 'mapa3d':
        include "map3d.php";
    break;

    case 'addocorrencias':
        include "add_ocorrencias.php";
    break;
    case 'listocorrencias':
        include "listar_ocorrencias.php";
    break;
    case 'removeocorrencias':
        include "remove_ocorrencias.php";
    break;

    case 'addocorrencias_estrada':
        include "add_ocorrencias_estrada.php";
    break;
    case 'listocorrencias_estrada':
        include "listar_ocorrencias_estrada.php";
    break;
    case 'removeocorrencias_estrada':
        include "remove_ocorrencias_estrada.php";
    break;

    case 'contact':
        include "contact.php";
    break;

    case 'addnoticias':
        include "add_noticias.php";
    break;
    case 'listarnoticias':
        include "listar_noticias.php";
    break;
    case 'listarcommentnoticias':
        include "listar_comment_noticias.php";
    break;
    case 'editarnoticias':
        include "editar_noticias.php";
    break;
    case 'removernoticias':
        include "remove_noticias.php";
    break;
    case 'removercommentnoticias':
        include "remove_comment_noticias.php";
    break;

    case 'sendnewsletter':
        include "send_newsletter.php";
    break;

    case 'addutilizador':
        include "add_utilizador.php";
    break;
    case 'listarutilizador':
        include "listar_utilizador.php";
    break;
    case 'editarutilizador':
        include "editar_utilizador.php";
    break;
    case 'removerutilizador':
        include "remove_utilizador.php";
    break;
    case 'removerutilizadorpublic':
        include "remove_utilizador_public.php";
    break;

    case 'profile':
        include "profile.php";
    break;
    case 'security':
        include "security.php";
    break;
    case 'logout':
        include "logout.php";
    break;

    case 'notificacoes':
        include "notificacoes.php";
    break;

    default:
        include "inicio.php";
    break;
}
?>
