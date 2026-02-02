<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// Log out the user
Auth::logout();

// Set flash message
flash('success', 'You have been logged out successfully.');

// Redirect to home
redirect(baseUrl('/'));