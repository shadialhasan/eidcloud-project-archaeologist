<?php
// legacy config with hardcoded credentials and globals
$db_pass = 'super_secret_db_password_123';
$api_key = 'abcdef1234567890abcdef1234567890';

global $GLOBAL_CONFIG;
$GLOBAL_CONFIG = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => $db_pass,
];
