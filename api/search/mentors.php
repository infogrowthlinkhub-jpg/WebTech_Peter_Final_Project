<?php
/**
 * API Endpoint: Search Mentors
 * Handles AJAX requests for searching mentors by skills
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Search mentors by skills, name, role, or bio
    $searchTerm = '%' . $conn->real_escape_string($query) . '%';
    
    // Check if mentorship table has skills column
    $columnsCheck = $conn->query("SHOW COLUMNS FROM mentorship LIKE 'skills'");
    $hasSkills = $columnsCheck && $columnsCheck->num_rows > 0;
    
    // Check if image column exists
    $imageCheck = $conn->query("SHOW COLUMNS FROM mentorship LIKE 'image'");
    $hasImage = $imageCheck && $imageCheck->num_rows > 0;
    
    if ($hasSkills) {
        $selectFields = $hasImage 
            ? 'id, name, role, bio, contact, image, skills'
            : 'id, name, role, bio, contact, skills';
        $stmt = $conn->prepare("
            SELECT $selectFields
            FROM mentorship 
            WHERE skills LIKE ? OR name LIKE ? OR role LIKE ? OR bio LIKE ?
            ORDER BY name ASC
        ");
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    } else {
        // Fallback: search by name, role, or bio
        $selectFields = $hasImage 
            ? 'id, name, role, bio, contact, image'
            : 'id, name, role, bio, contact';
        $stmt = $conn->prepare("
            SELECT $selectFields
            FROM mentorship 
            WHERE name LIKE ? OR role LIKE ? OR bio LIKE ?
            ORDER BY name ASC
        ");
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    }
    
    if (!$stmt) {
        throw new Exception('Failed to prepare search query');
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mentors = [];
    while ($row = $result->fetch_assoc()) {
        $mentor = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'role' => $row['role'] ?? '',
            'bio' => $row['bio'] ?? '',
            'contact' => $row['contact'] ?? ''
        ];
        
        if ($hasImage && isset($row['image'])) {
            $mentor['image'] = $row['image'];
        }
        
        if ($hasSkills && isset($row['skills'])) {
            $mentor['skills'] = $row['skills'];
        }
        
        $mentors[] = $mentor;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $mentors,
        'count' => count($mentors)
    ]);
} catch (Exception $e) {
    error_log("Mentor search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching']);
} finally {
    closeDBConnection($conn);
}
?>

