<?php

// Include all necessary classes
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Report.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/Comment.php';
require_once __DIR__ . '/StatusUpdate.php';
require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/NotificationRepository.php';
require_once __DIR__ . '/CommentRepository.php';
require_once __DIR__ . '/StatusUpdateRepository.php';

/**
 * CivicVoiceService Class
 * 
 * Main service class that provides a unified interface to all CivicVoice functionality.
 * Acts as a facade for all repositories and services, simplifying integration.
 */
class CivicVoiceService {
    private $pdo;
    private $authService;
    private $userRepository;
    private $reportRepository;
    private $notificationRepository;
    private $commentRepository;
    private $statusUpdateRepository;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        
        // Initialize services and repositories
        $this->authService = new AuthService($pdo);
        $this->userRepository = new UserRepository($pdo);
        $this->reportRepository = new ReportRepository($pdo);
        $this->notificationRepository = new NotificationRepository($pdo);
        $this->commentRepository = new CommentRepository($pdo);
        $this->statusUpdateRepository = new StatusUpdateRepository($pdo);
    }
    
    // Auth Service Methods
    
    /**
     * Authenticate user
     */
    public function authenticate(string $usernameOrEmail, string $password, bool $remember = false): array {
        return $this->authService->authenticate($usernameOrEmail, $password, $remember);
    }
    
    /**
     * Register new user
     */
    public function register(array $userData): array {
        $result = $this->authService->register($userData);
        
        // Create welcome notification if registration successful
        if ($result['success'] && $result['user']) {
            try {
                $this->notificationRepository->createWelcomeNotification(
                    $result['user']->getId(),
                    $result['user']->getFullName()
                );
            } catch (Exception $e) {
                // Log error but don't fail registration
                error_log('Failed to create welcome notification: ' . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?User {
        return $this->authService->getCurrentUser();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool {
        return $this->authService->isLoggedIn();
    }
    
    /**
     * Log out current user
     */
    public function logout(): bool {
        return $this->authService->logout();
    }
    
    /**
     * Change user password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array {
        return $this->authService->changePassword($user, $currentPassword, $newPassword);
    }
    
    // User Management Methods
    
    /**
     * Find user by ID
     */
    public function findUserById(int $id): ?User {
        return $this->userRepository->findById($id);
    }
    
    /**
     * Get active authorities
     */
    public function getActiveAuthorities(): array {
        return $this->userRepository->getActiveAuthorities();
    }
    
    /**
     * Save user
     */
    public function saveUser(User $user): User {
        return $this->userRepository->save($user);
    }
    
    // Report Management Methods
    
    /**
     * Create new report
     */
    public function createReport(array $reportData): Report {
        $report = new Report($reportData);
        $report = $this->reportRepository->save($report);
        
        // Notify authorities about new report
        $this->notifyAuthoritiesAboutNewReport($report);
        
        return $report;
    }
    
    /**
     * Find report by ID
     */
    public function findReportById(int $id): ?Report {
        return $this->reportRepository->findById($id);
    }
    
    /**
     * Get all reports with filters
     */
    public function getAllReports(array $filters = [], int $limit = 50, int $offset = 0): array {
        return $this->reportRepository->findAll($filters, $limit, $offset);
    }
    
    /**
     * Get reports by user
     */
    public function getReportsByUser(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array {
        return $this->reportRepository->findByUserId($userId, $filters, $limit, $offset);
    }
    
    /**
     * Update report status
     */
    public function updateReportStatus(int $reportId, string $newStatus, int $updatedByUserId, ?string $note = null): bool {
        $report = $this->reportRepository->findById($reportId);
        if (!$report) {
            return false;
        }
        
        $oldStatus = $report->getStatus();
        
        // Update report status
        $success = $this->reportRepository->updateStatus($reportId, $newStatus);
        
        if ($success) {
            // Create status update record
            $this->statusUpdateRepository->create($reportId, $updatedByUserId, $oldStatus, $newStatus, $note);
            
            // Notify report owner about status change
            $this->notifyReportOwnerStatusChange($report, $oldStatus, $newStatus);
        }
        
        return $success;
    }
    
    /**
     * Delete report
     */
    public function deleteReport(int $reportId): bool {
        return $this->reportRepository->deleteById($reportId);
    }
    
    // Notification Methods
    
    /**
     * Get unread notifications for user
     */
    public function getUnreadNotifications(int $userId, int $limit = 10): array {
        return $this->notificationRepository->getUnreadNotifications($userId, $limit);
    }
    
    /**
     * Get all notifications for user
     */
    public function getAllNotifications(int $userId, int $limit = 50, int $offset = 0): array {
        return $this->notificationRepository->getAllNotifications($userId, $limit, $offset);
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId): bool {
        return $this->notificationRepository->markAsRead($notificationId);
    }
    
    /**
     * Count unread notifications
     */
    public function countUnreadNotifications(int $userId): int {
        return $this->notificationRepository->countUnreadForUser($userId);
    }
    
    /**
     * Create notification
     */
    public function createNotification(int $userId, string $title, string $body): Notification {
        return $this->notificationRepository->create($userId, $title, $body);
    }
    
    // Comment Methods
    
    /**
     * Get comments for report
     */
    public function getReportComments(int $reportId, int $limit = 50, int $offset = 0): array {
        return $this->commentRepository->findByReportId($reportId, $limit, $offset);
    }
    
    /**
     * Add comment to report
     */
    public function addComment(int $reportId, int $userId, string $commentText): Comment {
        $comment = $this->commentRepository->create($reportId, $userId, $commentText);
        
        // Notify report owner about new comment (if not commenting on own report)
        $report = $this->reportRepository->findById($reportId);
        if ($report && $report->getUserId() !== $userId) {
            $commenter = $this->userRepository->findById($userId);
            if ($commenter) {
                $this->notificationRepository->createNewCommentNotification(
                    $report->getUserId(),
                    $report->getTitle(),
                    $commenter->getFullName()
                );
            }
        }
        
        return $comment;
    }
    
    /**
     * Delete comment
     */
    public function deleteComment(int $commentId): bool {
        return $this->commentRepository->deleteById($commentId);
    }
    
    // Status Update Methods
    
    /**
     * Get status updates for report
     */
    public function getReportStatusUpdates(int $reportId, int $limit = 50, int $offset = 0): array {
        return $this->statusUpdateRepository->findByReportId($reportId, $limit, $offset);
    }
    
    /**
     * Get audit trail for report
     */
    public function getReportAuditTrail(int $reportId): array {
        return $this->statusUpdateRepository->getAuditTrail($reportId);
    }
    
    // Statistics Methods
    
    /**
     * Get dashboard statistics for user
     */
    public function getDashboardStats(User $user): array {
        $stats = [];
        
        if ($user->isCitizen()) {
            // Citizen stats
            $myReports = $this->reportRepository->countSearch(['user_id' => $user->getId()]);
            $pending = $this->reportRepository->countSearch(['user_id' => $user->getId(), 'status' => 'pending']);
            $inProgress = $this->reportRepository->countSearch(['user_id' => $user->getId(), 'status' => 'in-progress']);
            $fixed = $this->reportRepository->countSearch(['user_id' => $user->getId(), 'status' => 'fixed']);
            $rejected = $this->reportRepository->countSearch(['user_id' => $user->getId(), 'status' => 'rejected']);
            
            $stats = compact('myReports', 'pending', 'inProgress', 'fixed', 'rejected');
            
        } elseif ($user->isAuthority()) {
            // Authority stats
            $reportStats = $this->reportRepository->getStatistics();
            $stats = $reportStats['by_status'] ?? [];
            $stats['total_reports'] = $reportStats['total_reports'] ?? 0;
            $stats['active_reports'] = $reportStats['active_reports'] ?? 0;
            
        } elseif ($user->isAdmin()) {
            // Admin stats
            $userStats = $this->userRepository->getStatistics();
            $reportStats = $this->reportRepository->getStatistics();
            $notificationStats = $this->notificationRepository->getStatistics();
            
            $stats = array_merge($userStats, $reportStats, $notificationStats);
        }
        
        return $stats;
    }
    
    // Helper Methods
    
    /**
     * Notify authorities about new report
     */
    private function notifyAuthoritiesAboutNewReport(Report $report): void {
        try {
            $authorities = $this->userRepository->getActiveAuthorities();
            $reportOwner = $this->userRepository->findById($report->getUserId());
            
            if ($reportOwner) {
                foreach ($authorities as $authority) {
                    $this->notificationRepository->createNewReportNotification(
                        $authority->getId(),
                        $report->getTitle(),
                        $reportOwner->getFullName()
                    );
                }
            }
        } catch (Exception $e) {
            error_log('Failed to notify authorities about new report: ' . $e->getMessage());
        }
    }
    
    /**
     * Notify report owner about status change
     */
    private function notifyReportOwnerStatusChange(Report $report, string $oldStatus, string $newStatus): void {
        try {
            $this->notificationRepository->createReportStatusNotification(
                $report->getUserId(),
                $report->getTitle(),
                $oldStatus,
                $newStatus
            );
        } catch (Exception $e) {
            error_log('Failed to notify report owner about status change: ' . $e->getMessage());
        }
    }
    
    // Search Methods
    
    /**
     * Search reports
     */
    public function searchReports(array $criteria, int $limit = 50, int $offset = 0): array {
        return $this->reportRepository->search($criteria, $limit, $offset);
    }
    
    /**
     * Search users
     */
    public function searchUsers(array $criteria, int $limit = 50, int $offset = 0): array {
        return $this->userRepository->search($criteria, $limit, $offset);
    }
    
    /**
     * Search notifications
     */
    public function searchNotifications(array $criteria, int $limit = 50, int $offset = 0): array {
        return $this->notificationRepository->search($criteria, $limit, $offset);
    }
    
    // Utility Methods
    
    /**
     * Check if user has permission to perform action
     */
    public function hasPermission(User $user, string $action, $resource = null): bool {
        switch ($action) {
            case 'create_report':
                return $user->isCitizen();
                
            case 'update_report_status':
                return $user->isAuthority() || $user->isAdmin();
                
            case 'delete_report':
                if ($user->isAdmin()) return true;
                if ($resource instanceof Report) {
                    return $resource->getUserId() === $user->getId();
                }
                return false;
                
            case 'manage_users':
                return $user->isAdmin();
                
            case 'moderate_comments':
                return $user->isAuthority() || $user->isAdmin();
                
            default:
                return false;
        }
    }
    
    /**
     * Get service repositories for advanced operations
     */
    public function getUserRepository(): UserRepository {
        return $this->userRepository;
    }
    
    public function getReportRepository(): ReportRepository {
        return $this->reportRepository;
    }
    
    public function getNotificationRepository(): NotificationRepository {
        return $this->notificationRepository;
    }
    
    public function getCommentRepository(): CommentRepository {
        return $this->commentRepository;
    }
    
    public function getStatusUpdateRepository(): StatusUpdateRepository {
        return $this->statusUpdateRepository;
    }
    
    public function getAuthService(): AuthService {
        return $this->authService;
    }
}