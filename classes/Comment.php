<?php

/**
 * Comment Class
 * 
 * Represents a comment on a report in the CivicVoice system for handling
 * user comments on reports with validation and moderation.
 */
class Comment {
    // Properties
    private $id;
    private $reportId;
    private $userId;
    private $commentText;
    private $createdAt;
    
    /**
     * Constructor
     * 
     * @param array $data Comment data array
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->reportId = $data['report_id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->commentText = $data['comment_text'] ?? '';
        $this->createdAt = $data['created_at'] ?? null;
    }
    
    // Getters
    public function getId(): ?int {
        return $this->id;
    }
    
    public function getReportId(): ?int {
        return $this->reportId;
    }
    
    public function getUserId(): ?int {
        return $this->userId;
    }
    
    public function getCommentText(): string {
        return $this->commentText;
    }
    
    public function getCreatedAt(): ?string {
        return $this->createdAt;
    }
    
    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }
    
    public function setReportId(int $reportId): void {
        $this->reportId = $reportId;
    }
    
    public function setUserId(int $userId): void {
        $this->userId = $userId;
    }
    
    public function setCommentText(string $commentText): void {
        $this->commentText = trim($commentText);
    }
    
    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
    }
    
    // Business methods
    
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
     * Get truncated comment text for display
     * 
     * @param int $maxLength Maximum length
     * @return string Truncated comment text
     */
    public function getTruncatedText(int $maxLength = 200): string {
        if (strlen($this->commentText) <= $maxLength) {
            return $this->commentText;
        }
        
        return substr($this->commentText, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Get word count of comment
     * 
     * @return int Number of words
     */
    public function getWordCount(): int {
        return str_word_count($this->commentText);
    }
    
    /**
     * Check if comment is long (more than 100 words)
     * 
     * @return bool True if comment is long
     */
    public function isLong(): bool {
        return $this->getWordCount() > 100;
    }
    
    /**
     * Get comment age in hours
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
     * Check if comment is fresh (less than 24 hours old)
     * 
     * @return bool True if comment is fresh
     */
    public function isFresh(): bool {
        return $this->getAgeInHours() < 24;
    }
    
    /**
     * Check if comment contains sensitive content (basic filter)
     * 
     * @return bool True if comment might contain sensitive content
     */
    public function containsSensitiveContent(): bool {
        $sensitiveWords = [
            'spam', 'scam', 'fake', 'fraud', 'hack', 'illegal',
            'abuse', 'harassment', 'threat', 'violence'
        ];
        
        $textLower = strtolower($this->commentText);
        
        foreach ($sensitiveWords as $word) {
            if (strpos($textLower, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if comment has links
     * 
     * @return bool True if comment contains URLs
     */
    public function hasLinks(): bool {
        return preg_match('/https?:\/\/[^\s]+/i', $this->commentText) === 1;
    }
    
    /**
     * Get sanitized comment text for display (remove harmful content)
     * 
     * @return string Sanitized comment text
     */
    public function getSanitizedText(): string {
        // Remove HTML tags
        $text = strip_tags($this->commentText);
        
        // Convert special characters to HTML entities
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Convert URLs to links (if allowed)
        $text = preg_replace(
            '/https?:\/\/[^\s]+/i',
            '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>',
            $text
        );
        
        // Convert line breaks to HTML
        $text = nl2br($text);
        
        return $text;
    }
    
    /**
     * Get CSS class for comment display
     * 
     * @return string CSS class name
     */
    public function getCssClass(): string {
        $classes = ['comment'];
        
        if ($this->isFresh()) {
            $classes[] = 'comment-fresh';
        }
        
        if ($this->isLong()) {
            $classes[] = 'comment-long';
        }
        
        if ($this->containsSensitiveContent()) {
            $classes[] = 'comment-flagged';
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Validate comment data
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        // Report ID validation
        if (empty($this->reportId)) {
            $errors[] = 'Report ID is required';
        }
        
        // User ID validation
        if (empty($this->userId)) {
            $errors[] = 'User ID is required';
        }
        
        // Comment text validation
        if (empty($this->commentText)) {
            $errors[] = 'Comment text is required';
        } elseif (strlen($this->commentText) < 3) {
            $errors[] = 'Comment must be at least 3 characters long';
        } elseif (strlen($this->commentText) > 2000) {
            $errors[] = 'Comment must not exceed 2000 characters';
        }
        
        // Check for spam patterns
        if ($this->isSpamLike()) {
            $errors[] = 'Comment appears to be spam';
        }
        
        return $errors;
    }
    
    /**
     * Check if comment appears to be spam
     * 
     * @return bool True if comment appears to be spam
     */
    private function isSpamLike(): bool {
        $text = strtolower($this->commentText);
        
        // Check for excessive repetition
        if (preg_match('/(.)\1{10,}/', $text)) {
            return true;
        }
        
        // Check for excessive caps
        $capsCount = preg_match_all('/[A-Z]/', $this->commentText);
        $totalLength = strlen($this->commentText);
        if ($totalLength > 0 && ($capsCount / $totalLength) > 0.5) {
            return true;
        }
        
        // Check for multiple URLs
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/i', $this->commentText);
        if ($urlCount > 2) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert comment object to array
     * 
     * @return array Comment data as array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'report_id' => $this->reportId,
            'user_id' => $this->userId,
            'comment_text' => $this->commentText,
            'created_at' => $this->createdAt
        ];
    }
    
    /**
     * Create Comment object from database row
     * 
     * @param array $row Database row data
     * @return Comment Comment object
     */
    public static function fromDatabaseRow(array $row): Comment {
        return new self($row);
    }
    
    /**
     * Get database-ready data for insert/update
     * 
     * @return array Database-ready data
     */
    public function toDatabaseArray(): array {
        return [
            'report_id' => $this->reportId,
            'user_id' => $this->userId,
            'comment_text' => $this->commentText
        ];
    }
    
    /**
     * Create a comment with automatic content filtering
     * 
     * @param int $reportId Report ID
     * @param int $userId User ID
     * @param string $commentText Comment text
     * @return Comment New comment object
     */
    public static function createFiltered(int $reportId, int $userId, string $commentText): Comment {
        // Basic content filtering
        $filteredText = trim($commentText);
        
        // Remove excessive whitespace
        $filteredText = preg_replace('/\s+/', ' ', $filteredText);
        
        // Remove potentially harmful HTML
        $filteredText = strip_tags($filteredText);
        
        return new self([
            'report_id' => $reportId,
            'user_id' => $userId,
            'comment_text' => $filteredText
        ]);
    }
}