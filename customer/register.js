// js/register.js
document.addEventListener("DOMContentLoaded", () => {
    const passwordInput = document.getElementById("registerPassword");
    const toggleIcon = document.getElementById("toggleRegisterPassword");
  
    if (passwordInput && toggleIcon) {
      toggleIcon.addEventListener("click", () => {
        const type = passwordInput.type === "password" ? "text" : "password";
        passwordInput.type = type;
        toggleIcon.textContent = type === "password" ? "👁️" : "🙈";
      });
    } else {
      console.warn("Toggle elements not found");
    }
  });
  