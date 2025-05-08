<?php
// index.php - Interface principale de messagerie
require 'config.php';
require 'functions.php';

// Vérifier l'authentification
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

// Traitement des actions rapides si demandé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['conv_id'])) {
    $convId = (int)$_POST['conv_id'];
    
    switch ($_POST['action']) {
        case 'archive':
            archiveConversation($convId, $user['id'], $user['type']);
            header('Location: index.php?folder=archives');
            exit;
            break;
            
        case 'delete':
            deleteConversation($convId, $user['id'], $user['type']);
            header('Location: index.php?folder=corbeille');
            exit;
            break;
            
        case 'restore':
            restoreConversation($convId, $user['id'], $user['type']);
            header('Location: index.php?folder=reception');
            exit;
            break;
            
        case 'delete_permanently':
            deletePermanently($convId, $user['id'], $user['type']);
            header('Location: index.php?folder=corbeille');
            exit;
            break;
    }
}

$currentFolder = isset($_GET['folder']) ? $_GET['folder'] : 'reception';
$convs = getConversations($user['id'], $user['type'], $currentFolder);
$unreadNotifications = getUnreadNotifications($user['id'], $user['type']);

// Liste des dossiers pour le menu
$folders = [
    'reception' => 'Boîte de réception',
    'envoyes' => 'Messages envoyés',
    'archives' => 'Archives',
    'information' => 'Informations',
    'corbeille' => 'Corbeille'
];

// Fonctionnalités disponibles selon le profil
$canSendAnnouncement = in_array($user['type'], ['vie_scolaire', 'administrateur']);
$isStudent = ($user['type'] === 'eleve');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Messagerie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Le CSS reste inchangé, mais avec ajout du style pour le menu d'action rapide */
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .notification-badge {
            background-color: #ff4757;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 11px;
            font-weight: bold;
        }

        /* --- CONTENU PRINCIPAL --- */
        .content {
            display: flex;
            flex-grow: 1;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
            padding: 20px 0;
            background-color: #f8f9fa;
            border-right: 1px solid #e9ecef;
        }

        .folder-menu {
            margin-bottom: 20px;
        }

        .folder-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: #495057;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .folder-menu a:hover {
            background-color: #e9ecef;
        }

        .folder-menu a.active {
            background-color: #e1f8f2;
            color: #009b72;
            border-left: 3px solid #009b72;
        }

        .action-buttons {
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* --- BOUTONS --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
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

        .btn.warning:hover {
            background-color: #e0a800;
        }

        .btn.cancel {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        /* --- ZONE PRINCIPALE --- */
        main {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }

        main h2 {
            font-size: 16px;
            margin-bottom: 20px;
            color: #212529;
            font-weight: 600;
        }

        /* --- LISTE DES CONVERSATIONS --- */
        .conversation-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            text-decoration: none;
            color: #495057;
            border-radius: 4px;
            transition: background-color 0.2s;
            border: 1px solid #e9ecef;
            position: relative;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .conversation-item.unread {
            background-color: #e6f7f2;
            border-left: 3px solid #009b72;
        }
        
        .conversation-item.annonce {
            background-color: #fff8e1;
            border-left: 3px solid #ffc107;
        }

        .conversation-icon {
            width: 36px;
            height: 36px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #6c757d;
        }
        
        .conversation-icon.annonce {
            background-color: #ffc107;
            color: white;
        }

        .conversation-content {
            flex-grow: 1;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .conversation-header h3 {
            font-size: 14px;
            font-weight: 600;
        }

        .conversation-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 12px;
        }

        /* --- ÉTATS VIDES --- */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* --- MENU D'ACTIONS RAPIDES --- */
        .quick-actions {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
        }
        
        .quick-actions-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
        }
        
        .quick-actions-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 5px 0;
            min-width: 160px;
            z-index: 100;
            display: none;
        }
        
        .quick-actions-menu.active {
            display: block;
        }
        
        .quick-actions-menu form {
            display: block;
        }
        
        .quick-actions-menu button {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            text-align: left;
            padding: 8px 15px;
            background: none;
            border: none;
            cursor: pointer;
            color: #495057;
            font-size: 13px;
        }
        
        .quick-actions-menu button:hover {
            background-color: #f8f9fa;
        }
        
        .quick-actions-menu button.delete {
            color: #dc3545;
        }
        
        .quick-actions-menu button.delete:hover {
            background-color: #fff8f8;
        }

        /* --- MEDIAS QUERIES --- */
        @media (max-width: 768px) {
            .container {
                max-width: none;
                width: 100%;
            }
            
            .content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e9ecef;
                padding: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Messagerie Pronote</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                <span class="badge"><?= htmlspecialchars(ucfirst($user['type'])) ?></span>
                <?php if (count($unreadNotifications) > 0): ?>
                <span class="notification-badge"><?= count($unreadNotifications) ?></span>
                <?php endif; ?>
            </div>
        </header>

        <div class="content">
            <nav class="sidebar">
                <div class="folder-menu">
                    <?php foreach ($folders as $key => $name): ?>
                    <a href="index.php?folder=<?= $key ?>" class="<?= $currentFolder === $key ? 'active' : '' ?>">
                        <i class="fas fa-<?= getFolderIcon($key) ?>"></i> <?= htmlspecialchars($name) ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="action-buttons">
                    <a href="new_message.php" class="btn primary">
                        <i class="fas fa-pen"></i> Nouveau message
                    </a>
                    
                    <?php if ($user['type'] === 'professeur'): ?>
                    <a href="class_message.php" class="btn secondary">
                        <i class="fas fa-graduation-cap"></i> Message à la classe
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($canSendAnnouncement): ?>
                    <a href="new_announcement.php" class="btn warning">
                        <i class="fas fa-bullhorn"></i> Nouvelle annonce
                    </a>
                    <?php endif; ?>
                </div>
            </nav>

            <main>
                <h2><?= htmlspecialchars($folders[$currentFolder]) ?></h2>
                
                <?php if (empty($convs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Aucun message dans ce dossier</p>
                </div>
                <?php elseif ($currentFolder === 'corbeille'): ?>
                <div class="conversation-list">
                    <?php foreach ($convs as $c): ?>
                    <div class="conversation-item <?= $c['type'] === 'annonce' ? 'annonce' : '' ?>">
                        <a href="conversation.php?id=<?= htmlspecialchars($c['id']) ?>" class="conversation-content">
                            <div class="conversation-icon <?= $c['type'] === 'annonce' ? 'annonce' : '' ?>">
                                <i class="fas fa-<?= getConversationIcon($c['type']) ?>"></i>
                            </div>
                            <div class="conversation-header">
                                <h3><?= htmlspecialchars($c['titre'] ?: 'Conversation #'.$c['id']) ?></h3>
                            </div>
                            <div class="conversation-meta">
                                <span class="type"><?= htmlspecialchars(getConversationType($c['type'])) ?></span>
                                <span class="date"><?= formatDate($c['dernier_message']) ?></span>
                            </div>
                        </a>
                        
                        <!-- Menu d'actions rapides -->
                        <div class="quick-actions">
                            <button class="quick-actions-btn" onclick="toggleQuickActions(<?= $c['id'] ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="quick-actions-menu" id="quick-actions-<?= $c['id'] ?>">
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="conv_id" value="<?= $c['id'] ?>">
                                    <button type="submit">
                                        <i class="fas fa-trash-restore"></i> Restaurer
                                    </button>
                                </form>
                                
                                <form method="post" action="index.php" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette conversation ? Cette action est irréversible.')">
                                    <input type="hidden" name="action" value="delete_permanently">
                                    <input type="hidden" name="conv_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="delete">
                                        <i class="fas fa-trash-alt"></i> Supprimer définitivement
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="conversation-list">
                    <?php foreach ($convs as $c): ?>
                    <div class="conversation-item <?= $c['non_lus'] > 0 ? 'unread' : '' ?> <?= $c['type'] === 'annonce' ? 'annonce' : '' ?>">
                        <a href="conversation.php?id=<?= htmlspecialchars($c['id']) ?>" class="conversation-content">
                            <div class="conversation-icon <?= $c['type'] === 'annonce' ? 'annonce' : '' ?>">
                                <i class="fas fa-<?= getConversationIcon($c['type']) ?>"></i>
                            </div>
                            <div class="conversation-header">
                                <h3><?= htmlspecialchars($c['titre'] ?: 'Conversation #'.$c['id']) ?></h3>
                                <?php if ($c['non_lus'] > 0): ?>
                                <span class="badge"><?= $c['non_lus'] ?> nouveau(x)</span>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-meta">
                                <span class="type"><?= htmlspecialchars(getConversationType($c['type'])) ?></span>
                                <span class="date"><?= formatDate($c['dernier_message']) ?></span>
                            </div>
                        </a>
                        
                        <!-- Menu d'actions rapides -->
                        <div class="quick-actions">
                            <button class="quick-actions-btn" onclick="toggleQuickActions(<?= $c['id'] ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="quick-actions-menu" id="quick-actions-<?= $c['id'] ?>">
                                <?php if ($currentFolder !== 'archives'): ?>
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="conv_id" value="<?= $c['id'] ?>">
                                    <button type="submit">
                                        <i class="fas fa-archive"></i> Archiver
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="post" action="index.php">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="conv_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="delete">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Gestion du menu d'actions rapides
        function toggleQuickActions(convId) {
            const menu = document.getElementById('quick-actions-' + convId);
            
            // Fermer tous les autres menus
            document.querySelectorAll('.quick-actions-menu').forEach(item => {
                if (item !== menu) {
                    item.classList.remove('active');
                }
            });
            
            // Basculer l'état du menu actuel
            menu.classList.toggle('active');
            
            // Empêcher la propagation du clic pour éviter la navigation
            event.stopPropagation();
        }
        
        // Fermer les menus lors d'un clic ailleurs sur la page
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.quick-actions')) {
                document.querySelectorAll('.quick-actions-menu').forEach(menu => {
                    menu.classList.remove('active');
                });
            }
        });

        // Auto-refresh pour les nouveaux messages toutes les 30 secondes
        setInterval(function() {
            // Vérifier s'il n'y a pas de menu ouvert avant de recharger
            const activeMenus = document.querySelectorAll('.quick-actions-menu.active');
            if (activeMenus.length === 0) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>