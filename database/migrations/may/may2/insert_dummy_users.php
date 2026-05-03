<?php
try {
    // DB CONNECTION (uses .env via config/db_connection.php)
    require_once __DIR__ . '/../../../../config/db_connection.php';

    // -------------------------
    // INSERT ROLES
    // -------------------------
    $roles = [
        1 => 'HR',
        2 => 'ACCOUNTING'
    ];

    foreach ($roles as $id => $name) {
        $stmt = $conn->prepare("INSERT INTO roles (role_id, role_name) VALUES (:id, :name)");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
    }

    echo "Roles inserted.<br>";

    // -------------------------
    // HASH PASSWORD
    // -------------------------
    $hashedPassword = password_hash("christina_828", PASSWORD_BCRYPT);

    // -------------------------
    // INSERT USERS
    // -------------------------
    $users = [
        [
            'email' => 'hr@gmail.com',
            'first_name' => 'HR',
            'last_name' => 'User',
            'role_id' => 1
        ],
        [
            'email' => 'accounting@gmail.com',
            'first_name' => 'Accounting',
            'last_name' => 'User',
            'role_id' => 2
        ]
    ];

    foreach ($users as $user) {
        $stmt = $conn->prepare("
            INSERT INTO users 
            (email, password, first_name, last_name, role_id) 
            VALUES (:email, :password, :first_name, :last_name, :role_id)
        ");

        $stmt->execute([
            ':email' => $user['email'],
            ':password' => $hashedPassword,
            ':first_name' => $user['first_name'],
            ':last_name' => $user['last_name'],
            ':role_id' => $user['role_id']
        ]);
    }

    echo "Users inserted successfully.";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>