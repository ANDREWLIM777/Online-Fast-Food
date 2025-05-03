document.addEventListener("DOMContentLoaded", () => {
  const forms = document.querySelectorAll('.add-to-cart-form');

  forms.forEach(form => {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      // 🛡️ Block guests before animation or fetch
      if (window.isGuest) {
        showToast('⚠️ Guests cannot add to cart. Please login first.');
        return;
      }

      const card = this.closest('.menu-card-square');
      const img = card.querySelector('.product-img');
      const cartIcon = document.getElementById('cart-icon');
      const cartCount = document.getElementById('cart-count');
      const itemId = this.dataset.id;

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

      setTimeout(() => imgClone.remove(), 900);

      // 🛒 Add to cart AJAX
      try {
        const res = await fetch('cart/add_to_cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `item_id=${itemId}`
        });

        const data = await res.json();
        if (data.status === 'success') {
          cartCount.innerText = data.cartCount;
          showUltraToast('🛒 Item added to cart!');
        } else {
          showToast(data.message || '❌ Failed to add item.');
        }
      } catch {
        showToast('⚠️ Network error.');
      }
    });
  });

  function showUltraToast(message = 'Item added to cart!') {
    const toast = document.createElement('div');
    toast.className = 'ultra-toast';
  
    toast.innerHTML = `
      <div class="checkmark-animation">
        <svg viewBox="0 0 52 52">
          <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
          <path class="checkmark-check" fill="none" d="M14 27l8 8 16-16"/>
        </svg>
      </div>
      <div class="toast-text">${message}</div>
    `;
  
    document.body.appendChild(toast);
  
    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => toast.classList.remove('show'), 2500);
    setTimeout(() => toast.remove(), 3000);
  }
  
  
});
