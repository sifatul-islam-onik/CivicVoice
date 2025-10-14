<?php
/*
 * Example of how to refactor existing files to use the new class structure
 * This shows how config.php could be updated to work with the new classes
 */

require_once 'config.php'; // Original config for database connection
require_once __DIR__ . '/classes/CivicVoiceService.php';

// Initialize the main service with database connection
$civicVoiceService = new CivicVoiceService(getDbConnection());

// Helper functions for backward compatibility with existing code

/**
 * Get current user (backward compatible)
 */
function getCurrentUser(): ?array {
    global $civicVoiceService;
    $user = $civicVoiceService->getCurrentUser();
    return $user ? $user->toArray() : null;
}

/**
 * Check if user is logged in (backward compatible)
 */
function isLoggedIn(): bool {
    global $civicVoiceService;
    return $civicVoiceService->isLoggedIn();
}

/**
 * Require login (backward compatible)
 */
function requireLogin(): void {
    global $civicVoiceService;
    if (!$civicVoiceService->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check user role (backward compatible)
 */
function hasRole(string $role): bool {
    global $civicVoiceService;
    $user = $civicVoiceService->getCurrentUser();
    return $user ? $user->hasRole($role) : false;
}

/**
 * Check if user has any of the roles (backward compatible)
 */
function hasAnyRole(array $roles): bool {
    global $civicVoiceService;
    $user = $civicVoiceService->getCurrentUser();
    return $user ? $user->hasAnyRole($roles) : false;
}

/**
 * Get user display name (backward compatible)
 */
function getUserDisplayName(): string {
    global $civicVoiceService;
    $user = $civicVoiceService->getCurrentUser();
    return $user ? $user->getDisplayName() : '';
}

/**
 * Get unread notifications (backward compatible)
 */
function getUnreadNotifications(int $userId, int $limit = 10): array {
    global $civicVoiceService;
    $notifications = $civicVoiceService->getUnreadNotifications($userId, $limit);
    
    // Convert to array format for backward compatibility
    $result = [];
    foreach ($notifications as $notification) {
        $result[] = $notification->toArray();
    }
    return $result;
}

/**
 * Create notification (backward compatible)
 */
function createNotification(int $userId, string $title, string $body): bool {
    global $civicVoiceService;
    try {
        $civicVoiceService->createNotification($userId, $title, $body);
        return true;
    } catch (Exception $e) {
        error_log('Failed to create notification: ' . $e->getMessage());
        return false;
    }
}

// Example of how dashboard.php could be refactored
function getDashboardDataForUser(array $user): array {
    global $civicVoiceService;
    
    $userObj = User::fromDatabaseRow($user);
    $stats = $civicVoiceService->getDashboardStats($userObj);
    
    return $stats;
}

// Example of how reports.php could use the new structure
function getReportsWithFilters(array $filters, int $limit = 50, int $offset = 0): array {
    global $civicVoiceService;
    
    $reports = $civicVoiceService->getAllReports($filters, $limit, $offset);
    
    // Convert to array format for existing templates
    $result = [];
    foreach ($reports as $report) {
        $reportArray = $report->toArray();
        // Add computed fields that templates expect
        $reportArray['status_display'] = $report->getStatusDisplayName();
        $reportArray['category_display'] = $report->getCategoryDisplayName();
        $reportArray['priority_display'] = $report->getPriorityDisplayName();
        $reportArray['formatted_date'] = $report->getFormattedCreatedAt();
        $reportArray['time_ago'] = $report->getTimeAgo();
        $result[] = $reportArray;
    }
    
    return $result;
}

// Example of how to handle form submissions with new classes
function handleReportSubmission(array $formData, array $user): array {
    global $civicVoiceService;
    
    try {
        // Add user ID to form data
        $formData['user_id'] = $user['id'];
        
        // Create report using service
        $report = $civicVoiceService->createReport($formData);
        
        return [
            'success' => true,
            'message' => 'Report submitted successfully',
            'report_id' => $report->getId()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error submitting report: ' . $e->getMessage()
        ];
    }
}

// Example of status update with new classes
function updateReportStatusWithNotification(int $reportId, string $newStatus, int $updatedByUserId, ?string $note = null): array {
    global $civicVoiceService;
    
    try {
        $success = $civicVoiceService->updateReportStatus($reportId, $newStatus, $updatedByUserId, $note);
        
        if ($success) {
            return [
                'success' => true,
                'message' => 'Status updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update status'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error updating status: ' . $e->getMessage()
        ];
    }
}

// Migration helper functions for existing code

/**
 * Convert old-style user array to User object
 */
function arrayToUser(array $userData): User {
    return User::fromDatabaseRow($userData);
}

/**
 * Convert User object back to array for existing templates
 */
function userToArray(User $user): array {
    return $user->toArray();
}

/**
 * Get report with all related data for existing templates
 */
function getReportForDisplay(int $reportId): ?array {
    global $civicVoiceService;
    
    $report = $civicVoiceService->findReportById($reportId);
    if (!$report) {
        return null;
    }
    
    // Get related data
    $comments = $civicVoiceService->getReportComments($reportId);
    $auditTrail = $civicVoiceService->getReportAuditTrail($reportId);
    $reporter = $civicVoiceService->findUserById($report->getUserId());
    
    // Format for existing templates
    $result = $report->toArray();
    $result['reporter_name'] = $reporter ? $reporter->getFullName() : 'Unknown';
    $result['reporter_email'] = $reporter ? $reporter->getEmail() : '';
    $result['status_display'] = $report->getStatusDisplayName();
    $result['category_display'] = $report->getCategoryDisplayName();
    $result['priority_display'] = $report->getPriorityDisplayName();
    $result['formatted_date'] = $report->getFormattedCreatedAt();
    $result['time_ago'] = $report->getTimeAgo();
    $result['has_photo'] = $report->hasPhoto();
    $result['photo_url'] = $report->getPhotoUrl();
    $result['comments'] = array_map(fn($c) => $c->toArray(), $comments);
    $result['audit_trail'] = $auditTrail;
    
    return $result;
}

?>

<!--
EXAMPLE USAGE IN EXISTING FILES:

// In dashboard.php - replace existing code with:
<?php
require_once 'bootstrap_classes.php'; // This file

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$stats = getDashboardDataForUser($user);

// Rest of the template remains the same
?>

// In reports.php - replace existing code with:
<?php
require_once 'bootstrap_classes.php';

requireLogin();
$user = getCurrentUser();

$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['category'])) {
    $filters['category'] = $_GET['category'];
}
if ($_GET['user'] === 'me') {
    $filters['user_id'] = $user['id'];
}

$reports = getReportsWithFilters($filters);

// Rest of the template remains the same, reports array has same structure
?>

// In report.php form processing:
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handleReportSubmission($_POST, $user);
    
    if ($result['success']) {
        header('Location: reports.php?success=1');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>

// For AJAX status updates:
<?php
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $result = updateReportStatusWithNotification(
        (int)$_POST['report_id'],
        $_POST['new_status'],
        $user['id'],
        $_POST['note'] ?? null
    );
    
    echo json_encode($result);
    exit;
}
?>

MIGRATION PLAN:

1. Create bootstrap_classes.php (this file) to provide backward compatibility
2. Update each existing PHP file one by one:
   - Include bootstrap_classes.php instead of config.php
   - Replace direct database queries with service calls
   - Use helper functions for common operations
   - Keep template structure the same initially
3. Gradually update templates to use object methods directly
4. Remove backward compatibility functions once all files are updated

BENEFITS OF THIS APPROACH:

1. Object-oriented design with proper encapsulation
2. Repository pattern for clean data access
3. Service layer for business logic
4. Better validation and error handling
5. Consistent API across all operations
6. Easier testing and maintenance
7. Type safety with proper class definitions
8. Audit trail and status tracking built-in
9. Automated notifications
10. Backward compatibility during migration
-->