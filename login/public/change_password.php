<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();

// Sécurité: Vérifier si l'utilisateur est arrivé via le processus normal d'authentification
if (!isset($_SESSION['password_change_required']) && !isset($_SESSION['user']['first_login'])) {
    // Rediriger vers index.php si l'accès est direct
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$errors = []; // Tableau pour stocker les différentes erreurs

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Vérification des règles de mot de passe
    $lengthValid = strlen($newPassword) >= 12 && strlen($newPassword) <= 50;
    $uppercaseValid = preg_match('/[A-Z]/', $newPassword);
    $lowercaseValid = preg_match('/[a-z]/', $newPassword);
    $digitValid = preg_match('/[0-9]/', $newPassword);
    $specialValid = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword);
    $matchValid = $newPassword === $confirmPassword;
    
    // Ajouter des messages d'erreur spécifiques
    if (!$lengthValid) {
        $errors[] = 'Le mot de passe doit contenir entre 12 et 50 caractères.';
    }
    if (!$uppercaseValid) {
        $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
    }
    if (!$lowercaseValid) {
        $errors[] = 'Le mot de passe doit contenir au moins une lettre minuscule.';
    }
    if (!$digitValid) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    if (!$specialValid) {
        $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*...).';
    }
    if (!$matchValid) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    // S'il n'y a pas d'erreurs, changer le mot de passe
    if (empty($errors)) {
        if ($auth->changePassword($newPassword)) {
            // Mettre à jour le flag de session pour indiquer que le mot de passe a été changé
            $_SESSION['password_change_required'] = false;
            $success = 'Votre mot de passe a été modifié avec succès.';
        } else {
            $error = 'Une erreur est survenue lors du changement de mot de passe.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$user = $_SESSION['user'];
$isFirstLogin = $user['first_login'] ?? false;
$avatars = [
    'eleve' => 'student.png',
    'parent' => 'parent.png',
    'professeur' => 'teacher.png',
    'vie_scolaire' => 'staff.png',
    'administrateur' => 'admin.png'
];
$avatarImg = $avatars[$user['profil']] ?? 'student.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Changement de mot de passe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/styles_password.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="change-password-container">
        <img src="assets/images/logo-pronote.png" alt="Logo Pronote" class="logo">
        
        <h2>Changement de mot de passe</h2>
        
        <div class="avatar">
            <img src="assets/images/avatars/<?php echo htmlspecialchars($avatarImg); ?>" alt="Avatar">
        </div>
        
        <?php if ($isFirstLogin): ?>
        <div class="info-message">
            <p>C'est votre première connexion. Vous devez changer votre mot de passe par défaut.</p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
        
        <div class="form-actions" style="margin-top: 20px; text-align: center;">
            <a href="#" onclick="
                // Charger le script de vérification de session
                var script = document.createElement('script');
                script.src = '/~u22405372/SAE/Pronote/login/public/assets/js/session_checker.js';
                document.head.appendChild(script);
                
                // Rediriger après chargement du script
                script.onload = function() {
                    window.location.href = '/~u22405372/SAE/Pronote/accueil/accueil.php';
                };
                return false;
            " class="btn-connect">Continuer</a>
        </div>
        <?php else: ?>
        
        <form action="change_password.php" method="post" id="passwordForm">
            <div class="required-notice">* champs obligatoires</div>
            
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <div class="input-group">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="visibility-toggle" id="newPasswordToggle">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <!-- Indicateur de force du mot de passe -->
            <div class="password-strength-meter">
                <div class="strength-indicator" id="strengthIndicator"></div>
            </div>
            <div class="password-strength-text" id="strengthText">Force du mot de passe: -</div>
            
            <!-- Liste des exigences avec indicateurs -->
            <div class="password-requirements">
                <p>Le mot de passe doit :</p>
                <ul>
                    <li class="requirement-item">
                        <span class="requirement-status" id="length-status"><i class="fas fa-times invalid"></i></span>
                        <span>Contenir entre 12 et 50 caractères</span>
                    </li>
                    <li class="requirement-item">
                        <span class="requirement-status" id="uppercase-status"><i class="fas fa-times invalid"></i></span>
                        <span>Contenir au moins une lettre majuscule</span>
                    </li>
                    <li class="requirement-item">
                        <span class="requirement-status" id="lowercase-status"><i class="fas fa-times invalid"></i></span>
                        <span>Contenir au moins une lettre minuscule</span>
                    </li>
                    <li class="requirement-item">
                        <span class="requirement-status" id="digit-status"><i class="fas fa-times invalid"></i></span>
                        <span>Contenir au moins un chiffre</span>
                    </li>
                    <li class="requirement-item">
                        <span class="requirement-status" id="special-status"><i class="fas fa-times invalid"></i></span>
                        <span>Contenir au moins un caractère spécial (!@#$%^&*...)</span>
                    </li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <div class="input-group">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="visibility-toggle" id="confirmPasswordToggle">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
                <div class="password-match" id="passwordMatch"></div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-connect">Enregistrer</button>
                <?php if (!$isFirstLogin): ?>
                <a href="/~u22405372/SAE/Pronote/accueil/accueil.php" class="btn-cancel">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('strengthIndicator');
            const strengthText = document.getElementById('strengthText');
            const passwordMatchText = document.getElementById('passwordMatch');
            const newPasswordToggle = document.getElementById('newPasswordToggle');
            const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
            
            // Tableau pour stocker les statuts des indicateurs
            const statusIcons = {
                'length': document.getElementById('length-status'),
                'uppercase': document.getElementById('uppercase-status'),
                'lowercase': document.getElementById('lowercase-status'),
                'digit': document.getElementById('digit-status'),
                'special': document.getElementById('special-status')
            };
            
            // Fonction pour mettre à jour les indicateurs de validation
            function updateRequirementStatus(requirement, isValid) {
                const statusIcon = statusIcons[requirement];
                if (isValid) {
                    statusIcon.innerHTML = '<i class="fas fa-check valid"></i>';
                } else {
                    statusIcon.innerHTML = '<i class="fas fa-times invalid"></i>';
                }
            }
            
            // Fonction pour calculer la force du mot de passe
            function calculatePasswordStrength(password) {
                if (!password) return 0;
                
                let strength = 0;
                
                // Longueur (30%)
                if (password.length >= 12 && password.length <= 50) {
                    strength += 30 * Math.min(1, password.length / 20); // Augmente jusqu'à 20 caractères
                    updateRequirementStatus('length', true);
                } else {
                    updateRequirementStatus('length', false);
                }
                
                // Majuscules (15%)
                const uppercaseCount = (password.match(/[A-Z]/g) || []).length;
                if (uppercaseCount >= 1) {
                    // Bonus pour plusieurs majuscules (jusqu'à 15%)
                    strength += 15 * Math.min(1, uppercaseCount / 3);
                    updateRequirementStatus('uppercase', true);
                } else {
                    updateRequirementStatus('uppercase', false);
                }
                
                // Minuscules (15%)
                const lowercaseCount = (password.match(/[a-z]/g) || []).length;
                if (lowercaseCount >= 1) {
                    // Bonus pour plusieurs minuscules
                    strength += 15 * Math.min(1, lowercaseCount / 5);
                    updateRequirementStatus('lowercase', true);
                } else {
                    updateRequirementStatus('lowercase', false);
                }
                
                // Chiffres (15%)
                const digitCount = (password.match(/[0-9]/g) || []).length;
                if (digitCount >= 1) {
                    // Bonus pour plusieurs chiffres
                    strength += 15 * Math.min(1, digitCount / 3);
                    updateRequirementStatus('digit', true);
                } else {
                    updateRequirementStatus('digit', false);
                }
                
                // Caractères spéciaux (15%)
                const specialCount = (password.match(/[!@#$%^&*(),.?":{}|<>]/g) || []).length;
                if (specialCount >= 1) {
                    // Bonus pour plusieurs caractères spéciaux
                    strength += 15 * Math.min(1, specialCount / 3);
                    updateRequirementStatus('special', true);
                } else {
                    updateRequirementStatus('special', false);
                }
                
                // Bonus pour la variété (10%)
                let varietyCount = 0;
                if (uppercaseCount > 0) varietyCount++;
                if (lowercaseCount > 0) varietyCount++;
                if (digitCount > 0) varietyCount++;
                if (specialCount > 0) varietyCount++;
                strength += (varietyCount / 4) * 10;
                
                return strength;
            }
            
            // Fonction pour mettre à jour l'indicateur de force
            function updateStrengthIndicator() {
                const password = newPasswordInput.value;
                const strength = calculatePasswordStrength(password);
                
                // Mise à jour de la barre de progression (s'assurer qu'elle peut atteindre 100%)
                strengthIndicator.style.width = strength + '%';
                
                // Détermination de la couleur en fonction de la force
                if (strength < 40) {
                    strengthIndicator.style.backgroundColor = '#dc3545'; // Rouge
                    strengthText.textContent = 'Force du mot de passe: Faible';
                    strengthText.style.color = '#dc3545';
                } else if (strength < 70) {
                    strengthIndicator.style.backgroundColor = '#fd7e14'; // Orange
                    strengthText.textContent = 'Force du mot de passe: Moyen';
                    strengthText.style.color = '#fd7e14';
                } else if (strength < 90) {
                    strengthIndicator.style.backgroundColor = '#28a745'; // Vert
                    strengthText.textContent = 'Force du mot de passe: Fort';
                    strengthText.style.color = '#28a745';
                } else {
                    // Gradient de vert à turquoise pour "Très fort"
                    strengthIndicator.style.background = 'linear-gradient(to right, #28a745, #20c997)';
                    strengthText.textContent = 'Force du mot de passe: Très fort';
                    strengthText.style.color = '#20c997';
                }
                
                // Ajouter une animation subtile pour les mots de passe très forts
                if (strength >= 90) {
                    strengthIndicator.style.animation = 'pulse 1.5s infinite';
                } else {
                    strengthIndicator.style.animation = 'none';
                }
                
                // Vérification de la correspondance des mots de passe
                if (confirmPasswordInput.value) {
                    checkPasswordMatch();
                }
            }
            
            // Fonction pour vérifier la correspondance des mots de passe
            function checkPasswordMatch() {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password === confirmPassword) {
                    passwordMatchText.textContent = '✓ Les mots de passe correspondent';
                    passwordMatchText.style.color = '#28a745';
                } else {
                    passwordMatchText.textContent = '✗ Les mots de passe ne correspondent pas';
                    passwordMatchText.style.color = '#dc3545';
                }
            }
            
            // Écoute des événements pour le mot de passe
            newPasswordInput.addEventListener('input', updateStrengthIndicator);
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Bloquer le copier-coller sur le champ de confirmation
            confirmPasswordInput.addEventListener('paste', function(e) {
                e.preventDefault();
                alert('Le collage n\'est pas autorisé pour le champ de confirmation du mot de passe');
            });
            
            // Fonctions pour le bouton "afficher mot de passe" avec appui maintenu
            function showNewPassword() {
                newPasswordInput.type = 'text';
                newPasswordToggle.querySelector('i').className = 'fa-regular fa-eye-slash';
            }
            
            function hideNewPassword() {
                newPasswordInput.type = 'password';
                newPasswordToggle.querySelector('i').className = 'fa-regular fa-eye';
            }
            
            // Désactiver l'affichage du mot de passe de confirmation
            function preventShowConfirmPassword(e) {
                e.preventDefault();
                alert('Il n\'est pas possible de visualiser le mot de passe de confirmation');
            }
            
            // Écouteurs d'événements pour le bouton du nouveau mot de passe
            newPasswordToggle.addEventListener('mousedown', showNewPassword);
            newPasswordToggle.addEventListener('mouseup', hideNewPassword);
            newPasswordToggle.addEventListener('mouseleave', hideNewPassword);
            newPasswordToggle.addEventListener('touchstart', showNewPassword);
            newPasswordToggle.addEventListener('touchend', hideNewPassword);
            newPasswordToggle.addEventListener('touchcancel', hideNewPassword);
            
            // Désactiver le bouton de visualisation pour le mot de passe de confirmation
            confirmPasswordToggle.addEventListener('mousedown', preventShowConfirmPassword);
            confirmPasswordToggle.addEventListener('touchstart', preventShowConfirmPassword);
        });
    </script>
</body>
</html>