<?php
$conn = new mysqli('localhost', 'root', '', 'database_pos');
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = $_POST['item_name'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $quantity = $_POST['item_quantity'] ?? 0;
    $medium_price = $_POST['medium_price'] ?? 0.0;
    $large_price = $_POST['large_price'] ?? 0.0;
    $status = isset($_POST['status']) ? 1 : 0;
    $imagePath = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadResult = handleImageUpload($_FILES['image']);
        if (!$uploadResult['success']) {
            die($uploadResult['message']);
        }
        $imagePath = $uploadResult['path'];
    }

    // Adding a new item
    if (isset($_POST['add_item'])) {
        // Fetch the latest medium and large sizes for the current item category
        $stmt = $conn->prepare("SELECT medium_size, large_size FROM items WHERE category_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->bind_result($last_medium_size, $last_large_size);
        $stmt->fetch();
        $stmt->close();

        // If no previous sizes are found, use the provided quantity as the starting point
        if ($last_medium_size === null || $last_large_size === null) {
            $medium_size = $quantity;
            $large_size = $quantity;
        } else {
            $medium_size = $last_medium_size;
            $large_size = $last_large_size;
        }

        // Insert the new item along with its quantity data
        $stmt = $conn->prepare("INSERT INTO items (name, category_id, quantity, medium_price, large_price, image, status, medium_size, large_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiddssii", $item_name, $category_id, $quantity, $medium_price, $large_price, $imagePath, $status, $medium_size, $large_size);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['update_item'])) {
        $item_id = $_POST['item_id'] ?? null;
        updateItem($conn, $item_id, $item_name, $category_id, $quantity, $medium_price, $large_price, $imagePath, $status);
    } elseif (isset($_POST['delete_item'])) {
        $item_id = $_POST['item_id'] ?? null;
        deleteItem($conn, $item_id);
    } elseif (isset($_POST['update_sizes'])) {
        $medium_size = $_POST['medium_size'] ?? 0;
        $large_size = $_POST['large_size'] ?? 0;
        updateSizes($conn, $medium_size, $large_size);
    }

    // For Category Handling
    if (isset($_POST['add_category']) || isset($_POST['update_category'])) {
        $category_name = $_POST['category_name'] ?? '';
        $category_id = $_POST['category_id'] ?? null;
        $imagePath = '';

        if (!empty($_FILES['image']['name'])) {
            $uploadResult = handleImageUpload($_FILES['image']);
            if (!$uploadResult['success']) {
                die($uploadResult['message']);
            }
            $imagePath = $uploadResult['path'];
        }

        if (isset($_POST['add_category'])) {
            $stmt = $conn->prepare("INSERT INTO categories (name, image) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_name, $imagePath);
            $stmt->execute();
            $stmt->close();
        } elseif (isset($_POST['update_category']) && $category_id) {
            $stmt = $conn->prepare($imagePath ? "UPDATE categories SET name=?, image=? WHERE id=?" : "UPDATE categories SET name=? WHERE id=?");
            $stmt->bind_param($imagePath ? "ssi" : "si", $category_name, $imagePath, $category_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: Inventory.php");
    exit();
}

// Function to handle image upload
function handleImageUpload($file)
{
    $targetDir = 'uploads/';
    $imageName = basename($file['name']);
    $imagePath = $targetDir . $imageName;
    $imageFileType = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

    // Validate the file
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    if ($file['size'] > 5000000) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit.'];
    }
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        return ['success' => false, 'message' => 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF.'];
    }

    // Save the file
    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
        return ['success' => false, 'message' => 'Failed to upload image.'];
    }

    return ['success' => true, 'path' => $imagePath];
}

// Function to add a new item
function addItem($conn, $name, $category_id, $quantity, $medium_price, $large_price, $image, $status, $medium_size, $large_size)
{
    $stmt = $conn->prepare("INSERT INTO items (name, category_id, quantity, medium_price, large_price, image, status, medium_size, large_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiddssii", $name, $category_id, $quantity, $medium_price, $large_price, $image, $status, $medium_size, $large_size);
    $stmt->execute();
    $stmt->close();
}

// Function to update an existing item
function updateItem($conn, $item_id, $name, $category_id, $quantity, $medium_price, $large_price, $image, $status)
{
    if ($image) {
        $stmt = $conn->prepare("UPDATE items SET name=?, category_id=?, quantity=?, medium_price=?, large_price=?, image=?, status=? WHERE id=?");
        $stmt->bind_param("siiddsii", $name, $category_id, $quantity, $medium_price, $large_price, $image, $status, $item_id);
    } else {
        $stmt = $conn->prepare("UPDATE items SET name=?, category_id=?, quantity=?, medium_price=?, large_price=?, status=? WHERE id=?");
        $stmt->bind_param("siiddii", $name, $category_id, $quantity, $medium_price, $large_price, $status, $item_id);
    }
    $stmt->execute();
    $stmt->close();
}

// Function to delete an item
function deleteItem($conn, $item_id)
{
    $stmtSales = $conn->prepare("DELETE FROM sales WHERE item_id=?");
    $stmtSales->bind_param("i", $item_id);
    $stmtSales->execute();
    $stmtSales->close();

    $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
}

// Function to update medium and large sizes for all items
function updateSizes($conn, $medium_size, $large_size)
{
    $stmt = $conn->prepare("UPDATE items SET medium_size=?, large_size=?");
    $stmt->bind_param("ii", $medium_size, $large_size);
    $stmt->execute();
    $stmt->close();
}

// Fetch items and categories
$items = $conn->query("SELECT items.*, categories.name as category_name FROM items JOIN categories ON items.category_id = categories.id");
$categories = $conn->query("SELECT * FROM categories");


?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Sales Dashboard</title>
    <link rel="stylesheet" href="Inventory.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="container">
       

 <!-- ITEM STOCK -->
        <div class="item-details">
            <h2>Item Details</h2>
            <form id="item-form" action="Inventory.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="item-id">
                <div>
                    <label for="item-name">Item Name</label>
                    <input type="text" name="item_name" id="item-name" required>
                </div>

                <div>
                    <label for="item-category">Category</label>
                    <select name="category_id" id="item-category" required class="custom-select">
                        <?php while ($row = $categories->fetch_assoc()) { ?>
                            <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="item-quantity">Quantity</label>
                    <input type="number" name="item_quantity" id="item-quantity" required>
                </div>

                <div>
                    <label for="medium-price">Medium Price</label>
                    <input type="number" step="0.01" name="medium_price" id="medium-price" required>
                </div>

                <div>
                    <label for="large-price">Large Price</label>
                    <input type="number" step="0.01" name="large_price" id="large-price" required>
                </div>

                <div>
                    <label for="item-status">Available</label>
                    <input type="checkbox" name="status" id="item-status" checked>
                </div>

                <div>
                    <label for="item-image">Image</label>
                    <input type="file" name="image" id="item-image" accept="image/*">
                </div>

                <button type="submit" name="add_item" id="add-item-btn">Add Item</button>
                <button type="submit" name="update_item" id="update-item-btn">Update Item</button>
                <button type="submit" name="delete_item" id="delete-item-btn">Delete Item</button>           
            </form>
        </div>

        <div class="item-list">
            <h2>Item List</h2>
            <input type="text" id="search-input" placeholder="Search for items..." onkeyup="searchItems()">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Medium Price</th>
                        <th>Large Price</th>
                        <th>Availability</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody id="item-table-body">
                    <?php while ($item = $items->fetch_assoc()) { ?>
                        <tr class="item-row" onclick="editItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?= $item['category_id'] ?>, <?= $item['quantity'] ?>, <?= $item['medium_price'] ?>, <?= $item['large_price'] ?>, '<?= htmlspecialchars($item['image']) ?>', <?= $item['status'] ?>)">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                            <td><?= htmlspecialchars($item['medium_price']) ?></td>
                            <td><?= htmlspecialchars($item['large_price']) ?></td>
                            <td><?= $item['status'] == 1 ? 'Available' : 'Unavailable' ?></td>
                            <td><img src="<?= htmlspecialchars($item['image']) ?>" alt="Image" width="50"></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="item-details">
            <h2>Category Details</h2>
            <form id="category-form" action="Inventory.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_id" id="category-id">
            <div>
                <label for="category-name">Category Name</label>
                <input type="text" name="category_name" id="category-name" required>
            </div>
            <div>
                <label for="category-image">Image</label>
                <input type="file" name="image" id="category-image" accept="image/*">
            </div>
            <button type="submit" name="add_category">Add Category</button>
            <button type="submit" name="update_category" style="display:none;">Update Category</button>
        </form>
    </div>

        <!-- Category List Section -->
        <div class="item-list2">
            <h2>Category List</h2>
            <input type="text" id="category-search" placeholder="Search categories..." onkeyup="searchCategories()">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="category-list-body">
                <?php foreach ($categories as $category) { ?>
                    <tr onclick="editCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>', '<?= htmlspecialchars($category['image']) ?>')">
                        <td><img src="<?= htmlspecialchars($category['image']) ?>" alt="Category Image" width="50"></td>
                        <td><?= htmlspecialchars($category['name']) ?></td>
                        <td>
                            <button onclick="deleteCategory(<?= $category['id'] ?>); event.stopPropagation();">Delete</button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="cups-info">
            <div class="cups-left">
                <h4>Remaining Cups</h4>
                <form method="POST">
                    <div class="cup-size">
                        <label for="large-size">Large</label>
                        <input type="number" name="large_size" id="large-size" required>
                    </div>
                    <div class="cup-size">
                        <label for="medium-size">Medium</label>
                        <input type="number" name="medium_size" id="medium-size" required>
                    </div>
                    <button type="submit" name="update_sizes" id="update-quantities-btn">ADD CUPS</button>
                </form>
            </div>
        </div>
    </div>

    
    

    <script>
        function editItem(id, name, categoryId, quantity, mediumPrice, largePrice, image, status) {
            document.getElementById('item-id').value = id;
            document.getElementById('item-name').value = name;
            document.getElementById('item-category').value = categoryId;
            document.getElementById('item-quantity').value = quantity;
            document.getElementById('medium-price').value = mediumPrice;
            document.getElementById('large-price').value = largePrice;
            document.getElementById('item-image').value = '';
            document.getElementById('item-status').checked = status == 1;
            document.getElementById('add-item-btn').style.display = 'inline';
            document.getElementById('update-item-btn').style.display = 'inline';
            document.getElementById('delete-item-btn').style.display = 'inline';
        }

        function searchItems() {
            let input = document.getElementById('search-input');
            let filter = input.value.toLowerCase();
            let rows = document.querySelectorAll('.item-row');

            rows.forEach(row => {
                let cells = row.getElementsByTagName('td');
                let itemName = cells[0].textContent || cells[0].innerText;

                if (itemName.toLowerCase().indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
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
</body>

</html>