<?php

/**
 * Notification Class
 * 
 * Represents a notification in the CivicVoice system for managing
 * user notifications with read/unread status and delivery methods.
 */
class Notification {
    // Properties
    private $id;
    private $userId;
    private $title;
    private $body;
    private $isRead;
    private $createdAt;
    
    /**
     * Constructor
     * 
     * @param array $data Notification data array
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->body = $data['body'] ?? '';
        $this->isRead = $data['is_read'] ?? false;
        $this->createdAt = $data['created_at'] ?? null;
    }
    
    // Getters
    public function getId(): ?int {
        return $this->id;
    }
    
    public function getUserId(): ?int {
        return $this->userId;
    }
    
    public function getTitle(): string {
        return $this->title;
    }
    
    public function getBody(): string {
        return $this->body;
    }
    
    public function isRead(): bool {
        return $this->isRead;
    }
    
    public function getCreatedAt(): ?string {
        return $this->createdAt;
    }
    
    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }
    
    public function setUserId(int $userId): void {
        $this->userId = $userId;
    }
    
    public function setTitle(string $title): void {
        $this->title = trim($title);
    }
    
    public function setBody(string $body): void {
        $this->body = trim($body);
    }
    
    public function setRead(bool $isRead): void {
        $this->isRead = $isRead;
    }
    
    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
    }
    
    // Business methods
    
    /**
     * Mark notification as read
     */
    public function markAsRead(): void {
        $this->isRead = true;
    }
    
    /**
     * Mark notification as unread
     */
    public function markAsUnread(): void {
        $this->isRead = false;
    }
    
    /**
     * Check if notification is unread
     * 
     * @return bool True if notification is unread
     */
    public function isUnread(): bool {
        return !$this->isRead;
    }
    
    /**
     * Get notification age in hours
     * 
     * @return int Hours since creation
     */
    public function getAgeInHours(): int {
        if (!$this->createdAt) {
            return 0;
        }
        
        return (int) ((time() - strtotime($this->createdAt)) / 3600);
    }
    
    /**
     * Check if notification is fresh (less than 24 hours old)
     * 
     * @return bool True if notification is fresh
     */
    public function isFresh(): bool {
        return $this->getAgeInHours() < 24;
    }
    
    /**
     * Get formatted creation date
     * 
     * @param string $format Date format
     * @return string Formatted date
     */
    public function getFormattedCreatedAt(string $format = 'M j, Y g:i A'): string {
        if (!$this->createdAt) {
            return '';
        }
        return date($format, strtotime($this->createdAt));
    }
    
    /**
     * Get time since creation
     * 
     * @return string Human-readable time difference
     */
    public function getTimeAgo(): string {
        if (!$this->createdAt) {
            return '';
        }
        
        $time = time() - strtotime($this->createdAt);
        
        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return $this->getFormattedCreatedAt('M j, Y');
        }
    }
    
    /**
     * Get truncated title for display
     * 
     * @param int $maxLength Maximum length
     * @return string Truncated title
     */
    public function getTruncatedTitle(int $maxLength = 50): string {
        if (strlen($this->title) <= $maxLength) {
            return $this->title;
        }
        
        return substr($this->title, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Get truncated body for display
     * 
     * @param int $maxLength Maximum length
     * @return string Truncated body
     */
    public function getTruncatedBody(int $maxLength = 100): string {
        if (strlen($this->body) <= $maxLength) {
            return $this->body;
        }
        
        return substr($this->body, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Get CSS class for notification display
     * 
     * @return string CSS class name
     */
    public function getCssClass(): string {
        $classes = ['notification'];
        
        if ($this->isRead()) {
            $classes[] = 'notification-read';
        } else {
            $classes[] = 'notification-unread';
        }
        
        if ($this->isFresh()) {
            $classes[] = 'notification-fresh';
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Get notification icon based on content
     * 
     * @return string Icon character or HTML
     */
    public function getIcon(): string {
        $titleLower = strtolower($this->title);
        
        if (strpos($titleLower, 'report') !== false) {
            return '📋';
        } elseif (strpos($titleLower, 'status') !== false) {
            return '🔄';
        } elseif (strpos($titleLower, 'comment') !== false) {
            return '💬';
        } elseif (strpos($titleLower, 'fixed') !== false) {
            return '✅';
        } elseif (strpos($titleLower, 'rejected') !== false) {
            return '❌';
        } elseif (strpos($titleLower, 'welcome') !== false) {
            return '👋';
        } else {
            return '🔔';
        }
    }
    
    /**
     * Validate notification data
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        // User ID validation
        if (empty($this->userId)) {
            $errors[] = 'User ID is required';
        }
        
        // Title validation
        if (empty($this->title)) {
            $errors[] = 'Title is required';
        } elseif (strlen($this->title) > 255) {
            $errors[] = 'Title must not exceed 255 characters';
        }
        
        // Body validation (optional but has max length)
        if (strlen($this->body) > 1000) {
            $errors[] = 'Body must not exceed 1000 characters';
        }
        
        return $errors;
    }
    
    /**
     * Convert notification object to array
     * 
     * @return array Notification data as array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'body' => $this->body,
            'is_read' => $this->isRead,
            'created_at' => $this->createdAt
        ];
    }
    
    /**
     * Create Notification object from database row
     * 
     * @param array $row Database row data
     * @return Notification Notification object
     */
    public static function fromDatabaseRow(array $row): Notification {
        // Convert database boolean (0/1) to PHP boolean
        if (isset($row['is_read'])) {
            $row['is_read'] = (bool)$row['is_read'];
        }
        
        return new self($row);
    }
    
    /**
     * Get database-ready data for insert/update
     * 
     * @return array Database-ready data
     */
    public function toDatabaseArray(): array {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'body' => $this->body,
            'is_read' => $this->isRead ? 1 : 0
        ];
    }
    
    /**
     * Create a notification for report status change
     * 
     * @param int $userId User ID to notify
     * @param string $reportTitle Report title
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @return Notification New notification object
     */
    public static function createReportStatusNotification(
        int $userId, 
        string $reportTitle, 
        string $oldStatus, 
        string $newStatus
    ): Notification {
        $title = 'Report Status Updated';
        $body = sprintf(
            'Your report "%s" status has been changed from %s to %s.',
            $reportTitle,
            ucfirst($oldStatus),
            ucfirst($newStatus)
        );
        
        return new self([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_read' => false
        ]);
    }
    
    /**
     * Create a notification for new report submission
     * 
     * @param int $userId User ID to notify (authority)
     * @param string $reportTitle Report title
     * @param string $reporterName Reporter name
     * @return Notification New notification object
     */
    public static function createNewReportNotification(
        int $userId, 
        string $reportTitle, 
        string $reporterName
    ): Notification {
        $title = 'New Report Submitted';
        $body = sprintf(
            'A new report "%s" has been submitted by %s.',
            $reportTitle,
            $reporterName
        );
        
        return new self([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_read' => false
        ]);
    }
    
    /**
     * Create a notification for new comment
     * 
     * @param int $userId User ID to notify
     * @param string $reportTitle Report title
     * @param string $commenterName Commenter name
     * @return Notification New notification object
     */
    public static function createNewCommentNotification(
        int $userId, 
        string $reportTitle, 
        string $commenterName
    ): Notification {
        $title = 'New Comment Added';
        $body = sprintf(
            '%s commented on your report "%s".',
            $commenterName,
            $reportTitle
        );
        
        return new self([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_read' => false
        ]);
    }
    
    /**
     * Create a welcome notification for new users
     * 
     * @param int $userId User ID to notify
     * @param string $userName User name
     * @return Notification New notification object
     */
    public static function createWelcomeNotification(int $userId, string $userName): Notification {
        $title = 'Welcome to CivicVoice!';
        $body = sprintf(
            'Hello %s! Welcome to CivicVoice. Start reporting community issues to help improve your neighborhood.',
            $userName
        );
        
        return new self([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'is_read' => false
        ]);
    }
}
