<div class="container"></div>
<div class="navigation">
    <ul>
        <!-- Logo Section -->
        <li>
            <a href="#">
                <span class="icon">
                    <img src="img/Logo.png" alt="Mr. Special Tea Logo" class="logo-image">
                </span>
                <span class="title">Mr. Boy Special Tea</span>
            </a>
        </li>

        <!-- Dashboard Section -->
        <li>
            <a href="admin_dash.php"><span class="icon">
                    <ion-icon name="grid-outline"></ion-icon>
                </span><span class="title">Dashboard</span>
            </a>
        </li>

        <!-- Inventory -->
        <li>
            <a href="Inventory.php"><span class="icon">
                    <ion-icon name="file-tray-stacked-outline"></ion-icon></span>
                <span class="title">Inventory</span>
            </a>
        </li>

        <!-- Employee -->
        <li>
            <a href="employee.php"><span class="icon">
                    <ion-icon name="person-outline"></ion-icon></span>
                <span class="title">Employee</span>
            </a>
        </li>
        <!-- Transaction Section -->
        <li>
            <a href="transaction.php"><span class="icon">
                    <ion-icon name="repeat-outline"></ion-icon></span>
                <span class="title">Transaction</span>
            </a>
        </li>

        <!-- Logout Section -->
        <li>
            <a href="logout.php" id="logout-link"><span class="icon">
                    <ion-icon name="log-out-outline"></ion-icon></span>
                <span class="title">Logout</span>
            </a>
        </li>
    </ul>
</div>
<!-- Logout Confirmation Popup -->
<div id="logoutPopup" class="popup-container">
    <div class="popup-content">
        <div class="logo-background">
            <img src="/img/123img.png" alt="Logo" class="popup-logo">
        </div>
        <p>Are you sure you want to logout?</p>
        <div class="popup-buttons">
            <a href="logout.php" class="confirm-logout">Yes, Logout</a>
            <button class="cancel-logout" onclick="closeLogoutPopup()">Cancel</button>
        </div>
    </div>
</div>
<!-- JavaScript for Popup -->
<script>
    document.getElementById('logout-link').addEventListener('click', function(event) {
        event.preventDefault();
        document.getElementById("logoutPopup").style.display = "flex";
    });

    function closeLogoutPopup() {
        document.getElementById("logoutPopup").style.display = "none";
    }
</script>
<!-- Include Ionicons Script -->
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>