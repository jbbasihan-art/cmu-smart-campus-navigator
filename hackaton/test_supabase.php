
<?php
require_once __DIR__ . '/config/database.php';

$tables = [
    'admin',
    'buildings',
    'building_layouts',
    'rooms',
    'offices',
    'facilities',
    'food_stalls',
    'departments',
    'professors',
    'room_professor',
    'organization_chart',
    'university_info',
    'update_requests'
];

echo "<h2>CMU Navigator - Supabase Data Check</h2>";

foreach ($tables as $table) {
    $stmt = $conn->query(
        'SELECT COUNT(*) FROM public."' . $table . '"'
    );

    $count = $stmt->fetchColumn();

    echo htmlspecialchars($table) . ": " . $count . " records<br>";
}
?>
