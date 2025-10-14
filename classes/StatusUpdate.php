<?php

/**
 * StatusUpdate Class
 * 
 * Represents a status update record for reports in the CivicVoice system
 * to track report status changes with audit trail functionality.
 */
class StatusUpdate {
    // Properties
    private $id;
    private $reportId;
    private $updatedByUserId;
    private $oldStatus;
    private $newStatus;
    private $updateNote;
    private $updatedAt;
    
    // Status constants (matching Report class)
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in-progress';
    const STATUS_FIXED = 'fixed';
    const STATUS_REJECTED = 'rejected';
    
    // Valid statuses array
    const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FIXED,
        self::STATUS_REJECTED
    ];
    
    /**
     * Constructor
     * 
     * @param array $data StatusUpdate data array
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->reportId = $data['report_id'] ?? null;
        $this->updatedByUserId = $data['updated_by_user_id'] ?? null;
        $this->oldStatus = $data['old_status'] ?? null;
        $this->newStatus = $data['new_status'] ?? null;
        $this->updateNote = $data['update_note'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }
    
    // Getters
    public function getId(): ?int {
        return $this->id;
    }
    
    public function getReportId(): ?int {
        return $this->reportId;
    }
    
    public function getUpdatedByUserId(): ?int {
        return $this->updatedByUserId;
    }
    
    public function getOldStatus(): ?string {
        return $this->oldStatus;
    }
    
    public function getNewStatus(): ?string {
        return $this->newStatus;
    }
    
    public function getUpdateNote(): ?string {
        return $this->updateNote;
    }
    
    public function getUpdatedAt(): ?string {
        return $this->updatedAt;
    }
    
    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }
    
    public function setReportId(int $reportId): void {
        $this->reportId = $reportId;
    }
    
    public function setUpdatedByUserId(int $updatedByUserId): void {
        $this->updatedByUserId = $updatedByUserId;
    }
    
    public function setOldStatus(string $oldStatus): void {
        if (in_array($oldStatus, self::VALID_STATUSES)) {
            $this->oldStatus = $oldStatus;
        } else {
            throw new InvalidArgumentException("Invalid old status: {$oldStatus}");
        }
    }
    
    public function setNewStatus(string $newStatus): void {
        if (in_array($newStatus, self::VALID_STATUSES)) {
            $this->newStatus = $newStatus;
        } else {
            throw new InvalidArgumentException("Invalid new status: {$newStatus}");
        }
    }
    
    public function setUpdateNote(?string $updateNote): void {
        $this->updateNote = $updateNote ? trim($updateNote) : null;
    }
    
    public function setUpdatedAt(string $updatedAt): void {
        $this->updatedAt = $updatedAt;
    }
    
    // Business methods
    
    /**
     * Check if status actually changed
     * 
     * @return bool True if status changed
     */
    public function isStatusChanged(): bool {
        return $this->oldStatus !== $this->newStatus;
    }
    
    /**
     * Check if update includes a note
     * 
     * @return bool True if update note is present
     */
    public function hasNote(): bool {
        return !empty($this->updateNote);
    }
    
    /**
     * Get status change description
     * 
     * @return string Human-readable status change description
     */
    public function getStatusChangeDescription(): string {
        if (!$this->isStatusChanged()) {
            return 'No status change';
        }
        
        $oldDisplayName = $this->getStatusDisplayName($this->oldStatus);
        $newDisplayName = $this->getStatusDisplayName($this->newStatus);
        
        return "Changed from {$oldDisplayName} to {$newDisplayName}";
    }
    
    /**
     * Get status display name
     * 
     * @param string $status Status code
     * @return string Human-readable status name
     */
    private function getStatusDisplayName(string $status): string {
        $statusNames = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_FIXED => 'Fixed',
            self::STATUS_REJECTED => 'Rejected'
        ];
        
        return $statusNames[$status] ?? 'Unknown';
    }
    
    /**
     * Get old status display name
     * 
     * @return string Human-readable old status name
     */
    public function getOldStatusDisplayName(): string {
        return $this->getStatusDisplayName($this->oldStatus);
    }
    
    /**
     * Get new status display name
     * 
     * @return string Human-readable new status name
     */
    public function getNewStatusDisplayName(): string {
        return $this->getStatusDisplayName($this->newStatus);
    }
    
    /**
     * Check if status update represents progression
     * 
     * @return bool True if status moved forward in the workflow
     */
    public function isProgression(): bool {
        $statusOrder = [
            self::STATUS_PENDING => 1,
            self::STATUS_IN_PROGRESS => 2,
            self::STATUS_FIXED => 3,
            self::STATUS_REJECTED => 3 // Same level as fixed (terminal states)
        ];
        
        $oldOrder = $statusOrder[$this->oldStatus] ?? 0;
        $newOrder = $statusOrder[$this->newStatus] ?? 0;
        
        return $newOrder > $oldOrder;
    }
    
    /**
     * Check if status update represents regression
     * 
     * @return bool True if status moved backward in the workflow
     */
    public function isRegression(): bool {
        $statusOrder = [
            self::STATUS_PENDING => 1,
            self::STATUS_IN_PROGRESS => 2,
            self::STATUS_FIXED => 3,
            self::STATUS_REJECTED => 3
        ];
        
        $oldOrder = $statusOrder[$this->oldStatus] ?? 0;
        $newOrder = $statusOrder[$this->newStatus] ?? 0;
        
        return $newOrder < $oldOrder;
    }
    
    /**
     * Check if status update is to a terminal state
     * 
     * @return bool True if new status is fixed or rejected
     */
    public function isTerminalUpdate(): bool {
        return in_array($this->newStatus, [self::STATUS_FIXED, self::STATUS_REJECTED]);
    }
    
    /**
     * Get formatted update date
     * 
     * @param string $format Date format
     * @return string Formatted date
     */
    public function getFormattedUpdatedAt(string $format = 'M j, Y g:i A'): string {
        if (!$this->updatedAt) {
            return '';
        }
        return date($format, strtotime($this->updatedAt));
    }
    
    /**
     * Get time since update
     * 
     * @return string Human-readable time difference
     */
    public function getTimeAgo(): string {
        if (!$this->updatedAt) {
            return '';
        }
        
        $time = time() - strtotime($this->updatedAt);
        
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
            return $this->getFormattedUpdatedAt('M j, Y');
        }
    }
    
    /**
     * Get CSS class for status update display
     * 
     * @return string CSS class name
     */
    public function getCssClass(): string {
        $classes = ['status-update'];
        
        if ($this->isProgression()) {
            $classes[] = 'status-progression';
        } elseif ($this->isRegression()) {
            $classes[] = 'status-regression';
        }
        
        if ($this->isTerminalUpdate()) {
            $classes[] = 'status-terminal';
        }
        
        $classes[] = 'status-to-' . str_replace('-', '_', $this->newStatus);
        
        return implode(' ', $classes);
    }
    
    /**
     * Get icon for status change
     * 
     * @return string Icon character
     */
    public function getStatusChangeIcon(): string {
        if (!$this->isStatusChanged()) {
            return '📝'; // Note update
        }
        
        switch ($this->newStatus) {
            case self::STATUS_PENDING:
                return '⏳';
            case self::STATUS_IN_PROGRESS:
                return '🔧';
            case self::STATUS_FIXED:
                return '✅';
            case self::STATUS_REJECTED:
                return '❌';
            default:
                return '🔄';
        }
    }
    
    /**
     * Get truncated update note
     * 
     * @param int $maxLength Maximum length
     * @return string Truncated note
     */
    public function getTruncatedNote(int $maxLength = 100): string {
        if (!$this->updateNote || strlen($this->updateNote) <= $maxLength) {
            return $this->updateNote ?? '';
        }
        
        return substr($this->updateNote, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Validate status update data
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        // Report ID validation
        if (empty($this->reportId)) {
            $errors[] = 'Report ID is required';
        }
        
        // Updated by user ID validation
        if (empty($this->updatedByUserId)) {
            $errors[] = 'Updated by user ID is required';
        }
        
        // Old status validation
        if (empty($this->oldStatus)) {
            $errors[] = 'Old status is required';
        } elseif (!in_array($this->oldStatus, self::VALID_STATUSES)) {
            $errors[] = 'Invalid old status specified';
        }
        
        // New status validation
        if (empty($this->newStatus)) {
            $errors[] = 'New status is required';
        } elseif (!in_array($this->newStatus, self::VALID_STATUSES)) {
            $errors[] = 'Invalid new status specified';
        }
        
        // Update note validation (optional but has max length)
        if ($this->updateNote && strlen($this->updateNote) > 1000) {
            $errors[] = 'Update note must not exceed 1000 characters';
        }
        
        return $errors;
    }
    
    /**
     * Convert status update object to array
     * 
     * @return array StatusUpdate data as array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'report_id' => $this->reportId,
            'updated_by_user_id' => $this->updatedByUserId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'update_note' => $this->updateNote,
            'updated_at' => $this->updatedAt
        ];
    }
    
    /**
     * Create StatusUpdate object from database row
     * 
     * @param array $row Database row data
     * @return StatusUpdate StatusUpdate object
     */
    public static function fromDatabaseRow(array $row): StatusUpdate {
        return new self($row);
    }
    
    /**
     * Get database-ready data for insert
     * 
     * @return array Database-ready data
     */
    public function toDatabaseArray(): array {
        return [
            'report_id' => $this->reportId,
            'updated_by_user_id' => $this->updatedByUserId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'update_note' => $this->updateNote
        ];
    }
    
    /**
     * Create a status update record
     * 
     * @param int $reportId Report ID
     * @param int $updatedByUserId User who made the update
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param string|null $updateNote Optional note about the update
     * @return StatusUpdate New status update object
     */
    public static function create(
        int $reportId,
        int $updatedByUserId,
        string $oldStatus,
        string $newStatus,
        ?string $updateNote = null
    ): StatusUpdate {
        return new self([
            'report_id' => $reportId,
            'updated_by_user_id' => $updatedByUserId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'update_note' => $updateNote
        ]);
    }
    
    /**
     * Get all valid statuses for forms
     * 
     * @return array Associative array of status => display name
     */
    public static function getStatusesForForm(): array {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_FIXED => 'Fixed',
            self::STATUS_REJECTED => 'Rejected'
        ];
    }
}