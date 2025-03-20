document.addEventListener("DOMContentLoaded", function () {
    const orderList = document.getElementById("order-list");
    const orderTotal = document.getElementById("order-total");
    const checkoutButton = document.getElementById("checkout");

    let orders = JSON.parse(localStorage.getItem("cart")) || [];

    function updateOrderPage() {
        orderList.innerHTML = "";
        let total = 0;

        orders.forEach((item, index) => {
            const li = document.createElement("li");
            li.textContent = `${item.name} - RM ${item.price.toFixed(2)}`;
            
            // Remove Button
            const removeBtn = document.createElement("button");
            removeBtn.textContent = "Remove";
            removeBtn.onclick = function () {
                orders.splice(index, 1);
                localStorage.setItem("cart", JSON.stringify(orders));
                updateOrderPage();
            };
            li.appendChild(removeBtn);
            orderList.appendChild(li);

            total += item.price;
        });

        orderTotal.textContent = total.toFixed(2);
    }

    checkoutButton.addEventListener("click", function () {
        alert("Thank you for your order!");
        localStorage.removeItem("cart");
        orders = [];
        updateOrderPage();
    });

    updateOrderPage();
});
