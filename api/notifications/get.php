<?php
/**
 * GET Notifications API Wrapper
 * This file serves as the specific endpoint requested by the user.
 * It forwards requests to the core notifications API logic.
 */
$_GET['source'] = 'get_api'; // Optional flag
require_once __DIR__ . '/../../src/api/notifications.php';
