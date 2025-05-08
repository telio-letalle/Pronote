<?php
// message_class.php - Fonctions pour la messagerie par classe

require_once 'functions.php';

/**
 * Récupère les membres d'une classe
 * @param string $classeId ID de la classe
 * @param bool $includeEleves Inclure les élèves
 * @param bool $includeParents Inclure les parents
 * @param bool $includeProfesseurs Inclure les professeurs
 * @return array Membres de la classe
 */
function getClassMembers($classeId, $includeEleves = true, $includeParents = false, $includeProfesseurs = false) {
    global $pdo;
    $members = [];
    
    // Récupérer les élèves de la classe
    if ($includeEleves) {
        $eleves = $pdo->prepare("
            SELECT id, 'eleve' as type, CONCAT(prenom, ' ', nom) as nom_complet 
            FROM eleves WHERE classe = ?
        ");
        $eleves->execute([$classeId]);
        $members = array_merge($members, $eleves->fetchAll());
    }
    
    // Récupérer les parents (dans la nouvelle DB, la relation parent-élève n'est pas explicite)
    if ($includeParents) {
        $parents = $pdo->prepare("
            SELECT id, 'parent' as type, CONCAT(prenom, ' ', nom) as nom_complet
            FROM parents WHERE est_parent_eleve = 'oui'
        ");
        $parents->execute();
        $members = array_merge($members, $parents->fetchAll());
    }
    
    // Récupérer les professeurs de la classe
    if ($includeProfesseurs) {
        $professeurs = $pdo->prepare("
            SELECT id, 'professeur' as type, CONCAT(prenom, ' ', nom) as nom_complet
            FROM professeurs 
            WHERE professeur_principal = ? OR professeur_principal = 'oui'
        ");
        $professeurs->execute([$classeId]);
        $members = array_merge($members, $professeurs->fetchAll());
    }
    
    return $members;
}

/**
 * Envoie un message à toute une classe
 * @param int $professeurId ID du professeur
 * @param string $classeId ID de la classe
 * @param string $titre Titre du message
 * @param string $contenu Contenu du message
 * @param string $importance Importance du message
 * @param bool $notificationObligatoire Si la notification est obligatoire
 * @param bool $includeParents Inclure les parents dans les destinataires
 * @param array $filesData Données des fichiers joints
 * @return int ID de la conversation créée
 */
function sendMessageToClass($professeurId, $classeId, $titre, $contenu, $importance = 'normal', 
                          $notificationObligatoire = false, $includeParents = false, $filesData = []) {
    
    // Récupérer les membres de la classe
    $members = getClassMembers($classeId, true, $includeParents, false);
    
    // Formater les participants pour createConversation
    $participants = [];
    foreach ($members as $member) {
        // Ne pas inclure le créateur dans les participants (il sera ajouté automatiquement)
        if ($member['id'] != $professeurId || $member['type'] != 'professeur') {
            $participants[] = ['id' => $member['id'], 'type' => $member['type']];
        }
    }
    
    // Créer la conversation
    $convId = createConversation($titre, 'classe', $professeurId, 'professeur', $participants);
    
    // Envoyer le message initial
    $messageId = addMessage(
        $convId, 
        $professeurId, 
        'professeur', 
        $contenu, 
        $importance, 
        false, // Est information
        $notificationObligatoire, 
        false, // Accusé de réception requis
        null, // Parent message ID
        'standard', // Type message
        $filesData
    );
    
    return $convId;
}