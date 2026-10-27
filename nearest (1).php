<?php
declare(strict_types=1);
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
session_start();
require_once __DIR__.'/database.php';
header('Content-Type: application/json; charset=utf-8');
function requireLogin(): int {
 if(empty($_SESSION['user_id'])) { http_response_code(401); exit(json_encode(['success'=>false,'message'=>'Not authenticated.'])); }
 return (int)$_SESSION['user_id'];
}
