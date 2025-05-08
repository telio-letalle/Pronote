<?php
/**
 * /api/check_notifications.php - Vérification des notifications
 */

 require_once __DIR__ . '/../config/config.php';
 require_once __DIR__ . '/../config/constants.php';
 require_once __DIR__ . '/../includes/functions.php';
 require_once __DIR__ . '/../includes/message_functions.php';
 require_once __DIR__ . '/../includes/auth.php';

// Vérifier l'authentification
$user = checkAuth();
if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    // Récupérer les notifications non lues
    $notifications = getUnreadNotifications($user['id'], $user['type']);
    $count = count($notifications);
    
    // Préparer la dernière notification (pour les notifications du navigateur)
    $latest = null;
    if ($count > 0) {
        $latest = [
            'id' => $notifications[0]['id'],
            'conversation_id' => $notifications[0]['conversation_id'],
            'expediteur_nom' => $notifications[0]['expediteur_nom'],
            'contenu' => substr(strip_tags($notifications[0]['contenu']), 0, 100)
        ];
    }
    
    // Réponse
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => $count,
        'latest' => $latest
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}