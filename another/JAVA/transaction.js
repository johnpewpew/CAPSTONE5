// js/transaction.js

document.addEventListener('DOMContentLoaded', () => {
    // Handle View Receipt Buttons
    const viewReceiptButtons = document.querySelectorAll('.view-receipt');
    viewReceiptButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Redirect to the view_receipt.php page with the transaction ID
            const transactionId = button.closest('tr').querySelector('td:first-child').innerText;
            window.location.href = `view_receipt.php?id=${transactionId}`;
        });
    });

    // Handle Pay Now Buttons with Confirmation
    const payNowButtons = document.querySelectorAll('.pay-now-button');
    payNowButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const confirmed = confirm('Are you sure you want to proceed with payment for this transaction?');
            if (!confirmed) {
                e.preventDefault(); // Prevent form submission if not confirmed
            }
        });
    });

    // Handle Show Password (if applicable)
    // Uncomment and modify if you have password fields
    /*
    const showPasswordCheckbox = document.getElementById('show-password');
    if (showPasswordCheckbox) {
        showPasswordCheckbox.addEventListener('change', () => {
            const passwordFields = document.querySelectorAll('input[type="password"]');
            passwordFields.forEach(field => {
                field.type = showPasswordCheckbox.checked ? 'text' : 'password';
            });
        });
    }
    */
});
