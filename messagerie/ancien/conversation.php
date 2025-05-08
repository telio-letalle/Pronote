<?php
// conversation.php - Affichage et gestion d'une conversation
require 'config.php';
require 'functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: /~u22405372/SAE/Pronote/login/public/index.php');
    exit;
}

$user = $_SESSION['user'];
// Adaptation: utiliser 'profil' comme 'type' si 'type' n'existe pas
if (!isset($user['type']) && isset($user['profil'])) {
    $user['type'] = $user['profil'];
}

// Vérifier que le type est défini
if (!isset($user['type'])) {
    die("Erreur: Type d'utilisateur non défini dans la session");
}

$convId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$convId) {
    header('Location: index.php');
    exit;
}

try {
    // Vérifier l'accès à la conversation
    $conversation = getConversationInfo($convId);
    $messages = getMessages($convId, $user['id'], $user['type']);
    $participants = getParticipants($convId);
    
    // Vérifier si la conversation est dans la corbeille
    $participantInfo = getParticipantInfo($convId, $user['id'], $user['type']);
    $isDeleted = $participantInfo && $participantInfo['is_deleted'] == 1;
    $isAdmin = $participantInfo && $participantInfo['is_admin'] == 1;
    $isModerator = $participantInfo && ($participantInfo['is_moderator'] == 1 || $isAdmin);
    
    // Traitement de l'envoi d'un nouveau message (uniquement si la conversation n'est pas dans la corbeille)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !$isDeleted) {
        switch ($_POST['action']) {
            case 'send_message':
                if (!empty($_POST['contenu'])) {
                    // Vérifier si l'utilisateur peut répondre à une annonce
                    if (!canReplyToAnnouncement($user['id'], $user['type'], $convId, $conversation['type'])) {
                        throw new Exception("Vous n'êtes pas autorisé à répondre à cette annonce");
                    }
                    
                    $filesData = isset($_FILES['attachments']) ? $_FILES['attachments'] : [];
                    $importance = 'normal'; // Importance par défaut
                    
                    // Seuls certains utilisateurs peuvent définir une importance
                    if (canSetMessageImportance($user['type']) && isset($_POST['importance'])) {
                        $importance = $_POST['importance'];
                    }
                    
                    $parentMessageId = isset($_POST['parent_message_id']) && !empty($_POST['parent_message_id']) ? 
                                      (int)$_POST['parent_message_id'] : null;
                    
                    addMessage(
                        $convId, 
                        $user['id'], 
                        $user['type'], 
                        $_POST['contenu'],
                        $importance,
                        false, // Est information
                        false, // Notification obligatoire
                        false, // Accusé de réception automatique (supprimé)
                        $parentMessageId, // Message parent
                        'standard',
                        $filesData
                    );
                    
                    // Redirection pour éviter les soumissions multiples
                    header("Location: conversation.php?id=$convId");
                    exit;
                }
                break;
                
            case 'add_participant':
                if (isset($_POST['participant_id']) && isset($_POST['participant_type'])) {
                    // Vérifier que l'utilisateur est admin ou modérateur
                    if (!$isModerator) {
                        throw new Exception("Vous n'êtes pas autorisé à ajouter des participants");
                    }
                    
                    addParticipantToConversation(
                        $convId, 
                        $_POST['participant_id'], 
                        $_POST['participant_type'], 
                        $user['id'], 
                        $user['type']
                    );
                    header("Location: conversation.php?id=$convId");
                    exit;
                }
                break;
                
            case 'promote_moderator':
                if (isset($_POST['participant_id'])) {
                    // Vérifier que l'utilisateur est admin
                    if (!$isAdmin) {
                        throw new Exception("Vous n'êtes pas autorisé à promouvoir des modérateurs");
                    }
                    
                    promoteToModerator(
                        $_POST['participant_id'], 
                        $user['id'], 
                        $user['type'], 
                        $convId
                    );
                    header("Location: conversation.php?id=$convId");
                    exit;
                }
                break;
                
            case 'demote_moderator':
                if (isset($_POST['participant_id'])) {
                    // Vérifier que l'utilisateur est admin
                    if (!$isAdmin) {
                        throw new Exception("Vous n'êtes pas autorisé à rétrograder des modérateurs");
                    }
                    
                    demoteFromModerator(
                        $_POST['participant_id'], 
                        $user['id'], 
                        $user['type'], 
                        $convId
                    );
                    header("Location: conversation.php?id=$convId");
                    exit;
                }
                break;
                
            case 'remove_participant':
                if (isset($_POST['participant_id'])) {
                    // Vérifier que l'utilisateur est admin ou modérateur
                    if (!$isModerator) {
                        throw new Exception("Vous n'êtes pas autorisé à supprimer des participants");
                    }
                    
                    removeParticipant(
                        $_POST['participant_id'], 
                        $user['id'], 
                        $user['type'], 
                        $convId
                    );
                    header("Location: conversation.php?id=$convId");
                    exit;
                }
                break;
                
            case 'archive_conversation':
                archiveConversation($convId, $user['id'], $user['type']);
                header("Location: index.php?folder=archives");
                exit;
                break;
                
            case 'delete_conversation':
                deleteConversation($convId, $user['id'], $user['type']);
                header("Location: index.php?folder=corbeille");
                exit;
                break;
                
            case 'restore_conversation':
                restoreConversation($convId, $user['id'], $user['type']);
                header("Location: conversation.php?id=$convId");
                exit;
                break;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Conversation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS avec corrections pour la largeur des messages et l'affichage des annonces */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', 'Helvetica', sans-serif;
        }

        body {
            background-color: #f0f3f8;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
        }

        /* --- CONTENEUR PRINCIPAL --- */
        .container {
            max-width: 1200px;
            min-height: 100vh;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        /* --- HEADER --- */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #009b72; /* Couleur principale Pronote */
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        header h1 {
            font-size: 18px;
            font-weight: 600;
        }

        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* --- CONTENU DE LA CONVERSATION --- */
        .content {
            flex-grow: 1;
            display: flex;
        }

        .conversation-page {
            display: flex;
            flex-grow: 1;
        }

        /* --- SIDEBAR --- */
        .conversation-sidebar {
            width: 250px;
            background-color: #f8f9fa;
            border-right: 1px solid #e9ecef;
            padding: 20px;
            overflow-y: auto;
        }

        .conversation-info h3 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            color: #495057;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }

        #add-participant-btn {
            font-size: 16px;
            color: #009b72;
            background: none;
            border: none;
            cursor: pointer;
        }

        .participants-list {
            list-style: none;
        }

        .participants-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f1f1;
            font-size: 13px;
        }

        .participant-type {
            color: #6c757d;
            font-size: 11px;
            margin-left: auto;
            margin-right: 5px;
        }

        .admin-tag, .mod-tag, .left-tag {
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .admin-tag {
            background-color: #e1f8f2;
            color: #009b72;
        }

        .mod-tag {
            background-color: #fff3cd;
            color: #856404;
        }

        .left-tag {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .participants-list li i {
            margin-right: 8px;
            color: #6c757d;
        }

        .participants-list li.admin {
            font-weight: 500;
        }

        .participants-list li.left {
            opacity: 0.5;
        }

        .action-btn {
            margin-left: 5px;
            color: #6c757d;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            padding: 2px;
        }

        .action-btn:hover {
            color: #009b72;
        }

        .action-btn.remove:hover {
            color: #dc3545;
        }

        /* Actions sur la conversation */
        .conversation-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .conversation-actions .action-button {
            display: flex;
            align-items: center;
            padding: 8px 0;
            color: #495057;
            text-decoration: none;
            cursor: pointer;
        }

        .conversation-actions .action-button:hover {
            color: #009b72;
        }

        .conversation-actions .action-button i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* --- ZONE PRINCIPALE DES MESSAGES --- */
        .conversation-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        .messages-container {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* --- MESSAGES --- */
        .message {
            display: flex;
            flex-direction: column;
            max-width: 80%;
            padding: 12px 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            width: fit-content; /* Adapté à la longueur du message */
            min-width: 200px; /* Taille minimale */
        }

        .message.self {
            align-self: flex-end;
            background-color: #e1f8f2;
            border-color: #d1e7e2;
        }
        
        .message.annonce {
            background-color: #fff8e1;
            border-color: #ffe082;
            border-left: 4px solid #ffc107;
            max-width: 90%; /* Plus large pour les annonces */
        }

        /* Nouvelles bordures pour les niveaux d'importance - seulement en bas */
        .message.normal {
            border-bottom: 4px solid #28a745; /* Vert - seulement en bas */
        }

        .message.important {
            border-bottom: 4px solid #ffc107; /* Orange - seulement en bas */
        }

        .message.urgent {
            border-bottom: 4px solid #dc3545; /* Rouge - seulement en bas */
        }

        /* Bordures grises pour les messages lus (sauf urgent) */
        .message.normal.read {
            border-bottom: 4px solid #adb5bd; /* Gris - seulement en bas */
        }

        .message.important.read {
            border-bottom: 4px solid #adb5bd; /* Gris - seulement en bas */
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .sender {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Alignement à gauche */
        }

        .sender strong {
            font-weight: 600;
            color: #212529;
        }

        .sender-type {
            font-size: 11px;
            color: #6c757d;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #6c757d;
            margin-left: auto; /* Force l'alignement à droite */
        }

        .importance-tag {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .importance-tag.important {
            background-color: #fff3cd;
            color: #856404;
        }

        .importance-tag.urgent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .message-content {
            margin-bottom: 10px;
            word-break: break-word;
        }

        .message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-top: 5px;
        }

        .message-status {
            display: flex;
            align-items: center;
        }

        .message-read {
            font-size: 12px;
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* --- PIÈCES JOINTES --- */
        .attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .attachment {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background-color: #f1f1f1;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .attachment:hover {
            background-color: #e9ecef;
            text-decoration: underline;
        }

        /* --- ACTIONS SUR MESSAGES --- */
        .btn-icon {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }

        .btn-icon:hover {
            color: #212529;
        }

        .message-actions {
            position: relative;
        }

        .message-actions-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 8px 0;
            min-width: 150px;
            display: none;
            z-index: 100;
        }

        .message-actions-menu.active {
            display: block;
        }

        .message-actions-menu a {
            display: block;
            padding: 8px 15px;
            color: #495057;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .message-actions-menu a:hover {
            background-color: #f8f9fa;
        }

        /* --- ZONE DE RÉPONSE --- */
        .reply-box {
            padding: 15px;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
        }
        
        .conversation-deleted {
            padding: 15px;
            text-align: center;
            background-color: #f8f9fa;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
        
        .conversation-deleted .btn {
            margin-top: 10px;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background-color: #f0f3f8;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .reply-to {
            font-weight: 500;
            color: #495057;
        }

        .reply-options {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .importance-select {
            padding: 6px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
        }

        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 10px;
        }

        textarea:focus {
            border-color: #009b72;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 155, 114, 0.1);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* --- BOUTONS --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            outline: none;
            font-size: 14px;
        }

        .btn.primary {
            background-color: #009b72;
            color: white;
        }

        .btn.primary:hover {
            background-color: #008a65;
        }

        .btn.secondary {
            background-color: #e9ecef;
            color: #495057;
        }

        .btn.secondary:hover {
            background-color: #dee2e6;
        }
        
        .btn.warning {
            background-color: #ffc107;
            color: #212529;
        }

        /* --- PIÈCES JOINTES --- */
        .file-upload {
            position: relative;
        }

        .file-upload input[type="file"] {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .file-upload label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background-color: #e9ecef;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 13px;
        }

        .file-upload label:hover {
            background-color: #dee2e6;
        }

        #file-list {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .file-info {
            background-color: #e9ecef;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* --- MODAL --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #555;
        }

        .modal h3 {
            margin-bottom: 20px;
            color: #212529;
            font-size: 18px;
        }

        .modal .form-group {
            margin-bottom: 20px;
        }

        .modal .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .modal .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        /* --- ALERTES --- */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .container {
                max-width: none;
                width: 100%;
            }
            
            .conversation-page {
                flex-direction: column;
            }
            
            .conversation-sidebar {
                width: 100%;
                max-height: 200px;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }
            
            .message {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour</a>
            <h1>Conversation</h1>
        </header>

        <div class="content conversation-page">
            <?php if (isset($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
            
            <aside class="conversation-sidebar">
                <div class="conversation-info">
                    <h3>
                        Participants 
                        <?php if (!$isDeleted && $isModerator): ?>
                        <button id="add-participant-btn" class="btn-icon"><i class="fas fa-plus-circle"></i></button>
                        <?php endif; ?>
                    </h3>
                    <ul class="participants-list">
                        <?php foreach ($participants as $p): ?>
                        <li class="<?= $p['a_quitte'] ? 'left' : ($p['est_administrateur'] ? 'admin' : ($p['est_moderateur'] ? 'mod' : '')) ?>">
                            <i class="fas fa-user-<?= getParticipantIcon($p['utilisateur_type']) ?>"></i>
                            <?= htmlspecialchars($p['nom_complet']) ?>
                            <span class="participant-type"><?= getParticipantType($p['utilisateur_type']) ?></span>
                            <?php if ($p['a_quitte']): ?>
                            <span class="left-tag">A quitté</span>
                            <?php elseif ($p['est_administrateur']): ?>
                            <span class="admin-tag">Admin/Envoyeur</span>
                            <?php elseif ($p['est_moderateur']): ?>
                            <span class="mod-tag">Mod</span>
                            <?php endif; ?>
                            
                            <?php if (!$isDeleted && $isAdmin && !$p['est_administrateur'] && !$p['a_quitte'] && $p['utilisateur_id'] != $user['id']): ?>
                                <?php if ($p['est_moderateur']): ?>
                                <button class="action-btn" onclick="demoteFromModerator(<?= $p['id'] ?>)" title="Rétrograder">
                                    <i class="fas fa-level-down-alt"></i>
                                </button>
                                <?php else: ?>
                                <button class="action-btn" onclick="promoteToModerator(<?= $p['id'] ?>)" title="Promouvoir en modérateur">
                                    <i class="fas fa-user-shield"></i>
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!$isDeleted && $isModerator && !$p['est_administrateur'] && !$p['a_quitte'] && $p['utilisateur_id'] != $user['id']): ?>
                            <button class="action-btn remove" onclick="removeParticipant(<?= $p['id'] ?>)" title="Supprimer de la conversation">
                                <i class="fas fa-user-minus"></i>
                            </button>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="conversation-actions">
                    <?php if ($isDeleted): ?>
                    <a href="#" class="action-button" onclick="restoreConversation()">
                        <i class="fas fa-trash-restore"></i> Restaurer la conversation
                    </a>
                    <?php else: ?>
                    <a href="#" class="action-button" onclick="archiveConversation()">
                        <i class="fas fa-archive"></i> Archiver la conversation
                    </a>
                    <a href="#" class="action-button" onclick="deleteConversation()">
                        <i class="fas fa-trash"></i> Supprimer la conversation
                    </a>
                    <?php endif; ?>
                </div>
            </aside>
            
            <main class="conversation-main">
                <div class="messages-container">
                    <?php foreach ($messages as $m): ?>
                    <div class="message <?= isCurrentUser($m['expediteur_id'], $m['expediteur_type'], $user) ? 'self' : '' ?> <?= $m['importance'] ?> <?= $m['est_lu'] ? 'read' : '' ?> <?= $conversation['type'] === 'annonce' ? 'annonce' : '' ?>">
                        <div class="message-header">
                            <div class="sender">
                                <strong><?= htmlspecialchars($m['expediteur_nom']) ?></strong>
                                <span class="sender-type"><?= getParticipantType($m['expediteur_type']) ?></span>
                            </div>
                            <div class="message-meta">
                                <span class="date"><?= formatDate($m['date_envoi']) ?></span>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?= nl2br(htmlspecialchars($m['contenu'])) ?>
                            
                            <?php if (!empty($m['pieces_jointes'])): ?>
                            <div class="attachments">
                                <?php foreach ($m['pieces_jointes'] as $attachment): ?>
                                <a href="<?= htmlspecialchars($attachment['chemin']) ?>" class="attachment" target="_blank">
                                    <i class="fas fa-paperclip"></i> <?= htmlspecialchars($attachment['nom_fichier']) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="message-footer">
                            <div class="message-status">
                                <?php if ($m['est_lu']): ?>
                                <div class="message-read">
                                    <i class="fas fa-check"></i> Vu
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$isDeleted && canReplyToAnnouncement($user['id'], $user['type'], $convId, $conversation['type'])): ?>
                            <div class="message-actions">
                                <button class="btn-icon" onclick="replyToMessage(<?= $m['id'] ?>, '<?= htmlspecialchars($m['expediteur_nom']) ?>')">
                                    <i class="fas fa-reply"></i> Répondre
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($isDeleted): ?>
                <div class="conversation-deleted">
                    <p>Cette conversation a été déplacée dans la corbeille. Vous ne pouvez plus y répondre.</p>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="restore_conversation">
                        <button type="submit" class="btn primary">Restaurer la conversation</button>
                    </form>
                </div>
                <?php elseif (canReplyToAnnouncement($user['id'], $user['type'], $convId, $conversation['type'])): ?>
                <div class="reply-box">
                    <form method="post" enctype="multipart/form-data" id="messageForm">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="parent_message_id" id="parent-message-id" value="">
                        
                        <!-- Interface de réponse (cachée par défaut) -->
                        <div id="reply-interface" style="display: none;">
                            <div class="reply-header">
                                <span id="reply-to" class="reply-to"></span>
                                <button type="button" class="btn-icon" onclick="cancelReply()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if (canSetMessageImportance($user['type'])): ?>
                        <div class="reply-options">
                            <select name="importance" class="importance-select">
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <textarea name="contenu" rows="4" placeholder="Envoyer un message..." required></textarea>
                        
                        <div class="form-footer">
                            <div class="file-upload">
                                <input type="file" name="attachments[]" id="attachments" multiple>
                                <label for="attachments">
                                    <i class="fas fa-paperclip"></i> Pièces jointes
                                </label>
                                <div id="file-list"></div>
                            </div>
                            
                            <button type="submit" class="btn primary">
                                <i class="fas fa-paper-plane"></i> Envoyer
                            </button>
                        </div>
                    </form>
                </div>
                <?php elseif (!$isDeleted && $conversation['type'] === 'annonce'): ?>
                <div class="conversation-deleted">
                    <p>Cette annonce est en lecture seule. Vous ne pouvez pas y répondre.</p>
                </div>
                <?php endif; ?>
            </main>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour ajouter des participants -->
    <div class="modal" id="addParticipantModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddParticipantModal()">&times;</span>
            <h3>Ajouter des participants</h3>
            <form method="post" id="addParticipantForm">
                <input type="hidden" name="action" value="add_participant">
                
                <div class="form-group">
                    <label for="participant_type">Type de participant</label>
                    <select name="participant_type" id="participant_type" onchange="loadParticipants()" required>
                        <option value="">Sélectionner un type</option>
                        <option value="eleve">Élève</option>
                        <option value="parent">Parent</option>
                        <option value="professeur">Professeur</option>
                        <option value="vie_scolaire">Vie scolaire</option>
                        <option value="administrateur">Administrateur</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="participant_id">Participant</label>
                    <select name="participant_id" id="participant_id" required>
                        <option value="">Choisissez d'abord un type</option>
                    </select>
                </div>
                
                <button type="submit" class="btn primary">Ajouter</button>
            </form>
        </div>
    </div>
    
    <!-- Formulaires cachés pour les actions -->
    <form id="archiveForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="archive_conversation">
    </form>
    
    <form id="deleteForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="delete_conversation">
    </form>
    
    <form id="restoreForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="restore_conversation">
    </form>
    
    <form id="promoteForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="promote_moderator">
        <input type="hidden" name="participant_id" id="promote_participant_id" value="">
    </form>
    
    <form id="demoteForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="demote_moderator">
        <input type="hidden" name="participant_id" id="demote_participant_id" value="">
    </form>
    
    <form id="removeForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="remove_participant">
        <input type="hidden" name="participant_id" id="remove_participant_id" value="">
    </form>

    <script>
        // Script pour la gestion des pièces jointes
        document.getElementById('attachments')?.addEventListener('change', function(e) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'file-info';
                    fileInfo.innerHTML = `
                        <i class="fas fa-file"></i>
                        ${file.name} (${formatFileSize(file.size)})
                    `;
                    fileList.appendChild(fileInfo);
                }
            }
        });
        
        // Fonctions pour les actions sur les messages
        function replyToMessage(messageId, senderName) {
            // Montrer l'interface de réponse
            const replyInterface = document.getElementById('reply-interface');
            const replyTo = document.getElementById('reply-to');
            const textarea = document.querySelector('textarea[name="contenu"]');
            
            replyInterface.style.display = 'block';
            replyTo.textContent = 'Répondre à ' + senderName;
            
            // Stocker l'ID du message parent
            document.getElementById('parent-message-id').value = messageId;
            
            // Faire défiler vers le bas et mettre le focus sur le textarea
            textarea.focus();
            window.scrollTo(0, document.body.scrollHeight);
        }

        function cancelReply() {
            const replyInterface = document.getElementById('reply-interface');
            replyInterface.style.display = 'none';
            document.getElementById('parent-message-id').value = '';
        }
        
        // Actions sur la conversation
        function archiveConversation() {
            if (confirm('Êtes-vous sûr de vouloir archiver cette conversation ?')) {
                document.getElementById('archiveForm').submit();
            }
        }
        
        function deleteConversation() {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                document.getElementById('deleteForm').submit();
            }
        }
        
        function restoreConversation() {
            document.getElementById('restoreForm').submit();
        }
        
        // Gestion des participants
        function promoteToModerator(participantId) {
            if (confirm('Êtes-vous sûr de vouloir promouvoir ce participant en modérateur ?')) {
                document.getElementById('promote_participant_id').value = participantId;
                document.getElementById('promoteForm').submit();
            }
        }
        
        function demoteFromModerator(participantId) {
            if (confirm('Êtes-vous sûr de vouloir rétrograder ce modérateur ?')) {
                document.getElementById('demote_participant_id').value = participantId;
                document.getElementById('demoteForm').submit();
            }
        }
        
        function removeParticipant(participantId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce participant de la conversation ? Il n\'aura plus accès à cette conversation.')) {
                document.getElementById('remove_participant_id').value = participantId;
                document.getElementById('removeForm').submit();
            }
        }
        
        // Ajout de participants
        function showAddParticipantModal() {
            document.getElementById('addParticipantModal').style.display = 'block';
        }

        function closeAddParticipantModal() {
            document.getElementById('addParticipantModal').style.display = 'none';
        }

        function loadParticipants() {
            const type = document.getElementById('participant_type').value;
            const select = document.getElementById('participant_id');
            
            // Vider la liste actuelle
            select.innerHTML = '<option value="">Chargement...</option>';
            
            // Faire une requête AJAX pour récupérer les participants
            fetch('get_participants.php?type=' + type + '&conv_id=<?= $convId ?>')
                .then(response => response.json())
                .then(data => {
                    select.innerHTML = '';
                    
                    if (data.length === 0) {
                        select.innerHTML = '<option value="">Aucun participant disponible</option>';
                        return;
                    }
                    
                    select.innerHTML = '<option value="">Sélectionner un participant</option>';
                    
                    data.forEach(participant => {
                        const option = document.createElement('option');
                        option.value = participant.id;
                        option.textContent = participant.nom_complet;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    select.innerHTML = '<option value="">Erreur lors du chargement</option>';
                    console.error('Erreur:', error);
                });
        }
        
        // Formatage de la taille des fichiers
        function formatFileSize(size) {
            if (size < 1024) return size + ' B';
            else if (size < 1048576) return Math.round(size / 1024) + ' KB';
            else return Math.round(size / 1048576 * 10) / 10 + ' MB';
        }

        // Attacher le clic sur le bouton d'ajout de participant
        const addParticipantBtn = document.getElementById('add-participant-btn');
        if (addParticipantBtn) {
            addParticipantBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showAddParticipantModal();
            });
        }

        // Auto-refresh de la page toutes les 30 secondes pour les nouveaux messages
        setInterval(function() {
            // Vérifier si un formulaire est actif ou si une entrée est en cours de rédaction
            const textarea = document.querySelector('textarea[name="contenu"]');
            if (!textarea || !textarea.value.trim()) {
                location.reload();
            }
        }, 30000);

        // Empêcher la soumission multiple du formulaire
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                // Désactiver le bouton d'envoi après soumission
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
            });
        }
// Actualisation en temps réel pour les modifications de conversation
function setupRealTimeUpdates() {
    // Vérifier les mises à jour toutes les 5 secondes
    const updateInterval = setInterval(checkForUpdates, 5000);
    
    function checkForUpdates() {
        // Récupérer l'horodatage du dernier message affiché
        const lastMessageTimestamp = document.querySelector('.message:last-child')?.getAttribute('data-timestamp') || 0;
        
        fetch(`get_conversation_updates.php?conv_id=<?= $convId ?>&last_timestamp=${lastMessageTimestamp}`)
            .then(response => response.json())
            .then(data => {
                if (data.hasUpdates) {
                    // Si des mises à jour sont disponibles, actualiser la page
                    location.reload();
                }
                
                // Si un utilisateur a été promu/rétrogradé, actualiser la liste des participants
                if (data.participantsChanged) {
                    refreshParticipantsList();
                }
            })
            .catch(error => console.error('Erreur lors de la vérification des mises à jour:', error));
    }
    
    function refreshParticipantsList() {
        fetch(`get_participants_list.php?conv_id=<?= $convId ?>`)
            .then(response => response.text())
            .then(html => {
                document.querySelector('.participants-list').innerHTML = html;
            })
            .catch(error => console.error('Erreur lors de l\'actualisation des participants:', error));
    }
    
    // Arrêter les mises à jour lorsque l'utilisateur quitte la page
    window.addEventListener('beforeunload', () => {
        clearInterval(updateInterval);
    });
}

// Démarrer les mises à jour en temps réel
setupRealTimeUpdates();
    </script>
</body>
</html>