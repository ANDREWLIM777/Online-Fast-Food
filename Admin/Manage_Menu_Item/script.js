document.addEventListener("DOMContentLoaded", function () {
    fetch("backend/menu.php")
        .then(response => response.json())
        .then(data => {
            const table = document.getElementById("menuTable");
            data.forEach(item => {
                let row = table.insertRow();
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td>${item.price}</td>
                    <td>${item.description}</td>
                    <td>${item.available ? "✅" : "❌"}</td>
                    <td>${item.discount}%</td>
                    <td><button onclick="editItem(${item.id})">Edit</button> <button onclick="deleteItem(${item.id})">Delete</button></td>
                `;
            });
        });
});

function addItem() {
    let name = prompt("Enter item name:");
    let price = prompt("Enter price:");
    let description = prompt("Enter description:");
    let available = confirm("Is this item available?") ? 1 : 0;
    let discount = prompt("Enter discount (if any):");

    fetch("backend/menu.php", {
        method: "POST",
        body: new URLSearchParams({ name, price, description, available, discount })
    }).then(() => location.reload());
}

function deleteItem(id) {
    if (confirm("Are you sure you want to delete this item?")) {
        fetch("backend/menu.php", {
            method: "DELETE",
            body: new URLSearchParams({ id })
        }).then(() => location.reload());
    }
}