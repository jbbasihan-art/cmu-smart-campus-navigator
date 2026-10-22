
<?php

$host = "aws-0-ap-southeast-1.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";

// Ilagay ang eksaktong database username mula sa Supabase
$username = "postgres.yxymypyiiczvuguzfsrw";

// Ilagay ang database password mo sa Supabase
$password = "jbcmu18210254";

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {
    die(
        "Database error: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}

?>
