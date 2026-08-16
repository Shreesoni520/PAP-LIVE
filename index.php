<?php

if (isset($_GET['evora_p'])) {

    switch ($_GET['evora_p']) {

        // Início
        case 'inicio' :
            include "inicio.php";
        break;

        // Login
        case 'login' :
            include "login.php";
        break;

        case 'signup' :
            include "signup.php";
        break;

        case 'segurancapublic' :
            include "seguranca_public.php";
        break;

        case 'profile' :
            include "profile.php";
        break;

        case 'myocorrencias' :
            include "myocorrencias.php";
        break;
        
        case 'mymensagens' :
            include "mymensagens.php";
        break;

        case 'logout' :
            include "logout.php";
        break;

        // Newsletter handlers
        case 'newsletter' :
            include "forms/newsletter.php";
        break;

        case 'confirmnewsletter' :
            include "forms/confirm_newsletter.php";
        break;

        case 'unsubscribe' :
            include "unsubscribe.php";
        break;

        // NEW: resubscribe route (renew via email button)
        case 'resubscribe' :
            include "resubscribe.php";
        break;

        // Information
        case 'information' :
            include "information.php";
        break;

        // Notícias
        case 'noticias' :
            include "noticias.php";
        break;

        // Mapas
        case 'mapa' :
            include "map2d.php";
        break;

        // Ocorrências
        case 'ocorrencias' :
            include "ocorrencias.php";
        break;
        case 'ocorrenciasestrada' :
            include "ocorrencias_estrada.php";
        break;
        case 'listocorrencias' :
            include "listar_ocorrencias.php";
        break;
        case 'listarocorrenciasestrada' :
            include "listar_ocorrencias_estrada.php";
        break;
        
        // Contact
        case 'contact' :
            include "contact.php";
        break;

        // Forgot password
        case 'forgot_password_public':
            include 'forgot_password_public.php';
        break;

        case 'reset_password_public':
            include 'reset_password_public.php';
        break;

        // 2FA verification
        case 'verify_2fa_public':
            include 'verify_2fa_public.php'; // (ficheiro que já tens ou fazes depois)
        break;
        
        case 'cron_newsletter':
            include 'cron_newsletter.php'; // (ficheiro que já tens ou fazes depois)
        break;

        // Caso venha um evora_p desconhecido
        default :
            include "inicio.php";
        break;


    }

} else {
    include "inicio.php";
}
?>
