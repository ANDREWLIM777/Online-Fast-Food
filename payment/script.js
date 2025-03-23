document.addEventListener("DOMContentLoaded", function () {
    // Toggle Active State in Bottom Navigation
    document.querySelectorAll(".nav-item").forEach(item => {
        item.addEventListener("click", function () {
            document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
            this.classList.add("active");
        });
    });

    // Menu Click Alert
    document.querySelector(".menu-icon").addEventListener("click", function () {
        alert("Menu clicked!");
    });
});

