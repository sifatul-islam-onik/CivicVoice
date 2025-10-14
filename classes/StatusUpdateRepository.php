<?php

require_once __DIR__ . '/StatusUpdate.php';

/**
 * StatusUpdateRepository Class
 * 
 * Handles database operations for StatusUpdate entities following the Repository pattern.
 * Provides data access layer abstraction for status update audit trail operations.
 */
class StatusUpdateRepository {
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
     * Find status update by ID
     * 
     * @param int $id Status update ID
     * @return StatusUpdate|null StatusUpdate object or null if not found
     */
    public function findById(int $id): ?StatusUpdate {
        $stmt = $this->pdo->prepare("
            SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                   r.title as report_title
            FROM status_updates su
            JOIN users u ON su.updated_by_user_id = u.id
            JOIN reports r ON su.report_id = r.id
            WHERE su.id = ?
        ");
        $stmt->execute([$id]);
        $statusUpdateData = $stmt->fetch();
        
        return $statusUpdateData ? StatusUpdate::fromDatabaseRow($statusUpdateData) : null;
    }
    
    /**
     * Get status updates for a report
     * 
     * @param int $reportId Report ID
     * @param int $limit Number of updates to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of StatusUpdate objects with user data
     */
    public function findByReportId(int $reportId, int $limit = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare("
            SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email, u.role as updated_by_role,
                   r.title as report_title
            FROM status_updates su
            JOIN users u ON su.updated_by_user_id = u.id
            JOIN reports r ON su.report_id = r.id
            WHERE su.report_id = ?
            ORDER BY su.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$reportId, $limit, $offset]);
        $statusUpdates = [];
        
        while ($statusUpdateData = $stmt->fetch()) {
            $statusUpdates[] = StatusUpdate::fromDatabaseRow($statusUpdateData);
        }
        
        return $statusUpdates;
    }
    
    /**
     * Get status updates by user ID (who made the updates)
     * 
     * @param int $userId User ID
     * @param int $limit Number of updates to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of StatusUpdate objects
     */
    public function findByUpdatedByUserId(int $userId, int $limit = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare("
            SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                   r.title as report_title
            FROM status_updates su
            JOIN users u ON su.updated_by_user_id = u.id
            JOIN reports r ON su.report_id = r.id
            WHERE su.updated_by_user_id = ?
            ORDER BY su.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $statusUpdates = [];
        
        while ($statusUpdateData = $stmt->fetch()) {
            $statusUpdates[] = StatusUpdate::fromDatabaseRow($statusUpdateData);
        }
        
        return $statusUpdates;
    }
    
    /**
     * Get recent status updates
     * 
     * @param int $days Number of days to look back
     * @param int $limit Number of updates to retrieve
     * @return array Array of StatusUpdate objects
     */
    public function findRecentUpdates(int $days = 7, int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                   r.title as report_title
            FROM status_updates su
            JOIN users u ON su.updated_by_user_id = u.id
            JOIN reports r ON su.report_id = r.id
            WHERE su.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY su.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        $statusUpdates = [];
        
        while ($statusUpdateData = $stmt->fetch()) {
            $statusUpdates[] = StatusUpdate::fromDatabaseRow($statusUpdateData);
        }
        
        return $statusUpdates;
    }
    
    /**
     * Get the latest status update for a report
     * 
     * @param int $reportId Report ID
     * @return StatusUpdate|null Latest StatusUpdate or null if no updates found
     */
    public function findLatestByReportId(int $reportId): ?StatusUpdate {
        $stmt = $this->pdo->prepare("
            SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                   r.title as report_title
            FROM status_updates su
            JOIN users u ON su.updated_by_user_id = u.id
            JOIN reports r ON su.report_id = r.id
            WHERE su.report_id = ?
            ORDER BY su.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$reportId]);
        $statusUpdateData = $stmt->fetch();
        
        return $statusUpdateData ? StatusUpdate::fromDatabaseRow($statusUpdateData) : null;
    }
    
    /**
     * Count status updates for a report
     * 
     * @param int $reportId Report ID
     * @return int Number of status updates
     */
    public function countByReportId(int $reportId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM status_updates WHERE report_id = ?");
        $stmt->execute([$reportId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Count status updates by user
     * 
     * @param int $userId User ID
     * @return int Number of status updates
     */
    public function countByUserId(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM status_updates WHERE updated_by_user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Save status update (insert or update)
     * 
     * @param StatusUpdate $statusUpdate StatusUpdate object
     * @return StatusUpdate Updated status update object with ID
     */
    public function save(StatusUpdate $statusUpdate): StatusUpdate {
        if ($statusUpdate->getId()) {
            return $this->update($statusUpdate);
        } else {
            return $this->insert($statusUpdate);
        }
    }
    
    /**
     * Insert new status update
     * 
     * @param StatusUpdate $statusUpdate StatusUpdate object
     * @return StatusUpdate StatusUpdate object with new ID
     */
    public function insert(StatusUpdate $statusUpdate): StatusUpdate {
        $data = $statusUpdate->toDatabaseArray();
        
        $sql = "INSERT INTO status_updates (report_id, updated_by_user_id, old_status, new_status, update_note, updated_at) 
                VALUES (:report_id, :updated_by_user_id, :old_status, :new_status, :update_note, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $statusUpdate->setId((int)$this->pdo->lastInsertId());
        $statusUpdate->setUpdatedAt(date('Y-m-d H:i:s'));
        
        return $statusUpdate;
    }
    
    /**
     * Update existing status update
     * 
     * @param StatusUpdate $statusUpdate StatusUpdate object
     * @return StatusUpdate Updated status update object
     */
    public function update(StatusUpdate $statusUpdate): StatusUpdate {
        if (!$statusUpdate->getId()) {
            throw new InvalidArgumentException('Cannot update status update without ID');
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE status_updates 
            SET old_status = ?, new_status = ?, update_note = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $statusUpdate->getOldStatus(),
            $statusUpdate->getNewStatus(),
            $statusUpdate->getUpdateNote(),
            $statusUpdate->getId()
        ]);
        
        return $statusUpdate;
    }
    
    /**
     * Delete status update by ID
     * 
     * @param int $id Status update ID
     * @return bool True if deleted successfully
     */
    public function deleteById(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM status_updates WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Delete status update
     * 
     * @param StatusUpdate $statusUpdate StatusUpdate object
     * @return bool True if deleted successfully
     */
    public function delete(StatusUpdate $statusUpdate): bool {
        if (!$statusUpdate->getId()) {
            return false;
        }
        
        return $this->deleteById($statusUpdate->getId());
    }
    
    /**
     * Delete all status updates for a report
     * 
     * @param int $reportId Report ID
     * @return int Number of status updates deleted
     */
    public function deleteByReportId(int $reportId): int {
        $stmt = $this->pdo->prepare("DELETE FROM status_updates WHERE report_id = ?");
        $stmt->execute([$reportId]);
        return $stmt->rowCount();
    }
    
    /**
     * Create and save a status update
     * 
     * @param int $reportId Report ID
     * @param int $updatedByUserId User who made the update
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param string|null $updateNote Optional note about the update
     * @return StatusUpdate Created status update object
     */
    public function create(
        int $reportId,
        int $updatedByUserId,
        string $oldStatus,
        string $newStatus,
        ?string $updateNote = null
    ): StatusUpdate {
        $statusUpdate = StatusUpdate::create($reportId, $updatedByUserId, $oldStatus, $newStatus, $updateNote);
        return $this->insert($statusUpdate);
    }
    
    /**
     * Get status update statistics
     * 
     * @return array Status update statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total status updates
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM status_updates");
        $stats['total_status_updates'] = $stmt->fetchColumn();
        
        // Recent status updates (last 30 days)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM status_updates WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_status_updates'] = $stmt->fetchColumn();
        
        // Status changes by type
        $stmt = $this->pdo->query("
            SELECT 
                CONCAT(old_status, ' → ', new_status) as status_change,
                COUNT(*) as change_count 
            FROM status_updates 
            WHERE old_status != new_status
            GROUP BY old_status, new_status 
            ORDER BY change_count DESC 
            LIMIT 10
        ");
        $stats['common_status_changes'] = $stmt->fetchAll();
        
        // Most active status updaters (authorities/admins)
        $stmt = $this->pdo->query("
            SELECT u.full_name, u.role, COUNT(*) as update_count 
            FROM status_updates su 
            JOIN users u ON su.updated_by_user_id = u.id 
            GROUP BY su.updated_by_user_id, u.full_name, u.role 
            ORDER BY update_count DESC 
            LIMIT 10
        ");
        $stats['top_updaters'] = $stmt->fetchAll();
        
        // Terminal status updates (to fixed/rejected)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM status_updates WHERE new_status IN ('fixed', 'rejected')");
        $stats['terminal_updates'] = $stmt->fetchColumn();
        
        // Progression vs regression updates
        $stmt = $this->pdo->query("
            SELECT 
                CASE 
                    WHEN (old_status = 'pending' AND new_status = 'in-progress') OR 
                         (old_status = 'pending' AND new_status IN ('fixed', 'rejected')) OR 
                         (old_status = 'in-progress' AND new_status IN ('fixed', 'rejected')) 
                    THEN 'progression'
                    WHEN (old_status = 'in-progress' AND new_status = 'pending') OR 
                         (old_status IN ('fixed', 'rejected') AND new_status IN ('pending', 'in-progress')) 
                    THEN 'regression'
                    ELSE 'no_change'
                END as update_type,
                COUNT(*) as count
            FROM status_updates 
            GROUP BY update_type
        ");
        $stats['update_types'] = $stmt->fetchAll();
        
        // Average time between status updates
        $stmt = $this->pdo->query("
            SELECT AVG(time_diff) as avg_time_between_updates
            FROM (
                SELECT 
                    TIMESTAMPDIFF(HOUR, 
                        LAG(updated_at) OVER (PARTITION BY report_id ORDER BY updated_at),
                        updated_at
                    ) as time_diff
                FROM status_updates
            ) as time_diffs
            WHERE time_diff IS NOT NULL AND time_diff > 0
        ");
        $stats['avg_hours_between_updates'] = round($stmt->fetchColumn(), 2);
        
        return $stats;
    }
    
    /**
     * Search status updates
     * 
     * @param array $criteria Search criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of StatusUpdate objects
     */
    public function search(array $criteria, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                       r.title as report_title
                FROM status_updates su 
                JOIN users u ON su.updated_by_user_id = u.id 
                JOIN reports r ON su.report_id = r.id 
                WHERE 1=1";
        $params = [];
        
        // Search by update note
        if (!empty($criteria['query'])) {
            $sql .= " AND su.update_note LIKE ?";
            $params[] = '%' . $criteria['query'] . '%';
        }
        
        // Filter by user who made the update
        if (!empty($criteria['updated_by_user_id'])) {
            $sql .= " AND su.updated_by_user_id = ?";
            $params[] = $criteria['updated_by_user_id'];
        }
        
        // Filter by report
        if (!empty($criteria['report_id'])) {
            $sql .= " AND su.report_id = ?";
            $params[] = $criteria['report_id'];
        }
        
        // Filter by old status
        if (!empty($criteria['old_status'])) {
            $sql .= " AND su.old_status = ?";
            $params[] = $criteria['old_status'];
        }
        
        // Filter by new status
        if (!empty($criteria['new_status'])) {
            $sql .= " AND su.new_status = ?";
            $params[] = $criteria['new_status'];
        }
        
        // Filter by date range
        if (!empty($criteria['date_from'])) {
            $sql .= " AND su.updated_at >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND su.updated_at <= ?";
            $params[] = $criteria['date_to'];
        }
        
        $sql .= " ORDER BY su.updated_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $statusUpdates = [];
        
        while ($statusUpdateData = $stmt->fetch()) {
            $statusUpdates[] = StatusUpdate::fromDatabaseRow($statusUpdateData);
        }
        
        return $statusUpdates;
    }
    
    /**
     * Get status update history for multiple reports
     * 
     * @param array $reportIds Array of report IDs
     * @return array Associative array where keys are report IDs and values are arrays of StatusUpdate objects
     */
    public function findByReportIds(array $reportIds): array {
        if (empty($reportIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $sql = "SELECT su.*, u.full_name as updated_by_name, u.email as updated_by_email,
                       r.title as report_title
                FROM status_updates su 
                JOIN users u ON su.updated_by_user_id = u.id 
                JOIN reports r ON su.report_id = r.id 
                WHERE su.report_id IN ($placeholders)
                ORDER BY su.report_id, su.updated_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($reportIds);
        
        $result = [];
        while ($statusUpdateData = $stmt->fetch()) {
            $reportId = $statusUpdateData['report_id'];
            if (!isset($result[$reportId])) {
                $result[$reportId] = [];
            }
            $result[$reportId][] = StatusUpdate::fromDatabaseRow($statusUpdateData);
        }
        
        return $result;
    }
    
    /**
     * Get audit trail for a report (formatted for display)
     * 
     * @param int $reportId Report ID
     * @return array Array of formatted audit trail entries
     */
    public function getAuditTrail(int $reportId): array {
        $statusUpdates = $this->findByReportId($reportId);
        $auditTrail = [];
        
        foreach ($statusUpdates as $update) {
            $auditTrail[] = [
                'id' => $update->getId(),
                'timestamp' => $update->getUpdatedAt(),
                'formatted_time' => $update->getFormattedUpdatedAt(),
                'time_ago' => $update->getTimeAgo(),
                'user_name' => $update->getUpdatedByName() ?? 'Unknown User',
                'action' => $update->getStatusChangeDescription(),
                'note' => $update->getUpdateNote(),
                'icon' => $update->getStatusChangeIcon(),
                'css_class' => $update->getCssClass(),
                'is_progression' => $update->isProgression(),
                'is_terminal' => $update->isTerminalUpdate()
            ];
        }
        
        return $auditTrail;
    }
}