<?php

require_once __DIR__ . '/Comment.php';

/**
 * CommentRepository Class
 * 
 * Handles database operations for Comment entities following the Repository pattern.
 * Provides data access layer abstraction for comment-related operations.
 */
class CommentRepository {
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
     * Find comment by ID
     * 
     * @param int $id Comment ID
     * @return Comment|null Comment object or null if not found
     */
    public function findById(int $id): ?Comment {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name as commenter_name, u.email as commenter_email
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $commentData = $stmt->fetch();
        
        return $commentData ? Comment::fromDatabaseRow($commentData) : null;
    }
    
    /**
     * Get comments for a report
     * 
     * @param int $reportId Report ID
     * @param int $limit Number of comments to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Comment objects with user data
     */
    public function findByReportId(int $reportId, int $limit = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name as commenter_name, u.email as commenter_email, u.role as commenter_role
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.report_id = ?
            ORDER BY c.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$reportId, $limit, $offset]);
        $comments = [];
        
        while ($commentData = $stmt->fetch()) {
            $comments[] = Comment::fromDatabaseRow($commentData);
        }
        
        return $comments;
    }
    
    /**
     * Get comments by user ID
     * 
     * @param int $userId User ID
     * @param int $limit Number of comments to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of Comment objects
     */
    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name as commenter_name, u.email as commenter_email,
                   r.title as report_title
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN reports r ON c.report_id = r.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $comments = [];
        
        while ($commentData = $stmt->fetch()) {
            $comments[] = Comment::fromDatabaseRow($commentData);
        }
        
        return $comments;
    }
    
    /**
     * Get recent comments
     * 
     * @param int $days Number of days to look back
     * @param int $limit Number of comments to retrieve
     * @return array Array of Comment objects
     */
    public function findRecentComments(int $days = 7, int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name as commenter_name, u.email as commenter_email,
                   r.title as report_title
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN reports r ON c.report_id = r.id
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY c.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$days, $limit]);
        $comments = [];
        
        while ($commentData = $stmt->fetch()) {
            $comments[] = Comment::fromDatabaseRow($commentData);
        }
        
        return $comments;
    }
    
    /**
     * Count comments for a report
     * 
     * @param int $reportId Report ID
     * @return int Number of comments
     */
    public function countByReportId(int $reportId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comments WHERE report_id = ?");
        $stmt->execute([$reportId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Count comments by user
     * 
     * @param int $userId User ID
     * @return int Number of comments
     */
    public function countByUserId(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Save comment (insert or update)
     * 
     * @param Comment $comment Comment object
     * @return Comment Updated comment object with ID
     */
    public function save(Comment $comment): Comment {
        if ($comment->getId()) {
            return $this->update($comment);
        } else {
            return $this->insert($comment);
        }
    }
    
    /**
     * Insert new comment
     * 
     * @param Comment $comment Comment object
     * @return Comment Comment object with new ID
     */
    public function insert(Comment $comment): Comment {
        $data = $comment->toDatabaseArray();
        
        $sql = "INSERT INTO comments (report_id, user_id, comment_text, created_at) 
                VALUES (:report_id, :user_id, :comment_text, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $comment->setId((int)$this->pdo->lastInsertId());
        $comment->setCreatedAt(date('Y-m-d H:i:s'));
        
        return $comment;
    }
    
    /**
     * Update existing comment
     * 
     * @param Comment $comment Comment object
     * @return Comment Updated comment object
     */
    public function update(Comment $comment): Comment {
        if (!$comment->getId()) {
            throw new InvalidArgumentException('Cannot update comment without ID');
        }
        
        $stmt = $this->pdo->prepare("UPDATE comments SET comment_text = ? WHERE id = ?");
        $stmt->execute([$comment->getCommentText(), $comment->getId()]);
        
        return $comment;
    }
    
    /**
     * Delete comment by ID
     * 
     * @param int $id Comment ID
     * @return bool True if deleted successfully
     */
    public function deleteById(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }
    
    /**
     * Delete comment
     * 
     * @param Comment $comment Comment object
     * @return bool True if deleted successfully
     */
    public function delete(Comment $comment): bool {
        if (!$comment->getId()) {
            return false;
        }
        
        return $this->deleteById($comment->getId());
    }
    
    /**
     * Delete all comments for a report
     * 
     * @param int $reportId Report ID
     * @return int Number of comments deleted
     */
    public function deleteByReportId(int $reportId): int {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE report_id = ?");
        $stmt->execute([$reportId]);
        return $stmt->rowCount();
    }
    
    /**
     * Create and save a comment
     * 
     * @param int $reportId Report ID
     * @param int $userId User ID
     * @param string $commentText Comment text
     * @return Comment Created comment object
     */
    public function create(int $reportId, int $userId, string $commentText): Comment {
        $comment = Comment::createFiltered($reportId, $userId, $commentText);
        return $this->insert($comment);
    }
    
    /**
     * Get comment statistics
     * 
     * @return array Comment statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total comments
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM comments");
        $stats['total_comments'] = $stmt->fetchColumn();
        
        // Recent comments (last 30 days)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_comments'] = $stmt->fetchColumn();
        
        // Most active commenters (top 10)
        $stmt = $this->pdo->query("
            SELECT u.full_name, COUNT(*) as comment_count 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            GROUP BY c.user_id, u.full_name 
            ORDER BY comment_count DESC 
            LIMIT 10
        ");
        $stats['top_commenters'] = $stmt->fetchAll();
        
        // Reports with most comments (top 10)
        $stmt = $this->pdo->query("
            SELECT r.title, COUNT(*) as comment_count 
            FROM comments c 
            JOIN reports r ON c.report_id = r.id 
            GROUP BY c.report_id, r.title 
            ORDER BY comment_count DESC 
            LIMIT 10
        ");
        $stats['most_commented_reports'] = $stmt->fetchAll();
        
        // Average comments per report
        $stmt = $this->pdo->query("
            SELECT AVG(comment_count) as avg_comments_per_report
            FROM (
                SELECT COUNT(*) as comment_count 
                FROM comments 
                GROUP BY report_id
            ) as report_comments
        ");
        $stats['avg_comments_per_report'] = round($stmt->fetchColumn(), 2);
        
        return $stats;
    }
    
    /**
     * Search comments
     * 
     * @param array $criteria Search criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Array of Comment objects
     */
    public function search(array $criteria, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT c.*, u.full_name as commenter_name, u.email as commenter_email,
                       r.title as report_title
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                JOIN reports r ON c.report_id = r.id 
                WHERE 1=1";
        $params = [];
        
        // Search by comment text
        if (!empty($criteria['query'])) {
            $sql .= " AND c.comment_text LIKE ?";
            $params[] = '%' . $criteria['query'] . '%';
        }
        
        // Filter by user
        if (!empty($criteria['user_id'])) {
            $sql .= " AND c.user_id = ?";
            $params[] = $criteria['user_id'];
        }
        
        // Filter by report
        if (!empty($criteria['report_id'])) {
            $sql .= " AND c.report_id = ?";
            $params[] = $criteria['report_id'];
        }
        
        // Filter by date range
        if (!empty($criteria['date_from'])) {
            $sql .= " AND c.created_at >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND c.created_at <= ?";
            $params[] = $criteria['date_to'];
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $comments = [];
        
        while ($commentData = $stmt->fetch()) {
            $comments[] = Comment::fromDatabaseRow($commentData);
        }
        
        return $comments;
    }
    
    /**
     * Get comments that might need moderation
     * 
     * @param int $limit Results limit
     * @return array Array of Comment objects that might contain sensitive content
     */
    public function findForModeration(int $limit = 20): array {
        // This is a simplified approach - in production, you might use more sophisticated content filtering
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.full_name as commenter_name, u.email as commenter_email,
                   r.title as report_title
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            JOIN reports r ON c.report_id = r.id 
            WHERE c.comment_text REGEXP 'spam|scam|fake|fraud|hack|illegal|abuse|harassment|threat|violence'
               OR LENGTH(c.comment_text) > 1000
               OR c.comment_text REGEXP 'https?://[^[:space:]]+'
            ORDER BY c.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $comments = [];
        
        while ($commentData = $stmt->fetch()) {
            $comment = Comment::fromDatabaseRow($commentData);
            // Only include if it actually contains sensitive content (double-check)
            if ($comment->containsSensitiveContent() || $comment->hasLinks() || $comment->isLong()) {
                $comments[] = $comment;
            }
        }
        
        return $comments;
    }
    
    /**
     * Bulk delete comments
     * 
     * @param array $commentIds Array of comment IDs
     * @return int Number of comments deleted
     */
    public function bulkDelete(array $commentIds): int {
        if (empty($commentIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "DELETE FROM comments WHERE id IN ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($commentIds);
        return $stmt->rowCount();
    }
}