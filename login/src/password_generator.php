<?php
// src/password_generator.php

/**
 * Classe de génération de mots de passe aléatoires
 * Génère des mots de passe sécurisés et conformes aux exigences
 */
class PasswordGenerator {
    /**
     * Génère un mot de passe aléatoire sécurisé
     * 
     * @param int $length Longueur du mot de passe (par défaut 12 caractères)
     * @return string Mot de passe généré
     */
    public static function generate($length = 12) {
        // S'assurer que la longueur est suffisante
        if ($length < 12) {
            $length = 12;
        }
        
        // Définir les ensembles de caractères
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Sans I, O pour éviter la confusion
        $lowercase = 'abcdefghijkmnopqrstuvwxyz'; // Sans l pour éviter la confusion
        $numbers = '23456789'; // Sans 0, 1 pour éviter la confusion
        $special = '!@#$%^&*_-+=';
        
        // Générer au moins un caractère de chaque type
        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $special[rand(0, strlen($special) - 1)];
        
        // Générer le reste des caractères aléatoirement
        $allChars = $uppercase . $lowercase . $numbers . $special;
        $remainingLength = $length - 4;
        
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }
        
        // Mélanger les caractères pour éviter un schéma prévisible
        return str_shuffle($password);
    }
}