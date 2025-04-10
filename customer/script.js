let cart = JSON.parse(localStorage.getItem('cart')) || [];

function addToCart(itemId) {
    fetch(`php/get_item.php?id=${itemId}`)
        .then(response => response.json())
        .then(item => {
            const existingItem = cart.find(i => i.item_id === item.item_id);
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({...item, quantity: 1});
            }
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
        });
}

function updateCartCount() {
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    document.getElementById('cart-count').textContent = count;
}