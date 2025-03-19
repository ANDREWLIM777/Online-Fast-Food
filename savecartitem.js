document.addEventListener("DOMContentLoaded", function () {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];

    document.querySelectorAll(".add-to-cart").forEach(button => {
        button.addEventListener("click", function () {
            const name = this.getAttribute("data-name");
            const price = parseFloat(this.getAttribute("data-price"));

            cart.push({ name, price });
            localStorage.setItem("cart", JSON.stringify(cart)); // Store in localStorage

            alert(name + " added to cart!");
        });
    });
});
