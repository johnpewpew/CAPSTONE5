// Select elements
const itemImage = document.getElementById('item-image');
const itemName = document.getElementById('item-name');
const itemDescription = document.getElementById('item-description');
const itemQuantity = document.getElementById('item-quantity');
const itemPrice = document.getElementById('item-price');
const addBtn = document.getElementById('add-btn');
const saveBtn = document.getElementById('save-btn');
const updateBtn = document.getElementById('update-btn');
const deleteBtn = document.getElementById('delete-btn');
const itemListBody = document.getElementById('item-list-body');
const imageUpload = document.getElementById('image-upload');
const searchInput = document.getElementById('search-input');

let items = [];
let currentIndex = -1;

// Function to render the items in the table
function renderItems(filteredItems = items) {
    itemListBody.innerHTML = '';
    filteredItems.forEach((item, index) => {
        const row = document.createElement('tr');
        row.classList.add('item-row');
        row.dataset.index = index;
        row.innerHTML = `
            <td><img src="${item.icon}" alt="Icon" style="width:50px; height:50px;"></td>
            <td>${item.name}</td>
            <td>${item.description}</td>
            <td>${item.quantity}</td>
            <td>${item.price}</td>
        `;
        row.addEventListener('click', () => displayItemDetails(item, index));
        itemListBody.appendChild(row);
    });
}

// Display selected item details
function displayItemDetails(item, index) {
    currentIndex = index;
    itemImage.src = item.icon;
    itemName.value = item.name;
    itemDescription.value = item.description;
    itemQuantity.value = item.quantity;
    itemPrice.value = item.price;
}

// Click event to add new item
addBtn.addEventListener('click', () => {
    const newItemIcon = imageUpload.files[0] ? URL.createObjectURL(imageUpload.files[0]) : 'https://via.placeholder.com/50';
    const newItemName = itemName.value;
    const newItemDescription = itemDescription.value;
    const newItemQuantity = itemQuantity.value;
    const newItemPrice = itemPrice.value;
    
    if (!newItemName || !newItemQuantity || !newItemPrice) {
        alert('Please fill all the required fields.');
        return;
    }

    const newItem = {
        icon: newItemIcon,
        name: newItemName,
        description: newItemDescription,
        quantity: newItemQuantity,
        price: newItemPrice
    };

    items.push(newItem);
    renderItems();
    clearItemDetails();
    alert('Item added successfully!');
});

// Click event to save item
saveBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select an item to save.");
        return;
    }
    
    const savedItemIcon = imageUpload.files[0] ? URL.createObjectURL(imageUpload.files[0]) : items[currentIndex].icon;
    items[currentIndex] = {
        icon: savedItemIcon,
        name: itemName.value,
        description: itemDescription.value,
        quantity: itemQuantity.value,
        price: itemPrice.value
    };
    renderItems();
    alert('Item saved successfully!');
});

// Click event to update item
updateBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select an item to update.");
        return;
    }
    
    itemName.disabled = false;
    itemDescription.disabled = false;
    itemQuantity.disabled = false;
    itemPrice.disabled = false;
    alert('You can now update the item details.');
});

// Click event to delete item
deleteBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select an item to delete.");
        return;
    }
    
    items.splice(currentIndex, 1);
    renderItems();
    clearItemDetails();
    alert('Item deleted successfully!');
});

// Function to clear item details
function clearItemDetails() {
    itemImage.src = "https://via.placeholder.com/200";
    itemName.value = '';
    itemDescription.value = '';
    itemQuantity.value = '';
    itemPrice.value = '';
    currentIndex = -1;
}

// Search functionality
searchInput.addEventListener('input', () => {
    const searchTerm = searchInput.value.toLowerCase();
    const filteredItems = items.filter(item => 
        item.name.toLowerCase().includes(searchTerm) ||
        item.description.toLowerCase().includes(searchTerm)
    );
    renderItems(filteredItems);
});