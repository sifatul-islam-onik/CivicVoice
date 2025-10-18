<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';
requireLogin();

if (!hasRole('admin')) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

require_once 'fpdf.php';

// Only support the full combined export. Older type parameters will be ignored and routed to 'full'.
$type = 'full';

class AdminPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,'CivicVoice Export',0,1,'C');
        $this->SetFont('Arial','',11);
        $this->Cell(0,8,'Generated: '.date('Y-m-d H:i'),0,1,'C');
        $this->Ln(4);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',9);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new AdminPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Configure error logging to a local file for diagnostics (safe to keep in dev)
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'export_admin_error.log');

$pdo = getDbConnection();

if ($type === 'reports') {
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Community Reports',0,1);
    $pdf->Ln(2);

    $stmt = executeQuery("SELECT r.*, u.full_name AS reporter FROM reports r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
    $rows = $stmt->fetchAll();

    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(40,8,'Title',1,0,'C',true);
    $pdf->Cell(30,8,'Status',1,0,'C',true);
    $pdf->Cell(30,8,'Category',1,0,'C',true);
    $pdf->Cell(30,8,'Reporter',1,0,'C',true);
    $pdf->Cell(40,8,'Created At',1,1,'C',true);

    $pdf->SetFont('Arial','',10);
    foreach ($rows as $r) {
        $pdf->Cell(40,8,mb_strimwidth($r['title'],0,30,'...'),1);
        $pdf->Cell(30,8,$r['status'],1);
        $pdf->Cell(30,8,ucfirst($r['category']),1);
        $pdf->Cell(30,8,mb_strimwidth($r['reporter'],0,20,'...'),1);
        $pdf->Cell(40,8,date('Y-m-d',strtotime($r['created_at'])),1,1);
    }

    $filename = 'civicvoice_reports_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

if ($type === 'authorities') {
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Authority Performance',0,1);
    $pdf->Ln(4);

    // Gather authority performance
    $stmt = executeQuery("SELECT u.id, u.full_name, u.username, u.is_active,
        (SELECT COUNT(DISTINCT r.id) FROM reports r JOIN status_updates su ON r.id = su.report_id WHERE su.updated_by_user_id = u.id) AS reports_handled,
        (SELECT COUNT(*) FROM status_updates su WHERE su.updated_by_user_id = u.id) AS updates_made
        FROM users u WHERE u.role = 'authority'");
    $rows = $stmt->fetchAll();

    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(60,8,'Authority',1,0,'C',true);
    $pdf->Cell(30,8,'Handled',1,0,'C',true);
    $pdf->Cell(30,8,'Updates',1,0,'C',true);
    $pdf->Cell(40,8,'Status',1,1,'C',true);

    $pdf->SetFont('Arial','',10);
    foreach ($rows as $r) {
        $pdf->Cell(60,8,mb_strimwidth($r['full_name'].' ('.$r['username'].')',0,35,'...'),1);
        $pdf->Cell(30,8,$r['reports_handled'],1,0,'C');
        $pdf->Cell(30,8,$r['updates_made'],1,0,'C');
        $pdf->Cell(40,8,$r['is_active'] ? 'Active' : 'Inactive',1,1,'C');
    }

    $filename = 'civicvoice_authority_performance_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

if ($type === 'system') {
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'System Analytics',0,1);
    $pdf->Ln(4);

    // Basic system metrics
    $totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $resolvedThisMonth = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed' AND MONTH(updated_at) = MONTH(CURRENT_DATE())")->fetchColumn();
    $avgResolution = executeQuery("SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)),1) FROM reports WHERE status = 'fixed'")->fetchColumn();
    $activeAuthorities = executeQuery("SELECT COUNT(*) FROM users WHERE role = 'authority' AND is_active = 1")->fetchColumn();

    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,'Total Reports: ' . $totalReports,0,1);
    $pdf->Cell(0,8,'Resolved This Month: ' . $resolvedThisMonth,0,1);
    $pdf->Cell(0,8,'Average Resolution Time: ' . ($avgResolution ?: 'N/A') . ' days',0,1);
    $pdf->Cell(0,8,'Active Authorities: ' . $activeAuthorities,0,1);

    $filename = 'civicvoice_system_analytics_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

// Full combined export with charts and detailed sections
try {
    if ($type === 'full') {
    // Section: Cover (banner with inner padding and horizontal margins)
    $pdf->SetFont('Arial','B',20);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFillColor(40, 116, 166); // deep blue
    // Banner dimensions with horizontal margins
    // Determine left margin from current X position (FPDF keeps X at left margin after AddPage)
    $leftMargin = $pdf->GetX();
    $pageW = $pdf->GetPageWidth();
    // Assume symmetric left/right margins and compute banner inner width accordingly
    $bannerW = $pageW - ($leftMargin * 2);
    $bannerH = 36; // mm total banner height
    // Draw filled rectangle for banner spanning full page width
    $yTop = $pdf->GetY();
    $pdf->Rect(0, $yTop, $pageW, $bannerH, 'F');
    // Move inside banner to place text (centered within inner margins)
    $pdf->SetXY($leftMargin, $yTop + 6);
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell($bannerW, 10, 'CivicVoice - Full Performance Report', 0, 1, 'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Cell($bannerW, 8, 'Generated: '.date('Y-m-d H:i'), 0, 1, 'C');
    // Move cursor below the banner
    $pdf->SetY($yTop + $bannerH + 2);
    $pdf->SetTextColor(0,0,0);

    // Section: System summary (continue on same page when possible)
    $pdf->SetFont('Arial','B',16);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFillColor(100, 181, 246); // light blue
    $pdf->Cell(0,10,'System Summary',0,1,'L',true);
    $pdf->Ln(4);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial','',12);
    $totalReports = executeQuery("SELECT COUNT(*) FROM reports")->fetchColumn();
    $resolvedThisMonth = executeQuery("SELECT COUNT(*) FROM reports WHERE status = 'fixed' AND MONTH(updated_at) = MONTH(CURRENT_DATE())")->fetchColumn();
    $avgResolution = executeQuery("SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)),1) FROM reports WHERE status = 'fixed'")->fetchColumn();
    $activeAuthorities = executeQuery("SELECT COUNT(*) FROM users WHERE role = 'authority' AND is_active = 1")->fetchColumn();

    $pdf->Cell(0,8,'Total Reports: ' . $totalReports,0,1);
    $pdf->Cell(0,8,'Resolved This Month: ' . $resolvedThisMonth,0,1);
    $pdf->Cell(0,8,'Average Resolution Time: ' . ($avgResolution ?: 'N/A') . ' days',0,1);
    $pdf->Cell(0,8,'Active Authorities: ' . $activeAuthorities,0,1);

    // Generate charts: monthly bar chart and category pie chart
    // Bar chart data: reports per month for last 6 months
    $monthsStmt = executeQuery("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt FROM reports WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC");
    $months = $monthsStmt->fetchAll();
    $barLabels = [];
    $barValues = [];
    foreach ($months as $m) { $barLabels[] = $m['ym']; $barValues[] = (int)$m['cnt']; }

    // Pie chart data: category breakdown
    $catStmt = executeQuery("SELECT category, COUNT(*) as cnt FROM reports GROUP BY category");
    $catRows = $catStmt->fetchAll();
    $pieLabels = [];
    $pieValues = [];
    foreach ($catRows as $c) { $pieLabels[] = $c['category']; $pieValues[] = (int)$c['cnt']; }

    // Check for GD availability before attempting to create charts
    $gdAvailable = function_exists('gd_info');

    // Helper: create bar chart PNG
    function createBarChart($labels, $values, $width = 600, $height = 300) {
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255,255,255);
        $bg = imagecolorallocate($img, 245,245,250);
        $barColor = imagecolorallocate($img, 30, 136, 229); // blue
        $axisColor = imagecolorallocate($img, 120,120,120);
        $textColor = imagecolorallocate($img, 33,33,33);
        imagefilledrectangle($img,0,0,$width,$height,$bg);

        $padding = 40;
        $chartW = $width - ($padding * 2);
        $chartH = $height - ($padding * 2);
        $max = max($values) > 0 ? max($values) : 1;
        $barW = ($chartW / count($values)) * 0.6;
        $gap = ($chartW / count($values)) * 0.4;

        // Draw axes
        imageline($img, $padding, $padding, $padding, $padding+$chartH, $axisColor);
        imageline($img, $padding, $padding+$chartH, $padding+$chartW, $padding+$chartH, $axisColor);

        // Bars
        for ($i = 0; $i < count($values); $i++) {
            $x1 = $padding + ($i * ($barW + $gap)) + ($gap/2);
            $barH = ($values[$i] / $max) * ($chartH - 20);
            $y1 = $padding + $chartH - $barH;
            imagefilledrectangle($img, $x1, $y1, $x1 + $barW, $padding + $chartH, $barColor);
            // Label
            imagestring($img, 3, $x1, $padding + $chartH + 6, $labels[$i], $textColor);
        }

        // Y axis ticks
        for ($t = 0; $t <= 5; $t++) {
            $y = $padding + $chartH - ($t/5)*($chartH);
            $val = round($max * ($t/5));
            imagestring($img, 3, 4, $y-6, $val, $textColor);
            imageline($img, $padding, $y, $padding+$chartW, $y, imagecolorallocate($img,230,230,230));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bar') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
    }

    // Helper: create pie chart PNG
    function createPieChart($labels, $values, $width = 400, $height = 300) {
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 255,255,255);
        imagefilledrectangle($img,0,0,$width,$height,$bg);

        $cx = $width/2;
        $cy = $height/2;
        $r = min($width,$height) * 0.35;
        $total = array_sum($values) > 0 ? array_sum($values) : 1;
        $start = 0;
        $colors = [ [66,133,244], [219,68,55], [244,180,0], [15,157,88], [171,71,188], [0,150,136] ];

        for ($i = 0; $i < count($values); $i++) {
            $angle = ($values[$i] / $total) * 360;
            $col = $colors[$i % count($colors)];
            $color = imagecolorallocate($img, $col[0], $col[1], $col[2]);
            imagefilledarc($img, $cx, $cy, $r*2, $r*2, $start, $start+$angle, $color, IMG_ARC_PIE);
            $start += $angle;
        }

        // Legend
        $lx = $width - 140;
        $ly = 20;
        for ($i = 0; $i < count($labels); $i++) {
            $col = $colors[$i % count($colors)];
            $color = imagecolorallocate($img, $col[0], $col[1], $col[2]);
            imagefilledrectangle($img, $lx, $ly + ($i*20), $lx+12, $ly+12 + ($i*20), $color);
            imagestring($img, 3, $lx+18, $ly - 2 + ($i*20), $labels[$i] . ' (' . $values[$i] . ')', imagecolorallocate($img,0,0,0));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pie') . '.png';
        imagepng($img, $tmp);
        imagedestroy($img);
        return $tmp;
    }
    $barFile = null;
    $pieFile = null;

    // Add charts to PDF (only if GD is available and chart generation succeeded)
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Trends & Category Breakdown',0,1);
    if ($gdAvailable) {
        try {
            $barFile = createBarChart($barLabels, $barValues);
            $pieFile = createPieChart($pieLabels, $pieValues);
            // If chart files exist, insert them and advance the cursor by the image height so following content flows naturally
            if ($barFile && file_exists($barFile)) {
                $barHeight = 60; // mm
                $pdf->Image($barFile, null, null, 170, $barHeight);
                $pdf->Ln($barHeight + 4);
            }
            if ($pieFile && file_exists($pieFile)) {
                $pieHeight = 60; // mm
                $pdf->Image($pieFile, null, null, 100, $pieHeight);
                $pdf->Ln($pieHeight + 4);
            }
        } catch (Exception $e) {
            // Chart generation failed; include a note and continue
            $pdf->SetFont('Arial','',10);
            $pdf->MultiCell(0,6,'Note: Chart generation failed on the server. The export includes textual summaries only.',0,'L');
            $pdf->Ln(6);
        }
    } else {
        $pdf->SetFont('Arial','',10);
        $pdf->MultiCell(0,6,'Charting is not available on this server (GD extension missing). The PDF includes textual summaries only.',0,'L');
        $pdf->Ln(6);
    }

    // Section: Authority performance (detailed)
    // Continue on the same page when possible so sections take only the space they need
    $pdf->SetFont('Arial','B',14);
    $pdf->SetFillColor(255, 204, 128); // warm
    $pdf->Cell(0,10,'Authority Performance (Detailed)',0,1,'L',true);
    $pdf->Ln(6);

    $stmt = executeQuery("SELECT u.id, u.full_name, u.username, u.is_active,
        (SELECT COUNT(DISTINCT r.id) FROM reports r JOIN status_updates su ON r.id = su.report_id WHERE su.updated_by_user_id = u.id) AS reports_handled,
        (SELECT COUNT(*) FROM status_updates su WHERE su.updated_by_user_id = u.id) AS updates_made
        FROM users u WHERE u.role = 'authority'");
    $rows = $stmt->fetchAll();

    $pdf->SetFont('Arial','',11);
    foreach ($rows as $r) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,6,$r['full_name'] . ' (' . $r['username'] . ')',0,1);
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0,6,'Status: ' . ($r['is_active'] ? 'Active' : 'Inactive'),0,1);
        $pdf->Cell(0,6,'Reports handled: ' . $r['reports_handled'] . ' | Updates: ' . $r['updates_made'],0,1);
        $pdf->Ln(4);
    }

    // Cleanup temp images
    @unlink($barFile);
    @unlink($pieFile);

        $filename = 'civicvoice_full_report_' . date('Ymd_His') . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }
} catch (Throwable $e) {
    // Log the exception for diagnostics and show a friendly error
    error_log('Export admin error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    // Ensure a minimal response so the browser doesn't get a blank page
    header('Content-Type: text/plain; charset=utf-8');
    echo "An error occurred while generating the export. The error has been logged.\n";
    echo 'Message: ' . $e->getMessage();
    exit;
}

// Default: redirect back
header('Location: dashboard.php');
exit;
?>