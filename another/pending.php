<?php
include 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_name'])) {
    header('location:index.php');
}

// Fetch all transactions
$transactions_query = $conn->query("SELECT * FROM transactions ORDER BY transaction_date DESC");
$transactions = $transactions_query->fetch_all(MYSQLI_ASSOC);

// Calculate total daily sales
$totalDailySales = 0;
$today = date("Y-m-d");
foreach ($transactions as $transaction) {
    if (date("Y-m-d", strtotime($transaction['transaction_date'])) === $today) {
        $totalDailySales += $transaction['total_amount'];
    }
}

// Fetch items to display in the dashboard
$items = $conn->query("
    SELECT items.id, items.name, items.image, items.medium_size, items.large_size,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id AND sales.size = 'Medium'), 0) AS total_medium_sold,
    COALESCE((SELECT SUM(quantity) FROM sales WHERE sales.item_id = items.id AND sales.size = 'Large'), 0) AS total_large_sold
    FROM items
");

// Calculate weekly sales
$monday = date('Y-m-d', strtotime('monday this week'));
$weekly_sales_query = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM transactions WHERE transaction_date >= ?");
$weekly_sales_query->bind_param("s", $monday);
$weekly_sales_query->execute();
$weekly_sales_result = $weekly_sales_query->get_result();
$totalWeeklySales = $weekly_sales_result->num_rows > 0 ? $weekly_sales_result->fetch_assoc()['total_sales'] : 0;

// Calculate monthly sales
$first_day_of_month = date('Y-m-01');
$monthly_sales_query = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM transactions WHERE transaction_date >= ?");
$monthly_sales_query->bind_param("s", $first_day_of_month);
$monthly_sales_query->execute();
$monthly_sales_result = $monthly_sales_query->get_result();
$totalMonthlySales = $monthly_sales_result->num_rows > 0 ? $monthly_sales_result->fetch_assoc()['total_sales'] : 0;

// Calculate annual sales
$current_year = date('Y-01-01');
$annual_sales_query = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM transactions WHERE transaction_date >= ?");
$annual_sales_query->bind_param("s", $current_year);
$annual_sales_query->execute();
$annual_sales_result = $annual_sales_query->get_result();
$totalAnnualSales = $annual_sales_result->num_rows > 0 ? $annual_sales_result->fetch_assoc()['total_sales'] : 0;

function formatOrderDetails($orderDetails) {
    $items = json_decode($orderDetails, true);
    $formattedItems = [];

    foreach ($items as $item) {
        $name = htmlspecialchars($item['name']);
        $size = htmlspecialchars($item['size']);
        $formattedItems[] = "{$name} ({$size})";
    }

    return implode(", ", $formattedItems);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pending.css">
    <link rel="stylesheet" href="meme.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <link rel="stylesheet" type="text/css" href="./css/dashboard.css"> <!-- New CSS for dashboard -->
    <title>Pending Transactions</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>

    </style>
</head>
<body>
    <div class="order-side">
        <?php include 'order_side.php'; ?>
    </div>

    <div class="container">
        <h1>Transactions Record</h1>

        <div class="search-section">
            <input type="text" class="search-bar" placeholder="Search" id="search-transaction" onkeyup="searchTransactions()">
            <button class="search-button">Search</button>
            <select class="filter-dropdown" id="filter-dropdown" onchange="filterTransactions()">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Trans #</th>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Total</th>
                        <th>Cash</th> <!-- Added cash column -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body">
                    <?php foreach ($transactions as $transaction) { ?>
                        <tr>
                            <td><?= $transaction['id'] ?></td>
                            <td><?= date("Y-m-d H:i:s", strtotime($transaction['transaction_date'])) ?></td>
                            <td class="order-details"><?= htmlspecialchars($transaction['order_details']) ?></td>
                            <td><?= number_format($transaction['total_amount'], 2) ?></td>
                            <td><?= number_format($transaction['cash'], 2) ?></td> <!-- Display cash -->
                            <td>
                                <button class="view-receipt" onclick="viewReceipt(<?= $transaction['id'] ?>)">View Receipt</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="total-info-container">
            <div class="total-sales-box">
                <h2>Daily Sales</h2>
                <p>₱<?= number_format($totalDailySales, 2) ?></p>
            </div>
            <div class="weekly-sales-box">
                <h2>Weekly Sales</h2>
                <p>₱<?= number_format($totalWeeklySales, 2) ?></p>
            </div>
            <div class="monthly-sales-box">
                <h2>Monthly Sales</h2>
                <p>₱<?= number_format($totalMonthlySales, 2) ?></p>
            </div>
            <div class="annual-sales-box">
                <h2>Annual Sales</h2>
                <p>₱<?= number_format($totalAnnualSales, 2) ?></p>
            </div>
        </div>

        <!-- Modal for displaying receipt details -->
        <div id="receiptModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Receipt Details</h2>
                <div id="receiptDetails"></div>
            </div>
        </div>

        <script>
          function viewReceipt(transactionId) {
    const transaction = transactions.find(t => t.id == transactionId);

    if (!transaction) {
        alert('Transaction not found!');
        return;
    }

    const storeName = "Mr. Boy Special Tea";
    const orderDetails = transaction.order_details || 'No items';
    const totalAmount = parseFloat(transaction.total_amount).toFixed(2);
    const discountAmount = parseFloat(transaction.discount_amount || 0).toFixed(2);
    const discountedTotal = (totalAmount - discountAmount).toFixed(2);
    const subTotal = (discountedTotal / 1.12).toFixed(2);
    const tax = (discountedTotal * 0.12 / 1.12).toFixed(2);
    const cash = parseFloat(transaction.cash).toFixed(2);
    const change = (cash - discountedTotal).toFixed(2);

    let itemNames = "";
    if (Array.isArray(orderDetails) && orderDetails.length > 0) {
        itemNames = orderDetails.map(item => item.name).join(", ");
    } else if (typeof orderDetails === "string") {
        itemNames = orderDetails;
    } else {
        itemNames = 'No items';
    }

    const receiptHTML = `
        <div class="receipt">
            <h3 class="store-name">${storeName}</h3>
            <p><span class="text-bold">Date:</span> ${new Date(transaction.transaction_date).toLocaleString()}</p>
            <div class="line"></div>
            <div class="text-bold">CASH RECEIPT</div>
            <div class="line"></div>
            <table>
                <thead>
                    <tr>
                        <td class="text-bold">Description:</td>
                        <td>${itemNames}</td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-bold">Total Amount:</td>
                        <td>₱${(parseFloat(totalAmount) + parseFloat(discountAmount)).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="text-bold">Sub-total:</td>
                        <td>₱${subTotal}</td>
                    </tr>
                    <tr>
                        <td class="text-bold">Sales Tax:</td>
                        <td>₱${tax}</td>
                    </tr>
                    
                    <tr>
                        <td class="text-bold">Discounted Total:</td>
                        <td>₱${discountedTotal}</td>
                    </tr>
                    <tr>
                        <td class="text-bold">Cash:</td>
                        <td>₱${cash}</td>
                    </tr>
                    <tr>
                        <td class="text-bold">Change:</td>
                        <td>₱${change}</td>
                    </tr>
                </tbody>
            </table>
            <div class="line"></div>
            <p class="thank-you">THANK YOU!</p>
        </div>
    `;

    document.getElementById('receiptDetails').innerHTML = receiptHTML;
    document.getElementById('receiptModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('receiptModal').style.display = 'none';
}

        </script>



        <script>
            const transactions = <?= json_encode($transactions) ?>;

            // Set the default filter to 'daily' on page load
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('filter-dropdown').value = 'daily';
                filterTransactions();
            });

            function searchTransactions() {
                const searchTerm = document.getElementById('search-transaction').value.toLowerCase();
                const rows = document.querySelectorAll('#transaction-table-body tr');

                rows.forEach(row => {
                    const orderDetails = row.querySelector('.order-details').textContent.toLowerCase();
                    row.style.display = orderDetails.includes(searchTerm) ? '' : 'none';
                });
            }

            function filterTransactions() {
                const filterValue = document.getElementById('filter-dropdown').value;
                const today = new Date();
                const todayString = today.toISOString().split('T')[0];
                const rows = document.querySelectorAll('#transaction-table-body tr');

                rows.forEach(row => {
                    const transactionDate = new Date(row.cells[1].textContent);

                    if (filterValue === 'daily' && transactionDate.toISOString().split('T')[0] !== todayString) {
                        row.style.display = 'none';
                    } else if (filterValue === 'weekly' && !isSameWeek(transactionDate, today)) {
                        row.style.display = 'none';
                    } else if (filterValue === 'monthly' && transactionDate.getMonth() !== today.getMonth()) {
                        row.style.display = 'none';
                    } else if (filterValue === 'yearly' && transactionDate.getFullYear() !== today.getFullYear()) {
                        row.style.display = 'none';
                    } else {
                        row.style.display = '';
                    }
                });
            }

            function isSameWeek(date1, date2) {
                const startOfWeek = date2.getDate() - date2.getDay(); // Adjust to get start of week
                const endOfWeek = startOfWeek + 6; // End of week
                return date1.getDate() >= startOfWeek && date1.getDate() <= endOfWeek &&
                    date1.getMonth() === date2.getMonth() && date1.getFullYear() === date2.getFullYear();
            }
        </script>
    </div>
</body>
</html>