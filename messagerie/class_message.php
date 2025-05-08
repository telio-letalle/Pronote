<?php
/**
 * /class_message.php - Envoi de messages à une classe
 */

// Inclure les fichiers nécessaires
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/message_functions.php';
require_once __DIR__ . '/includes/auth.php';

// Vérifier l'authentification
$user = requireAuth();

// Vérifier que l'utilisateur est un professeur
if ($user['type'] !== 'professeur') {
    redirect('index.php');
}

// Définir le titre de la page
$pageTitle = 'Message à la classe';

$error = '';
$success = '';

// Récupérer les classes depuis la base de données
$classes = getAvailableClasses();

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

// Inclure l'en-tête
include 'templates/header.php';
?>

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
            <div class="form-icon class">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h2 class="form-title">Envoyer un message à une classe</h2>
        </div>
        
        <div class="alert info">
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

<?php
// Inclure le pied de page
include 'templates/footer.php';
?>