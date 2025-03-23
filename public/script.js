let token = '';

// Show/Hide Forms
function showForm(formId) {
    document.querySelectorAll('.form-section').forEach(form => form.style.display = 'none');
    document.getElementById(formId).style.display = 'block';
}

document.getElementById('login-link').addEventListener('click', (e) => {
    e.preventDefault();
    showForm('login-form');
});

document.getElementById('register-link').addEventListener('click', (e) => {
    e.preventDefault();
    showForm('register-form');
});

document.getElementById('edit-profile-link').addEventListener('click', (e) => {
    e.preventDefault();
    showForm('edit-profile-form');
});

document.getElementById('change-password-link').addEventListener('click', (e) => {
    e.preventDefault();
    showForm('change-password-form');
});

document.getElementById('reset-link').addEventListener('click', (e) => {
    e.preventDefault();
    showForm('reset-password-form');
});

document.getElementById('logout-link').addEventListener('click', (e) => {
    e.preventDefault();
    token = '';
    document.getElementById('profile-link').style.display = 'none';
    document.getElementById('login-link').style.display = 'block';
    document.getElementById('register-link').style.display = 'block';
    document.getElementById('reset-link').style.display = 'block';
    document.querySelectorAll('.form-section').forEach(form => form.style.display = 'none');
    alert('Logged out!');
});

// Register
document.getElementById('register-form-inner').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    alert(await res.text());
});

// Login
document.getElementById('login-form-inner').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (res.ok) {
        token = result.token;
        document.getElementById('profile-link').style.display = 'block';
        document.getElementById('login-link').style.display = 'none';
        document.getElementById('register-link').style.display = 'none';
        document.getElementById('reset-link').style.display = 'none';
        document.querySelectorAll('.form-section').forEach(form => form.style.display = 'none');
        alert('Logged in!');
    } else {
        alert(result);
    }
});

// Edit Profile
document.getElementById('profile-form-inner').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body: JSON.stringify(data)
    });
    alert(await res.text());
});

// Change Password
document.getElementById('change-password-form-inner').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/change-password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body: JSON.stringify(data)
    });
    alert(await res.text());
});

// Reset Password
document.getElementById('reset-form-inner').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    alert(await res.text());
});