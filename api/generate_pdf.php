<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo 'No submission ID provided';
    exit;
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
$stmt->execute([$id]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    echo 'Submission not found';
    exit;
}

// Parse form data
$formData = null;
if ($submission['form_data']) {
    $formData = json_decode($submission['form_data'], true);
}

if (!$formData) {
    // If no form data, just redirect to the original file
    header('Location: view_file.php?file=' . urlencode($submission['filename']));
    exit;
}

// Generate HTML content
$barangay = htmlspecialchars($formData['barangay'] ?? '');
$date = htmlspecialchars($formData['date'] ?? '');
$period = htmlspecialchars($formData['period'] ?? '');
$category = $submission['category'];

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 40px;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }
        .republic {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .city {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .office {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-top: 10px;
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .separator {
            border-top: 2px solid #000;
            margin: 15px 0;
        }
        .field {
            margin: 10px 0;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .field-value {
            display: inline-block;
        }
        .section-title {
            font-weight: bold;
            font-size: 12pt;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
        }
        .textarea-value {
            white-space: pre-wrap;
            margin-left: 20px;
            margin-top: 5px;
        }
        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
        }
        .signature-box {
            text-align: center;
        }
        .signature-image {
            width: 150px;
            height: 60px;
            object-fit: contain;
            margin: 10px auto;
            display: block;
        }
        .signature-line {
            border-bottom: 2px solid #000;
            width: 200px;
            margin: 10px auto 5px;
            font-weight: bold;
        }
        .signature-title {
            font-size: 10pt;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../images/Mbnlg.png" class="logo" alt="Logo">
        <div class="republic">REPUBLIC OF THE PHILIPPINES</div>
        <div class="city">MUNICIPALITY OF MABINI, BATANGAS</div>
        <div class="office">BARANGAY ' . strtoupper($barangay) . '</div>
    </div>
    
    <div class="separator"></div>
    
    <div class="doc-title">';

// Document title based on category
if ($category === 'administrative') {
    $html .= 'ADMINISTRATIVE DOCUMENT';
} elseif ($category === 'resolutions') {
    $html .= 'BARANGAY RESOLUTION/ORDINANCE';
} elseif ($category === 'plans') {
    $html .= 'PLANS AND BUDGET';
} elseif ($category === 'peace') {
    $html .= 'PEACE AND ORDER REPORT';
} elseif ($category === 'welfare') {
    $html .= 'SOCIAL WELFARE REPORT';
} elseif ($category === 'sk') {
    $html .= 'SANGGUNIANG KABATAAN REPORT';
} else {
    $html .= htmlspecialchars($formData['docTitle'] ?? 'OTHER DOCUMENT');
}

$html .= '</div>
    
    <div class="field">
        <span class="field-label">Date:</span>
        <span class="field-value">' . $date . '</span>
    </div>
    <div class="field">
        <span class="field-label">Reporting Period:</span>
        <span class="field-value">' . $period . '</span>
    </div>
    
    <div class="section-title">Document Details</div>';

// Add category-specific fields
if ($category === 'administrative') {
    $html .= '
    <div class="field">
        <span class="field-label">Document Type:</span>
        <span class="field-value">' . htmlspecialchars($formData['docType'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Document Title:</span>
        <span class="field-value">' . htmlspecialchars($formData['docNumber'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Date Issued:</span>
        <span class="field-value">' . htmlspecialchars($formData['issuedDate'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Recipient:</span>
        <span class="field-value">' . htmlspecialchars($formData['recipient'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Purpose:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['purpose'] ?? 'N/A')) . '</div>
    </div>';
    if (!empty($formData['remarks'])) {
        $html .= '
    <div class="field">
        <span class="field-label">Remarks:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['remarks'])) . '</div>
    </div>';
    }
} elseif ($category === 'resolutions') {
    $html .= '
    <div class="field">
        <span class="field-label">Document Type:</span>
        <span class="field-value">' . htmlspecialchars($formData['docType'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Number:</span>
        <span class="field-value">' . htmlspecialchars($formData['number'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Date Passed:</span>
        <span class="field-value">' . htmlspecialchars($formData['datePassed'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Title:</span>
        <span class="field-value">' . htmlspecialchars($formData['title'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Author/Sponsor:</span>
        <span class="field-value">' . htmlspecialchars($formData['author'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Background:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['background'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Be It Resolved:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['resolved'] ?? 'N/A')) . '</div>
    </div>';
} elseif ($category === 'plans') {
    $html .= '
    <div class="field">
        <span class="field-label">Plan Type:</span>
        <span class="field-value">' . htmlspecialchars($formData['planType'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Plan Title:</span>
        <span class="field-value">' . htmlspecialchars($formData['title'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Fiscal Year:</span>
        <span class="field-value">' . htmlspecialchars($formData['fiscalYear'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Total Budget:</span>
        <span class="field-value">' . htmlspecialchars($formData['totalBudget'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Source of Funds:</span>
        <span class="field-value">' . htmlspecialchars($formData['sourceFunds'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Programs/Projects:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['programs'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Objectives:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['objectives'] ?? 'N/A')) . '</div>
    </div>';
} elseif ($category === 'peace') {
    $html .= '
    <div class="field">
        <span class="field-label">Crime Count:</span>
        <span class="field-value">' . htmlspecialchars($formData['crimeCount'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Resolved Cases:</span>
        <span class="field-value">' . htmlspecialchars($formData['resolved'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Incidents Reported:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['incidents'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Programs Implemented:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['programs'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Recommendations:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['recommendations'] ?? 'N/A')) . '</div>
    </div>';
} elseif ($category === 'welfare') {
    $html .= '
    <div class="field">
        <span class="field-label">Programs/Services:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['programs'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Beneficiaries Count:</span>
        <span class="field-value">' . htmlspecialchars($formData['beneficiaries'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Budget Utilized:</span>
        <span class="field-value">' . htmlspecialchars($formData['budget'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Activities Conducted:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['activities'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Impact/Results:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['impact'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Challenges and Issues:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['challenges'] ?? 'N/A')) . '</div>
    </div>';
} elseif ($category === 'sk') {
    $html .= '
    <div class="field">
        <span class="field-label">SK Chairperson:</span>
        <span class="field-value">' . htmlspecialchars($formData['chairperson'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Reporting Period:</span>
        <span class="field-value">' . htmlspecialchars(ucfirst($formData['period'] ?? 'N/A')) . '</span>
    </div>
    <div class="field">
        <span class="field-label">Participants Count:</span>
        <span class="field-value">' . htmlspecialchars($formData['participants'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Budget Utilized:</span>
        <span class="field-value">' . htmlspecialchars($formData['budget'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Programs/Activities:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['programs'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Activities Conducted:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['activities'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Achievements:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['achievements'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Future Plans:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['plans'] ?? 'N/A')) . '</div>
    </div>';
} elseif ($category === 'others') {
    $html .= '
    <div class="field">
        <span class="field-label">Document Title:</span>
        <span class="field-value">' . htmlspecialchars($formData['docTitle'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Document Type:</span>
        <span class="field-value">' . htmlspecialchars($formData['docType'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Document Number:</span>
        <span class="field-value">' . htmlspecialchars($formData['docNumber'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Document Date:</span>
        <span class="field-value">' . htmlspecialchars($formData['docDate'] ?? 'N/A') . '</span>
    </div>
    <div class="field">
        <span class="field-label">Purpose:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['purpose'] ?? 'N/A')) . '</div>
    </div>
    <div class="field">
        <span class="field-label">Content:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['content'] ?? 'N/A')) . '</div>
    </div>';
    if (!empty($formData['remarks'])) {
        $html .= '
    <div class="field">
        <span class="field-label">Remarks:</span>
        <div class="textarea-value">' . nl2br(htmlspecialchars($formData['remarks'])) . '</div>
    </div>';
    }
}

// Signatures
if (isset($formData['captainName']) || isset($formData['secretaryName'])) {
    $html .= '
    <div class="signatures">
        <div class="signature-box">
            <p style="margin-bottom: 5px; font-size: 10pt;">Noted by:</p>';
    
    // Add secretary signature image if available
    if (!empty($formData['secretarySignature'])) {
        $html .= '
            <img src="' . htmlspecialchars($formData['secretarySignature']) . '" alt="Secretary Signature" class="signature-image">';
    }
    
    $html .= '
            <div class="signature-line">' . htmlspecialchars($formData['secretaryName'] ?? '') . '</div>
            <div class="signature-title">BARANGAY SECRETARY</div>
        </div>
        <div class="signature-box">
            <p style="margin-bottom: 5px; font-size: 10pt;">Respectfully submitted,</p>';
    
    // Add captain signature image if available
    if (!empty($formData['captainSignature'])) {
        $html .= '
            <img src="' . htmlspecialchars($formData['captainSignature']) . '" alt="Captain Signature" class="signature-image">';
    }
    
    $html .= '
            <div class="signature-line">' . htmlspecialchars($formData['captainName'] ?? '') . '</div>
            <div class="signature-title">PUNONG BARANGAY</div>
        </div>
    </div>';
}

$html .= '
</body>
</html>';

// Output as HTML (browser will render it, user can print to PDF)
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>
