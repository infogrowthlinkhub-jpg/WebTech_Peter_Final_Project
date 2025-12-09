<?php
/**
 * Certificate View/Download Page
 * Displays and allows download of certificates
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/certificate.php';

// Get module ID from URL
$moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;

if ($moduleId <= 0) {
    header('Location: profile.php');
    exit;
}

$conn = getDBConnection();

// Get certificate
$certificate = getCertificate($conn, $currentUserId, $moduleId);

if (!$certificate) {
    $_SESSION['error_message'] = 'Certificate not found.';
    header('Location: profile.php');
    exit;
}

// Check if file exists
$filePath = __DIR__ . '/' . $certificate['certificate_path'];
if (!file_exists($filePath)) {
    // Try to regenerate certificate if file is missing
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $userStmt->bind_param("i", $currentUserId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();
    
    if ($user) {
        $newPath = generateCertificate($conn, $currentUserId, $moduleId, $user['full_name'], $certificate['module_name']);
        if ($newPath) {
            $filePath = __DIR__ . '/' . $newPath;
        }
    }
    
    if (!file_exists($filePath)) {
        $_SESSION['error_message'] = 'Certificate file not found.';
        header('Location: profile.php');
        exit;
    }
}

// If download requested
if (isset($_GET['download'])) {
    $fileName = 'Certificate_' . str_replace(' ', '_', $certificate['module_name']) . '_' . date('Y-m-d', strtotime($certificate['issued_at'])) . '.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    readfile($filePath);
    exit;
}

// Display certificate
$certificateContent = file_get_contents($filePath);
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($certificate['module_name']); ?> - NileTech</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .certificate-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .certificate-actions {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .btn-download {
            background: linear-gradient(135deg, #006994 0%, #008b8b 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            margin: 0 10px;
            transition: transform 0.3s;
        }
        .btn-download:hover {
            transform: scale(1.05);
        }
        .btn-print {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            margin: 0 10px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-print:hover {
            transform: scale(1.05);
        }
        .certificate-info {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        @media print {
            body {
                padding: 0;
                background: white;
            }
            .certificate-container {
                box-shadow: none;
                padding: 0;
            }
            .certificate-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-actions">
            <h2 style="margin-top: 0; color: #006994;">Your Certificate</h2>
            <p class="certificate-info">
                <strong>Module:</strong> <?php echo htmlspecialchars($certificate['module_name']); ?> | 
                <strong>Issued:</strong> <?php echo formatDate($certificate['issued_at']); ?>
            </p>
            <a href="certificate.php?module_id=<?php echo $moduleId; ?>&download=1" class="btn-download">📥 Download Certificate</a>
            <button onclick="window.print()" class="btn-print">🖨️ Print Certificate</button>
            <a href="profile.php" style="color: #666; text-decoration: none; margin-left: 20px;">← Back to Profile</a>
        </div>
        
        <div class="certificate-content">
            <?php echo $certificateContent; ?>
        </div>
    </div>
</body>
</html>

