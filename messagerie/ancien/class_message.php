<?php
// class_message.php - Envoi de messages à une classe
require 'config.php';
require 'functions.php';
require 'message_class.php';

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

// Vérifier que l'utilisateur est un professeur
if ($user['type'] !== 'professeur') {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Récupérer les classes depuis le fichier etablissement.json
$etablissementFile = __DIR__ . '/../login/data/etablissement.json';
$classes = [];

if (file_exists($etablissementFile)) {
    $etablissement = json_decode(file_get_contents($etablissementFile), true);
    if (isset($etablissement['classes']) && is_array($etablissement['classes'])) {
        $classes = $etablissement['classes'];
    }
} else {
    // Fallback: récupérer les classes depuis la base de données
    $query = $pdo->query("SELECT DISTINCT classe FROM eleves ORDER BY classe");
    $classes = $query->fetchAll(PDO::FETCH_COLUMN);
}

// Traitement du formulaire d'envoi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $classe = isset($_POST['classe']) ? trim($_POST['classe']) : '';
        $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
        $contenu = isset($_POST['contenu']) ? trim($_POST['contenu']) : '';
        $importance = isset($_POST['importance']) ? $_POST['importance'] : 'normal';
        $includeParents = isset($_POST['include_parents']) && $_POST['include_parents'] === 'on';
        $notificationObligatoire = isset($_POST['notification_obligatoire']) && $_POST['notification_obligatoire'] === 'on';
        
        if (empty($classe)) {
            throw new Exception("Veuillez sélectionner une classe");
        }
        
        if (empty($titre)) {
            throw new Exception("Le titre est obligatoire");
        }
        
        if (empty($contenu)) {
            throw new Exception("Le message ne peut pas être vide");
        }
        
        // Envoi du message à la classe
        $filesData = isset($_FILES['attachments']) ? $_FILES['attachments'] : [];
        $convId = sendMessageToClass(
            $user['id'],
            $classe,
            $titre,
            $contenu,
            $importance,
            $notificationObligatoire,
            $includeParents,
            $filesData
        );
        
        $success = "Votre message a été envoyé avec succès à la classe " . htmlspecialchars($classe);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Message à la classe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Le CSS reste inchangé */
        /* --- RESET ET STYLES DE BASE --- */
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

        /* --- CONTENU PRINCIPAL --- */
        .content {
            flex-grow: 1;
            padding: 20px;
        }

        /* --- FORMULAIRES --- */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .form-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            color: #212529;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #009b72;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 155, 114, 0.1);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .options-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            position: relative;
            padding-left: 28px;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input {
            position: absolute;
            opacity: 0;
            height: 0;
            width: 0;
        }

        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 18px;
            width: 18px;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 3px;
        }

        .checkbox-container:hover input ~ .checkmark {
            background-color: #f8f9fa;
        }

        .checkbox-container input:checked ~ .checkmark {
            background-color: #009b72;
            border-color: #009b72;
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
            left: 6px;
            top: 2px;
            width: 4px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .form-actions {
            display: flex;
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

        .btn.cancel {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .btn.cancel:hover {
            background-color: #e9ecef;
        }

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

        /* --- ALERTES --- */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Classes spécifiques */
        .class-icon {
            background-color: #4A6572;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour</a>
            <h1>Message à la classe</h1>
        </header>

        <div class="content">
            <?php if (!empty($success)): ?>
            <div class="alert success">
                <p><?= htmlspecialchars($success) ?></p>
                <div style="margin-top: 15px">
                    <a href="index.php" class="btn primary">Retour à la messagerie</a>
                </div>
            </div>
            <?php else: ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="form-container">
                <div class="form-header">
                    <div class="class-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h2 class="form-title">Envoyer un message à une classe</h2>
                </div>
                
                <div class="alert-info">
                    <i class="fas fa-info-circle"></i> Ce message sera envoyé à tous les élèves de la classe sélectionnée.
                </div>
                
                <form method="post" enctype="multipart/form-data" id="messageForm">
                    <div class="form-group">
                        <label for="classe">Classe</label>
                        <select name="classe" id="classe" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $classe): ?>
                            <option value="<?= htmlspecialchars($classe) ?>"><?= htmlspecialchars($classe) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="titre">Titre</label>
                        <input type="text" name="titre" id="titre" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contenu">Message</label>
                        <textarea name="contenu" id="contenu" required></textarea>
                    </div>
                    
                    <div class="options-group">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="importance">Importance</label>
                            <select name="importance" id="importance">
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="checkbox-container">
                                <input type="checkbox" name="include_parents">
                                <span class="checkmark"></span>
                                Inclure les parents d'élèves
                            </label>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="checkbox-container">
                                <input type="checkbox" name="notification_obligatoire">
                                <span class="checkmark"></span>
                                Notification obligatoire
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachments">Pièces jointes</label>
                        <div class="file-upload">
                            <input type="file" name="attachments[]" id="attachments" multiple>
                            <label for="attachments">
                                <i class="fas fa-paperclip"></i> Sélectionner des fichiers
                            </label>
                        </div>
                        <div id="file-list"></div>
                    </div>
                    
                    <div class="form-footer">
                        <div class="form-actions">
                            <button type="submit" class="btn primary">Envoyer</button>
                            <a href="index.php" class="btn cancel">Annuler</a>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Gestion des pièces jointes
        document.getElementById('attachments').addEventListener('change', function(e) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileSize = formatFileSize(file.size);
                    
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'file-info';
                    fileInfo.innerHTML = `
                        <i class="fas fa-file"></i>
                        <span>${file.name} (${fileSize})</span>
                    `;
                    fileList.appendChild(fileInfo);
                }
            }
        });
        
        // Formater la taille des fichiers
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            else if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
            else return Math.round(bytes / 1048576 * 10) / 10 + ' MB';
        }

        // Empêcher la soumission multiple du formulaire
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            // Désactiver le bouton d'envoi après soumission
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
        });
    </script>
</body>
</html>