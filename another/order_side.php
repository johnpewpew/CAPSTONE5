<div class="container">
    <div class="sidebar">
        <div class="nav-item home">
            <a href="order_management.php" style="color: inherit; text-decoration: none;">
                <span class="material-icons-outlined">home</span>HOME
            </a>
        </div>
        <div class="nav-item pending">
            <a href="pending.php" style="color: inherit; text-decoration: none;">
                <span class="material-icons-outlined">pending</span>Reports
            </a>
        </div>
        <!-- Logout Button -->
        <div class="nav-item logout">
            <button type="button" class="logout-button" onclick="showLogoutPopup();">
                <span class="material-icons-outlined">logout</span> Logout
            </button>
        </div>

        <!-- Logout Confirmation Popup -->
        <div id="logout-popup" class="popup-overlay">
            <div class="popup-content">
                <div class="logo-container">
                    <div class="logo-background">
                        <img src="/img/123img.png" alt="Logo" class="popup-logo">
                    </div>
                </div>
                <p>Are you sure you want to log out?</p>
                <div class="popup-buttons">
                    <button onclick="confirmLogout(true);" class="popup-btn confirm">Yes</button>
                    <button onclick="confirmLogout(false);" class="popup-btn cancel">No</button>
                </div>
            </div>
        </div>

        <!-- JavaScript for Logout Modal -->
        <script>
            function showLogoutPopup() {
                document.getElementById('logout-popup').classList.add('active');
            }

            function confirmLogout(isConfirmed) {
                if (isConfirmed) {
                    document.body.classList.add('fade-out'); // Apply fade-out animation
                    setTimeout(() => {
                        window.location.href = "logout.php";
                    }, 800);
                } else {
                    document.getElementById('logout-popup').classList.remove('active');
                }
            }
        </script>

        <!-- CSS for Animations & Styling -->
        <style>

        </style>



    </div>
</div>

<!-- Include Ionicons Script -->
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>