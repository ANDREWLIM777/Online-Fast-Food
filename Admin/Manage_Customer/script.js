// 在script.js中添加
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    }
}

