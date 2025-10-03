<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';
requireLogin();

$user = getCurrentUser();

// Load FPDF
require_once 'fpdf.php';

class PDF extends FPDF
{
    // Header
    function Header()
    {
        // Logo (optional, comment out if not available)
        // $this->Image('logo.png',10,6,20);
        $this->SetFont('Arial','B',18);
        $this->Cell(0,10,'CivicVoice Community Reports',0,1,'C');
        $this->SetFont('Arial','',12);
        $this->Cell(0,8,'Exported: '.date('Y-m-d H:i'),0,1,'C');
        $this->Ln(2);
        $this->SetDrawColor(100,100,100);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    // Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',9);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    // Table header
    function ReportTableHeader()
    {
        $this->SetFont('Arial','B',11);
        $this->SetFillColor(220,220,220);
        $this->Cell(35,8,'Title',1,0,'C',true);
        $this->Cell(30,8,'Category',1,0,'C',true);
        $this->Cell(30,8,'Location',1,0,'C',true);
        $this->Cell(20,8,'Priority',1,0,'C',true);
        $this->Cell(35,8,'Reporter',1,0,'C',true);
        $this->Cell(40,8,'Created At',1,1,'C',true);
    }

    // Calculate number of lines a MultiCell of width w will take
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',$txt);
        $nb = strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb)
        {
            $c = $s[$i];
            if($c=="\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += $cw[$c];
            if($l > $wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i = $sep+1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Fetch reports depending on role
if (hasAnyRole(['authority', 'admin'])) {
    $sql = "SELECT r.*, u.full_name AS reporter 
            FROM reports r
            JOIN users u ON r.user_id = u.id
            ORDER BY r.status, r.created_at DESC";
    $stmt = executeQuery($sql);
    $reports = $stmt->fetchAll();
} else {
    $sql = "SELECT r.*, u.full_name AS reporter 
            FROM reports r
            JOIN users u ON r.user_id = u.id
            WHERE r.user_id = ?
            ORDER BY r.status, r.created_at DESC";
    $stmt = executeQuery($sql, [$user['id']]);
    $reports = $stmt->fetchAll();
}

// Group reports by status
$groupedReports = ['pending' => [], 'in-progress' => [], 'fixed' => []];
foreach ($reports as $report) {
    $groupedReports[$report['status']][] = $report;
}

foreach ($groupedReports as $status => $statusReports) {
    if (empty($statusReports)) continue;

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetFillColor(180, 205, 255);
    $pdf->Cell(0, 10, ucfirst(str_replace('-', ' ', $status)) . " Reports", 0, 1, 'L', true);
    $pdf->Ln(2);

    foreach ($statusReports as $report) {
        // Prepare cell data
        $row = [
            mb_strimwidth($report['title'],0,100,'...'),
            ucfirst($report['category']),
            mb_strimwidth($report['location'],0,100,'...'),
            ucfirst($report['priority']),
            mb_strimwidth($report['reporter'],0,100,'...'),
            date('Y-m-d', strtotime($report['created_at']))
        ];
        $widths = [35, 30, 30, 20, 35, 40];
        $aligns = ['L', 'L', 'L', 'C', 'L', 'C'];
        $cellHeight = 8;

        // 1. Calculate max number of lines for this row
        $lineCounts = [];
        for ($i = 0; $i < count($row); $i++) {
            $lineCounts[$i] = $pdf->NbLines($widths[$i], $row[$i]);
        }
        $maxLines = max($lineCounts);

        // Height needed: table row + description + updated_at + some padding
        $neededHeight = ($cellHeight * $maxLines) + 12;

        // 2. Check if there is enough space, otherwise add a page
        if ($pdf->GetY() + $neededHeight > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
        }

        // ---- Render the row ----
        $pdf->ReportTableHeader();
        $pdf->SetFont('Arial', '', 10);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        for ($i = 0; $i < count($row); $i++) {
            $pdf->Rect($x, $y, $widths[$i], $cellHeight * $maxLines);
            $pdf->SetXY($x, $y);
            $cellText = $row[$i];
            $extraLines = $maxLines - $lineCounts[$i];
            if ($extraLines > 0) {
                $cellText .= str_repeat("\n", $extraLines);
            }
            $pdf->MultiCell($widths[$i], $cellHeight, $cellText, 0, $aligns[$i]);
            $x += $widths[$i];
        }
        $pdf->SetXY(10, $y + $cellHeight * $maxLines);

        // ---- Description and updated at ----
        $pdf->SetFont('Arial','I',9);
        $pdf->SetTextColor(80,80,80);
        $desc = "Description: " . $report['description'];
        $pdf->MultiCell(190, 6, $desc, 0, 'L');
        if (!empty($report['updated_at'])) {
            $pdf->Cell(190,5,"Updated At: ".$report['updated_at'],0,1,'L');
        }
        $pdf->SetTextColor(0,0,0);
        $pdf->Ln(2);
    }
    $pdf->Ln(5);
}

// Output PDF for download
$filename = hasAnyRole(['authority','admin']) ? "all_reports.pdf" : "my_reports.pdf";
$pdf->Output('D', $filename);
exit;
?>
