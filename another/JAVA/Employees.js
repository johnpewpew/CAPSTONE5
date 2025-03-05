// Select elements
const employeeImage = document.getElementById('employee-image');
const employeeFname = document.getElementById('employee-fname');
const employeeLname = document.getElementById('employee-lname');
const employeeUsername = document.getElementById('employee-username');
const employeeBday = document.getElementById('employee-bday');
const employeeAge = document.getElementById('employee-age');
const registerBtn = document.getElementById('register-btn');
const deleteBtn = document.getElementById('delete-btn');
const registerForm = document.getElementById('register-form');
const submitRegisterBtn = document.getElementById('submit-register-btn');
const employeeListBody = document.getElementById('employee-list-body');

// Sample employees data
let employees = [];

// Function to render the employees in the table
function renderEmployees() {
    employeeListBody.innerHTML = '';
    employees.forEach((employee, index) => {
        const row = document.createElement('tr');
        row.classList.add('employee-row');
        row.dataset.index = index;
        row.innerHTML = `
            <td><img src="${employee.icon}" alt="Icon" style="width:50px; height:50px;"></td>
            <td>${employee.username}</td>
            <td>${employee.fname}</td>
            <td>${employee.lname}</td>
            <td>${employee.age}</td>
            <td>${employee.bday}</td>
        `;
        row.addEventListener('click', () => displayEmployeeDetails(employee));
        employeeListBody.appendChild(row);
    });
}

// Display selected employee details
function displayEmployeeDetails(employee) {
    employeeImage.src = employee.icon;
    employeeFname.value = employee.fname;
    employeeLname.value = employee.lname;
    employeeUsername.value = employee.username;
    employeeBday.value = employee.bday;
    employeeAge.value = employee.age;
}

// Click event to show register form
registerBtn.addEventListener('click', () => {
    registerForm.style.display = 'block';
});

// Click event to submit new employee registration
submitRegisterBtn.addEventListener('click', () => {
    const newEmployeeIcon = document.getElementById('new-employee-image').files[0];
    const newEmployeeFname = document.getElementById('new-employee-fname').value;
    const newEmployeeLname = document.getElementById('new-employee-lname').value;
    const newEmployeeUsername = document.getElementById('new-employee-username').value;
    const newEmployeeBday = document.getElementById('new-employee-bday').value;
    const newEmployeeAge = document.getElementById('new-employee-age').value;
    
    const newEmployee = {
        icon: newEmployeeIcon ? URL.createObjectURL(newEmployeeIcon) : 'https://via.placeholder.com/50',
        fname: newEmployeeFname,
        lname: newEmployeeLname,
        username: newEmployeeUsername,
        bday: newEmployeeBday,
        age: newEmployeeAge
    };

    employees.push(newEmployee);
    renderEmployees();
    registerForm.style.display = 'none'; // Hide registration form
    alert('Employee registered successfully!');
});

// Click event to delete employee
deleteBtn.addEventListener('click', () => {
    const selectedFname = employeeFname.value;
    const selectedLname = employeeLname.value;
    employees = employees.filter(employee => employee.fname !== selectedFname || employee.lname !== selectedLname);
    renderEmployees();
    alert('Employee deleted successfully!');
    // Clear details section
    employeeImage.src = "https://via.placeholder.com/150";
    employeeFname.value = "";
    employeeLname.value = "";
    employeeUsername.value = "";
    employeeBday.value = "";
    employeeAge.value = "";
});

// Initialize employee list
renderEmployees();