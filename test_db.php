<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=uivent_db;charset=utf8mb4', 'root', '');
    echo "✅ Connected! DB: uivent_db";
    $r = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    echo " | Events: $r";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}