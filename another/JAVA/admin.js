// js/admin.js

document.addEventListener('DOMContentLoaded', () => {
    const showPasswordCheckbox = document.getElementById('show-password');
    const passwordFields = [
        document.getElementById('current-password'),
        document.getElementById('new-password'),
        document.getElementById('confirm-password')
    ];

    if (showPasswordCheckbox) {
        showPasswordCheckbox.addEventListener('change', () => {
            passwordFields.forEach(field => {
                if (field) {
                    field.type = showPasswordCheckbox.checked ? 'text' : 'password';
                }
            });
        });
    }
});
