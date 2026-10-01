<?php
// legacy fixture index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/ActiveUser.php';

$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'home':
        $user = new ActiveUser();
        $user->renderDashboard();
        break;
    case 'login':
        echo "<h1>Login Page</h1>";
        break;
    default:
        die("404 Not Found");
}
