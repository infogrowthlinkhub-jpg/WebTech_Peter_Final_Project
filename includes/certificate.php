<?php
/**
 * Certificate Generation Functions
 * Generates PDF certificates using HTML to PDF conversion
 */

/**
 * Generate PDF certificate for module completion
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $moduleId Module ID
 * @param string $userName User's full name
 * @param string $moduleName Module name
 * @return string|false Certificate file path on success, false on failure
 */
function generateCertificate($conn, $userId, $moduleId, $userName, $moduleName) {
    try {
        // Create certificates directory if it doesn't exist
        $certDir = __DIR__ . '/../certificates';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }
        
        // Generate unique filename
        $certificateId = strtoupper(substr(md5($userId . $moduleId . time()), 0, 8));
        $filename = 'certificate_' . $userId . '_' . $moduleId . '_' . time() . '.html';
        $filepath = $certDir . '/' . $filename;
        $issueDate = date('F j, Y');
        
        // Generate HTML certificate
        $html = generateCertificateHTML($userName, $moduleName, $issueDate, $certificateId);
        
        // Save HTML file (can be printed to PDF by user)
        file_put_contents($filepath, $html);
        
        // Store certificate in database
        $relativePath = 'certificates/' . $filename;
        $stmt = $conn->prepare("
            INSERT INTO certificates (user_id, module_id, certificate_path, issued_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE certificate_path = ?, issued_at = NOW()
        ");
        $stmt->bind_param("iiss", $userId, $moduleId, $relativePath, $relativePath);
        
        if ($stmt->execute()) {
            $stmt->close();
            return $relativePath;
        } else {
            $stmt->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Certificate generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate HTML for certificate
 * @param string $userName User's full name
 * @param string $moduleName Module name
 * @param string $issueDate Issue date
 * @param string $certificateId Certificate ID
 * @return string HTML content
 */
function generateCertificateHTML($userName, $moduleName, $issueDate, $certificateId) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Times New Roman", serif;
            background: white;
        }
        .certificate {
            width: 297mm;
            height: 210mm;
            border: 5mm solid #006994;
            position: relative;
            background: white;
            margin: 0 auto;
        }
        .inner-border {
            position: absolute;
            top: 10mm;
            left: 10mm;
            right: 10mm;
            bottom: 10mm;
            border: 2mm solid #006994;
        }
        .header {
            background: linear-gradient(135deg, #006994 0%, #008b8b 100%);
            color: white;
            padding: 15mm 0;
            text-align: center;
            margin: 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24mm;
            font-weight: bold;
            letter-spacing: 2mm;
        }
        .content {
            padding: 20mm;
            text-align: center;
        }
        .cert-text {
            font-size: 8mm;
            color: #333;
            margin: 10mm 0;
        }
        .user-name {
            font-size: 14mm;
            font-weight: bold;
            color: #006994;
            margin: 15mm 0;
            text-transform: uppercase;
            letter-spacing: 1mm;
        }
        .module-name {
            font-size: 10mm;
            font-weight: bold;
            color: #006994;
            margin: 10mm 0;
        }
        .date {
            font-size: 6mm;
            color: #666;
            margin-top: 15mm;
        }
        .footer {
            position: absolute;
            bottom: 15mm;
            left: 20mm;
            right: 20mm;
            display: flex;
            justify-content: space-between;
            font-size: 4mm;
            color: #006994;
            font-style: italic;
        }
        .decorative {
            position: absolute;
            width: 30mm;
            height: 30mm;
            border: 2mm solid #008b8b;
            border-radius: 50%;
            opacity: 0.3;
        }
        .decorative.left {
            left: 30mm;
            top: 50%;
            transform: translateY(-50%);
        }
        .decorative.right {
            right: 30mm;
            top: 50%;
            transform: translateY(-50%);
        }
        @media print {
            body {
                margin: 0;
            }
            .certificate {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="inner-border"></div>
        <div class="decorative left"></div>
        <div class="decorative right"></div>
        
        <div class="header">
            <h1>CERTIFICATE OF COMPLETION</h1>
        </div>
        
        <div class="content">
            <p class="cert-text">This is to certify that</p>
            <p class="user-name">' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</p>
            <p class="cert-text">has successfully completed the course</p>
            <p class="module-name">' . htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8') . '</p>
            <p class="date">Issued on ' . htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8') . '</p>
        </div>
        
        <div class="footer">
            <span>NileTech Learning Platform</span>
            <span>Certificate ID: ' . htmlspecialchars($certificateId, ENT_QUOTES, 'UTF-8') . '</span>
        </div>
    </div>
</body>
</html>';
}

/**
 * Check if module is complete and generate certificate if needed
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $moduleId Module ID
 * @return bool True if certificate was generated, false otherwise
 */
function checkAndGenerateCertificate($conn, $userId, $moduleId) {
    // Check if certificate already exists
    $checkStmt = $conn->prepare("SELECT id FROM certificates WHERE user_id = ? AND module_id = ?");
    $checkStmt->bind_param("ii", $userId, $moduleId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $checkStmt->close();
        return false; // Certificate already exists
    }
    $checkStmt->close();
    
    // Get module progress
    $progress = getUserModuleProgress($conn, $userId, $moduleId);
    
    // Check if module is 100% complete
    if ($progress['percentage'] >= 100 && $progress['total'] > 0) {
        // Get user and module information
        $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        $moduleStmt = $conn->prepare("SELECT name FROM modules WHERE id = ? LIMIT 1");
        $moduleStmt->bind_param("i", $moduleId);
        $moduleStmt->execute();
        $moduleResult = $moduleStmt->get_result();
        $module = $moduleResult->fetch_assoc();
        $moduleStmt->close();
        
        if ($user && $module) {
            // Generate certificate
            $certificatePath = generateCertificate($conn, $userId, $moduleId, $user['full_name'], $module['name']);
            
            if ($certificatePath) {
                // Create notification
                $notificationMessage = "Congratulations! You've completed the {$module['name']} module. Your certificate is ready for download!";
                createNotification($conn, $userId, 'module_completed', $notificationMessage);
                
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Get certificate for user and module
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $moduleId Module ID
 * @return array|null Certificate data or null
 */
function getCertificate($conn, $userId, $moduleId) {
    $stmt = $conn->prepare("
        SELECT c.*, m.name as module_name 
        FROM certificates c
        INNER JOIN modules m ON c.module_id = m.id
        WHERE c.user_id = ? AND c.module_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $userId, $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $certificate = $result->fetch_assoc();
    $stmt->close();
    
    return $certificate;
}

/**
 * Get all certificates for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return array Array of certificates
 */
function getUserCertificates($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT c.*, m.name as module_name, m.slug as module_slug
        FROM certificates c
        INNER JOIN modules m ON c.module_id = m.id
        WHERE c.user_id = ?
        ORDER BY c.issued_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $certificates = [];
    while ($row = $result->fetch_assoc()) {
        $certificates[] = $row;
    }
    $stmt->close();
    
    return $certificates;
}

?>
