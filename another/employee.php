<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'database_pos');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Add, Update, or Delete Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_employee']) && !empty($_POST['employee_id'])) {
        // DELETE EMPLOYEE
        $id = $_POST['employee_id'];

        // Get the employee email before deletion
        $stmt = $conn->prepare("SELECT email FROM employees WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        $stmt->close();

        // Delete from employees table
        $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Delete from users table
        $stmt = $conn->prepare("DELETE FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    } else {
        // ADD OR UPDATE EMPLOYEE
        $id = $_POST['employee_id'] ?? null;
        $name = $_POST['employee_name'];
        $email = $_POST['email'];
        $password = $_POST['password'] ?? null;
        $available = $_POST['available'];
        
        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $imagePath = 'uploads/' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
        } else {
            $stmt = $conn->prepare("SELECT image FROM employees WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->bind_result($existingImage);
            $stmt->fetch();
            $stmt->close();
            $imagePath = $existingImage;
        }

        if ($id) {
            // Retain old password if new password is not provided
            if (empty($password)) {
                $stmt = $conn->prepare("SELECT password FROM employees WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->bind_result($password);
                $stmt->fetch();
                $stmt->close();
            }

            $stmt = $conn->prepare("UPDATE employees SET name=?, email=?, password=?, available=?, image=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $email, $password, $available, $imagePath, $id);
            $stmt->execute();
            $stmt->close();

            // Update the users table
            $user_stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE email=?");
            $user_stmt->bind_param("ssss", $name, $email, $password, $email);
            $user_stmt->execute();
            $user_stmt->close();
        } else {
            // Insert new employee
            $stmt = $conn->prepare("INSERT INTO employees (name, email, password, available, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $available, $imagePath);
            $stmt->execute();
            $stmt->close();

            // Insert into users table as 'cashier'
            $user_stmt = $conn->prepare("INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, 'user')");
            $user_stmt->bind_param("sss", $name, $email, $password);
            $user_stmt->execute();
            $user_stmt->close();
        }
    }
}

// Fetch employees
$result = $conn->query("SELECT * FROM employees");
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link rel="stylesheet" href="employee.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function populateForm(id, name, email, availability, image) {
            document.getElementById('employee-id').value = id;
            document.getElementById('employee-name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('available').value = availability;
            document.getElementById('add-item-btn').innerText = 'Update Employee';
            document.getElementById('remove-employee-btn').style.display = 'block';
        }

        function confirmDelete() {
            return confirm("Are you sure you want to remove this employee?");
        }
    </script>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="item-details">
            <h2>Employee</h2>
            <form id="item-form" action="employee.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" id="employee-id">
                <div>
                    <label for="employee-name">Name</label>
                    <input type="text" name="employee_name" id="employee-name" required>
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password">
                </div>
                <div>
                    <label for="available">Availability</label>
                    <select name="available" id="available" required>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label for="item-image">Image</label>
                    <input type="file" name="image" id="item-image" accept="image/*">
                </div>
                <button type="submit" name="add_item" id="add-item-btn">Add Employee</button>
                <button type="submit" name="remove_employee" id="remove-employee-btn">Remove Employee</button>
            </form>
        </div>

        <div class="item-list">
            <h2>Employee List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Password</th> <!-- Changed from "Hashed Password" -->
                        <th>Availability</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr onclick="populateForm('<?= $row['id'] ?>', '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['available']) ?>', '<?= $row['image'] ?>')">
                            <td><img src="<?= $row['image'] ?>" width="50"></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['password']) ?></td> <!-- Display Plain Password -->
                            <td><?= htmlspecialchars($row['available']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>