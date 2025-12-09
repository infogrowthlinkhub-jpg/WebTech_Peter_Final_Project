<?php
/**
 * API Endpoint: User Statistics
 * Returns user progress statistics
 */

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Get overall statistics
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM lessons) as total_lessons,
            (SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND completed = TRUE) as completed_lessons,
            (SELECT COUNT(*) FROM modules) as total_modules
    ";
    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $overallStats = $result->fetch_assoc();
    $stmt->close();
    
    // Get module-wise progress
    $moduleStatsQuery = "
        SELECT 
            m.id,
            m.name,
            m.slug,
            COUNT(l.id) as total_lessons,
            SUM(CASE WHEN up.completed = TRUE THEN 1 ELSE 0 END) as completed_lessons
        FROM modules m
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        GROUP BY m.id, m.name, m.slug
        ORDER BY m.id
    ";
    $stmt = $conn->prepare($moduleStatsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $moduleStats = [];
    while ($row = $result->fetch_assoc()) {
        $moduleStats[] = $row;
    }
    $stmt->close();
    
    // Calculate completion percentage
    $completionPercentage = $overallStats['total_lessons'] > 0 
        ? round(($overallStats['completed_lessons'] / $overallStats['total_lessons']) * 100, 2)
        : 0;
    
    echo json_encode([
        'success' => true,
        'overall' => [
            'total_lessons' => (int)$overallStats['total_lessons'],
            'completed_lessons' => (int)$overallStats['completed_lessons'],
            'remaining_lessons' => (int)$overallStats['total_lessons'] - (int)$overallStats['completed_lessons'],
            'completion_percentage' => $completionPercentage,
            'total_modules' => (int)$overallStats['total_modules']
        ],
        'modules' => $moduleStats
    ]);
} catch (Exception $e) {
    error_log("Stats API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} finally {
    closeDBConnection($conn);
}
?>

