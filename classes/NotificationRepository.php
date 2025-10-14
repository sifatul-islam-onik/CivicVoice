<?php

require_once __DIR__ . '/Notification.php';

/**
 * NotificationRepository Class
 * 
 * Handles database operations for Notification entities following the Repository pattern.
 * Provides data access layer abstraction for notification-related operations.
 */
class NotificationRepository {
    private $pdo;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Find notification by ID
     * 
     * @param int $id Notification ID
     * @return Notification|null Notification object or null if not found
     */
    public function findById(int $id): ?Notification {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $notificationData = $stmt->fetch();
        
        return $notificationData ? Notification::fromDatabaseRow($notificationData) : null;
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of notifications to retrieve
     * @param int $offset Offset for pagination
     * @param bool $unreadOnly Whether to get only unread notifications
     * @return array Array of Notification objects
     */
    public function findByUserId(int $userId, int $limit = 50, int $offset = 0, bool $unreadOnly = false): array {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = [];
        
        while ($notificationData = $stmt->fetch()) {
            $notifications[] = Notification::fromDatabaseRow($notificationData);
        }
        
        return $notifications;
    }
    
    /**
     * Get unread notifications for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of notifications to retrieve
     * @return array Array of Notification objects
     */
    public function getUnreadNotifications(int $userId, int $limit = 10): array {
        return $this->findByUserId($userId, $limit, 0, true);
    }
    
    /**
     * Get all notifications for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of notifications to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Notification objects
     */
    public function getAllNotifications(int $userId, int $limit = 50, int $offset = 0): array {
        return $this->findByUserId($userId, $limit, $offset, false);
    }
    
    /**
     * Count unread notifications for a user
     * 
     * @param int $userId User ID
     * @return int Number of unread notifications
     */
    public function countUnreadForUser(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Count total notifications for a user
     * 
     * @param int $userId User ID
     * @return int Total number of notifications
     */
    public function countForUser(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Save notification (insert or update)
     * 
     * @param Notification $notification Notification object
     * @return Notification Updated notification object with ID
     */
    public function save(Notification $notification): Notification {
        if ($notification->getId()) {
            return $this->update($notification);
        } else {
            return $this->insert($notification);
        }
    }
    
    /**
     * Insert new notification
     * 
     * @param Notification $notification Notification object
     * @return Notification Notification object with new ID
     */
    public function insert(Notification $notification): Notification {
        $data = $notification->toDatabaseArray();
        
        $sql = "INSERT INTO notifications (user_id, title, body, is_read, created_at) 
                VALUES (:user_id, :title, :body, :is_read, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $notification->setId((int)$this->pdo->lastInsertId());
        $notification->setCreatedAt(date('Y-m-d H:i:s'));
        
        return $notification;
    }
    
    /**
     * Update existing notification
     * 
     * @param Notification $notification Notification object
     * @return Notification Updated notification object
     */
    public function update(Notification $notification): Notification {
        if (!$notification->getId()) {
            throw new InvalidArgumentException('Cannot update notification without ID');
        }
        
        $data = $notification->toDatabaseArray();
        $notificationId = $notification->getId();
        
        $sql = "UPDATE notifications SET user_id = :user_id, title = :title, body = :body, is_read = :is_read WHERE id = :id";
        
        $data['id'] = $notificationId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $notification;
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $id Notification ID
     * @return bool True if marked as read successfully
     */
    public function markAsRead(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Mark notification as unread
     * 
     * @param int $id Notification ID
     * @return bool True if marked as unread successfully
     */
    public function markAsUnread(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsReadForUser(int $userId): int {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
    
    /**
     * Delete notification by ID
     * 
     * @param int $id Notification ID
     * @return bool True if deleted successfully
     */
    public function deleteById(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Delete notification
     * 
     * @param Notification $notification Notification object
     * @return bool True if deleted successfully
     */
    public function delete(Notification $notification): bool {
        if (!$notification->getId()) {
            return false;
        }
        
        return $this->deleteById($notification->getId());
    }
    
    /**
     * Delete all notifications for a user
     * 
     * @param int $userId User ID
     * @return int Number of notifications deleted
     */
    public function deleteAllForUser(int $userId): int {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
    
    /**
     * Delete old notifications
     * 
     * @param int $daysOld Number of days old to consider for deletion
     * @return int Number of notifications deleted
     */
    public function deleteOldNotifications(int $daysOld = 90): int {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }
    
    /**
     * Create and save a notification
     * 
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @return Notification Created notification object
     */
    public function create(int $userId, string $title, string $body): Notification {
        $notification = new Notification([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_read' => false
        ]);
        
        return $this->insert($notification);
    }
    
    /**
     * Create notification for report status change
     * 
     * @param int $userId User ID to notify
     * @param string $reportTitle Report title
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return Notification Created notification object
     */
    public function createReportStatusNotification(
        int $userId, 
        string $reportTitle, 
        string $oldStatus, 
        string $newStatus
    ): Notification {
        $notification = Notification::createReportStatusNotification($userId, $reportTitle, $oldStatus, $newStatus);
        return $this->insert($notification);
    }
    
    /**
     * Create notification for new report
     * 
     * @param int $userId User ID to notify (authority)
     * @param string $reportTitle Report title
     * @param string $reporterName Reporter name
     * @return Notification Created notification object
     */
    public function createNewReportNotification(
        int $userId, 
        string $reportTitle, 
        string $reporterName
    ): Notification {
        $notification = Notification::createNewReportNotification($userId, $reportTitle, $reporterName);
        return $this->insert($notification);
    }
    
    /**
     * Create notification for new comment
     * 
     * @param int $userId User ID to notify
     * @param string $reportTitle Report title
     * @param string $commenterName Commenter name
     * @return Notification Created notification object
     */
    public function createNewCommentNotification(
        int $userId, 
        string $reportTitle, 
        string $commenterName
    ): Notification {
        $notification = Notification::createNewCommentNotification($userId, $reportTitle, $commenterName);
        return $this->insert($notification);
    }
    
    /**
     * Create welcome notification for new user
     * 
     * @param int $userId User ID
     * @param string $userName User name
     * @return Notification Created notification object
     */
    public function createWelcomeNotification(int $userId, string $userName): Notification {
        $notification = Notification::createWelcomeNotification($userId, $userName);
        return $this->insert($notification);
    }
    
    /**
     * Notify all authorities about a new report
     * 
     * @param array $authorityIds Array of authority user IDs
     * @param string $reportTitle Report title
     * @param string $reporterName Reporter name
     * @return array Array of created Notification objects
     */
    public function notifyAuthoritiesNewReport(array $authorityIds, string $reportTitle, string $reporterName): array {
        $notifications = [];
        
        foreach ($authorityIds as $authorityId) {
            $notifications[] = $this->createNewReportNotification($authorityId, $reportTitle, $reporterName);
        }
        
        return $notifications;
    }
    
    /**
     * Get notification statistics
     * 
     * @return array Notification statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total notifications
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        $stats['total_notifications'] = $stmt->fetchColumn();
        
        // Unread notifications
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        $stats['unread_notifications'] = $stmt->fetchColumn();
        
        // Recent notifications (last 24 hours)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['recent_notifications'] = $stmt->fetchColumn();
        
        // Notifications by user (top 10 most notified users)
        $stmt = $this->pdo->query("
            SELECT u.full_name, COUNT(*) as notification_count 
            FROM notifications n 
            JOIN users u ON n.user_id = u.id 
            GROUP BY n.user_id, u.full_name 
            ORDER BY notification_count DESC 
            LIMIT 10
        ");
        $stats['top_notified_users'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Search notifications
     * 
     * @param array $criteria Search criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of Notification objects
     */
    public function search(array $criteria, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT n.*, u.full_name, u.email 
                FROM notifications n 
                JOIN users u ON n.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // Search by title or body
        if (!empty($criteria['query'])) {
            $sql .= " AND (n.title LIKE ? OR n.body LIKE ?)";
            $searchTerm = '%' . $criteria['query'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by user
        if (!empty($criteria['user_id'])) {
            $sql .= " AND n.user_id = ?";
            $params[] = $criteria['user_id'];
        }
        
        // Filter by read status
        if (isset($criteria['is_read'])) {
            $sql .= " AND n.is_read = ?";
            $params[] = $criteria['is_read'] ? 1 : 0;
        }
        
        // Filter by date range
        if (!empty($criteria['date_from'])) {
            $sql .= " AND n.created_at >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND n.created_at <= ?";
            $params[] = $criteria['date_to'];
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = [];
        
        while ($notificationData = $stmt->fetch()) {
            $notifications[] = Notification::fromDatabaseRow($notificationData);
        }
        
        return $notifications;
    }
    
    /**
     * Bulk mark notifications as read
     * 
     * @param array $notificationIds Array of notification IDs
     * @return int Number of notifications marked as read
     */
    public function bulkMarkAsRead(array $notificationIds): int {
        if (empty($notificationIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $sql = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($notificationIds);
        return $stmt->rowCount();
    }
    
    /**
     * Bulk delete notifications
     * 
     * @param array $notificationIds Array of notification IDs
     * @return int Number of notifications deleted
     */
    public function bulkDelete(array $notificationIds): int {
        if (empty($notificationIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $sql = "DELETE FROM notifications WHERE id IN ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($notificationIds);
        return $stmt->rowCount();
    }
}