<?php

/**
 * Report Class
 * 
 * Represents an issue report in the CivicVoice system with status management,
 * location handling, and photo attachments.
 */
class Report {
    // Properties
    private $id;
    private $userId;
    private $title;
    private $description;
    private $category;
    private $location;
    private $latitude;
    private $longitude;
    private $status;
    private $photoPath;
    private $priority;
    private $createdAt;
    private $updatedAt;
    
    // Status constants
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
    
    // Category constants
    const CATEGORY_STREETLIGHT = 'streetlight';
    const CATEGORY_POTHOLE = 'pothole';
    const CATEGORY_GARBAGE = 'garbage';
    const CATEGORY_TRAFFIC = 'traffic';
    const CATEGORY_OTHER = 'other';
    
    // Valid categories array
    const VALID_CATEGORIES = [
        self::CATEGORY_STREETLIGHT,
        self::CATEGORY_POTHOLE,
        self::CATEGORY_GARBAGE,
        self::CATEGORY_TRAFFIC,
        self::CATEGORY_OTHER
    ];
    
    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    
    // Valid priorities array
    const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH
    ];
    
    /**
     * Constructor
     * 
     * @param array $data Report data array
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->category = $data['category'] ?? self::CATEGORY_OTHER;
        $this->location = $data['location'] ?? '';
        $this->latitude = $data['latitude'] ?? null;
        $this->longitude = $data['longitude'] ?? null;
        $this->status = $data['status'] ?? self::STATUS_PENDING;
        $this->photoPath = $data['photo_path'] ?? null;
        $this->priority = $data['priority'] ?? self::PRIORITY_MEDIUM;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
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
    
    public function getDescription(): string {
        return $this->description;
    }
    
    public function getCategory(): string {
        return $this->category;
    }
    
    public function getLocation(): string {
        return $this->location;
    }
    
    public function getLatitude(): ?float {
        return $this->latitude ? (float)$this->latitude : null;
    }
    
    public function getLongitude(): ?float {
        return $this->longitude ? (float)$this->longitude : null;
    }
    
    public function getStatus(): string {
        return $this->status;
    }
    
    public function getPhotoPath(): ?string {
        return $this->photoPath;
    }
    
    public function getPriority(): string {
        return $this->priority;
    }
    
    public function getCreatedAt(): ?string {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): ?string {
        return $this->updatedAt;
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
    
    public function setDescription(string $description): void {
        $this->description = trim($description);
    }
    
    public function setCategory(string $category): void {
        if (in_array($category, self::VALID_CATEGORIES)) {
            $this->category = $category;
        } else {
            throw new InvalidArgumentException("Invalid category: {$category}");
        }
    }
    
    public function setLocation(string $location): void {
        $this->location = trim($location);
    }
    
    public function setLatitude(?float $latitude): void {
        $this->latitude = $latitude;
    }
    
    public function setLongitude(?float $longitude): void {
        $this->longitude = $longitude;
    }
    
    public function setCoordinates(?float $latitude, ?float $longitude): void {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }
    
    public function setStatus(string $status): void {
        if (in_array($status, self::VALID_STATUSES)) {
            $this->status = $status;
        } else {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }
    }
    
    public function setPhotoPath(?string $photoPath): void {
        $this->photoPath = $photoPath;
    }
    
    public function setPriority(string $priority): void {
        if (in_array($priority, self::VALID_PRIORITIES)) {
            $this->priority = $priority;
        } else {
            throw new InvalidArgumentException("Invalid priority: {$priority}");
        }
    }
    
    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
    }
    
    public function setUpdatedAt(string $updatedAt): void {
        $this->updatedAt = $updatedAt;
    }
    
    // Business methods
    
    /**
     * Check if report has coordinates
     * 
     * @return bool True if both latitude and longitude are set
     */
    public function hasCoordinates(): bool {
        return $this->latitude !== null && $this->longitude !== null;
    }
    
    /**
     * Check if report has photo
     * 
     * @return bool True if photo path is set
     */
    public function hasPhoto(): bool {
        return !empty($this->photoPath);
    }
    
    /**
     * Get full photo URL
     * 
     * @param string $baseUrl Base URL for uploads
     * @return string|null Full photo URL or null
     */
    public function getPhotoUrl(string $baseUrl = '/uploads/'): ?string {
        if (!$this->hasPhoto()) {
            return null;
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($this->photoPath, '/');
    }
    
    /**
     * Check if report is pending
     * 
     * @return bool True if status is pending
     */
    public function isPending(): bool {
        return $this->status === self::STATUS_PENDING;
    }
    
    /**
     * Check if report is in progress
     * 
     * @return bool True if status is in-progress
     */
    public function isInProgress(): bool {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
    
    /**
     * Check if report is fixed
     * 
     * @return bool True if status is fixed
     */
    public function isFixed(): bool {
        return $this->status === self::STATUS_FIXED;
    }
    
    /**
     * Check if report is rejected
     * 
     * @return bool True if status is rejected
     */
    public function isRejected(): bool {
        return $this->status === self::STATUS_REJECTED;
    }
    
    /**
     * Check if report is active (not fixed or rejected)
     * 
     * @return bool True if status is pending or in-progress
     */
    public function isActive(): bool {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }
    
    /**
     * Check if report is closed (fixed or rejected)
     * 
     * @return bool True if status is fixed or rejected
     */
    public function isClosed(): bool {
        return in_array($this->status, [self::STATUS_FIXED, self::STATUS_REJECTED]);
    }
    
    /**
     * Get status badge class for CSS styling
     * 
     * @return string CSS class name for status
     */
    public function getStatusBadgeClass(): string {
        $statusClasses = [
            self::STATUS_PENDING => 'status-pending',
            self::STATUS_IN_PROGRESS => 'status-progress',
            self::STATUS_FIXED => 'status-fixed',
            self::STATUS_REJECTED => 'status-rejected'
        ];
        
        return $statusClasses[$this->status] ?? 'status-unknown';
    }
    
    /**
     * Get priority badge class for CSS styling
     * 
     * @return string CSS class name for priority
     */
    public function getPriorityBadgeClass(): string {
        $priorityClasses = [
            self::PRIORITY_LOW => 'priority-low',
            self::PRIORITY_MEDIUM => 'priority-medium',
            self::PRIORITY_HIGH => 'priority-high'
        ];
        
        return $priorityClasses[$this->priority] ?? 'priority-unknown';
    }
    
    /**
     * Get category display name
     * 
     * @return string Human-readable category name
     */
    public function getCategoryDisplayName(): string {
        $categoryNames = [
            self::CATEGORY_STREETLIGHT => 'Street Light',
            self::CATEGORY_POTHOLE => 'Pothole',
            self::CATEGORY_GARBAGE => 'Garbage',
            self::CATEGORY_TRAFFIC => 'Traffic',
            self::CATEGORY_OTHER => 'Other'
        ];
        
        return $categoryNames[$this->category] ?? 'Unknown';
    }
    
    /**
     * Get status display name
     * 
     * @return string Human-readable status name
     */
    public function getStatusDisplayName(): string {
        $statusNames = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_FIXED => 'Fixed',
            self::STATUS_REJECTED => 'Rejected'
        ];
        
        return $statusNames[$this->status] ?? 'Unknown';
    }
    
    /**
     * Get priority display name
     * 
     * @return string Human-readable priority name
     */
    public function getPriorityDisplayName(): string {
        return ucfirst($this->priority);
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
     * Validate report data
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
        
        // Description validation
        if (empty($this->description)) {
            $errors[] = 'Description is required';
        } elseif (strlen($this->description) < 10) {
            $errors[] = 'Description must be at least 10 characters long';
        }
        
        // Category validation
        if (!in_array($this->category, self::VALID_CATEGORIES)) {
            $errors[] = 'Invalid category specified';
        }
        
        // Location validation
        if (empty($this->location)) {
            $errors[] = 'Location is required';
        }
        
        // Coordinates validation (if provided)
        if ($this->latitude !== null && ($this->latitude < -90 || $this->latitude > 90)) {
            $errors[] = 'Latitude must be between -90 and 90 degrees';
        }
        
        if ($this->longitude !== null && ($this->longitude < -180 || $this->longitude > 180)) {
            $errors[] = 'Longitude must be between -180 and 180 degrees';
        }
        
        // Status validation
        if (!in_array($this->status, self::VALID_STATUSES)) {
            $errors[] = 'Invalid status specified';
        }
        
        // Priority validation
        if (!in_array($this->priority, self::VALID_PRIORITIES)) {
            $errors[] = 'Invalid priority specified';
        }
        
        return $errors;
    }
    
    /**
     * Convert report object to array
     * 
     * @return array Report data as array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'photo_path' => $this->photoPath,
            'priority' => $this->priority,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
    
    /**
     * Create Report object from database row
     * 
     * @param array $row Database row data
     * @return Report Report object
     */
    public static function fromDatabaseRow(array $row): Report {
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
            'description' => $this->description,
            'category' => $this->category,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'photo_path' => $this->photoPath,
            'priority' => $this->priority
        ];
    }
    
    /**
     * Get all valid categories for forms
     * 
     * @return array Associative array of category => display name
     */
    public static function getCategoriesForForm(): array {
        return [
            self::CATEGORY_STREETLIGHT => 'Street Light',
            self::CATEGORY_POTHOLE => 'Pothole',
            self::CATEGORY_GARBAGE => 'Garbage',
            self::CATEGORY_TRAFFIC => 'Traffic',
            self::CATEGORY_OTHER => 'Other'
        ];
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
    
    /**
     * Get all valid priorities for forms
     * 
     * @return array Associative array of priority => display name
     */
    public static function getPrioritiesForForm(): array {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High'
        ];
    }
}
