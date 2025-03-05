// Open popup to enter quantity when product is clicked
const productItems = document.querySelectorAll('.product-item');
const popup = document.getElementById('quantity-popup');
const overlay = document.getElementById('overlay');
const itemIdInput = document.getElementById('item-id-input');

productItems.forEach(item => {
    item.addEventListener('click', () => {
        const itemId = item.getAttribute('data-item-id');
        itemIdInput.value = itemId;
        popup.style.display = 'block';
        overlay.style.display = 'block';
    });
});

// Close popup when clicking outside
overlay.addEventListener('click', () => {
    popup.style.display = 'none';
    overlay.style.display = 'none';
});
