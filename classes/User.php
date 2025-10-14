<?php

/**
 * User Class
 * 
 * Represents a user in the CivicVoice system with authentication,
 * profile management, and role-based functionality.
 */
class User {
    // Properties
    private $id;
    private $username;
    private $email;
    private $passwordHash;
    private $fullName;
    private $phone;
    private $role;
    private $isActive;
    private $emailVerified;
    private $createdAt;
    private $updatedAt;
    
    // Role constants
    const ROLE_CITIZEN = 'citizen';
    const ROLE_AUTHORITY = 'authority';
    const ROLE_ADMIN = 'admin';
    
    // Valid roles array
    const VALID_ROLES = [
        self::ROLE_CITIZEN,
        self::ROLE_AUTHORITY,
        self::ROLE_ADMIN
    ];
    
    /**
     * Constructor
     * 
     * @param array $data User data array
     */
    public function __construct(array $data = []) {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->passwordHash = $data['password_hash'] ?? '';
        $this->fullName = $data['full_name'] ?? '';
        $this->phone = $data['phone'] ?? null;
        $this->role = $data['role'] ?? self::ROLE_CITIZEN;
        $this->isActive = $data['is_active'] ?? true;
        $this->emailVerified = $data['email_verified'] ?? false;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }
    
    // Getters
    public function getId(): ?int {
        return $this->id;
    }
    
    public function getUsername(): string {
        return $this->username;
    }
    
    public function getEmail(): string {
        return $this->email;
    }
    
    public function getPasswordHash(): string {
        return $this->passwordHash;
    }
    
    public function getFullName(): string {
        return $this->fullName;
    }
    
    public function getPhone(): ?string {
        return $this->phone;
    }
    
    public function getRole(): string {
        return $this->role;
    }
    
    public function isActive(): bool {
        return $this->isActive;
    }
    
    public function isEmailVerified(): bool {
        return $this->emailVerified;
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
    
    public function setUsername(string $username): void {
        $this->username = trim($username);
    }
    
    public function setEmail(string $email): void {
        $this->email = strtolower(trim($email));
    }
    
    public function setPassword(string $password): void {
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function setPasswordHash(string $hash): void {
        $this->passwordHash = $hash;
    }
    
    public function setFullName(string $fullName): void {
        $this->fullName = trim($fullName);
    }
    
    public function setPhone(?string $phone): void {
        $this->phone = $phone ? trim($phone) : null;
    }
    
    public function setRole(string $role): void {
        if (in_array($role, self::VALID_ROLES)) {
            $this->role = $role;
        } else {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }
    }
    
    public function setActive(bool $isActive): void {
        $this->isActive = $isActive;
    }
    
    public function setEmailVerified(bool $emailVerified): void {
        $this->emailVerified = $emailVerified;
    }
    
    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
    }
    
    public function setUpdatedAt(string $updatedAt): void {
        $this->updatedAt = $updatedAt;
    }
    
    // Business methods
    
    /**
     * Verify password against stored hash
     * 
     * @param string $password Plain text password
     * @return bool True if password matches
     */
    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->passwordHash);
    }
    
    /**
     * Check if user has a specific role
     * 
     * @param string $role Role to check
     * @return bool True if user has the role
     */
    public function hasRole(string $role): bool {
        return $this->role === $role;
    }
    
    /**
     * Check if user has any of the specified roles
     * 
     * @param array $roles Array of roles to check
     * @return bool True if user has any of the roles
     */
    public function hasAnyRole(array $roles): bool {
        return in_array($this->role, $roles);
    }
    
    /**
     * Check if user is a citizen
     * 
     * @return bool True if user is a citizen
     */
    public function isCitizen(): bool {
        return $this->role === self::ROLE_CITIZEN;
    }
    
    /**
     * Check if user is an authority
     * 
     * @return bool True if user is an authority
     */
    public function isAuthority(): bool {
        return $this->role === self::ROLE_AUTHORITY;
    }
    
    /**
     * Check if user is an admin
     * 
     * @return bool True if user is an admin
     */
    public function isAdmin(): bool {
        return $this->role === self::ROLE_ADMIN;
    }
    
    /**
     * Get display name for the user
     * 
     * @return string User's full name or username
     */
    public function getDisplayName(): string {
        return !empty($this->fullName) ? $this->fullName : $this->username;
    }
    
    /**
     * Get user's initials for avatar
     * 
     * @return string Two-character initials
     */
    public function getInitials(): string {
        $words = explode(' ', $this->getDisplayName());
        $initials = '';
        
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        
        return $initials ?: substr(strtoupper($this->username), 0, 2);
    }
    
    /**
     * Validate user data
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(): array {
        $errors = [];
        
        // Username validation
        if (empty($this->username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $this->username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        // Email validation
        if (empty($this->email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Full name validation
        if (empty($this->fullName)) {
            $errors[] = 'Full name is required';
        } elseif (strlen($this->fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters long';
        }
        
        // Phone validation (if provided)
        if (!empty($this->phone) && !preg_match('/^[\+]?[0-9\-\s\(\)]{10,}$/', $this->phone)) {
            $errors[] = 'Invalid phone number format';
        }
        
        // Role validation
        if (!in_array($this->role, self::VALID_ROLES)) {
            $errors[] = 'Invalid role specified';
        }
        
        return $errors;
    }
    
    /**
     * Convert user object to array
     * 
     * @param bool $includePassword Whether to include password hash
     * @return array User data as array
     */
    public function toArray(bool $includePassword = false): array {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->fullName,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'email_verified' => $this->emailVerified,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
        
        if ($includePassword) {
            $data['password_hash'] = $this->passwordHash;
        }
        
        return $data;
    }
    
    /**
     * Create User object from database row
     * 
     * @param array $row Database row data
     * @return User User object
     */
    public static function fromDatabaseRow(array $row): User {
        return new self($row);
    }
    
    /**
     * Convert boolean values for database storage
     * 
     * @return array Database-ready data
     */
    public function toDatabaseArray(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'full_name' => $this->fullName,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->isActive ? 1 : 0,
            'email_verified' => $this->emailVerified ? 1 : 0
        ];
    }
}
