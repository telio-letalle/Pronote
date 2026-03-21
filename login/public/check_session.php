<?php
/**
 * Validation de session - Vérifie si l'utilisateur est toujours valide
 * 
 * Ce script vérifie les informations de l'utilisateur connecté contre la base de données
 * Si les informations ont changé (mot de passe, etc.), la session est invalidée
 */

// Pour empêcher l'accès direct
define('INCLUDED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';

header('Content-Type: application/json');

// Initialisation de l'authentification
$auth = new Auth($pdo);

// Si l'utilisateur n'est pas connecté, retourner une réponse indiquant la déconnexion
if (!$auth->isLoggedIn()) {
    echo json_encode(['valid' => false, 'reason' => 'not_logged_in']);
    exit;
}

// Vérifier si les informations utilisateur sont toujours valides
$isValid = $auth->validateUserData();

if ($isValid) {
    // La session est valide
    echo json_encode(['valid' => true]);
} else {
    // La session n'est plus valide (informations modifiées)
    // On déconnecte l'utilisateur
    $auth->logout();
    echo json_encode(['valid' => false, 'reason' => 'data_changed']);
}