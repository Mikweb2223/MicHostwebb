<?php
require_once __DIR__ . '/../config/bootstrap.php';

session_unset();
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
