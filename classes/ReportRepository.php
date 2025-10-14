<?php

require_once __DIR__ . '/Report.php';

/**
 * ReportRepository Class
 * 
 * Handles database operations for Report entities following the Repository pattern.
 * Provides data access layer abstraction for report-related operations.
 */
class ReportRepository {
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
     * Find report by ID
     * 
     * @param int $id Report ID
     * @return Report|null Report object or null if not found
     */
    public function findById(int $id): ?Report {
        $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$id]);
        $reportData = $stmt->fetch();
        
        return $reportData ? Report::fromDatabaseRow($reportData) : null;
    }
    
    /**
     * Get all reports with optional filters
     * 
     * @param array $filters Filter criteria
     * @param int $limit Number of reports to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Report objects
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND r.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND r.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND r.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Order by latest activity
        $sql .= " ORDER BY GREATEST(r.created_at, COALESCE(r.updated_at, r.created_at)) DESC, 
                  FIELD(r.priority, 'high','medium','low') ASC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reports = [];
        
        while ($reportData = $stmt->fetch()) {
            $reports[] = Report::fromDatabaseRow($reportData);
        }
        
        return $reports;
    }
    
    /**
     * Get reports by user ID
     * 
     * @param int $userId User ID
     * @param array $filters Additional filters
     * @param int $limit Number of reports to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Report objects
     */
    public function findByUserId(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array {
        $filters['user_id'] = $userId;
        return $this->findAll($filters, $limit, $offset);
    }
    
    /**
     * Get reports by status
     * 
     * @param string $status Report status
     * @param int $limit Number of reports to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Report objects
     */
    public function findByStatus(string $status, int $limit = 50, int $offset = 0): array {
        return $this->findAll(['status' => $status], $limit, $offset);
    }
    
    /**
     * Get reports by category
     * 
     * @param string $category Report category
     * @param int $limit Number of reports to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Report objects
     */
    public function findByCategory(string $category, int $limit = 50, int $offset = 0): array {
        return $this->findAll(['category' => $category], $limit, $offset);
    }
    
    /**
     * Get active reports (pending or in-progress)
     * 
     * @param int $limit Number of reports to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Report objects
     */
    public function findActiveReports(int $limit = 50, int $offset = 0): array {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.status IN ('pending', 'in-progress')
                ORDER BY FIELD(r.priority, 'high','medium','low') ASC, r.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $reports = [];
        
        while ($reportData = $stmt->fetch()) {
            $reports[] = Report::fromDatabaseRow($reportData);
        }
        
        return $reports;
    }
    
    /**
     * Get recent reports
     * 
     * @param int $days Number of days to look back
     * @param int $limit Number of reports to retrieve
     * @return array Array of Report objects
     */
    public function findRecentReports(int $days = 7, int $limit = 20): array {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY r.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days, $limit]);
        $reports = [];
        
        while ($reportData = $stmt->fetch()) {
            $reports[] = Report::fromDatabaseRow($reportData);
        }
        
        return $reports;
    }
    
    /**
     * Save report (insert or update)
     * 
     * @param Report $report Report object
     * @return Report Updated report object with ID
     */
    public function save(Report $report): Report {
        if ($report->getId()) {
            return $this->update($report);
        } else {
            return $this->insert($report);
        }
    }
    
    /**
     * Insert new report
     * 
     * @param Report $report Report object
     * @return Report Report object with new ID
     */
    public function insert(Report $report): Report {
        $data = $report->toDatabaseArray();
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $sql = "INSERT INTO reports (" . implode(', ', $columns) . ", created_at) 
                VALUES (" . implode(', ', $placeholders) . ", NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $report->setId((int)$this->pdo->lastInsertId());
        $report->setCreatedAt(date('Y-m-d H:i:s'));
        $report->setUpdatedAt(date('Y-m-d H:i:s'));
        
        return $report;
    }
    
    /**
     * Update existing report
     * 
     * @param Report $report Report object
     * @return Report Updated report object
     */
    public function update(Report $report): Report {
        if (!$report->getId()) {
            throw new InvalidArgumentException('Cannot update report without ID');
        }
        
        $data = $report->toDatabaseArray();
        $reportId = $report->getId();
        
        $setParts = array_map(function($col) { return $col . ' = :' . $col; }, array_keys($data));
        $sql = "UPDATE reports SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
        
        $data['id'] = $reportId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $report->setUpdatedAt(date('Y-m-d H:i:s'));
        
        return $report;
    }
    
    /**
     * Update report status
     * 
     * @param int $reportId Report ID
     * @param string $newStatus New status
     * @return bool True if updated successfully
     */
    public function updateStatus(int $reportId, string $newStatus): bool {
        $stmt = $this->pdo->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$newStatus, $reportId]);
    }
    
    /**
     * Delete report by ID
     * 
     * @param int $id Report ID
     * @return bool True if deleted successfully
     */
    public function deleteById(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM reports WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Delete report
     * 
     * @param Report $report Report object
     * @return bool True if deleted successfully
     */
    public function delete(Report $report): bool {
        if (!$report->getId()) {
            return false;
        }
        
        return $this->deleteById($report->getId());
    }
    
    /**
     * Get report statistics
     * 
     * @return array Report statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total reports
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM reports");
        $stats['total_reports'] = $stmt->fetchColumn();
        
        // Reports by status
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
        $statusStats = $stmt->fetchAll();
        foreach ($statusStats as $statusStat) {
            $stats['by_status'][$statusStat['status']] = $statusStat['count'];
        }
        
        // Reports by category
        $stmt = $this->pdo->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
        $categoryStats = $stmt->fetchAll();
        foreach ($categoryStats as $categoryStat) {
            $stats['by_category'][$categoryStat['category']] = $categoryStat['count'];
        }
        
        // Reports by priority
        $stmt = $this->pdo->query("SELECT priority, COUNT(*) as count FROM reports GROUP BY priority");
        $priorityStats = $stmt->fetchAll();
        foreach ($priorityStats as $priorityStat) {
            $stats['by_priority'][$priorityStat['priority']] = $priorityStat['count'];
        }
        
        // Active reports (pending + in-progress)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'in-progress')");
        $stats['active_reports'] = $stmt->fetchColumn();
        
        // Recent reports (last 30 days)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_reports'] = $stmt->fetchColumn();
        
        // Average resolution time (in days)
        $stmt = $this->pdo->query("
            SELECT AVG(DATEDIFF(updated_at, created_at)) 
            FROM reports 
            WHERE status = 'fixed' AND updated_at IS NOT NULL
        ");
        $stats['avg_resolution_days'] = round($stmt->fetchColumn(), 1);
        
        return $stats;
    }
    
    /**
     * Search reports by various criteria
     * 
     * @param array $criteria Search criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of Report objects
     */
    public function search(array $criteria, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // Search by title or description
        if (!empty($criteria['query'])) {
            $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)";
            $searchTerm = '%' . $criteria['query'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by status
        if (!empty($criteria['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $criteria['status'];
        }
        
        // Filter by category
        if (!empty($criteria['category'])) {
            $sql .= " AND r.category = ?";
            $params[] = $criteria['category'];
        }
        
        // Filter by priority
        if (!empty($criteria['priority'])) {
            $sql .= " AND r.priority = ?";
            $params[] = $criteria['priority'];
        }
        
        // Filter by user
        if (!empty($criteria['user_id'])) {
            $sql .= " AND r.user_id = ?";
            $params[] = $criteria['user_id'];
        }
        
        $sql .= " ORDER BY GREATEST(r.created_at, COALESCE(r.updated_at, r.created_at)) DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reports = [];
        
        while ($reportData = $stmt->fetch()) {
            $reports[] = Report::fromDatabaseRow($reportData);
        }
        
        return $reports;
    }
    
    /**
     * Count reports matching search criteria
     * 
     * @param array $criteria Search criteria
     * @return int Number of matching reports
     */
    public function countSearch(array $criteria): int {
        $sql = "SELECT COUNT(*) FROM reports r JOIN users u ON r.user_id = u.id WHERE 1=1";
        $params = [];
        
        // Search by title or description
        if (!empty($criteria['query'])) {
            $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)";
            $searchTerm = '%' . $criteria['query'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filter by status
        if (!empty($criteria['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $criteria['status'];
        }
        
        // Filter by category
        if (!empty($criteria['category'])) {
            $sql .= " AND r.category = ?";
            $params[] = $criteria['category'];
        }
        
        // Filter by priority
        if (!empty($criteria['priority'])) {
            $sql .= " AND r.priority = ?";
            $params[] = $criteria['priority'];
        }
        
        // Filter by user
        if (!empty($criteria['user_id'])) {
            $sql .= " AND r.user_id = ?";
            $params[] = $criteria['user_id'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get reports with geo coordinates
     * 
     * @param float|null $minLat Minimum latitude
     * @param float|null $maxLat Maximum latitude
     * @param float|null $minLng Minimum longitude
     * @param float|null $maxLng Maximum longitude
     * @return array Array of Report objects with coordinates
     */
    public function findWithCoordinates(?float $minLat = null, ?float $maxLat = null, 
                                       ?float $minLng = null, ?float $maxLng = null): array {
        $sql = "SELECT r.*, u.full_name as reporter_name, u.email as reporter_email 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL";
        $params = [];
        
        if ($minLat !== null) {
            $sql .= " AND r.latitude >= ?";
            $params[] = $minLat;
        }
        
        if ($maxLat !== null) {
            $sql .= " AND r.latitude <= ?";
            $params[] = $maxLat;
        }
        
        if ($minLng !== null) {
            $sql .= " AND r.longitude >= ?";
            $params[] = $minLng;
        }
        
        if ($maxLng !== null) {
            $sql .= " AND r.longitude <= ?";
            $params[] = $maxLng;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reports = [];
        
        while ($reportData = $stmt->fetch()) {
            $reports[] = Report::fromDatabaseRow($reportData);
        }
        
        return $reports;
    }
    
    /**
     * Bulk update report status
     * 
     * @param array $reportIds Array of report IDs
     * @param string $newStatus New status
     * @return int Number of reports updated
     */
    public function bulkUpdateStatus(array $reportIds, string $newStatus): int {
        if (empty($reportIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $sql = "UPDATE reports SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
        
        $params = [$newStatus];
        $params = array_merge($params, $reportIds);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
