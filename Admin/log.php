<?php
function regista_log($conn, $user_id, $acao, $entidade, $entidade_id, $detalhes = "") {
    $stmt = $conn->prepare("INSERT INTO log (user_id, acao, entidade, entidade_id, detalhes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $user_id, $acao, $entidade, $entidade_id, $detalhes);
    $stmt->execute();
    $stmt->close();
}
?>
