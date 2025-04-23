document.addEventListener("DOMContentLoaded", () => {
  const forms = document.querySelectorAll('.add-to-cart-form');

  forms.forEach(form => {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      const card = this.closest('.menu-card-square');
      const img = card.querySelector('.product-img');
      const cartIcon = document.getElementById('cart-icon');
      const cartCount = document.getElementById('cart-count');
      const itemId = this.dataset.id;

      // Clone the product image for animation
      const imgClone = img.cloneNode(true);
      const rect = img.getBoundingClientRect();
      imgClone.style.position = 'fixed';
      imgClone.style.top = rect.top + 'px';
      imgClone.style.left = rect.left + 'px';
      imgClone.style.width = img.offsetWidth + 'px';
      imgClone.style.height = img.offsetHeight + 'px';
      imgClone.style.zIndex = 1000;
      imgClone.style.transition = 'all 0.8s ease-in-out';
      document.body.appendChild(imgClone);

      const cartRect = cartIcon.getBoundingClientRect();
      setTimeout(() => {
        imgClone.style.top = cartRect.top + 'px';
        imgClone.style.left = cartRect.left + 'px';
        imgClone.style.width = '20px';
        imgClone.style.height = '20px';
        imgClone.style.opacity = 0;
        imgClone.style.transform = 'scale(0.5)';
      }, 20);

      // Remove clone after animation
      setTimeout(() => imgClone.remove(), 900);

      // Fetch to add_to_cart.php
      try {
        const response = await fetch('cart/add_to_cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `item_id=${itemId}`
        });

        const result = await response.json();
        if (result.status === 'success') {
          cartCount.innerText = result.cartCount;
          showToast('🛒 Item added to cart!');
        } else {
          showToast(result.message || '❌ Failed to add item.');
        }
      } catch (err) {
        showToast('⚠️ Network error.');
      }
    });
  });

  // Optional toast animation
  function showToast(msg) {
    let toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => toast.classList.remove('show'), 4000);
    setTimeout(() => toast.remove(), 4500);
  }
});
