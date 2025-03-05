<?php
include 'config.php';
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_name'])) {
    header('location:index.php');
    exit();
}

// Fetch all categories for category buttons
$categories = $conn->query("SELECT * FROM categories");

// Handle search and item addition
$search_term = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? null;

// Fetch items based on search or category
// Modify the item fetch query to include category name
if (!empty($search_term)) {
    $item_query = $conn->prepare("SELECT items.*, categories.name AS category_name FROM items 
                                  JOIN categories ON items.category_id = categories.id 
                                  WHERE items.name LIKE ?");
    $like_term = "%" . $search_term . "%";
    $item_query->bind_param("s", $like_term);
    $item_query->execute();
    $items = $item_query->get_result();
} else {
    if ($category_id) {
        $item_query = $conn->prepare("SELECT items.*, categories.name AS category_name FROM items 
                                      JOIN categories ON items.category_id = categories.id 
                                      WHERE items.category_id = ?");
        $item_query->bind_param("i", $category_id);
        $item_query->execute();
        $items = $item_query->get_result();
    } else {
        $items = $conn->query("SELECT items.*, categories.name AS category_name FROM items 
                               JOIN categories ON items.category_id = categories.id");
    }
}


// Initialize the current order array
if (!isset($_SESSION['order'])) {
    $_SESSION['order'] = [];
}

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Clear order action
    if (isset($_POST['clear_order'])) {
        $_SESSION['order'] = [];
    }
    // Handle pay now action
    if (isset($_POST['pay_now'])) {
        $payment = $change = $error = "";
        $popupVisible = false;

        // Get the discount type from the form submission
        $discountType = $_POST['discount_type'] ?? 'none'; // Default to 'none' if not selected

        // Set discount percentage based on selection
        $discountPercentage = 0;
        if ($discountType == 'pwd' || $discountType == 'senior') {
            $discountPercentage = 0.20; // 20% discount for both PWD and Senior Citizens
        }

        // Calculate total amount for the order
        $totalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
            return $sum + ($order_item['price'] * $order_item['quantity']);
        }, 0);

        // Apply the discount to the total amount
        $discountAmount = $totalAmount * $discountPercentage;
        $totalAmountAfterDiscount = $totalAmount - $discountAmount;
        if (empty($_SESSION['order'])) {
            $error = "No items in the order to process payment.";
            $popupVisible = true;
        } else {
            if (!empty($_POST["payment-input"])) {
                $payment = floatval($_POST["payment-input"]);
                if ($payment >= $totalAmountAfterDiscount) {
                    $change = $payment - $totalAmountAfterDiscount;
                    $today = date('Y-m-d');

                    // Update daily sales
                    $check_sales_query = $conn->prepare("SELECT total_sales FROM daily_sales WHERE date = ?");
                    $check_sales_query->bind_param("s", $today);
                    $check_sales_query->execute();
                    $result = $check_sales_query->get_result();

                    if ($result->num_rows > 0) {
                        $current_sales = $result->fetch_assoc()['total_sales'];
                        $new_sales = $current_sales + $totalAmountAfterDiscount;
                        $update_sales_query = $conn->prepare("UPDATE daily_sales SET total_sales = ? WHERE date = ?");
                        $update_sales_query->bind_param("ds", $new_sales, $today);
                        $update_sales_query->execute();
                    } else {
                        $insert_sales_query = $conn->prepare("INSERT INTO daily_sales (date, total_sales) VALUES (?, ?)");
                        $insert_sales_query->bind_param("sd", $today, $totalAmountAfterDiscount);
                        $insert_sales_query->execute();
                    }

                    // Deduct item quantities from inventory and record sales
                    foreach ($_SESSION['order'] as $order_item) {
                        $size_column = ($order_item['size'] === 'Large') ? 'large_size' : 'medium_size';
                        // Update inventory
                        $update_query = $conn->prepare("UPDATE items SET $size_column = $size_column - ? WHERE $size_column >= ?");
                        $update_query->bind_param("ii", $order_item['quantity'], $order_item['quantity']);
                        $update_query->execute();

                        // Record sale in the sales table
                        $insert_sales_query = $conn->prepare("INSERT INTO sales (item_id, quantity, size) VALUES (?, ?, ?)");
                        $insert_sales_query->bind_param("iis", $order_item['id'], $order_item['quantity'], $order_item['size']);
                        $insert_sales_query->execute();
                    }

                    // Prepare order details for transaction record
                    $orderDetails = '';
                    foreach ($_SESSION['order'] as $order_item) {
                        $orderDetails .= $order_item['name'] . ' x ' . $order_item['quantity'] . ' (' . $order_item['price'] . ' each)\n';
                    }

                    // Deduct item quantities from inventory and record sales
                    foreach ($_SESSION['order'] as $order_item) {
                        $update_query = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ?");
                        $update_query->bind_param("ii", $order_item['quantity'], $order_item['id']);
                        $update_query->execute();

                        // Record sale in the sales table
                        $insert_sales_query = $conn->prepare("INSERT INTO sales (item_id, quantity) VALUES (?, ?)");
                        $insert_sales_query->bind_param("ii", $order_item['id'], $order_item['quantity']);
                        $insert_sales_query->execute();
                    }
                    // Record transaction
                    $paymentStatus = "Paid";
                    $transaction_query = $conn->prepare("INSERT INTO transactions (total_amount, order_details, payment_status, cash) VALUES (?, ?, ?, ?)");
                    $transaction_query->bind_param("dsss", $totalAmountAfterDiscount, $orderDetails, $paymentStatus, $payment);
                    $transaction_query->execute();

                    // Clear the session order
                    $_SESSION['order'] = [];
                    $popupVisible = false;
                } else {
                    $error = "Insufficient payment. Please enter a valid amount.";
                    $popupVisible = true;
                }
            } else {
                $error = "Please enter a valid payment amount.";
                $popupVisible = true;
            }
        }
    }
    // Add item to order action
    elseif (isset($_POST['add_item'])) {
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'];
        $size = $_POST['size'];
        $sugar_level = $_POST['sugar_level'];
        $add_ons = isset($_POST['add_ons']) ? $_POST['add_ons'] : [];

        // Fetch the item details from the database
        $item_query = $conn->prepare("SELECT * FROM items WHERE id = ?");
        $item_query->bind_param("i", $item_id);
        $item_query->execute();
        $item = $item_query->get_result()->fetch_assoc();

        $price = ($size == 'Large') ? $item['large_price'] : $item['medium_price'];
        $size_column = ($size == 'Large') ? 'large_size' : 'medium_size';
        $available_quantity = $item[$size_column];

        // Add-on prices
        $add_on_prices = [
            "Tapioca" => 6,
            "Nata" => 6,
            "Crushed Graham" => 6,
            "Crushed Oreo" => 9,
            "Coffee Jelly" => 9,
            "Espresso Shot" => 6,
            "Cream Cheese" => 11,
        ];

        $total_add_on_price = 0;
        foreach ($add_ons as $add_on) {
            if (isset($add_on_prices[$add_on])) {
                $total_add_on_price += $add_on_prices[$add_on];
            }
        }

        $final_price = ($price + $total_add_on_price) * $quantity;

        if ($available_quantity >= $quantity) {
            // Check if item exists in session order
            $existing_item_index = null;
            foreach ($_SESSION['order'] as $index => $order_item) {
                if ($order_item['id'] === $item['id'] && $order_item['size'] === $size && $order_item['sugar_level'] === $sugar_level && $order_item['add_ons'] == $add_ons) {
                    $existing_item_index = $index;
                    break;
                }
            }

            if ($existing_item_index === null) {
                $_SESSION['order'][] = [
                    'id' => $item['id'],
                    'name' => $item['name'] . " ($size)",
                    'quantity' => $quantity,
                    'price' => $final_price / $quantity,
                    'size' => $size,
                    'add_ons' => $add_ons,
                    'sugar_level' => $sugar_level,
                    'image' => $item['image']
                ];
            } else {
                $_SESSION['order'][$existing_item_index]['quantity'] += $quantity;
                $_SESSION['order'][$existing_item_index]['price'] = ($final_price / $quantity);
            }
        } else {
            echo "<script>alert('Insufficient stock for {$item['name']}. Available: $available_quantity');</script>";
        }
    }

    // Calculate total amount including add-ons
    $totalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
        return $sum + ($order_item['price'] * $order_item['quantity']);
    }, 0);
}


// Payment logic
$payment = $change = $error = "";

$popupVisible = false; // Variable to control popup visibility

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["pay_now"])) {
    if (empty($_SESSION['order'])) {
        $error = "No items in the order to process payment.";
        $popupVisible = true; // Keep the popup open on error
    } else {
        if (!empty($_POST["payment-input"])) {
            $payment = floatval($_POST["payment-input"]);
            if ($payment >= $totalAmount) {
                $change = $payment - $totalAmount;

                // Update daily sales
                $today = date('Y-m-d');
                $check_sales_query = $conn->prepare("SELECT total_sales FROM daily_sales WHERE date = ?");
                $check_sales_query->bind_param("s", $today);
                $check_sales_query->execute();
                $result = $check_sales_query->get_result();

                if ($result->num_rows > 0) {
                    $current_sales = $result->fetch_assoc()['total_sales'];
                    $new_sales = $current_sales + $totalAmount;
                    $update_sales_query = $conn->prepare("UPDATE daily_sales SET total_sales = ? WHERE date = ?");
                    $update_sales_query->bind_param("ds", $new_sales, $today);
                    $update_sales_query->execute();
                } else {
                    $insert_sales_query = $conn->prepare("INSERT INTO daily_sales (date, total_sales) VALUES (?, ?)");
                    $insert_sales_query->bind_param("sd", $today, $totalAmount);
                    $insert_sales_query->execute();
                }

                // Deduct item quantities from inventory
                foreach ($_SESSION['order'] as $order_item) {
                    $item_id = $order_item['id'];
                    $item_query = $conn->prepare("SELECT quantity FROM items WHERE id = ?");
                    $item_query->bind_param("i", $item_id);
                    $item_query->execute();
                    $item_result = $item_query->get_result()->fetch_assoc();

                    $new_quantity = $item_result['quantity'] - $order_item['quantity'];
                    $update_query = $conn->prepare("UPDATE items SET quantity = ? WHERE id = ?");
                    $update_query->bind_param("ii", $new_quantity, $item_id);
                    $update_query->execute();
                }

                // Prepare order details for transaction record
                $orderDetails = '';
                foreach ($_SESSION['order'] as $order_item) {
                    $orderDetails .= $order_item['name'] . ' x ' . $order_item['quantity'] . ' (' . $order_item['price'] . ' each)\n';
                }

                // Insert transaction into transactions table only if order is not empty
                if (!empty($_SESSION['order'])) {
                    $paymentStatus = "Paid";
                    $transaction_query = $conn->prepare("INSERT INTO transactions (total_amount, order_details, payment_status) VALUES (?, ?, ?)");
                    $transaction_query->bind_param("dss", $totalAmount, $orderDetails, $paymentStatus);
                    $transaction_query->execute();
                }

                $_SESSION['order'] = []; // Clear the order after successful payment
                $totalAmount = 0; // Reset total amount
                $popupVisible = false; // Hide popup after successful payment
            } else {
                $error = "Insufficient payment. Please enter a valid amount.";
                $popupVisible = true; // Keep the popup open on insufficient payment
            }
        } else {
            $error = "Please enter a valid payment amount.";
            $popupVisible = true; // Keep the popup open if no payment input
        }
    }
}
$categories = $conn->query("SELECT * FROM categories");

// Fetch the first item only to calculate remaining cups
$first_cup_query = $conn->query("SELECT id, name, medium_size, large_size FROM items LIMIT 1");
$first_cup = $first_cup_query->fetch_assoc();

// Initialize remaining cups for the first item
$remainingMedium = $first_cup ? $first_cup['medium_size'] : 0;
$remainingLarge = $first_cup ? $first_cup['large_size'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_item') {
    $itemIdToRemove = $_POST['id'];

    // Find the item in the session order and remove it
    foreach ($_SESSION['order'] as $index => $order_item) {
        if ($order_item['id'] == $itemIdToRemove) {
            // Remove the item from the order array
            unset($_SESSION['order'][$index]);
            $_SESSION['order'] = array_values($_SESSION['order']); // Reindex the array

            // Calculate new total
            $newTotalAmount = array_reduce($_SESSION['order'], function ($sum, $order_item) {
                return $sum + ($order_item['price'] * $order_item['quantity']);
            }, 0);

            // Respond with new total amount and success status
            echo json_encode([
                'success' => true,
                'newTotalAmount' => $newTotalAmount
            ]);
            exit;
        }
    }

    // If item wasn't found, return failure response
    echo json_encode(['success' => false]);
    exit;
}

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure order session exists
if (!isset($_SESSION['order'])) {
    $_SESSION['order'] = [];
}

// Calculate total amount including add-ons
$totalAmount = !empty($_SESSION['order']) ? array_reduce($_SESSION['order'], function ($sum, $order_item) {
    return $sum + ($order_item['price'] * $order_item['quantity']);
}, 0) : 0;


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link rel="stylesheet" href="meme.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
</head>

<body>

    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <?php include 'order_side.php' ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="search-container">
                <form method="GET" action="order_management.php">
                    <input type="text" placeholder="Search" name="search" id="search-input" autocomplete="off" list="suggestions-list">
                    <datalist id="suggestions-list"></datalist>
                    <button class="search-button" type="submit">
                        <h3>Search</h3>
                    </button>
                </form>
            </div>

            <div class="buttons-container">
                <?php while ($row = $categories->fetch_assoc()) { ?>
                    <form method="GET" action="order_management.php" style="display: inline;">
                        <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                        <button class="category-button" type="submit">
                            <h5><?= $row['name'] ?></h5>
                        </button>
                    </form>
                <?php } ?>
                <form method="GET" action="order_management.php" style="display: inline;">
                    <button class="category-button" type="submit">
                        <h5>All Items</h5>
                    </button>
                </form>
            </div>

            <div class="product-container">
                <div class="product-grid">
                    <?php while ($item = $items->fetch_assoc()) {
                        $mediumOutOfStock = ($item['medium_size'] <= 0);
                        $largeOutOfStock = ($item['large_size'] <= 0);
                        $outOfStockClass = ($mediumOutOfStock && $largeOutOfStock) ? 'out-of-stock' : '';
                    ?>
                        <?php if ($item['quantity'] > 0): ?> <!-- Only display if quantity is greater than 0 -->
                            <div class="product-item <?= $item['status'] == 0 ? 'out-of-stock' : '' ?>" data-item-id="<?= $item['id'] ?>">
                                <small class="category-name"><?= htmlspecialchars($item['category_name']) ?></small> <!-- Display category name here -->
                                <img src="<?= $item['image'] ?>" alt="Product Image">
                                <h5 class="<?= $outOfStockClass ?>"><?= htmlspecialchars($item['name']) ?></h5>
                                <p>Medium Price: ₱<?= number_format($item['medium_price'], 2) ?></p>
                                <p>Large Price: ₱<?= number_format($item['large_price'], 2) ?></p>
                                <!-- Display the quantity -->
                                <p class="available-quantity"><strong>Stocks:</strong> <?= $item['quantity'] ?></p>
                            </div>
                        <?php else: ?>
                            <div class="product-item out-of-stock" data-item-id="<?= $item['id'] ?>">
                                <img src="<?= $item['image'] ?>" alt="Product Image">
                                <h5><?= $item['name'] ?> (Out of Stock)</h5>
                                <p>Medium Price: ₱<?= number_format($item['medium_price'], 2) ?></p>
                                <p>Large Price: ₱<?= number_format($item['large_price'], 2) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php } ?>
                </div>
            </div>


            <div class="pagination">
                <?php
                $limit = 10; // Items per page
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $limit;

                // Fetch total number of items
                $totalItemsQuery = $conn->query("SELECT COUNT(*) AS total FROM items");
                $totalItems = $totalItemsQuery->fetch_assoc()['total'];
                $totalPages = ceil($totalItems / $limit);

                if ($totalPages > 1) {
                    echo '<div class="pagination-buttons">';

                    if ($page > 1) {
                        echo '<a href="?page=' . ($page - 1) . '" class="pagination-button">&laquo; Previous</a>';
                    }

                    for ($i = 1; $i <= $totalPages; $i++) {
                        echo '<a href="?page=' . $i . '" class="pagination-button ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
                    }

                    if ($page < $totalPages) {
                        echo '<a href="?page=' . ($page + 1) . '" class="pagination-button">Next &raquo;</a>';
                    }

                    echo '</div>';
                }
                ?>
            </div>

        </div>

        <!-- Order Section -->
        <div class="order-section">
            <!-- Order Header -->
            <div class="order-header">
                <h1>Current Order</h1>
                <button class="clear-button" onclick="openClearPopup()">
                    <h3>Clear</h3>
                </button>
            </div>

            <!-- Popup Modal for Confirmation -->
            <div id="clearPopup" class="popup-container">
                <div class="popup-content">
                <div class="logo-background1">
                        <img src="/img/123img.png" alt="Logo" class="popup-logo">
                    </div>
                    <p>Are you sure you want to clear the current order?</p>
                    <form method="POST" action="order_management.php">
                        <button type="submit" name="clear_order" class="confirm-clear">Yes, Clear</button>
                        <button type="button" class="cancel-clear" onclick="closeClearPopup()">Cancel</button>
                    </form>
                </div>
            </div>


            <!-- JavaScript for Popup -->
            <script>
                function openClearPopup() {
                    document.getElementById("clearPopup").style.display = "flex";
                }

                function closeClearPopup() {
                    document.getElementById("clearPopup").style.display = "none";
                }
            </script>


            <!-- Cashier Label -->
            <div class="cashier-label">
                <span class="cashier-text">CASHIER </span>
                <span class="cashier-username">- <?= htmlspecialchars($_SESSION['user_name']); ?></span>

            </div>


            <!-- JavaScript for Confirmation Dialog -->
            <script>
                function confirmClearOrder() {
                    return confirm("Are you sure you want to clear the current order?");
                }
            </script>

            <div class="order-list" id="order-list">
                <?php if (!empty($_SESSION['order'])): ?>
                    <ul>
                        <?php foreach ($_SESSION['order'] as $order_item): ?>
                            <li class="order-item" data-item-id="<?= $order_item['id'] ?>">
                                <img src="<?= $order_item['image'] ?>" alt="Item Image" class="order-item-image">
                                <div class="order-item-details">
                                    <h5><?= $order_item['name'] ?></h5>
                                    <p>₱<?= $order_item['price'] ?></p>
                                    <!-- Display add-ons and sugar level -->
                                    <p><strong>Add-ons:</strong> <?= implode(', ', $order_item['add_ons']) ?></p>
                                    <p><strong>Sugar Level:</strong> <?= $order_item['sugar_level'] ?>%</p>
                                </div>
                                <div class="order-item-actions">
                                    <input type="number" value="<?= $order_item['quantity'] ?>" class="item-quantity" min="1">
                                    <span class="material-icons-outlined delete-icon">delete</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No items in the order</p>
                <?php endif; ?>
            </div>


            <div class="order-footer">
                <div class="quantity-labels">
                    <p style="text-decoration: <?= ($remainingMedium <= 0) ? 'line-through' : 'none'; ?>">
                        Medium Stock: <?= $remainingMedium ?>
                    </p>
                    <p style="text-decoration: <?= ($remainingLarge <= 0) ? 'line-through' : 'none'; ?>">
                        Large Stock: <?= $remainingLarge ?>
                    </p>
                </div>
            </div>


            <div class="order-footer1">
                <p class="total-row">Total <span id="total-amount">₱: <?= number_format($totalAmount, 2); ?> </span></p>
                <p class="payment-row1">Sub-total <span id="popup-subtotal">₱: <?= number_format($totalAmount / 1.12, 2); ?> </span></p>
                <p class="payment-row1">Sales Tax <span id="popup-tax">₱: <?= number_format($totalAmount * 0.12 / 1.12, 2); ?> </span></p>
                <div class="payment-buttons">
                    <button class="pay-now" id="pay-now-btn">
                        <h1>Process Payment</h1>
                    </button>
                </div>
            </div>
        </div>

    </div>
    </div>

    <!-- Quantity Input Popup -->
    <!-- Enhanced Quantity Input Popup -->
    <div class="popup" id="quantity-popup">
        <form method="POST" action="order_management.php">
            <input type="hidden" id="item-id-input" name="item_id">

            <label for="quantity"><strong>Enter Quantity:</strong></label>
            <input type="number" id="quantity" name="quantity" min="1" required>

            <label for="size"><strong>Select Size:</strong></label>
            <select id="size" name="size" required>
                <option value="Medium">Medium</option>
                <option value="Large">Large</option>
            </select>

            <label><strong>Add-ons</strong></label>
            <div class="add-ons-container">
                <label><input type="checkbox" name="add_ons[]" value="Tapioca"> Tapioca</label>
                <label><input type="checkbox" name="add_ons[]" value="Nata"> Nata</label>
                <label><input type="checkbox" name="add_ons[]" value="Crushed Graham"> Crushed Graham</label>
                <label><input type="checkbox" name="add_ons[]" value="Crushed Oreo"> Crushed Oreo</label>
                <label><input type="checkbox" name="add_ons[]" value="Coffee Jelly"> Coffee Jelly</label>
                <label><input type="checkbox" name="add_ons[]" value="Espresso Shot"> Espresso Shot</label>
                <label><input type="checkbox" name="add_ons[]" value="Cream Cheese"> Cream Cheese</label>
            </div>

            <label><strong>Sugar Level:</strong></label>
            <div class="sugar-options">
                <label><input type="radio" name="sugar_level" value="0" checked> 0%</label>
                <label><input type="radio" name="sugar_level" value="25"> 25%</label>
                <label><input type="radio" name="sugar_level" value="50"> 50%</label>
                <label><input type="radio" name="sugar_level" value="75"> 75%</label>
                <label><input type="radio" name="sugar_level" value="100"> 100%</label>
            </div>

            <button type="submit" name="add_item" class="add-order-button">Add To Order</button>
        </form>
    </div>

    <div class="overlay" id="overlay"></div>


    <script>
        // Select all necessary elements
        const productItems = document.querySelectorAll('.product-item');
        const orderItems = document.querySelectorAll('.order-item');
        const deleteIcons = document.querySelectorAll('.delete-icon');
        const popup = document.getElementById('quantity-popup');
        const overlay = document.getElementById('overlay');
        const itemIdInput = document.getElementById('item-id-input');
        const sizeInput = document.getElementById('size');
        const quantityInput = document.getElementById('quantity');

        // Open popup for products
        productItems.forEach(item => {
            item.addEventListener('click', () => {
                const itemId = item.getAttribute('data-item-id');
                itemIdInput.value = itemId;
                sizeInput.value = "Medium"; // Default size
                quantityInput.value = 1; // Default quantity
                popup.style.display = 'block';
                overlay.style.display = 'block';
            });
        });

        // Open popup for order items (except delete action)
        orderItems.forEach(order => {
            order.addEventListener('click', (e) => {
                if (e.target.classList.contains('delete-icon')) {
                    // Prevent opening the popup if the delete icon is clicked
                    return;
                }

                const itemId = order.getAttribute('data-item-id');
                const orderDetails = order.querySelector('.order-item-details');
                const currentSize = orderDetails.getAttribute('data-size');
                const currentQuantity = order.querySelector('.item-quantity').value;

                itemIdInput.value = itemId;
                sizeInput.value = currentSize;
                quantityInput.value = currentQuantity;
                popup.style.display = 'block';
                overlay.style.display = 'block';
            });
        });

        // Handle delete icon click
        deleteIcons.forEach(icon => {
            icon.addEventListener('click', (e) => {
                const itemId = icon.closest('.order-item').getAttribute('data-item-id');

                // Send a POST request to remove the item via AJAX
                fetch('order_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'remove_item',
                            id: itemId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the UI, e.g., remove the item and update total amount
                            icon.closest('.order-item').remove();
                            document.getElementById('total-amount').textContent = data.newTotalAmount.toFixed(2);
                        } else {
                            alert('Failed to remove the item. Please try again.');
                        }
                    })
                    .catch(error => console.error('Error:', error));

                e.stopPropagation(); // Prevent triggering order item click event
            });
        });

        // Close popup when clicking outside
        overlay.addEventListener('click', () => {
            popup.style.display = 'none';
            overlay.style.display = 'none';
        });

        // Dynamically update price based on size selection
        sizeInput.addEventListener('change', () => {
            const size = sizeInput.value;
            const itemId = itemIdInput.value;

            fetch(`get_item_price.php?item_id=${itemId}&size=${size}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newPrice = data.price;
                        document.querySelector('#popup-price').textContent = `Price: ₱${newPrice}`;
                    }
                })
                .catch(error => console.error('Error fetching price:', error));
        });

        // Open popup with default values
        document.querySelectorAll('.product-item').forEach(item => {
            item.addEventListener('click', () => {
                const itemId = item.getAttribute('data-item-id');
                document.getElementById('item-id-input').value = itemId;

                document.getElementById('size').value = "Medium"; // Default size
                document.getElementById('quantity').value = 1; // Default quantity
                document.querySelector('input[name="sugar_level"][value="50"]').checked = true; // Default sugar level

                document.getElementById('quantity-popup').style.display = 'block';
                document.getElementById('overlay').style.display = 'block';
            });
        });

        // Close popup
        document.getElementById('overlay').addEventListener('click', () => {
            document.getElementById('quantity-popup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        });
    </script>



    <!-- Payment Input Popup -->
    <div class="popup" id="payment-popup" style="display: <?= $popupVisible ? 'block' : 'none'; ?>;">
        <h2>Payment</h2>

        <p class="payment-row3">Total: ₱<span id="popup-total"> <?= number_format($totalAmount, 2); ?> </span></p>
        <p class="payment-row2">Sub-total: ₱<span id="popup-subtotal"><?= number_format($totalAmount / 1.12, 2); ?> </span></p>
        <p class="payment-row2">Sales Tax: ₱<span id="popup-tax"><?= number_format($totalAmount * 0.12 / 1.12, 2); ?> </span></p>
        <p class="payment-row2">Change: ₱<span id="popup-change">0.00</span></p>

        <!-- Add Discount Dropdown to Payment Form -->
        <form method="POST" action="">
            <div class="input-display">
                <label for="discount-select">Select Discount:</label>
                <select id="discount-select" name="discount_type">
                    <option value="none">None</option>
                    <option value="pwd">PWD</option>
                    <option value="senior">Senior Citizen</option>
                </select>
            </div>

            <!-- Payment Input -->
            <div class="input-display">
                <input type="text" id="payment-input" name="payment-input" placeholder="0"
                    value="<?= htmlspecialchars($payment); ?>" oninput="calculateChange(<?= $totalAmount; ?>)">
                <button type="button" onclick="clearInput()">clear</button>
            </div>

            <!-- Numpad for Payment Input -->
            <div class="num-pad-container">
                <div class="num-pad">
                    <button type="button" onclick="addNumber(7)">7</button>
                    <button type="button" onclick="addNumber(8)">8</button>
                    <button type="button" onclick="addNumber(9)">9</button>
                    <button type="button" onclick="addNumber(4)">4</button>
                    <button type="button" onclick="addNumber(5)">5</button>
                    <button type="button" onclick="addNumber(6)">6</button>
                    <button type="button" onclick="addNumber(1)">1</button>
                    <button type="button" onclick="addNumber(2)">2</button>
                    <button type="button" onclick="addNumber(3)">3</button>
                    <button type="button" onclick="addNumber('.')">.</button>
                    <button type="button" onclick="addNumber(0)">0</button>
                    <button type="button" onclick="addNumber('00')">00</button>
                </div>
            </div>

            <!-- Submit Payment Button -->
            <button type="submit" class="pay-button" name="pay_now" onclick="handlePayment()">Pay</button>
        </form>

        <!-- Cancel Button -->
        <button class="cancel-button" onclick="cancelOrder()">Cancel</button>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?= $error; ?></p>
        <?php endif; ?>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="popup-overlay" style="display: <?= $popupVisible ? 'block' : 'none'; ?>;"></div>

    <script>
        function updateTotal() {
            let total = parseFloat(<?= $totalAmount; ?>);
            let discountType = document.querySelector('input[name="discount_type"]:checked').value;

            if (discountType === "pwd" || discountType === "senior") {
                total *= 0.80; // Apply 20% discount
            }

            let subTotal = total / 1.12;
            let tax = total * 0.12 / 1.12;

            document.getElementById("popup-subtotal").textContent = subTotal.toFixed(2);
            document.getElementById("popup-tax").textContent = tax.toFixed(2);
            document.getElementById("popup-total").textContent = total.toFixed(2);

            calculateChange();
        }
        // JavaScript Variables for Dynamic Data
        const orderDetails = <?= json_encode($_SESSION['order'] ?? []); ?>;

        // Show Payment Popup
        document.getElementById('pay-now-btn').addEventListener('click', function() {
            document.getElementById('payment-popup').style.display = 'block';
            document.getElementById('popup-overlay').style.display = 'block';
        });

        // Close Popup when clicking outside
        document.getElementById('popup-overlay').addEventListener('click', () => {
            cancelOrder();
        });

        // Add Number to Payment Input
        function addNumber(number) {
            let input = document.getElementById('payment-input');
            input.value += number;
            calculateChange(<?= $totalAmount; ?>); // Recalculate change
        }

        // Clear Input
        function clearInput() {
            document.getElementById('payment-input').value = '';
            document.getElementById('popup-change').innerText = '0.00'; // Reset change display
        }

        // Calculate Change
        function calculateChange(totalAmount) {
            const paymentInput = parseFloat(document.getElementById('payment-input').value) || 0;
            const change = paymentInput - totalAmount;

            // Set change to 0 if negative
            const displayChange = change > 0 ? change.toFixed(2) : "0.00";

            // Update the change display
            document.getElementById('popup-change').innerText = displayChange;
        }

        // Cancel Payment Process
        function cancelOrder() {
            document.getElementById('payment-popup').style.display = 'none';
            document.getElementById('popup-overlay').style.display = 'none';
        }
        //==============================================================================================//
        // Handle Payment and Print Receipt
        function handlePayment() {
            const paymentInput = parseFloat(document.getElementById('payment-input').value) || 0;
            const totalAmount = parseFloat(document.getElementById('popup-total').innerText.replace('₱', ''));

            if (paymentInput >= totalAmount) {
                // If payment is sufficient, generate receipt
                const transactionId = Math.random().toString(36).substr(2, 9); // Mock transaction ID
                printReceipt(transactionId); // Print receipt automatically
                cancelOrder(); // Close the payment popup
            } else {
                alert("Insufficient payment!");
            }
        }

        // Function to Calculate Discounted Total
        function calculateDiscountedTotal(totalAmount, discountType) {
            const discountRates = {
                pwd: 0.20, // 20% discount for PWD
                senior: 0.20, // 20% discount for Senior Citizen
                none: 0.00 // No discount
            };

            const discountRate = discountRates[discountType] || 0.00;
            const discountAmount = totalAmount * discountRate;
            const discountedTotal = totalAmount - discountAmount;
            const subTotal = discountedTotal / 1.12;
            const tax = discountedTotal * 0.12 / 1.12;

            return {
                discountedTotal: discountedTotal.toFixed(2),
                discountAmount: discountAmount.toFixed(2),
                subTotal: subTotal.toFixed(2),
                tax: tax.toFixed(2)
            };
        }

        // Updated Calculate Change
        function calculateChange(totalAmount) {
            const discountType = document.getElementById('discount-select').value;
            const { discountedTotal, discountAmount, subTotal, tax } = calculateDiscountedTotal(totalAmount, discountType);

            const paymentInput = parseFloat(document.getElementById('payment-input').value) || 0;
            const change = paymentInput - parseFloat(discountedTotal);

            const displayChange = change > 0 ? change.toFixed(2) : "0.00";

            // Update Display with Peso Sign After Amount
            document.getElementById('popup-change').innerText = `${displayChange} ₱`;
            document.getElementById('popup-total').innerText = `${discountedTotal} ₱`;
            document.getElementById('popup-subtotal').innerText = `${subTotal} ₱`;
            document.getElementById('popup-tax').innerText = `${tax} ₱`;
        }

        function printReceipt(transactionId) {
    const discountType = document.getElementById('discount-select').value;
    const { discountedTotal, discountAmount, subTotal, tax } = calculateDiscountedTotal(<?= $totalAmount; ?>, discountType);

    const cashierName = "<?= htmlspecialchars($_SESSION['user_name']); ?>"; // Get the cashier's name

    const transaction = {
        id: transactionId,
        order_details: orderDetails.map(item => {
            let details = `${item.quantity}x ${item.name}`;
            if (item.add_ons) details += ` (Add-ons: ${item.add_ons})`;
            if (item.sugar_level) details += `, Sugar Level: ${item.sugar_level}%`;
            return details;
        }),
        total_amount: discountedTotal,
        discount_type: discountType,
        discount_amount: discountAmount,
        sub_total: subTotal,
        tax: tax,
        cash: document.getElementById('payment-input').value,
        transaction_date: new Date().toISOString()
    };

    const storeName = "Mr. Boy Special Tea";
    const orderDetailsString = transaction.order_details.map(item => `<span class="item-Names">${item}</span>`).join("<br>");
    const totalAmount = parseFloat(transaction.total_amount).toFixed(2);
    const cash = parseFloat(transaction.cash).toFixed(2);
    const change = (cash - totalAmount).toFixed(2);

    // Receipt HTML with Cashier Name
    const receiptHTML = `
<html>
<head>
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 330px;
            width: 150%;
            text-align: center;
        }
        .close {
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
            font-size: 20px;
        }
        .receipt {
            font-size: 12px;
            font-family: Arial, sans-serif;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 20px 0;
        }
        .title {
            font-size: 10px;
            font-weight: bold;
        }
        .text-bold {
            font-weight: bold;
        }
        .text-bold1 {
            font-weight: bold;
            text-align: center;
        }
        .store-name1 {
            font-weight: bold;
            text-align: center;
        }
        table th, table td {
            padding: 5px;
            font-size: 13px;
        }
        .thank-you {
            margin-top: 20px;
            font-weight: bold;
            text-align: center;
        }
        .receipt .item-Names {
            font-size: 10px;
            margin-bottom: 15px;
            font-weight: normal;
        }
    </style>
</head>
<body>
   <div class="receipt">
        <h3 class="store-name1">${storeName}</h3>
        <p><span class="text-bold">Date:</span> ${new Date(transaction.transaction_date).toLocaleString()}</p>
        <p><span class="text-bold">Cashier:</span> ${cashierName}</p>
        <div class="line"></div>
        <div class="text-bold1">CASH RECEIPT</div>
        <div class="line"></div>
        <div>
            <span class="text-bold">Description:</span><br>
            ${orderDetailsString}
            <div class="line"></div>
        </div>
        <table>
           <tbody>
                <tr>
                    <td class="text-bold">Total Amount  </td>
                     <td>₱: ${(parseFloat(discountedTotal) + parseFloat(discountAmount)).toFixed(2)} </td>
                </tr>
                <tr>
                    <td class="text-bold">Sub-total  </td>
                    <td>₱: ${subTotal}</td>
                </tr>
                <tr>
                    <td class="text-bold">Sales Tax </td>
                    <td>₱: ${tax}</td>
                </tr>
                <tr>
                    <td class="text-bold">Discount </td>
                    <td>₱: ${discountAmount}</td>
                </tr>
                <tr>
                    <td class="text-bold">Discounted </td>
                    <td>₱: ${totalAmount}</td>
                </tr>
                <tr>
                    <td class="text-bold">Cash </td>
                    <td>₱: ${cash}</td>
                </tr>
                <tr>
                    <td class="text-bold">Change </td>
                    <td>₱: ${change}</td>
                </tr>
            </tbody>
        </table>
        <div class="line"></div>
        <p class="thank-you">THANK YOU!</p>
    </div>
</body>
</html>
`;

    // Open Receipt Print Window
    const receiptWindow = window.open('', '', 'height=600,width=400');
    receiptWindow.document.write(receiptHTML);
    receiptWindow.document.close();
    receiptWindow.print(); // Automatically trigger print dialog
}



        // Attach event listener to delete icons
        document.addEventListener('DOMContentLoaded', function() {
            const deleteIcons = document.querySelectorAll('.delete-icon');

            deleteIcons.forEach(icon => {
                icon.addEventListener('click', function(event) {
                    const itemId = event.target.closest('.order-item').dataset.itemId;

                    // Send a request to remove the item from the session
                    fetch('order_management.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=remove_item&id=${itemId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove item from the list
                                event.target.closest('.order-item').remove();
                                updateTotalAmount(data.newTotalAmount);
                            } else {
                                alert('Error removing item');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });
            });

            // Function to update total amount displayed
            function updateTotalAmount(newTotalAmount) {
                document.getElementById('total-amount').textContent = newTotalAmount.toFixed(2);
            }
        });


        const searchInput = document.getElementById('search-input');
        const suggestionsList = document.getElementById('suggestions-list');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value;

            if (query.length > 0) {
                fetch(`search_suggestions.php?query=${query}`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionsList.innerHTML = ''; // Clear previous suggestions

                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item; // Display the suggestion in the dropdown
                            suggestionsList.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching suggestions:', error));
            } else {
                suggestionsList.innerHTML = ''; // Clear suggestions if input is empty
            }
        });
    </script>

</body>

</html>