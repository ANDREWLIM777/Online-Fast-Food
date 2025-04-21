// js/register.js
document.addEventListener("DOMContentLoaded", () => {
    const passwordInput = document.getElementById("registerPassword");
    const toggleIcon = document.getElementById("toggleRegisterPassword");
  
    toggleIcon.addEventListener("click", () => {
      const type = passwordInput.type === "password" ? "text" : "password";
      passwordInput.type = type;
      toggleIcon.textContent = type === "password" ? "👁️" : "🙈";
    });
  });

  document.addEventListener("DOMContentLoaded", () => {
    const alert = document.querySelector('.alert-success');
    if (alert) {
      setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.8s ease';
      }, 10000); // hide after 10 seconds
  
      setTimeout(() => {
        alert.remove();
      }, 10000);
    }
  });
  