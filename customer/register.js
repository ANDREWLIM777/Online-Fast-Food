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
  