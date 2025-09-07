<?php
session_start();

// ==== CONFIG ====
$servername = "localhost";
$db_username = "root";
$db_password = "";
$user_db = "user_db";
$contact_db = "contact_db";

$message = "";
$message_type = "";

// ==== CREATE USER DATABASE & TABLE ====
$conn = new mysqli($servername, $db_username, $db_password);
if (!$conn->connect_error) {
    $conn->query("CREATE DATABASE IF NOT EXISTS $user_db");
    $conn->select_db($user_db);
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    )");

    // Insert default user if not exists
    $check = $conn->query("SELECT * FROM users WHERE username='admin'");
    if ($check->num_rows == 0) {
        $hashed = password_hash("12345", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $u, $p);
        $u = "admin"; $p = $hashed;
        $stmt->execute();
        $stmt->close();
    }
}
$conn->close();

// ==== LOGOUT ====
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==== LOGIN HANDLER ====
if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    $conn = new mysqli($servername, $db_username, $db_password, $user_db);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->bind_result($hashed);
        if ($stmt->fetch() && password_verify($pass, $hashed)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user;
        } else {
            $message = "Invalid username or password!";
            $message_type = "error";
        }
        $stmt->close();
    }
    $conn->close();
}

// ==== DELETE CONTACT ====
if (isset($_GET['delete']) && isset($_SESSION['logged_in'])) {
    $delete_id = intval($_GET['delete']);
    $conn = new mysqli($servername, $db_username, $db_password, $contact_db);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id=?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "Entry deleted!";
            $message_type = "success";
        }
        $stmt->close();
    }
    $conn->close();
}

// ==== SAVE CONTACT ====
if (isset($_POST['submit']) && isset($_SESSION['logged_in'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    if (empty($name) || empty($contact)) {
        $message = "Please fill all fields!";
        $message_type = "error";
    } else {
        $conn = new mysqli($servername, $db_username, $db_password);
        if (!$conn->connect_error) {
            $conn->query("CREATE DATABASE IF NOT EXISTS $contact_db");
            $conn->select_db($contact_db);
            $conn->query("CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                contact VARCHAR(50) NOT NULL,
                reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $stmt = $conn->prepare("INSERT INTO contacts (name, contact) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $contact);
            if ($stmt->execute()) {
                $message = "Data saved successfully!";
                $message_type = "success";
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        $conn->close();
    }
}

// ==== DISPLAY CONTACTS ====
function displayEntries() {
    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $contact_db = "contact_db";

    $output = "";
    $conn = new mysqli($servername, $db_username, $db_password, $contact_db);
    if (!$conn->connect_error) {
        $check = $conn->query("SHOW TABLES LIKE 'contacts'");
        if ($check && $check->num_rows > 0) {
            $result = $conn->query("SELECT * FROM contacts ORDER BY reg_date DESC");
            if ($result && $result->num_rows > 0) {
                $output .= "<h3>Saved Contacts</h3><div class='entries'>";
                while ($row = $result->fetch_assoc()) {
                    $output .= "<div class='entry-card'>
                        <div>
                            <strong>" . htmlspecialchars($row["name"]) . "</strong><br>
                            <small>" . htmlspecialchars($row["contact"]) . "</small><br>
                            <span class='date'>" . $row["reg_date"] . "</span>
                        </div>
                        <a href='?delete=" . $row["id"] . "' class='delete-btn' onclick=\"return confirm('Delete this entry?');\">✖</a>
                    </div>";
                }
                $output .= "</div>";
            } else {
                $output .= "<p>No entries yet. Add one above!</p>";
            }
        }
        $conn->close();
    }
    return $output;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Contact Manager 2025</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg,#d1f4ff,#f0ffe0); margin:0; }
    .container { max-width: 600px; margin: 60px auto; background: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    h2 { text-align: center; margin-bottom: 20px; color: #333; }
    input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
    input[type="submit"] { background: linear-gradient(135deg,#4CAF50,#2E8B57); color: white; border: none; padding: 12px; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
    input[type="submit"]:hover { background: linear-gradient(135deg,#45a049,#226644); }
    .message { padding: 10px; margin-bottom: 15px; border-radius: 8px; text-align: center; font-weight: bold; }
    .success { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
    .entries { margin-top: 20px; }
    .entry-card { display: flex; justify-content: space-between; align-items: center; background: #f9fafc; padding: 14px 16px; margin: 10px 0; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); transition: 0.2s; }
    .entry-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .date { font-size: 12px; color: #666; }
    .delete-btn { text-decoration: none; color: #ff4d4d; font-size: 18px; font-weight: bold; }
    .delete-btn:hover { color: #e60000; }
    .logout { display: block; text-align: right; margin-bottom: 10px; }
    .logout a { color: #444; text-decoration: none; font-size: 14px; }
    .logout a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
<?php if (!isset($_SESSION['logged_in'])): ?>
    <h2>🔐 Login</h2>
    <?php if (!empty($message)) echo "<div class='message $message_type'>$message</div>"; ?>
    <form method="post" action="">
        <input type="text" name="username" placeholder="Enter Username">
        <input type="password" name="password" placeholder="Enter Password">
        <input type="submit" name="login" value="Login">
    </form>
<?php else: ?>
    <div class="logout"><a href="?logout=1">🚪 Logout</a></div>
    <h2>📇 Contact Manager 2025</h2>
    <?php if (!empty($message)) echo "<div class='message $message_type'>$message</div>"; ?>
    <form method="post" action="">
        <input type="text" name="name" placeholder="Enter Name">
        <input type="text" name="contact" placeholder="Enter Contact">
        <input type="submit" name="submit" value="Save">
    </form>
    <?php echo displayEntries(); ?>
<?php endif; ?>
</div>
</body>
</html>
