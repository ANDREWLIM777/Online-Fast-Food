document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("contact-form");

    form.addEventListener("submit", function (event) {
        event.preventDefault(); // Prevent page reload

        const name = document.getElementById("name").value;
        const email = document.getElementById("email").value;
        const message = document.getElementById("message").value;

        if (name && email && message) {
            document.getElementById("response-message").textContent = 
                "Thank you, " + name + "! Your message has been sent.";
            form.reset();
        } else {
            document.getElementById("response-message").textContent = 
                "Please fill in all fields.";
        }
    });
});

