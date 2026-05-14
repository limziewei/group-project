<?php
function connect_database(): mysqli
{
    $host = 'localhost';
    $database = 'restaurant';

    $candidates = [
        ['root', ''],
        ['admin', 'admin123'],
        ['aemetha', '123456'],
    ];

    foreach ($candidates as [$user, $password]) {
        try {
            $conn = mysqli_connect($host, $user, $password, $database);
            if ($conn) {
                return $conn;
            }
        } catch (mysqli_sql_exception $e) {
            continue;
        }
    }

    die("
    <div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px; font-family: Arial;'>
        <h3>❌ Database Connection Error</h3>
        <p><strong>Error:</strong> Unable to connect using the configured MySQL accounts.</p>
        <p><strong>Try:</strong></p>
        <ul>
            <li>Start MySQL in XAMPP/WAMP</li>
            <li>Confirm the <strong>restaurant</strong> database exists</li>
            <li>Check your local MySQL username and password</li>
        </ul>
    </div>
    ");
}