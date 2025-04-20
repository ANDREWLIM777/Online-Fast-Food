document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
  
        const card = this.closest('.menu-card-square');
        const img = card.querySelector('.product-img');
        const cartIcon = document.getElementById('cart-icon');
        const cartCount = document.getElementById('cart-count');
  
        // Clone the product image
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
  
        setTimeout(() => {
          imgClone.remove();
  
          // Update cart count visually
          let currentCount = parseInt(cartCount.innerText || '0');
          cartCount.innerText = currentCount + 1;
        }, 900);
  
        // Delay submit to allow animation
        setTimeout(() => {
          this.submit();
        }, 950);
      });
    });
  });
  