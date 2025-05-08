<?php
// functions.php - Fonctions adaptées pour la messagerie Pronote

// ==========================================
// FONCTIONS DE CONVERSATIONS
// ==========================================

/**
 * Récupère les conversations d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur (eleve, parent, professeur, etc.)
 * @param string $dossier Type de dossier (reception, envoyes, archives, etc.)
 * @return array Conversations de l'utilisateur
 */
function getConversations($userId, $userType, $dossier = 'reception') {
    global $pdo;
    
    // Requête de base pour récupérer les conversations
    $baseQuery = "
        SELECT DISTINCT c.id, c.subject as titre, 
               CASE WHEN EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce') THEN 'annonce' ELSE 'standard' END as type,
               c.created_at as date_creation, 
               c.updated_at as dernier_message,
               COUNT(CASE WHEN (cp.last_read_at IS NULL OR m.created_at > cp.last_read_at) AND m.sender_id != ? AND m.sender_type != ? THEN 1 END) as non_lus
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE cp.user_id = ? AND cp.user_type = ?
    ";
    
    // Paramètres initiaux
    $params = [$userId, $userType, $userId, $userType];
    
    // Conditions spécifiques à chaque dossier
    switch ($dossier) {
        case 'reception':
            // Messages reçus (non envoyés par l'utilisateur)
            $baseQuery .= " AND cp.is_deleted = 0 AND cp.is_archived = 0
                           AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND (sender_id != ? OR sender_type != ?))";
            $params[] = $userId;
            $params[] = $userType;
            break;
            
        case 'envoyes':
            // Messages envoyés par l'utilisateur
            $baseQuery .= " AND cp.is_deleted = 0 AND cp.is_archived = 0
                           AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND sender_id = ? AND sender_type = ?)";
            $params[] = $userId;
            $params[] = $userType;
            break;
            
        case 'archives':
            // Messages archivés
            $baseQuery .= " AND cp.is_archived = 1 AND cp.is_deleted = 0";
            break;
            
        case 'information':
            // Messages marqués comme annonce
            $baseQuery .= " AND cp.is_deleted = 0 
                           AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce')";
            break;
            
        case 'corbeille':
            // Messages dans la corbeille
            $baseQuery .= " AND cp.is_deleted = 1";
            break;
            
        default:
            // Par défaut, ne pas montrer les messages supprimés ou archivés
            $baseQuery .= " AND cp.is_deleted = 0 AND cp.is_archived = 0";
    }
    
    // Grouper et ordonner
    $baseQuery .= " GROUP BY c.id ORDER BY c.updated_at DESC";
    
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Crée une nouvelle conversation
 * @param string $titre Titre de la conversation
 * @param string $type Type de conversation (individuelle, groupe, information, etc.)
 * @param int $createurId ID du créateur
 * @param string $createurType Type du créateur
 * @param array $participants Liste des participants (format: [['id' => x, 'type' => y], ...])
 * @param bool $notificationObligatoire Si la notification est obligatoire
 * @return int ID de la conversation créée
 */
function createConversation($titre, $type, $createurId, $createurType, $participants, $notificationObligatoire = false) {
    global $pdo;
    
    $pdo->beginTransaction();
    try {
        // Créer la conversation
        $sql = "INSERT INTO conversations (subject, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$titre]);
        $convId = $pdo->lastInsertId();
        
        // Ajouter le créateur comme administrateur
        $sql = "INSERT INTO conversation_participants 
                (conversation_id, user_id, user_type, joined_at, is_admin) 
                VALUES (?, ?, ?, NOW(), 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $createurId, $createurType]);
        
        // Ajouter les autres participants
        $sql = "INSERT INTO conversation_participants 
                (conversation_id, user_id, user_type, joined_at) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        foreach ($participants as $p) {
            $stmt->execute([$convId, $p['id'], $p['type']]);
        }
        
        $pdo->commit();
        return $convId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Récupère les participants d'une conversation
 * @param int $convId ID de la conversation
 * @return array Participants de la conversation
 */
function getParticipants($convId) {
    global $pdo;
    $sql = "
        SELECT cp.id, cp.user_id as utilisateur_id, cp.user_type as utilisateur_type, 
               cp.is_admin as est_administrateur, cp.is_moderator as est_moderateur, 
               cp.is_deleted as a_quitte,
               CASE 
                   WHEN cp.user_type = 'eleve' THEN 
                       (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = cp.user_id)
                   WHEN cp.user_type = 'parent' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = cp.user_id)
                   WHEN cp.user_type = 'professeur' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = cp.user_id)
                   WHEN cp.user_type = 'vie_scolaire' THEN 
                       (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = cp.user_id)
                   WHEN cp.user_type = 'administrateur' THEN 
                       (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = cp.user_id)
                   ELSE 'Inconnu'
               END as nom_complet
        FROM conversation_participants cp
        WHERE cp.conversation_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$convId]);
    return $stmt->fetchAll();
}

/**
 * Vérifie si un utilisateur est modérateur dans une conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @param int $conversationId ID de la conversation
 * @return bool True si l'utilisateur est modérateur
 */
function isConversationModerator($userId, $userType, $conversationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id FROM conversation_participants
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? 
        AND (is_moderator = 1 OR is_admin = 1)
    ");
    $stmt->execute([$conversationId, $userId, $userType]);
    
    return $stmt->fetch() !== false;
}

/**
 * Vérifie si un utilisateur est le créateur d'une conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @param int $conversationId ID de la conversation
 * @return bool True si l'utilisateur est le créateur
 */
function isConversationCreator($userId, $userType, $conversationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id FROM conversation_participants
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_admin = 1
    ");
    $stmt->execute([$conversationId, $userId, $userType]);
    
    return $stmt->fetch() !== false;
}

/**
 * Promouvoir un participant au statut de modérateur
 * @param int $participantId ID du participant
 * @param int $promoterId ID du promoteur
 * @param string $promoterType Type du promoteur
 * @param int $conversationId ID de la conversation
 * @return bool True si succès
 */
function promoteToModerator($participantId, $promoterId, $promoterType, $conversationId) {
    global $pdo;
    
    // Vérifier que le promoteur est admin de la conversation
    if (!isConversationCreator($promoterId, $promoterType, $conversationId)) {
        throw new Exception("Vous n'êtes pas autorisé à promouvoir des modérateurs");
    }
    
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_moderator = 1 
        WHERE id = ? AND conversation_id = ?
    ");
    $stmt->execute([$participantId, $conversationId]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Ajouter un participant à une conversation
 * @param int $conversationId ID de la conversation
 * @param int $userId ID de l'utilisateur à ajouter
 * @param string $userType Type d'utilisateur à ajouter
 * @param int $adderId ID de l'utilisateur qui ajoute
 * @param string $adderType Type d'utilisateur qui ajoute
 * @return bool True si succès
 */
function addParticipantToConversation($conversationId, $userId, $userType, $adderId, $adderType) {
    global $pdo;
    
    // Vérifier que l'ajouteur est participant à la conversation
    if (!isConversationModerator($adderId, $adderType, $conversationId)) {
        throw new Exception("Vous n'êtes pas autorisé à ajouter des participants");
    }
    
    // Vérifier que l'utilisateur n'est pas déjà participant
    $check = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $check->execute([$conversationId, $userId, $userType]);
    
    if ($check->fetch()) {
        throw new Exception("Cet utilisateur est déjà participant à la conversation");
    }
    
    // Ajouter le participant
    $add = $pdo->prepare("
        INSERT INTO conversation_participants 
        (conversation_id, user_id, user_type, joined_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $add->execute([$conversationId, $userId, $userType]);
    
    return $add->rowCount() > 0;
}

/**
 * Archiver une conversation pour un utilisateur
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return bool True si succès
 */
function archiveConversation($convId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_archived = 1 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprimer une conversation pour un utilisateur
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return bool True si succès
 */
function deleteConversation($convId, $userId, $userType) {
    global $pdo;
    
    // Marquer les notifications comme lues d'abord
    $stmt = $pdo->prepare("
        UPDATE message_notifications AS mn
        JOIN messages AS m ON mn.message_id = m.id
        SET mn.is_read = 1
        WHERE m.conversation_id = ? AND mn.user_id = ? AND mn.user_type = ? AND mn.is_read = 0
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    // Mettre à jour la dernière lecture pour tous les messages
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET last_read_at = NOW() 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    // Maintenant marquer comme supprimé
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_deleted = 1 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

// ==========================================
// FONCTIONS DE MESSAGES
// ==========================================

/**
 * Récupère les messages d'une conversation
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @return array Messages de la conversation
 */
function getMessages($convId, $userId, $userType) {
    global $pdo;
    
    // Vérifier que l'utilisateur est participant à la conversation
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkParticipant->execute([$convId, $userId, $userType]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
    }
    
    // Récupérer tous les messages de la conversation
    $sql = "
        SELECT m.*, 
               CASE 
                   WHEN cp.last_read_at IS NULL OR m.created_at > cp.last_read_at THEN 0
                   ELSE 1
               END as est_lu,
               CASE 
                   WHEN m.sender_type = 'eleve' THEN 
                       (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = m.sender_id)
                   WHEN m.sender_type = 'parent' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'professeur' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'vie_scolaire' THEN 
                       (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = m.sender_id)
                   WHEN m.sender_type = 'administrateur' THEN 
                       (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = m.sender_id)
                   ELSE 'Inconnu'
               END as expediteur_nom,
               m.sender_id as expediteur_id, 
               m.sender_type as expediteur_type,
               m.body as contenu,
               COALESCE(m.status, 'normal') as status,
               m.created_at as date_envoi
        FROM messages m
        LEFT JOIN conversation_participants cp ON (
            m.conversation_id = cp.conversation_id AND 
            cp.user_id = ? AND 
            cp.user_type = ?
        )
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userType, $convId]);
    $messages = $stmt->fetchAll();

    // Récupérer les pièces jointes pour chaque message
    $attachmentStmt = $pdo->prepare("
        SELECT id, message_id, file_name as nom_fichier, file_path as chemin
        FROM message_attachments 
        WHERE message_id = ?
    ");
    
    foreach ($messages as &$message) {
        $attachmentStmt->execute([$message['id']]);
        $message['pieces_jointes'] = $attachmentStmt->fetchAll();
        
        // Marquer comme lu si pas encore lu
        if (!$message['est_lu']) {
            markMessageAsRead($message['id'], $userId, $userType);
        }
    }

    return $messages;
}

/**
 * Ajoute un nouveau message
 * @param int $convId ID de la conversation
 * @param int $senderId ID de l'expéditeur
 * @param string $senderType Type d'expéditeur
 * @param string $content Contenu du message
 * @param string $importance Importance du message (normal, important, urgent)
 * @param bool $estAnnonce Si c'est une annonce
 * @param bool $notificationObligatoire Si la notification est obligatoire
 * @param int|null $parentMessageId ID du message parent (pour les réponses)
 * @param string $typeMessage Type de message (standard, absence, punition, etc.)
 * @param array $filesData Données des fichiers joints (format: $_FILES)
 * @return int ID du message créé
 */
function addMessage($convId, $senderId, $senderType, $content, $importance = 'normal', 
                   $estAnnonce = false, $notificationObligatoire = false, 
                   $parentMessageId = null, $typeMessage = 'standard', $filesData = []) {
    global $pdo;
    
    // Vérifier que l'expéditeur est participant à la conversation
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkParticipant->execute([$convId, $senderId, $senderType]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à envoyer des messages dans cette conversation");
    }
    
    $pdo->beginTransaction();
    try {
        // Déterminer le statut du message
        $status = $estAnnonce ? 'annonce' : $importance;
        
        // Insérer le message
        $sql = "INSERT INTO messages (conversation_id, sender_id, sender_type, body, created_at, updated_at, status) 
                VALUES (?, ?, ?, ?, NOW(), NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $senderId, $senderType, $content, $status]);
        $messageId = $pdo->lastInsertId();
        
        // Mettre à jour la date du dernier message
        $upd = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $upd->execute([$convId]);
        
        // Récupérer les participants
        $participantsStmt = $pdo->prepare("
            SELECT user_id, user_type FROM conversation_participants 
            WHERE conversation_id = ? AND is_deleted = 0
        ");
        $participantsStmt->execute([$convId]);
        $participants = $participantsStmt->fetchAll();
        
        // Créer des notifications
        $notificationType = $estAnnonce ? 'broadcast' : 'unread';
        $addNotification = $pdo->prepare("
            INSERT INTO message_notifications (user_id, user_type, message_id, notification_type) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($participants as $p) {
            if ($p['user_id'] != $senderId || $p['user_type'] != $senderType) {
                $addNotification->execute([
                    $p['user_id'], 
                    $p['user_type'], 
                    $messageId, 
                    $notificationType
                ]);
            }
        }
        
        // Traiter les pièces jointes
        if (!empty($filesData) && isset($filesData['name']) && is_array($filesData['name'])) {
            // Utiliser un répertoire qui existe déjà avec les bonnes permissions
            $uploadDir = './uploads/';
            
            // Créer le répertoire si nécessaire, avec gestion des erreurs
            if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
                // Si on ne peut pas créer le répertoire, utiliser le répertoire temporaire
                $uploadDir = sys_get_temp_dir() . '/';
            }
            
            foreach ($filesData['name'] as $key => $name) {
                if ($filesData['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $filesData['tmp_name'][$key];
                    $filename = uniqid() . '_' . basename($name);
                    $filePath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $filePath)) {
                        $addFile = $pdo->prepare("
                            INSERT INTO message_attachments (message_id, file_name, file_path, uploaded_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $addFile->execute([$messageId, $name, $filePath]);
                    }
                }
            }
        }
        
        $pdo->commit();
        return $messageId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Marque un message comme lu
 * @param int $messageId ID du message
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @return bool True si succès
 */
function markMessageAsRead($messageId, $userId, $userType) {
    global $pdo;
    
    // Récupérer l'ID de la conversation pour ce message
    $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $result = $stmt->fetch();
    
    if ($result) {
        $convId = $result['conversation_id'];
        
        // Mettre à jour la date de dernière lecture du participant
        $updateStmt = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_at = NOW() 
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        $updateStmt->execute([$convId, $userId, $userType]);
        
        // Marquer les notifications comme lues
        $updNotif = $pdo->prepare("
            UPDATE message_notifications 
            SET is_read = 1 
            WHERE message_id = ? AND user_id = ? AND user_type = ?
        ");
        $updNotif->execute([$messageId, $userId, $userType]);
    }
    
    return true;
}

/**
 * Marque un message comme non lu
 * @param int $messageId ID du message
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @return bool True si succès
 */
function markMessageAsUnread($messageId, $userId, $userType) {
    global $pdo;
    
    // Récupérer l'ID de la conversation pour ce message
    $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $result = $stmt->fetch();
    
    if ($result) {
        $convId = $result['conversation_id'];
        
        // Marquer la notification comme non lue
        $updNotif = $pdo->prepare("
            UPDATE message_notifications 
            SET is_read = 0 
            WHERE message_id = ? AND user_id = ? AND user_type = ?
        ");
        $updNotif->execute([$messageId, $userId, $userType]);
        
        // Mettre à jour la date de dernière lecture pour être nulle
        $updateStmt = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_at = NULL 
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        $updateStmt->execute([$convId, $userId, $userType]);
    }
    
    return true;
}

/**
 * Récupère les notifications non lues d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @return array Notifications non lues
 */
function getUnreadNotifications($userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT n.*, 
               m.body as contenu, m.sender_id as expediteur_id, m.sender_type as expediteur_type,
               c.subject as conversation_titre,
               CASE 
                   WHEN m.sender_type = 'eleve' THEN 
                       (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = m.sender_id)
                   WHEN m.sender_type = 'parent' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'professeur' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'vie_scolaire' THEN 
                       (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = m.sender_id)
                   WHEN m.sender_type = 'administrateur' THEN 
                       (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = m.sender_id)
                   ELSE 'Inconnu'
               END as expediteur_nom,
               notified_at as date_creation
        FROM message_notifications n
        JOIN messages m ON n.message_id = m.id
        JOIN conversations c ON m.conversation_id = c.id
        WHERE n.user_id = ? AND n.user_type = ? AND n.is_read = 0
        ORDER BY n.notified_at DESC
    ");
    $stmt->execute([$userId, $userType]);
    
    return $stmt->fetchAll();
}

// ==========================================
// FONCTIONS POUR LES CLASSES
// ==========================================

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
        false, // Est annonce = false pour messages de classe
        $notificationObligatoire, 
        null, // Parent message ID
        'standard', // Type message
        $filesData
    );
    
    return $convId;
}

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================

/**
 * Vérifie si un utilisateur est l'utilisateur courant
 * @param int $id ID de l'utilisateur à vérifier
 * @param string $type Type de l'utilisateur à vérifier
 * @param array $user Informations de l'utilisateur courant
 * @return bool True si c'est l'utilisateur courant
 */
function isCurrentUser($id, $type, $user) {
    return $id == $user['id'] && $type == $user['type'];
}

/**
 * Fonction utilitaire pour formater une date 
 * @param string $date Date à formater
 * @return string Date formatée
 */
function formatDate($date) {
    if (!$date) return 'Jamais';
    
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'À l\'instant';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
    } elseif ($diff < 172800) {
        return 'Hier à ' . date('H:i', $timestamp);
    } else {
        return date('d/m/Y à H:i', $timestamp);
    }
}

/**
 * Retourne l'icône correspondante au type de participant
 * @param string $type Type de participant
 * @return string Nom de l'icône
 */
function getParticipantIcon($type) {
    $icons = [
        'eleve' => 'graduate',
        'parent' => 'users',
        'professeur' => 'tie',
        'vie_scolaire' => 'clipboard',
        'administrateur' => 'cog'
    ];
    return $icons[$type] ?? 'user';
}

/**
 * Renvoie le libellé du type de participant
 * @param string $type Type de participant
 * @return string Libellé du type
 */
function getParticipantType($type) {
    $types = [
        'eleve' => 'Élève',
        'parent' => 'Parent',
        'professeur' => 'Professeur',
        'vie_scolaire' => 'Vie scolaire',
        'administrateur' => 'Administrateur'
    ];
    return $types[$type] ?? ucfirst($type);
}

/**
 * Retourne l'icône pour un dossier
 * @param string $folder Nom du dossier
 * @return string Nom de l'icône
 */
function getFolderIcon($folder) {
    $icons = [
        'reception' => 'inbox',
        'envoyes' => 'paper-plane',
        'archives' => 'archive',
        'information' => 'info-circle',
        'corbeille' => 'trash'
    ];
    return $icons[$folder] ?? 'folder';
}

/**
 * Retourne l'icône pour une conversation
 * @param string $type Type de conversation
 * @return string Nom de l'icône
 */
function getConversationIcon($type) {
    $icons = [
        'individuelle' => 'comment',
        'groupe' => 'users',
        'information' => 'info-circle',
        'annonce' => 'bullhorn',
        'sondage' => 'poll-h',
        'classe' => 'graduation-cap',
        'standard' => 'comment'
    ];
    return $icons[$type] ?? 'comment';
}

/**
 * Retourne le type de conversation
 * @param string $type Type de conversation
 * @return string Libellé du type
 */
function getConversationType($type) {
    $types = [
        'individuelle' => 'Message individuel',
        'groupe' => 'Conversation de groupe',
        'information' => 'Information',
        'annonce' => 'Annonce importante',
        'sondage' => 'Sondage',
        'classe' => 'Message à la classe',
        'standard' => 'Message standard'
    ];
    return $types[$type] ?? ucfirst($type);
}

/**
 * Retourne le libellé d'un statut de message
 * @param string $status Statut du message
 * @return string Libellé du statut
 */
function getMessageStatusLabel($status) {
    $statuses = [
        'normal' => 'Message normal',
        'important' => 'Message important',
        'urgent' => 'Message urgent',
        'annonce' => 'Annonce importante'
    ];
    return $statuses[$status] ?? 'Message normal';
}

/**
 * Restaure une conversation depuis la corbeille
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return bool True si succès
 */
function restoreConversation($convId, $userId, $userType) {
    global $pdo;
    
    // Vérifier si le participant existe déjà avec is_deleted = 0
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkStmt->execute([$convId, $userId, $userType]);
    $exists = $checkStmt->fetchColumn() > 0;
    
    if ($exists) {
        // Si déjà actif, ne rien faire
        return true;
    }
    
    // Sinon, mettre à jour le statut existant
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_deleted = 0, is_archived = 0 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprime définitivement une conversation pour un utilisateur
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return bool True si succès
 */
function deletePermanently($convId, $userId, $userType) {
    global $pdo;
    
    // Supprimer le participant
    $stmt = $pdo->prepare("
        DELETE FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprime définitivement plusieurs conversations pour un utilisateur
 * @param array $convIds Tableau des IDs de conversations
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return int Nombre de conversations supprimées
 */
function deleteMultipleConversations($convIds, $userId, $userType) {
    global $pdo;
    
    if (empty($convIds)) {
        return 0;
    }
    
    // Créer les placeholders pour la requête préparée
    $placeholders = implode(',', array_fill(0, count($convIds), '?'));
    
    // Supprimer les participants
    $stmt = $pdo->prepare("
        DELETE FROM conversation_participants 
        WHERE conversation_id IN ($placeholders) AND user_id = ? AND user_type = ?
    ");
    
    // Ajouter user_id et user_type à la fin des paramètres
    $params = array_merge($convIds, [$userId, $userType]);
    $stmt->execute($params);
    
    return $stmt->rowCount();
}

/**
 * Rétrograder un modérateur au statut normal
 * @param int $participantId ID du participant
 * @param int $demoterId ID du déclasseur
 * @param string $demoterType Type du déclasseur
 * @param int $conversationId ID de la conversation
 * @return bool True si succès
 */
function demoteFromModerator($participantId, $demoterId, $demoterType, $conversationId) {
    global $pdo;
    
    // Vérifier que la personne qui rétrograde est admin de la conversation
    if (!isConversationCreator($demoterId, $demoterType, $conversationId)) {
        throw new Exception("Vous n'êtes pas autorisé à rétrograder des modérateurs");
    }
    
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_moderator = 0 
        WHERE id = ? AND conversation_id = ?
    ");
    $stmt->execute([$participantId, $conversationId]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprime un participant d'une conversation
 * @param int $participantId ID du participant à supprimer
 * @param int $removerId ID de celui qui supprime
 * @param string $removerType Type de celui qui supprime
 * @param int $conversationId ID de la conversation
 * @return bool True si succès
 */
function removeParticipant($participantId, $removerId, $removerType, $conversationId) {
    global $pdo;
    
    // Vérifier que la personne qui supprime est admin ou modérateur
    if (!isConversationModerator($removerId, $removerType, $conversationId)) {
        throw new Exception("Vous n'êtes pas autorisé à supprimer des participants");
    }
    
    // Récupérer les informations du participant à supprimer
    $stmt = $pdo->prepare("
        SELECT user_id, user_type FROM conversation_participants
        WHERE id = ? AND conversation_id = ?
    ");
    $stmt->execute([$participantId, $conversationId]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        throw new Exception("Participant introuvable");
    }
    
    // Vérifier qu'on ne supprime pas un admin (sauf si on est admin soi-même)
    $checkAdmin = $pdo->prepare("
        SELECT is_admin FROM conversation_participants
        WHERE id = ? AND is_admin = 1
    ");
    $checkAdmin->execute([$participantId]);
    $isAdmin = $checkAdmin->fetch();
    
    if ($isAdmin && !isConversationCreator($removerId, $removerType, $conversationId)) {
        throw new Exception("Vous ne pouvez pas supprimer l'administrateur de la conversation");
    }
    
    // Marquer comme supprimé pour l'utilisateur
    $deleteForUser = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_deleted = 1 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $deleteForUser->execute([$conversationId, $participant['user_id'], $participant['user_type']]);
    
    return true;
}

/**
 * Vérifie si un utilisateur peut répondre à une annonce
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @param int $conversationId ID de la conversation
 * @param string $convType Type de la conversation
 * @return bool True si l'utilisateur peut répondre
 */
function canReplyToAnnouncement($userId, $userType, $conversationId, $convType) {
    // Si ce n'est pas une annonce, tout le monde peut répondre
    if ($convType !== 'annonce') {
        return true;
    }
    
    // Si c'est un élève, il ne peut pas répondre aux annonces sauf s'il est modérateur
    if ($userType === 'eleve' && !isConversationModerator($userId, $userType, $conversationId)) {
        return false;
    }
    
    // Les administrateurs et modérateurs peuvent répondre
    if (isConversationModerator($userId, $userType, $conversationId)) {
        return true;
    }
    
    // Pour les autres types d'utilisateurs (professeurs, parents, vie scolaire)
    // Ils peuvent répondre si promus modérateur, sinon lecture seule
    return isConversationModerator($userId, $userType, $conversationId);
}

/**
 * Vérifie si un utilisateur peut définir une importance pour un message
 * @param string $userType Type d'utilisateur
 * @return bool True si l'utilisateur peut définir une importance
 */
function canSetMessageImportance($userType) {
    // Seuls les parents, professeurs, vie scolaire et administrateurs peuvent définir des priorités
    return in_array($userType, ['parent', 'professeur', 'vie_scolaire', 'administrateur']);
}

/**
 * Récupère les informations d'une conversation
 * @param int $convId ID de la conversation
 * @return array Informations de la conversation
 */
function getConversationInfo($convId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.subject as titre, 
        CASE 
            WHEN EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce') THEN 'annonce'
            ELSE 'standard'
        END as type
        FROM conversations c
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();
    
    return $conv;
}

/**
 * Récupère les informations d'un participant dans une conversation
 * @param int $convId ID de la conversation
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type de l'utilisateur
 * @return array|false Informations du participant ou false
 */
function getParticipantInfo($convId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM conversation_participants
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->fetch();
}