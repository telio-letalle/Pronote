/**
 * /assets/js/notifications.js - Gestion des notifications
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les notifications
    initNotifications();
});

/**
 * Initialise les notifications
 */
function initNotifications() {
    // Vérifier les nouvelles notifications toutes les 60 secondes
    setInterval(checkNotifications, 60000);
    
    // Gérer les clics sur les notifications
    initNotificationClicks();
}

/**
 * Vérifie les nouvelles notifications
 */
function checkNotifications() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
                
                // Si l'utilisateur a activé les notifications du navigateur et qu'il y a de nouvelles notifications
                if (data.count > 0 && hasNotificationPermission()) {
                    showBrowserNotification(data.count, data.latest);
                }
            }
        })
        .catch(error => console.error('Erreur lors de la vérification des notifications:', error));
}

/**
 * Met à jour le badge de notification
 * @param {number} count - Nombre de notifications non lues
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    
    if (count > 0) {
        // Créer un badge s'il n'existe pas
        if (!badge) {
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge';
                newBadge.textContent = count;
                userInfo.appendChild(newBadge);
            }
        } else {
            // Mettre à jour le badge existant
            badge.textContent = count;
            badge.style.display = 'flex';
        }
    } else if (badge) {
        // Masquer le badge s'il n'y a pas de notification
        badge.style.display = 'none';
    }
}

/**
 * Initialise les clics sur les notifications
 */
function initNotificationClicks() {
    const notificationItems = document.querySelectorAll('.notification-item');
    
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            const conversationId = this.dataset.conversationId;
            
            // Marquer comme lu
            markNotificationRead(notificationId);
            
            // Rediriger vers la conversation
            window.location.href = `conversation.php?id=${conversationId}`;
        });
    });
}

/**
 * Marque une notification comme lue
 * @param {number} notificationId - ID de la notification
 */
function markNotificationRead(notificationId) {
    fetch(`mark_notification_read.php?id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'interface utilisateur
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.add('read');
                }
            }
        })
        .catch(error => console.error('Erreur lors du marquage de la notification:', error));
}

/**
 * Vérifie si les notifications du navigateur sont autorisées
 * @returns {boolean} True si les notifications sont autorisées
 */
function hasNotificationPermission() {
    return window.Notification && Notification.permission === 'granted';
}

/**
 * Demande l'autorisation pour les notifications du navigateur
 */
function requestNotificationPermission() {
    if (window.Notification && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
}

/**
 * Affiche une notification dans le navigateur
 * @param {number} count - Nombre de notifications
 * @param {Object} latestNotification - Dernière notification
 */
function showBrowserNotification(count, latestNotification) {
    if (!window.Notification || Notification.permission !== 'granted') {
        return;
    }
    
    const title = 'Pronote Messagerie';
    const options = {
        body: count === 1 
            ? `Nouveau message de ${latestNotification.expediteur_nom}`
            : `${count} nouveaux messages non lus`,
        icon: '/assets/images/pronote-icon.png'
    };
    
    const notification = new Notification(title, options);
    
    // Rediriger vers la conversation au clic sur la notification
    notification.onclick = function() {
        window.focus();
        if (latestNotification && latestNotification.conversation_id) {
            window.location.href = `conversation.php?id=${latestNotification.conversation_id}`;
        } else {
            window.location.href = 'index.php';
        }
    };
}