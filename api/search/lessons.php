<?php
/**
 * API Endpoint: Search Lessons
 * Handles AJAX requests for searching lessons within a module
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

// Get search query and module ID
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;

if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

if ($moduleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Search lessons by title, objective, or content summary
    // Note: We'll search in title and a substring of content (first 500 chars as summary)
    $searchTerm = '%' . $conn->real_escape_string($query) . '%';
    
    // Check if lessons table has objective column
    $columnsCheck = $conn->query("SHOW COLUMNS FROM lessons LIKE 'objective'");
    $hasObjective = $columnsCheck && $columnsCheck->num_rows > 0;
    
    if ($hasObjective) {
        $stmt = $conn->prepare("
            SELECT id, title, slug, lesson_order, lesson_type, 
                   SUBSTRING(content, 1, 200) as content_summary
            FROM lessons 
            WHERE module_id = ? 
            AND (title LIKE ? OR objective LIKE ? OR content LIKE ?)
            ORDER BY lesson_order ASC
        ");
        $stmt->bind_param("isss", $moduleId, $searchTerm, $searchTerm, $searchTerm);
    } else {
        $stmt = $conn->prepare("
            SELECT id, title, slug, lesson_order, lesson_type,
                   SUBSTRING(content, 1, 200) as content_summary
            FROM lessons 
            WHERE module_id = ? 
            AND (title LIKE ? OR content LIKE ?)
            ORDER BY lesson_order ASC
        ");
        $stmt->bind_param("iss", $moduleId, $searchTerm, $searchTerm);
    }
    
    if (!$stmt) {
        throw new Exception('Failed to prepare search query');
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lessons = [];
    while ($row = $result->fetch_assoc()) {
        $lessons[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'lesson_order' => (int)$row['lesson_order'],
            'lesson_type' => $row['lesson_type'],
            'content_summary' => strip_tags($row['content_summary'] ?? '')
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $lessons,
        'count' => count($lessons)
    ]);
} catch (Exception $e) {
    error_log("Lesson search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching']);
} finally {
    closeDBConnection($conn);
}
?>

