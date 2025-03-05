// Select elements
const categoryImage = document.getElementById('category-image');
const categoryName = document.getElementById('category-name');
const addBtn = document.getElementById('add-btn');
const saveBtn = document.getElementById('save-btn');
const updateBtn = document.getElementById('update-btn');
const deleteBtn = document.getElementById('delete-btn');
const categoryListBody = document.getElementById('category-list-body');
const imageUpload = document.getElementById('image-upload');
const searchInput = document.getElementById('search-input');

let categories = [];
let currentIndex = -1;

// Function to render the categories in the table
function renderCategories(filteredCategories = categories) {
    categoryListBody.innerHTML = '';
    filteredCategories.forEach((category, index) => {
        const row = document.createElement('tr');
        row.classList.add('category-row');
        row.dataset.index = index;
        row.innerHTML = `
            <td><img src="${category.icon}" alt="Icon" style="width:50px; height:50px;"></td>
            <td>${category.name}</td>
        `;
        row.addEventListener('click', () => displayCategoryDetails(category, index));
        categoryListBody.appendChild(row);
    });
}

// Display selected category details
function displayCategoryDetails(category, index) {
    currentIndex = index;
    categoryImage.src = category.icon;
    categoryName.value = category.name;
}

// Click event to add new category
addBtn.addEventListener('click', () => {
    const newCategoryIcon = imageUpload.files[0] ? URL.createObjectURL(imageUpload.files[0]) : 'https://via.placeholder.com/50';
    const newCategoryName = categoryName.value;
    
    if (!newCategoryName) {
        alert('Please fill in the category name.');
        return;
    }

    const newCategory = {
        icon: newCategoryIcon,
        name: newCategoryName
    };

    categories.push(newCategory);
    renderCategories();
    clearCategoryDetails();
    alert('Category added successfully!');
});

// Click event to save category
saveBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select a category to save.");
        return;
    }
    
    const savedCategoryIcon = imageUpload.files[0] ? URL.createObjectURL(imageUpload.files[0]) : categories[currentIndex].icon;
    categories[currentIndex] = {
        icon: savedCategoryIcon,
        name: categoryName.value
    };
    renderCategories();
    alert('Category saved successfully!');
});

// Click event to update category
updateBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select a category to update.");
        return;
    }
    
    categoryName.disabled = false;
    alert('You can now update the category details.');
});

// Click event to delete category
deleteBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
        alert("Select a category to delete.");
        return;
    }
    
    categories.splice(currentIndex, 1);
    renderCategories();
    clearCategoryDetails();
    alert('Category deleted successfully!');
});

// Function to clear category details
function clearCategoryDetails() {
    categoryImage.src = "https://via.placeholder.com/200";
    categoryName.value = '';
    currentIndex = -1;
}

// Search functionality
searchInput.addEventListener('input', () => {
    const searchTerm = searchInput.value.toLowerCase();
    const filteredCategories = categories.filter(category => 
        category.name.toLowerCase().includes(searchTerm)
    );
    renderCategories(filteredCategories);
});