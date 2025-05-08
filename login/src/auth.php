<?php
// src/Auth.php

class Auth {
    private $pdo;
    private $tableMap = [
        'eleve'        => 'eleves',
        'parent'       => 'parents',
        'professeur'   => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];
    
    private $isDefaultPassword = false;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Authentifie un utilisateur
     * 
     * @param string $profil Type de profil (eleve, parent, professeur, etc.)
     * @param string $identifiant Identifiant de l'utilisateur (format nom.prenom)
     * @param string $password Mot de passe en clair
     * @return bool True si l'authentification réussit, false sinon
     */
    public function login($profil, $identifiant, $password) {
        if (!isset($this->tableMap[$profil])) {
            return false;
        }
        
        $table = $this->tableMap[$profil];
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `$table` WHERE identifiant = :id LIMIT 1"
        );
        $stmt->execute(['id' => $identifiant]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Vérifier si l'utilisateur utilise un mot de passe temporaire
            $isTemporaryPassword = $this->isTemporaryPassword($user['mot_de_passe']);
            $this->isDefaultPassword = $isTemporaryPassword;
            
            // Stocker les informations utilisateur en session
            $_SESSION['user'] = [
                'id'      => $user['id'],
                'profil'  => $profil,
                'nom'     => $user['nom'],
                'prenom'  => $user['prenom'],
                'table'   => $table,
                'first_login' => $isTemporaryPassword,
                'last_check' => time(), // Ajouter un timestamp pour la dernière vérification
                'password_hash' => $user['mot_de_passe'] // Stocker le hash du mot de passe pour vérification
            ];
            return true;
        }
        return false;
    }
    
    /**
     * Vérifie si le mot de passe est un mot de passe temporaire
     * 
     * @param string $hashedPassword Mot de passe hashé
     * @return bool True si le mot de passe semble être temporaire
     */
    private function isTemporaryPassword($hashedPassword) {
        // Vérifier si les options de hachage correspondent à celles par défaut
        $info = password_get_info($hashedPassword);
        $options = $info['options'];
        
        // Si le coût est celui par défaut (10), on considère que c'est un mot de passe temporaire
        if ($options['cost'] === 10) {
            return true;
        }
        
        return false;
    }

    /**
     * Vérifie si l'utilisateur s'est connecté avec le mot de passe par défaut
     * 
     * @return bool True si le mot de passe utilisé est le mot de passe par défaut
     */
    public function isDefaultPassword() {
        return $this->isDefaultPassword;
    }

    /**
     * Vérifie si l'utilisateur est connecté
     * 
     * @return bool True si l'utilisateur est connecté, false sinon
     */
    public function isLoggedIn() {
        return !empty($_SESSION['user']);
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        session_unset();
        session_destroy();
    }

    /**
     * Redirige vers la page de login si l'utilisateur n'est pas connecté
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // Redirection vers la page de login
            header('Location: /~u22405372/SAE/Pronote/login/public/index.php');
            exit;
        }
    }
    
    /**
     * Vérifie si le mot de passe respecte les critères de complexité
     * - Entre 12 et 50 caractères
     * - Au moins une majuscule
     * - Au moins une minuscule
     * - Au moins un chiffre
     * - Au moins un caractère spécial
     * 
     * @param string $password Mot de passe à vérifier
     * @return bool True si le mot de passe est valide
     */
    public function validatePassword($password) {
        // Vérifier la longueur
        if (strlen($password) < 12 || strlen($password) > 50) {
            return false;
        }
        
        // Vérifier la présence d'une majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Vérifier la présence d'une minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Vérifier la présence d'un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Vérifier la présence d'un caractère spécial
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Change le mot de passe d'un utilisateur
     * 
     * @param string $password Nouveau mot de passe
     * @return bool True si le changement a réussi
     */
    public function changePassword($password) {
        if (!$this->isLoggedIn() || !$this->validatePassword($password)) {
            return false;
        }
        
        // Utiliser un coût plus élevé pour les mots de passe changés
        $options = ['cost' => 12];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, $options);
        
        $user = $_SESSION['user'];
        $table = $user['table'];
        
        $stmt = $this->pdo->prepare(
            "UPDATE `$table` SET mot_de_passe = :password WHERE id = :id"
        );
        
        $result = $stmt->execute([
            'password' => $hashedPassword,
            'id' => $user['id']
        ]);
        
        if ($result) {
            // Mettre à jour la session
            $_SESSION['user']['first_login'] = false;
            $_SESSION['user']['password_hash'] = $hashedPassword; // Mettre à jour le hash dans la session
            $this->isDefaultPassword = false;
        }
        
        return $result;
    }
    
    /**
     * Vérifie si les données de l'utilisateur sont toujours valides
     * 
     * @return bool True si les données sont valides, false si elles ont changé
     */
    public function validateUserData() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $user = $_SESSION['user'];
        $table = $user['table'];
        $id = $user['id'];
        
        // Vérifier si l'utilisateur existe toujours et si le mot de passe n'a pas changé
        $stmt = $this->pdo->prepare("SELECT mot_de_passe FROM `$table` WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $dbUser = $stmt->fetch();
        
        if (!$dbUser) {
            // L'utilisateur n'existe plus
            return false;
        }
        
        // Vérifier si le mot de passe a changé
        $storedPasswordHash = $user['password_hash'];
        $currentPasswordHash = $dbUser['mot_de_passe'];
        
        if ($storedPasswordHash !== $currentPasswordHash) {
            // Le mot de passe a été modifié ailleurs
            return false;
        }
        
        // Mettre à jour l'horodatage de la dernière vérification
        $_SESSION['user']['last_check'] = time();
        
        return true;
    }
    
    /**
     * Vérifie si l'utilisateur a un certain rôle
     * 
     * @param string|array $roles Un rôle ou un tableau de rôles
     * @return bool True si l'utilisateur a le rôle, false sinon
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['user']['profil'], $roles);
    }
}