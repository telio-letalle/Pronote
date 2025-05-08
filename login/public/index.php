<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';
$auth = new Auth($pdo);

// Vérification simple : si l'utilisateur est déjà connecté, on le redirige
// Sauf si on vient de register.php avec un message de succès
if ($auth->isLoggedIn() && !isset($_GET['from_register'])) {
    // Redirection vers accueil.php
    header('Location: /~u22405372/SAE/Pronote/accueil/accueil.php');
    exit;
}

// Si l'utilisateur vient de register.php, on n'a PAS besoin de le déconnecter
// Car il n'est PAS encore connecté - il doit saisir ses identifiants

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profil      = isset($_POST['profil']) ? $_POST['profil'] : '';
    $identifiant = isset($_POST['identifiant']) ? $_POST['identifiant'] : '';
    $password    = isset($_POST['mot_de_passe']) ? $_POST['mot_de_passe'] : '';

    if ($auth->login($profil, $identifiant, $password)) {
        // Vérification si c'est la première connexion (mot de passe par défaut)
        if ($auth->isDefaultPassword()) {
            // Définir un drapeau de session pour autoriser l'accès à change_password.php
            $_SESSION['password_change_required'] = true;
            header('Location: change_password.php');
            exit;
        }
        // On utilise JavaScript avec le script session_checker.js pour la redirection
        echo '
        <script src="/~u22405372/SAE/Pronote/login/public/assets/js/session_checker.js"></script>
        <script>
            window.location.href = "/~u22405372/SAE/Pronote/accueil/accueil.php";
        </script>';
        exit;
    } else {
        $error = 'Identifiant ou mot de passe invalides.';
    }
}

// Déterminer quel avatar afficher en fonction du profil sélectionné
$profil = isset($_POST['profil']) ? $_POST['profil'] : 'eleve';
$avatars = [
    'eleve' => 'student.png',
    'parent' => 'parent.png',
    'professeur' => 'teacher.png',
    'vie_scolaire' => 'staff.png',
    'administrateur' => 'admin.png'
];
$avatarImg = $avatars[$profil] ?? 'student.png';
$espaceTitle = 'Espace ' . ucfirst($profil) . 's';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Connexion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/styles_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <img src="assets/images/logo-pronote.png" alt="Logo Pronote" class="logo">
        
        <h2 id="espaceTitle"><?php echo htmlspecialchars($espaceTitle); ?></h2>
        
        <div class="avatar">
            <img id="avatarImage" src="assets/images/avatars/<?php echo htmlspecialchars($avatarImg); ?>" alt="Avatar">
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['from_register']) && isset($_GET['success'])): ?>
            <div class="success-message">
                <p>Compte créé avec succès ! Veuillez vous connecter avec l'identifiant et le mot de passe qui vous ont été fournis.</p>
            </div>
        <?php endif; ?>
        
        <form action="index.php<?php echo (isset($_GET['from_register']) && isset($_GET['success'])) ? '?from_register=1&success=1' : ''; ?>" method="post" id="loginForm">
            <div class="required-notice">* champs obligatoires</div>
            
            <div class="form-group">
                <label for="profil">Espace</label>
                <select name="profil" id="profil" required onchange="updateAvatar()">
                    <option value="eleve" <?php echo ($profil === 'eleve') ? 'selected' : ''; ?>>Élèves</option>
                    <option value="parent" <?php echo ($profil === 'parent') ? 'selected' : ''; ?>>Parents</option>
                    <option value="professeur" <?php echo ($profil === 'professeur') ? 'selected' : ''; ?>>Professeurs</option>
                    <option value="vie_scolaire" <?php echo ($profil === 'vie_scolaire') ? 'selected' : ''; ?>>Vie Scolaire</option>
                    <option value="administrateur" <?php echo ($profil === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                </select>
            </div>

            <div class="form-group">
                <label for="identifiant">Identifiant</label>
                <input type="text" name="identifiant" id="identifiant" placeholder="nom.prenom" required>
            </div>

            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <div class="input-group">
                    <input type="password" name="mot_de_passe" id="mot_de_passe" required>
                    <button type="button" class="visibility-toggle" id="passwordToggle">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-connect">Se connecter</button>
        </form>
    </div>

    <script>
        // Fonction pour mettre à jour l'avatar et le titre en fonction de l'espace sélectionné
        function updateAvatar() {
            const profil = document.getElementById('profil').value;
            const avatarElement = document.getElementById('avatarImage');
            const titleElement = document.getElementById('espaceTitle');
            
            const avatars = {
                'eleve': 'student.png',
                'parent': 'parent.png',
                'professeur': 'teacher.png',
                'vie_scolaire': 'staff.png',
                'administrateur': 'admin.png'
            };
            
            // Mise à jour de l'avatar
            avatarElement.src = 'assets/images/avatars/' + avatars[profil];
            
            // Mise à jour du titre
            titleElement.textContent = 'Espace ' + profil.charAt(0).toUpperCase() + profil.slice(1) + 's';
        }

        // Modification du comportement du bouton "afficher mot de passe" pour exiger un appui maintenu
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('mot_de_passe');
            const toggleButton = document.getElementById('passwordToggle');
            const visibilityIcon = toggleButton.querySelector('i');

            // Fonction pour afficher le mot de passe
            function showPassword() {
                passwordInput.type = 'text';
                visibilityIcon.className = 'fa-regular fa-eye-slash';
            }

            // Fonction pour masquer le mot de passe
            function hidePassword() {
                passwordInput.type = 'password';
                visibilityIcon.className = 'fa-regular fa-eye';
            }

            // Écouteurs d'événements pour appui maintenu
            toggleButton.addEventListener('mousedown', showPassword);
            toggleButton.addEventListener('mouseup', hidePassword);
            toggleButton.addEventListener('mouseleave', hidePassword);
            toggleButton.addEventListener('touchstart', showPassword);
            toggleButton.addEventListener('touchend', hidePassword);
            toggleButton.addEventListener('touchcancel', hidePassword);
        });
    </script>
</body>
</html>