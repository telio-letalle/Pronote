<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/user.php';

$auth = new Auth($pdo);
$user = new User($pdo);
$error = '';
$success = '';
$generatedPassword = '';
$identifiant = '';

// Chargement des données d'établissement (classes et matières)
$etablissementData = $user->getEtablissementData();

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $profil = isset($_POST['profil']) ? $_POST['profil'] : '';
    
    // Configuration des champs requis par profil
    $requiredFields = [
        'eleve' => ['nom', 'prenom', 'date_naissance', 'lieu_naissance', 'classe', 'adresse', 'mail'],
        'parent' => ['nom', 'prenom', 'mail', 'adresse'],
        'professeur' => ['nom', 'prenom', 'mail', 'adresse', 'matiere'],
        'vie_scolaire' => ['nom', 'prenom', 'mail'],
        'administrateur' => ['nom', 'prenom', 'mail']
    ];
    
    // Champs optionnels par profil
    $optionalFields = [
        'eleve' => ['telephone'],
        'parent' => ['telephone', 'metier', 'est_parent_eleve'],
        'professeur' => ['telephone', 'professeur_principal'],
        'vie_scolaire' => ['telephone', 'est_CPE', 'est_infirmerie'],
        'administrateur' => ['telephone']
    ];

    if (!isset($requiredFields[$profil])) {
        $error = 'Profil invalide.';
    } else {
        $data = [];
        
        // Validation des champs requis
        foreach ($requiredFields[$profil] as $field) {
            if (empty($_POST[$field])) {
                $error = "Le champ '$field' est obligatoire.";
                break;
            }
            $data[$field] = trim($_POST[$field]);
        }
        
        // Si pas d'erreur, on ajoute les champs optionnels
        if (!$error) {
            foreach ($optionalFields[$profil] as $field) {
                if (isset($_POST[$field]) && $_POST[$field] !== '') {
                    $data[$field] = trim($_POST[$field]);
                }
            }
            
            // Tentative de création de l'utilisateur
            if ($user->create($profil, $data)) {
                $generatedPassword = $user->getGeneratedPassword();
                $identifiant = $user->getGeneratedIdentifier();
                
                $success = "Compte créé avec succès!";
                
                // Si c'est un administrateur, on s'assure de ne pas le connecter automatiquement
                if ($profil === 'administrateur') {
                    // Déconnecter si connecté automatiquement
                    if ($auth->isLoggedIn()) {
                        $auth->logout();
                    }
                }
                
                // Réinitialiser les données du formulaire après création réussie
                unset($data);
                $_POST = [];
                $profil = 'eleve'; // Réinitialiser le profil sélectionné
            } else {
                // Récupérer le message d'erreur spécifique
                $error = $user->getErrorMessage();
                if (empty($error)) {
                    $error = "Échec de l'enregistrement pour une raison inconnue. Veuillez réessayer.";
                }
            }
        }
    }
}

// Détermination du profil sélectionné (pour le formulaire)
$profil = isset($_POST['profil']) ? $_POST['profil'] : 'eleve';

// Déterminer quel avatar afficher en fonction du profil sélectionné
$avatars = [
    'eleve' => 'student.png',
    'parent' => 'parent.png',
    'professeur' => 'teacher.png',
    'vie_scolaire' => 'staff.png',
    'administrateur' => 'admin.png'
];
$avatarImg = $avatars[$profil] ?? 'student.png';
$espaceTitle = 'Création compte ' . ucfirst($profil);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pronote - Inscription</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/styles_register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="register-container">
        <img src="assets/images/logo-pronote.png" alt="Logo Pronote" class="logo">
        
        <h2><?php echo htmlspecialchars($espaceTitle); ?></h2>
        
        <div class="avatar">
            <img src="assets/images/avatars/<?php echo htmlspecialchars($avatarImg); ?>" alt="Avatar">
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <!-- Section de succès redessinée -->
            <div class="success-message">
                <p><?php echo htmlspecialchars($success); ?></p>
                <div class="credentials-info">
                    <p><strong>Identifiant :</strong> <?php echo htmlspecialchars($identifiant); ?></p>
                    <p><strong>Mot de passe temporaire :</strong> <?php echo htmlspecialchars($generatedPassword); ?></p>
                    <p class="warning">Notez bien ces informations, le mot de passe ne sera plus jamais affiché !</p>
                    <p>L'utilisateur devra changer ce mot de passe lors de sa première connexion.</p>
                </div>
            </div>
            <!-- Boutons en dehors du message de succès -->
            <div class="form-actions" style="margin-top: 20px;">
                <a href="register.php" class="btn-connect">Créer un nouveau compte</a>
                <a href="index.php?from_register=1&success=1" class="btn-cancel">Page de connexion</a>
            </div>
        <?php else: ?>
        
        <form action="register.php" method="post" id="registrationForm">
            <div class="required-notice">* champs obligatoires</div>
            
            <div class="form-group">
                <label for="profil">Type de compte</label>
                <select name="profil" id="profil" required onchange="updateFormFields()">
                    <option value="eleve" <?php echo ($profil === 'eleve') ? 'selected' : ''; ?>>Élève</option>
                    <option value="parent" <?php echo ($profil === 'parent') ? 'selected' : ''; ?>>Parent</option>
                    <option value="professeur" <?php echo ($profil === 'professeur') ? 'selected' : ''; ?>>Professeur</option>
                    <option value="vie_scolaire" <?php echo ($profil === 'vie_scolaire') ? 'selected' : ''; ?>>Vie scolaire</option>
                    <option value="administrateur" <?php echo ($profil === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                </select>
            </div>

            <div class="register-form">
                <!-- Champs communs à tous les profils -->
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" name="nom" id="nom" value="<?php echo htmlspecialchars(isset($_POST['nom']) ? $_POST['nom'] : ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" name="prenom" id="prenom" value="<?php echo htmlspecialchars(isset($_POST['prenom']) ? $_POST['prenom'] : ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="mail">Email</label>
                    <input type="email" name="mail" id="mail" value="<?php echo htmlspecialchars(isset($_POST['mail']) ? $_POST['mail'] : ''); ?>" 
                        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" 
                        title="Veuillez entrer une adresse email valide" required>
                </div>

                <div class="form-group">
                    <label for="telephone" class="optional">Téléphone</label>
                    <input type="tel" name="telephone" id="telephone" 
                        value="<?php echo htmlspecialchars(isset($_POST['telephone']) ? $_POST['telephone'] : ''); ?>" 
                        pattern="[0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2} [0-9]{2}|[0-9]{10}" 
                        placeholder="XX XX XX XX XX">
                </div>

                <!-- Champs spécifiques dynamiques -->
                <div id="dynamicFields">
                    <!-- Ces champs seront remplis dynamiquement par JavaScript -->
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="submit" value="1" class="btn-connect">Créer le compte</button>
                <a href="index.php" class="btn-cancel">Annuler</a>
            </div>
        </form>
        
        <!-- Templates de champs spécifiques à chaque profil -->
        <div id="eleveFields" style="display:none;">
            <div class="form-group">
                <label for="date_naissance">Date de naissance</label>
                <input type="date" name="date_naissance" id="date_naissance" 
                       value="<?php echo htmlspecialchars(isset($_POST['date_naissance']) ? $_POST['date_naissance'] : ''); ?>" 
                       max="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label for="lieu_naissance">Lieu de naissance</label>
                <div class="input-group">
                    <input type="text" name="lieu_naissance" id="lieu_naissance" 
                           value="<?php echo htmlspecialchars(isset($_POST['lieu_naissance']) ? $_POST['lieu_naissance'] : ''); ?>" 
                           autocomplete="off" required>
                    <div id="villesSuggestions" class="suggestions-container"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="classe">Classe</label>
                <select name="classe" id="classe" required>
                    <option value="">Sélectionner une classe</option>
                    
                    <?php if (!empty($etablissementData['classes']['college'])): ?>
                    <optgroup label="Collège">
                        <?php foreach ($etablissementData['classes']['college'] as $niveau => $classes): ?>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo htmlspecialchars($classe); ?>">
                                    <?php echo htmlspecialchars($classe); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    
                    <?php if (!empty($etablissementData['classes']['lycee'])): ?>
                    <optgroup label="Lycée">
                        <?php foreach ($etablissementData['classes']['lycee'] as $niveau => $classes): ?>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo htmlspecialchars($classe); ?>">
                                    <?php echo htmlspecialchars($classe); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>
                <div class="input-group">
                    <input type="text" name="adresse" id="adresse" 
                           value="<?php echo htmlspecialchars(isset($_POST['adresse']) ? $_POST['adresse'] : ''); ?>" 
                           placeholder="Commencez à taper une adresse" autocomplete="off" required>
                    <div id="adressesSuggestions" class="suggestions-container"></div>
                </div>
            </div>
        </div>

        <div id="parentFields" style="display:none;">
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <div class="input-group">
                    <input type="text" name="adresse" id="adresse" 
                           value="<?php echo htmlspecialchars(isset($_POST['adresse']) ? $_POST['adresse'] : ''); ?>" 
                           placeholder="Commencez à taper une adresse" autocomplete="off" required>
                    <div id="adressesSuggestions" class="suggestions-container"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="metier" class="optional">Métier</label>
                <input type="text" name="metier" id="metier" 
                       value="<?php echo htmlspecialchars(isset($_POST['metier']) ? $_POST['metier'] : ''); ?>">
            </div>

            <div class="form-group">
                <label for="est_parent_eleve" class="optional">Parent d'élève</label>
                <select name="est_parent_eleve" id="est_parent_eleve">
                    <option value="oui" <?php echo (isset($_POST['est_parent_eleve']) && $_POST['est_parent_eleve'] === 'oui') ? 'selected' : ''; ?>>Oui</option>
                    <option value="non" <?php echo (!isset($_POST['est_parent_eleve']) || $_POST['est_parent_eleve'] === 'non') ? 'selected' : ''; ?>>Non</option>
                </select>
            </div>
        </div>

        <div id="professeurFields" style="display:none;">
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <div class="input-group">
                    <input type="text" name="adresse" id="adresse" 
                           value="<?php echo htmlspecialchars(isset($_POST['adresse']) ? $_POST['adresse'] : ''); ?>" 
                           placeholder="Commencez à taper une adresse" autocomplete="off" required>
                    <div id="adressesSuggestions" class="suggestions-container"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="matiere">Matière enseignée</label>
                <select name="matiere" id="matiere" required>
                    <option value="">Sélectionner une matière</option>
                    <?php foreach ($etablissementData['matieres'] as $matiere): ?>
                        <option value="<?php echo htmlspecialchars($matiere['nom']); ?>">
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="professeur_principal" class="optional">Professeur principal</label>
                <select name="professeur_principal" id="professeur_principal">
                    <option value="non">Non</option>
                    
                    <?php if (!empty($etablissementData['classes']['college'])): ?>
                    <optgroup label="Collège">
                        <?php foreach ($etablissementData['classes']['college'] as $niveau => $classes): ?>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo htmlspecialchars($classe); ?>">
                                    <?php echo htmlspecialchars($classe); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    
                    <?php if (!empty($etablissementData['classes']['lycee'])): ?>
                    <optgroup label="Lycée">
                        <?php foreach ($etablissementData['classes']['lycee'] as $niveau => $classes): ?>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo htmlspecialchars($classe); ?>">
                                    <?php echo htmlspecialchars($classe); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div id="vieScolaireFields" style="display:none;">
            <div class="form-group">
                <label for="est_CPE" class="optional">CPE</label>
                <select name="est_CPE" id="est_CPE">
                    <option value="oui" <?php echo (isset($_POST['est_CPE']) && $_POST['est_CPE'] === 'oui') ? 'selected' : ''; ?>>Oui</option>
                    <option value="non" <?php echo (!isset($_POST['est_CPE']) || $_POST['est_CPE'] === 'non') ? 'selected' : ''; ?>>Non</option>
                </select>
            </div>

            <div class="form-group">
                <label for="est_infirmerie" class="optional">Infirmerie</label>
                <select name="est_infirmerie" id="est_infirmerie">
                    <option value="oui" <?php echo (isset($_POST['est_infirmerie']) && $_POST['est_infirmerie'] === 'oui') ? 'selected' : ''; ?>>Oui</option>
                    <option value="non" <?php echo (!isset($_POST['est_infirmerie']) || $_POST['est_infirmerie'] === 'non') ? 'selected' : ''; ?>>Non</option>
                </select>
            </div>
        </div>

        <!-- Pas de champs spécifiques pour administrateur -->
        <div id="administrateurFields" style="display:none;">
            <!-- Aucun champ supplémentaire nécessaire -->
        </div>
        
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fonction pour afficher les champs en fonction du profil sélectionné
            function updateFormFields() {
                var profil = document.getElementById('profil').value;
                var dynamicFields = document.getElementById('dynamicFields');
                var avatar = document.querySelector('.avatar img');
                var title = document.querySelector('h2');
                
                // Mettre à jour l'avatar
                avatar.src = 'assets/images/avatars/' + getAvatarForProfile(profil);
                
                // Mettre à jour le titre
                title.textContent = 'Création compte ' + capitalizeFirstLetter(profil);
                
                // Vider les champs dynamiques
                dynamicFields.innerHTML = '';
                
                // Récupérer le template correspondant
                var template = document.getElementById(profil + 'Fields');
                
                if (template) {
                    // Cloner le contenu du template
                    var clone = template.cloneNode(true);
                    clone.style.display = 'block';
                    clone.id = 'active' + profil + 'Fields';
                    
                    // Ajouter au formulaire
                    dynamicFields.appendChild(clone);
                    
                    // Initialiser les autocompletions pour les nouveaux champs
                    initAutocompletion();
                }
            }
            
            function getAvatarForProfile(profil) {
                var avatars = {
                    'eleve': 'student.png',
                    'parent': 'parent.png',
                    'professeur': 'teacher.png',
                    'vie_scolaire': 'staff.png',
                    'administrateur': 'admin.png'
                };
                return avatars[profil] || 'student.png';
            }
            
            function capitalizeFirstLetter(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Initialiser les autocompletions
            function initAutocompletion() {
                // Autocomplétion des villes (lieu de naissance)
                const lieuNaissanceInput = document.getElementById('lieu_naissance');
                const villesSuggestions = document.getElementById('villesSuggestions');
                
                if (lieuNaissanceInput && villesSuggestions) {
                    lieuNaissanceInput.addEventListener('input', function() {
                        const query = this.value.trim();
                        if (query.length < 2) {
                            villesSuggestions.style.display = 'none';
                            return;
                        }
                        
                        // Appel à l'API pour les suggestions de villes
                        fetch(`https://geo.api.gouv.fr/communes?nom=${query}&fields=nom&boost=population&limit=5`)
                            .then(response => response.json())
                            .then(data => {
                                villesSuggestions.innerHTML = '';
                                
                                if (data.length > 0) {
                                    data.forEach(ville => {
                                        const item = document.createElement('div');
                                        item.className = 'suggestion-item';
                                        item.textContent = ville.nom;
                                        item.addEventListener('click', function() {
                                            lieuNaissanceInput.value = ville.nom;
                                            villesSuggestions.style.display = 'none';
                                        });
                                        villesSuggestions.appendChild(item);
                                    });
                                    villesSuggestions.style.display = 'block';
                                } else {
                                    villesSuggestions.style.display = 'none';
                                }
                            })
                            .catch(error => {
                                console.error('Erreur lors de la récupération des villes:', error);
                            });
                    });
                }
                
                // Autocomplétion des adresses
                const adresseInput = document.getElementById('adresse');
                const adressesSuggestions = document.getElementById('adressesSuggestions');
                
                if (adresseInput && adressesSuggestions) {
                    adresseInput.addEventListener('input', function() {
                        const query = this.value.trim();
                        if (query.length < 3) {
                            adressesSuggestions.style.display = 'none';
                            return;
                        }
                        
                        // Appel à l'API pour les suggestions d'adresses
                        fetch(`https://api-adresse.data.gouv.fr/search/?q=${query}&limit=5`)
                            .then(response => response.json())
                            .then(data => {
                                adressesSuggestions.innerHTML = '';
                                
                                if (data.features && data.features.length > 0) {
                                    data.features.forEach(feature => {
                                        const item = document.createElement('div');
                                        item.className = 'suggestion-item';
                                        item.textContent = feature.properties.label;
                                        item.addEventListener('click', function() {
                                            adresseInput.value = feature.properties.label;
                                            adressesSuggestions.style.display = 'none';
                                        });
                                        adressesSuggestions.appendChild(item);
                                    });
                                    adressesSuggestions.style.display = 'block';
                                } else {
                                    adressesSuggestions.style.display = 'none';
                                }
                            })
                            .catch(error => {
                                console.error('Erreur lors de la récupération des adresses:', error);
                            });
                    });
                }
                
                // Fermer les suggestions si on clique ailleurs
                document.addEventListener('click', function(e) {
                    if (villesSuggestions && lieuNaissanceInput && !lieuNaissanceInput.contains(e.target) && !villesSuggestions.contains(e.target)) {
                        villesSuggestions.style.display = 'none';
                    }
                    
                    if (adressesSuggestions && adresseInput && !adresseInput.contains(e.target) && !adressesSuggestions.contains(e.target)) {
                        adressesSuggestions.style.display = 'none';
                    }
                });
            }
            
            // Écouter les changements sur le sélecteur de profil
            document.getElementById('profil').addEventListener('change', updateFormFields);
            
            // Initialiser le formulaire
            updateFormFields();
        });
    </script>
</body>
</html>