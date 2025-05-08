<?php
require 'config.php';
require 'functions.php';

// Vérifier l'authentification
if (!isset($_SESSION['user'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<p>Non authentifié</p>";
    exit;
}

$user = $_SESSION['user'];
// Adaptation: utiliser 'profil' comme 'type' si 'type' n'existe pas
if (!isset($user['type']) && isset($user['profil'])) {
    $user['type'] = $user['profil'];
}

$convId = isset($_GET['conv_id']) ? (int)$_GET['conv_id'] : 0;

if (!$convId) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<p>ID de conversation invalide</p>";
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
    
    // Récupérer les informations de l'utilisateur
    $participantInfo = getParticipantInfo($convId, $user['id'], $user['type']);
    $isAdmin = $participantInfo && $participantInfo['is_admin'] == 1;
    $isModerator = $participantInfo && ($participantInfo['is_moderator'] == 1 || $isAdmin);
    
    // Récupérer les participants
    $participants = getParticipants($convId);
    
    header('Content-Type: text/html; charset=UTF-8');
    
    // Générer le HTML des participants
    foreach ($participants as $p) {
        $cssClass = $p['a_quitte'] ? 'left' : ($p['est_administrateur'] ? 'admin' : ($p['est_moderateur'] ? 'mod' : ''));
        echo '<li class="' . $cssClass . '">';
        echo '<i class="fas fa-user-' . getParticipantIcon($p['utilisateur_type']) . '"></i>';
        echo htmlspecialchars($p['nom_complet']);
        echo '<span class="participant-type">' . getParticipantType($p['utilisateur_type']) . '</span>';
        
        if ($p['a_quitte']) {
            echo '<span class="left-tag">A quitté</span>';
        } elseif ($p['est_administrateur']) {
            echo '<span class="admin-tag">Admin/Envoyeur</span>';
        } elseif ($p['est_moderateur']) {
            echo '<span class="mod-tag">Mod</span>';
        }
        
        // Boutons d'action (promotion/rétrogradation)
        if (!$p['a_quitte'] && $isAdmin && !$p['est_administrateur'] && $p['utilisateur_id'] != $user['id']) {
            if ($p['est_moderateur']) {
                echo '<button class="action-btn" onclick="demoteFromModerator(' . $p['id'] . ')" title="Rétrograder">';
                echo '<i class="fas fa-level-down-alt"></i>';
                echo '</button>';
            } else {
                echo '<button class="action-btn" onclick="promoteToModerator(' . $p['id'] . ')" title="Promouvoir en modérateur">';
                echo '<i class="fas fa-user-shield"></i>';
                echo '</button>';
            }
        }
        
        // Bouton de suppression de participant
        if (!$p['a_quitte'] && $isModerator && !$p['est_administrateur'] && $p['utilisateur_id'] != $user['id']) {
            echo '<button class="action-btn remove" onclick="removeParticipant(' . $p['id'] . ')" title="Supprimer de la conversation">';
            echo '<i class="fas fa-user-minus"></i>';
            echo '</button>';
        }
        
        echo '</li>';
    }
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<p>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}