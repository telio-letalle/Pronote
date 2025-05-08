<?php
require 'config.php';
require 'functions.php';

// Vérifier l'authentification
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user = $_SESSION['user'];
// Adaptation: utiliser 'profil' comme 'type' si 'type' n'existe pas
if (!isset($user['type']) && isset($user['profil'])) {
    $user['type'] = $user['profil'];
}

$convId = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;
$lastTimestamp = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : 0;

if (!$convId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de conversation invalide']);
    exit;
}

try {
    // Vérifier que l'utilisateur est participant à la conversation
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkParticipant->execute([$convId, $user['id'], $user['type']]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
    }
    
    // Vérifier s'il y a de nouveaux messages
    $newMessagesStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM messages
        WHERE conversation_id = ? AND UNIX_TIMESTAMP(created_at) > ?
    ");
    $newMessagesStmt->execute([$convId, $lastTimestamp]);
    $newMessagesCount = $newMessagesStmt->fetchColumn();
    
    // Vérifier si les participants ont changé (promus, rétrogradés, etc.)
    $participantsChangedStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM conversation_participants
        WHERE conversation_id = ? AND UNIX_TIMESTAMP(joined_at) > ?
    ");
    $participantsChangedStmt->execute([$convId, $lastTimestamp]);
    $participantsChangedCount = $participantsChangedStmt->fetchColumn();
    
    // Réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'hasUpdates' => $newMessagesCount > 0,
        'participantsChanged' => $participantsChangedCount > 0,
        'updateCount' => $newMessagesCount
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}