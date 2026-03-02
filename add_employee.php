<?php
include 'db.php';
include 'auth.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $location = $_POST['location'];

    $stmt = $conn->prepare("INSERT INTO employees (full_name, location) VALUES (?, ?)");
    $stmt->bind_param("ss", $full_name, $location);

    if ($stmt->execute()) {
        $message = "<p style='color: green;'>Employee added successfully! <a href='employee_list.php'>View List</a></p>";
    } else {
        $message = "<p style='color: red;'>Error: " . $conn->error . "</p>";
    }
    $stmt->close();
}
?>

<div class="main">
    <h1 align="center">➕ Add New Employee</h1>
    
    <div style="max-width: 500px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1);">
        <?= $message ?>
        <form method="POST" action="">
            <div style="margin-bottom: 15px;">
                <label>Full Name:</label><br>
                <input type="text" name="full_name" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label>Location:</label><br>
                <input type="text" name="location" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <button type="submit" style="background-color: #2E8B57; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%;">
                Save Employee
            </button>
            <br><br>
            <a href="employee_list.php" style="display: block; text-align: center; color: #666; text-decoration: none;">Cancel and Go Back</a>
        </form>
    </div>
</div>