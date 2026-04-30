<?php
/**
 * Logout
 * Orphanage Management System
 */
session_start();
session_unset();
session_destroy();
header("Location: /orphanage-management-system/login.php");
exit();
?>
