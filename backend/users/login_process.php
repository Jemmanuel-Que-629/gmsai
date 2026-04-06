<?php
    session_start();

    include '../config/db_connection.php';

    if(isset($_POST['email']) && isset($_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_assoc();

        $redirect_urls = [
            'HR' => '../../users/hr/dashboard.php',
            'Accounting' => '../../users/accounting/dashboard.php'
        ];

        if($users && password_verify($password, $users['password'])){

            session_regenerate_id(true);

            $_SESSION['user_id'] = $users['id'];
            $_SESSION['user_email'] = $users['email'];
            $_SESSION['user_role'] = $users['role'];
           
            if (isset($redirect_urls[$user['role']])) {
                header("Location: " . $redirect_urls[$user['role']]);
                exit();
            } else {
                header("Location: ../users/login.php?error=Role Not Assigned");
                exit();
            }
        } else {
            header("Location: ../users/login.php?error=Invalid email or password");
            exit();
        }
    }
?>