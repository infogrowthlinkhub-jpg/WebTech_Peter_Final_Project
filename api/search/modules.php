<?php
/**
 * API Endpoint: Search Modules
 * Handles AJAX requests for searching modules by title or description
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
    // Search modules by title or description
    $searchTerm = '%' . $conn->real_escape_string($query) . '%';
    $stmt = $conn->prepare("SELECT id, name, slug, description, icon FROM modules WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare search query');
    }
    
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'icon' => $row['icon']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $modules,
        'count' => count($modules)
    ]);
} catch (Exception $e) {
    error_log("Module search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching']);
} finally {
    closeDBConnection($conn);
}
?>

