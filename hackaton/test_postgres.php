
<?php
echo "PDO PostgreSQL: ";

if (extension_loaded('pdo_pgsql')) {
    echo "ENABLED";
} else {
    echo "NOT ENABLED";
}

echo "<br>PostgreSQL: ";

if (extension_loaded('pgsql')) {
    echo "ENABLED";
} else {
    echo "NOT ENABLED";
}
?>
