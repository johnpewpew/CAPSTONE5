<?php
include 'config.php';
session_start();

if (!isset($_SESSION['admin_name'])) {
    header('location:index.php');
}

// Fetch all transactions
$transactions_query = $conn->query("SELECT * FROM transactions ORDER BY transaction_date DESC");
$transactions = $transactions_query->fetch_all(MYSQLI_ASSOC);

// Get the current date for filtering
$currentDate = new DateTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="transaction.css">
    <title>Transactions</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
</head>
<body>
    <?php include 'sidebar.php' ?>
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
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
            <div class="scrollable-tbody">
                <table>
                    <tbody id="transaction-table-body">
                        <!-- Content will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
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
                const cash = parseFloat(transaction.cash).toFixed(2);
                const change = (cash - totalAmount).toFixed(2);
                const discountAmount = parseFloat(transaction.discount_amount || 0).toFixed(2);
                const subTotal = (totalAmount / 1.12).toFixed(2);
                const tax = (totalAmount * 0.12 / 1.12).toFixed(2);

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
                            <td>₱${totalAmount}</td>
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

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('filter-dropdown').value = 'daily';
            filterTransactions(); // Load default filter
        });

        function searchTransactions() {
            const searchTerm = document.getElementById('search-transaction').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table-body tr');

            rows.forEach(row => {
                const orderDetails = row.cells[2].textContent.toLowerCase();
                row.style.display = orderDetails.includes(searchTerm) ? '' : 'none';
            });
        }

        function filterTransactions() {
            const filterValue = document.getElementById('filter-dropdown').value;
            const filteredTransactions = transactions.filter(transaction => {
                const transactionDate = new Date(transaction.transaction_date);
                const now = new Date();
                let startDate, endDate;

                switch (filterValue) {
                    case 'daily':
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                        break;
                    case 'weekly':
                        const weekStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay());
                        startDate = weekStart;
                        endDate = new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 7);
                        break;
                    case 'monthly':
                        startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                        endDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                        break;
                    case 'yearly':
                        startDate = new Date(now.getFullYear(), 0, 1);
                        endDate = new Date(now.getFullYear() + 1, 0, 1);
                        break;
                    default:
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                }

                return transactionDate >= startDate && transactionDate < endDate;
            });

            const tbody = document.getElementById('transaction-table-body');
            tbody.innerHTML = '';

            filteredTransactions.forEach(transaction => {
                const row = `
                <tr>
                    <td>${transaction.id}</td>
                    <td>${new Date(transaction.transaction_date).toLocaleString()}</td>
                    <td class="order-cell">${transaction.order_details}</td>
                    <td>${parseFloat(transaction.total_amount).toFixed(2)}</td>
                    <td>
                        <button class="view-receipt" onclick="viewReceipt(${transaction.id})">View Receipt</button>
                    </td>
                </tr>
                `;
                tbody.innerHTML += row;
            });
        }

    </script>
</body>
</html>
