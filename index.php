<?php
/**
 * Entry Point - Redirect to appropriate page
 */

session_start();
require_once 'config/db.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: auth/login.php");
}
exit();
