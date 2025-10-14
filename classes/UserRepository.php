<?php

require_once __DIR__ . '/User.php';

/**
 * UserRepository Class
 * 
 * Handles database operations for User entities following the Repository pattern.
 * Provides data access layer abstraction for user-related operations.
 */
class UserRepository {
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
     * Find user by ID
     * 
     * @param int $id User ID
     * @return User|null User object or null if not found
     */
    public function findById(int $id): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    /**
     * Find user by username
     * 
     * @param string $username Username
     * @return User|null User object or null if not found
     */
    public function findByUsername(string $username): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    /**
     * Find user by email
     * 
     * @param string $email Email address
     * @return User|null User object or null if not found
     */
    public function findByEmail(string $email): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    /**
     * Find user by username or email
     * 
     * @param string $usernameOrEmail Username or email address
     * @return User|null User object or null if not found
     */
    public function findByUsernameOrEmail(string $usernameOrEmail): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $userData = $stmt->fetch();
        
        return $userData ? User::fromDatabaseRow($userData) : null;
    }
    
    /**
     * Get all users
     * 
     * @param int $limit Number of users to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of User objects
     */
    public function findAll(int $limit = 100, int $offset = 0): array {
        $stmt = $this->pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $users = [];
        
        while ($userData = $stmt->fetch()) {
            $users[] = User::fromDatabaseRow($userData);
        }
        
        return $users;
    }
    
    /**
     * Get users by role
     * 
     * @param string $role User role
     * @param bool $activeOnly Whether to include only active users
     * @return array Array of User objects
     */
    public function findByRole(string $role, bool $activeOnly = true): array {
        $sql = "SELECT * FROM users WHERE role = ?";
        $params = [$role];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY full_name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $users = [];
        
        while ($userData = $stmt->fetch()) {
            $users[] = User::fromDatabaseRow($userData);
        }
        
        return $users;
    }
    
    /**
     * Get active authorities
     * 
     * @return array Array of User objects with authority role
     */
    public function getActiveAuthorities(): array {
        return $this->findByRole(User::ROLE_AUTHORITY, true);
    }
    
    /**
     * Get active citizens
     * 
     * @return array Array of User objects with citizen role
     */
    public function getActiveCitizens(): array {
        return $this->findByRole(User::ROLE_CITIZEN, true);
    }
    
    /**
     * Save user (insert or update)
     * 
     * @param User $user User object
     * @return User Updated user object with ID
     */
    public function save(User $user): User {
        if ($user->getId()) {
            return $this->update($user);
        } else {
            return $this->insert($user);
        }
    }
    
    /**
     * Insert new user
     * 
     * @param User $user User object
     * @return User User object with new ID
     */
    public function insert(User $user): User {
        $data = $user->toDatabaseArray();
        unset($data['id']); // Remove ID for insert
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $user->setId((int)$this->pdo->lastInsertId());
        $user->setCreatedAt(date('Y-m-d H:i:s'));
        $user->setUpdatedAt(date('Y-m-d H:i:s'));
        
        return $user;
    }
    
    /**
     * Update existing user
     * 
     * @param User $user User object
     * @return User Updated user object
     */
    public function update(User $user): User {
        if (!$user->getId()) {
            throw new InvalidArgumentException('Cannot update user without ID');
        }
        
        $data = $user->toDatabaseArray();
        $userId = $data['id'];
        unset($data['id']);
        
        $setParts = array_map(function($col) { return $col . ' = :' . $col; }, array_keys($data));
        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
        
        $data['id'] = $userId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $user->setUpdatedAt(date('Y-m-d H:i:s'));
        
        return $user;
    }
    
    /**
     * Delete user by ID
     * 
     * @param int $id User ID
     * @return bool True if deleted successfully
     */
    public function deleteById(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Delete user
     * 
     * @param User $user User object
     * @return bool True if deleted successfully
     */
    public function delete(User $user): bool {
        if (!$user->getId()) {
            return false;
        }
        
        return $this->deleteById($user->getId());
    }
    
    /**
     * Check if username exists
     * 
     * @param string $username Username to check
     * @param int|null $excludeId User ID to exclude from check (for updates)
     * @return bool True if username exists
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email Email to check
     * @param int|null $excludeId User ID to exclude from check (for updates)
     * @return bool True if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user statistics
     * 
     * @return array User statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Active users
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stats['active_users'] = $stmt->fetchColumn();
        
        // Users by role
        $stmt = $this->pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $roleStats = $stmt->fetchAll();
        foreach ($roleStats as $roleStat) {
            $stats['by_role'][$roleStat['role']] = $roleStat['count'];
        }
        
        // Email verified users
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE email_verified = 1");
        $stats['email_verified'] = $stmt->fetchColumn();
        
        // Recent registrations (last 30 days)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_registrations'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Search users by various criteria
     * 
     * @param array $criteria Search criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of User objects
     */
    public function search(array $criteria, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        // Search by name or email
        if (!empty($criteria['query'])) {
            $sql .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
            $searchTerm = '%' . $criteria['query'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by role
        if (!empty($criteria['role'])) {
            $sql .= " AND role = ?";
            $params[] = $criteria['role'];
        }
        
        // Filter by active status
        if (isset($criteria['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $criteria['is_active'] ? 1 : 0;
        }
        
        // Filter by email verification
        if (isset($criteria['email_verified'])) {
            $sql .= " AND email_verified = ?";
            $params[] = $criteria['email_verified'] ? 1 : 0;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $users = [];
        
        while ($userData = $stmt->fetch()) {
            $users[] = User::fromDatabaseRow($userData);
        }
        
        return $users;
    }
    
    /**
     * Count users matching search criteria
     * 
     * @param array $criteria Search criteria
     * @return int Number of matching users
     */
    public function countSearch(array $criteria): int {
        $sql = "SELECT COUNT(*) FROM users WHERE 1=1";
        $params = [];
        
        // Search by name or email
        if (!empty($criteria['query'])) {
            $sql .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
            $searchTerm = '%' . $criteria['query'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by role
        if (!empty($criteria['role'])) {
            $sql .= " AND role = ?";
            $params[] = $criteria['role'];
        }
        
        // Filter by active status
        if (isset($criteria['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $criteria['is_active'] ? 1 : 0;
        }
        
        // Filter by email verification
        if (isset($criteria['email_verified'])) {
            $sql .= " AND email_verified = ?";
            $params[] = $criteria['email_verified'] ? 1 : 0;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Update user's last login time
     * 
     * @param int $userId User ID
     * @return bool True if updated successfully
     */
    public function updateLastLogin(int $userId): bool {
        $stmt = $this->pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Bulk update user active status
     * 
     * @param array $userIds Array of user IDs
     * @param bool $isActive New active status
     * @return int Number of users updated
     */
    public function bulkUpdateActiveStatus(array $userIds, bool $isActive): int {
        if (empty($userIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "UPDATE users SET is_active = ? WHERE id IN ($placeholders)";
        
        $params = [$isActive ? 1 : 0];
        $params = array_merge($params, $userIds);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
