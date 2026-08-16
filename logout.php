<?php
session_start();

$publicSessionKeys = [
    'public_user_id',
    'public_user_nome',
    'public_user_avatar',
    'public_2fa_pending',
    'pending_signup_public_email',
    'pending_email_change_user',
    'pending_email_change_nome',
    'pending_email_change_phone',
    'pending_email_change_birth',
    'pending_email_change_gender',
    'pending_email_change_photo',
    'ocorrencia_msg',
];

foreach ($publicSessionKeys as $key) {
    unset($_SESSION[$key]);
}

header('Location: index.php?evora_p=inicio');
exit;
