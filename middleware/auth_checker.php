<?php
session_start();

function checkAccess($allowedRole) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php?error=Please login");
        exit();
    }
    if ($_SESSION['user_role'] !== $allowedRole) {
        header("Location: ../login.php?error=Unauthorized Access");
        exit();
    }
}

?>