let token = '';
let customerName = '';

// Show/Hide Sections
function showSection(sectionId) {
    document.querySelectorAll('.form-section').forEach(section => section.style.display = 'none');
    document.getElementById(sectionId).style.display = 'block';
}

document.getElementById('show-register').addEventListener('click', (e) => {
    e.preventDefault();
    showSection('register-section');
});

document.getElementById('show-reset').addEventListener('click', (e) => {
    e.preventDefault();
    showSection('reset-section');
});

document.getElementById('show-login-from-register').addEventListener('click', (e) => {
    e.preventDefault();
    showSection('login-section');
});

document.getElementById('show-login-from-reset').addEventListener('click', (e) => {
    e.preventDefault();
    showSection('login-section');
});

document.getElementById('logout').addEventListener('click', () => {
    token = '';
    customerName = '';
    showSection('login-section');
    alert('Logged out!');
});

// Register
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/customer/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    alert(await res.text());
    if (res.ok) showSection('login-section');
});

// Login
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/customer/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (res.ok) {
        token = result.token;
        customerName = result.name;
        document.getElementById('customer-name').textContent = customerName;
        showSection('profile-section');
        alert('Logged in!');
    } else {
        alert(result);
    }
});

// Edit Profile
document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/customer/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body: JSON.stringify(data)
    });
    alert(await res.text());
    if (res.ok) {
        customerName = data.name;
        document.getElementById('customer-name').textContent = customerName;
    }
});

// Change Password
document.getElementById('change-password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/customer/change-password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body: JSON.stringify(data)
    });
    alert(await res.text());
});

// Reset Password
document.getElementById('reset-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/customer/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    alert(await res.text());
    if (res.ok) showSection('login-section');
});