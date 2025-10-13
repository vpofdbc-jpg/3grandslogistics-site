<?php
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';                 // must define $pdo (PDO)
require_once __DIR__ . '/emails.php';        // contains send_welcome_email()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = password_hash((string)($_POST['password'] ?? ''), PASSWORD_DEFAULT);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $street     = trim($_POST['street'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $state      = trim($_POST['state'] ?? '');
    $zipcode    = trim($_POST['zipcode'] ?? '');

    // Check if username or email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
    $check->execute([':username' => $username, ':email' => $email]);
    if ($check->rowCount() > 0) {
        die("❌ Username or Email already exists.");
    }

    // Insert new user
    $sql = "INSERT INTO users 
        (username, email, password, first_name, last_name, phone, street, city, state, zipcode, created_at) 
        VALUES 
        (:username, :email, :password, :first_name, :last_name, :phone, :street, :city, :state, :zipcode, NOW())";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':username'   => $username,
            ':email'      => $email,
            ':password'   => $password,
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':phone'      => $phone,
            ':street'     => $street,
            ':city'       => $city,
            ':state'      => $state,
            ':zipcode'    => $zipcode
        ]);

        // AFTER successful insert -> send welcome email
        $userId = (int)$pdo->lastInsertId();
        @send_welcome_email($userId);

        header('Location: login.php?registered=1');
        exit;
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}



