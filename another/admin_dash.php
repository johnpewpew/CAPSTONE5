<?php
include 'config.php';
session_start();

if (!isset($_SESSION['admin_name'])) {
    header('location:index.php');
}

// Process payment success and update sales
if (isset($_POST['payment_success'])) { 
    $totalAmount = $_POST['total_amount']; 
    $today = date('Y-m-d');
    
    // Update daily sales
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

    // Update monthly sales
    $current_month = date('Y-m');
    $check_monthly_sales_query = $conn->prepare("SELECT total_sales FROM monthly_sales WHERE month_year = ?");
    $check_monthly_sales_query->bind_param("s", $current_month);
    $check_monthly_sales_query->execute();
    $monthly_result = $check_monthly_sales_query->get_result();

    if ($monthly_result->num_rows > 0) {
        $current_monthly_sales = $monthly_result->fetch_assoc()['total_sales'];
        $new_monthly_sales = $current_monthly_sales + $totalAmount;
        $update_monthly_sales_query = $conn->prepare("UPDATE monthly_sales SET total_sales = ? WHERE month_year = ?");
        $update_monthly_sales_query->bind_param("ds", $new_monthly_sales, $current_month);
        $update_monthly_sales_query->execute();
    } else {
        $insert_monthly_sales_query = $conn->prepare("INSERT INTO monthly_sales (month_year, total_sales) VALUES (?, ?)");
        $insert_monthly_sales_query->bind_param("sd", $current_month, $totalAmount);
        $insert_monthly_sales_query->execute();
    }

    // Update annual sales
    $current_year = date('Y');
    $check_annual_sales_query = $conn->prepare("SELECT total_sales FROM annual_sales WHERE year = ?");
    $check_annual_sales_query->bind_param("s", $current_year);
    $check_annual_sales_query->execute();
    $annual_result = $check_annual_sales_query->get_result();

    if ($annual_result->num_rows > 0) {
        $current_annual_sales = $annual_result->fetch_assoc()['total_sales'];
        $new_annual_sales = $current_annual_sales + $totalAmount;
        $update_annual_sales_query = $conn->prepare("UPDATE annual_sales SET total_sales = ? WHERE year = ?");
        $update_annual_sales_query->bind_param("ds", $new_annual_sales, $current_year);
        $update_annual_sales_query->execute();
    } else {
        $insert_annual_sales_query = $conn->prepare("INSERT INTO annual_sales (year, total_sales) VALUES (?, ?)");
        $insert_annual_sales_query->bind_param("sd", $current_year, $totalAmount);
        $insert_annual_sales_query->execute();
    } 
}

// Retrieve daily sales
$today = date('Y-m-d');
$daily_sales_query = $conn->prepare("SELECT total_sales FROM daily_sales WHERE date = ?");
$daily_sales_query->bind_param("s", $today);
$daily_sales_query->execute();
$daily_sales_result = $daily_sales_query->get_result();
$daily_sales = $daily_sales_result->num_rows > 0 ? $daily_sales_result->fetch_assoc()['total_sales'] : 0;

// Retrieve weekly sales
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$weekly_sales_query = $conn->prepare("SELECT SUM(total_sales) AS total_sales FROM daily_sales WHERE date BETWEEN ? AND ?");
$weekly_sales_query->bind_param("ss", $monday, $sunday);
$weekly_sales_query->execute();
$weekly_sales_result = $weekly_sales_query->get_result();
$weekly_sales = $weekly_sales_result->num_rows > 0 ? $weekly_sales_result->fetch_assoc()['total_sales'] : 0;

// Retrieve monthly sales
$monthly_sales = []; 
for ($i = 1; $i <= 12; $i++) {
    $first_day_of_month = date("Y-$i-01");
    $last_day_of_month = date("Y-$i-t"); 
    $check_monthly_sales_query = $conn->prepare("SELECT SUM(total_sales) AS total_sales FROM daily_sales WHERE date BETWEEN ? AND ?");
    $check_monthly_sales_query->bind_param("ss", $first_day_of_month, $last_day_of_month);
    $check_monthly_sales_query->execute();
    $monthly_sales_result = $check_monthly_sales_query->get_result();
    $monthly_sales[$i] = $monthly_sales_result->num_rows > 0 ? (float)$monthly_sales_result->fetch_assoc()['total_sales'] : 0;
}

// Retrieve annual sales
$current_year = date('Y-01-01'); 
$check_annual_sales_query = $conn->prepare("SELECT SUM(total_sales) AS total_sales FROM daily_sales WHERE date >= ?");
$check_annual_sales_query->bind_param("s", $current_year);
$check_annual_sales_query->execute();
$annual_sales_result = $check_annual_sales_query->get_result();
$annual_sales = $annual_sales_result->num_rows > 0 ? $annual_sales_result->fetch_assoc()['total_sales'] : 0;

// Fetch top 5 most sold products with quantities
$top_products_query = $conn->prepare("
    SELECT items.name, SUM(product_sales.quantity_sold) AS total_sold
    FROM product_sales
    JOIN items ON items.id = product_sales.product_id
    GROUP BY items.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products_query->execute();
$top_products_result = $top_products_query->get_result();

$top_product_names = [];
$top_product_sales = [];
while ($product = $top_products_result->fetch_assoc()) {
    $top_product_names[] = $product['name'];
    $top_product_sales[] = (int)$product['total_sold'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">
    <link rel="stylesheet" type="text/css" href="./css/admin.css">
    <link rel="stylesheet" type="text/css" href="./css/util.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.35.3/apexcharts.min.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'?>

        

        <div class="main">
            <div class="topbar">
                <div class="toggle"></div>
            </div>
           
            <main class="main-container">
                <div class="cardBox">
                    <div class="card">
                        <div>
                            <div class="numbers">₱<?= number_format($annual_sales, 2); ?></div>
                            <div class="cardName">Annual Sales</div>
                        </div>
                        <div class="iconBx">
                        <ion-icon name="bar-chart-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card">
                        <div>
                            <div class="numbers">₱<?= number_format($monthly_sales[date('n')], 2); ?></div>
                            <div class="cardName">Monthly Sales</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="cart-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card">
                        <div>
                            <div class="numbers">₱<?= number_format($weekly_sales, 2); ?></div>
                            <div class="cardName">Weekly Sales</div>
                        </div>
                        <div class="iconBx">
                        <ion-icon name="server-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card">
                        <div>
                            <div class="numbers">₱<?= number_format($daily_sales, 2); ?></div>
                            <div class="cardName">Daily Sales</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="cash-outline"></ion-icon>
                        </div>
                    </div>
                </div>

                <div class="charts">
                    <div class="charts-card">
                        <p class="chart-title">Sales Performance</p>
                        <div id="sales-chart"></div>
                    </div>
                    <div class="charts-card">
                        <p class="chart-title">Top 5 Products</p>
                        <div id="bar-chart"></div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <div class="color-picker-container">
        <input type="color" id="salesColor" value="#4CAF50">
        <input type="color" id="topProductsColor" value="#FF4D00">
    </div>

    <script>
           document.addEventListener("DOMContentLoaded", function() {
        // Sales performance chart
        const salesData = <?= json_encode(array_values($monthly_sales)); ?>;

        // New sales chart options
        const salesOptions = {
            series: [{
                name: "Sales",
                data: salesData  // Use the sales data dynamically fetched from the database
            }],
            chart: {
                height: 350,
                type: 'line',
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth'  // Changed to straight as per the new requirements
            },
            grid: {
                row: {
                    colors: ['#f3f3f3', 'transparent'], // Alternating row colors
                    opacity: 0.5
                },
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],  // Months of the year
            },
            colors: [document.getElementById('salesColor').value],  // Set dynamic color from input
            tooltip: {
                theme: 'dark',
                x: { show: true },
                y: { formatter: value => '₱' + value.toFixed(2) }
            }
        };


            // Top 5 products bar chart
            const productNames = <?= json_encode($top_product_names); ?>;
            const productSales = <?= json_encode($top_product_sales); ?>;
            const barOptions = {
                chart: {
                    type: 'bar',
                    height: 350
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        endingShape: 'rounded',
                        columnWidth: '55%'
                    }
                },
                dataLabels: {
                    enabled: true,
                    style: {
                        colors: ['#fff'],
                    }
                },
                series: [{
                    name: 'Products Sold',
                    data: productSales
                }],
                xaxis: {
                    categories: productNames,
                    labels: {
                        style: {
                            colors: '#888',
                            fontSize: '12px'
                        }
                    }
                },
                colors: [document.getElementById('topProductsColor').value],
                tooltip: {
                    theme: 'dark',
                    x: { show: true },
                    y: { formatter: value => value + ' units' }
                }
            };
            const barChart = new ApexCharts(document.querySelector("#bar-chart"), barOptions);
            barChart.render();

           // Initialize and render the sales chart
        const salesChart = new ApexCharts(document.querySelector("#sales-chart"), salesOptions);
        salesChart.render();

        // Event listener for color change
        document.getElementById('salesColor').addEventListener('input', function() {
            salesOptions.colors = [this.value];
            salesChart.updateOptions(salesOptions);
        });
    });
    </script>
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
