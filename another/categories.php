<?php
$conn = new mysqli('localhost', 'root', '', 'database_pos');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Handle form submission for adding, updating, or deleting categories
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = $_POST['category_name'];
    $category_id = $_POST['category_id'] ?? null;
    $imagePath = '';

    if (!empty($_FILES['image']['name'])) {
        $imagePath = 'uploads/' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
    }

    if (isset($_POST['add_category'])) {
        $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
        $stmt->bind_param("ss", $category_name, $imagePath);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['update_category']) && !empty($category_id)) {
        // Update with image check (if new image uploaded, use it)
        if (!empty($imagePath)) {
            $stmt = $conn->prepare("UPDATE categories SET name=?, image=? WHERE id=?");
            $stmt->bind_param("ssi", $category_name, $imagePath, $category_id);
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
            $stmt->bind_param("si", $category_name, $category_id);
        }
        $stmt->execute();
        $stmt->close();
    }

    header("Location: categories.php");
    exit();
}

// Fetch all categories
$categories = $conn->query("SELECT * FROM categories");


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="category.css">
    <title>Categories</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
</head>

<body>
    <?php include 'sidebar.php' ?>
    <div class="container">
        <div class="category-details">
            <h2>Category Details</h2>
            <form action="categories.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="category_id" id="category-id">
                <div>
                    <label for="category-name">Category Name</label>
                    <input type="text" name="category_name" id="category-name" required>
                </div>
                <div>
                    <label for="image-upload">Image</label>
                    <input type="file" name="image" id="image-upload">
                </div>
                <button type="submit" name="add_category" id="add-btn">Add Category</button>
                <button type="submit" name="update_category" id="update-btn" style="display: none;">Update Category</button>
            </form>
        </div>

        <!-- Category List Section -->
        <div class="category-list">
            <h2>Category List</h2>
            <input type="text" id="search-input" placeholder="Search categories...">
            <table border="1">
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Category Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="category-list-body">
                    <?php while ($row = $categories->fetch_assoc()) { ?>
                        <tr id="category-<?= $row['id'] ?>" onclick="populateForm('<?= $row['id'] ?>', '<?= $row['name'] ?>', '<?= $row['image'] ?>')">
                            <td><img src="<?= $row['image'] ?>" width="50"></td>
                            <td><?= $row['name'] ?></td>
                            <td>
                                <button onclick="deleteCategory(<?= $row['id'] ?>); event.stopPropagation();">Delete</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Populate form with category details when a row is clicked
        function populateForm(id, name, image) {
            document.getElementById('category-id').value = id;
            document.getElementById('category-name').value = name;

            // Show image preview if the image is available
            if (image) {
                document.getElementById('image-preview').src = image;
                document.getElementById('image-preview').style.display = 'block';
            } else {
                document.getElementById('image-preview').style.display = 'none';
            }

            // Show the update button and hide the add button
            document.getElementById('add-btn').style.display = 'none';
            document.getElementById('update-btn').style.display = 'inline';
        }

        // Preview image when uploading
        document.getElementById('image-upload').addEventListener('change', function (event) {
            const reader = new FileReader();
            reader.onload = function () {
                document.getElementById('image-preview').src = reader.result;
                document.getElementById('image-preview').style.display = 'block';
            }
            reader.readAsDataURL(event.target.files[0]);
        });

        // Search functionality
        document.getElementById('search-input').addEventListener('input', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#category-list-body tr');

            rows.forEach(row => {
                const name = row.cells[1].textContent.toLowerCase();
                if (name.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Delete category using AJAX
        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'controller/delete_category.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            document.getElementById('category-' + id).remove();
                        } else {
                            alert('Error deleting category.');
                        }
                    }
                };
                xhr.send('category_id=' + id);
            }
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
