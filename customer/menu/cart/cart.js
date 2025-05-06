document.addEventListener("DOMContentLoaded", () => {
    const showToast = (message = "Done!") => {
      const toast = document.createElement("div");
      toast.className = "toast-msg";
      toast.innerText = message;
      document.body.appendChild(toast);
      setTimeout(() => toast.classList.add("show"), 10);
      setTimeout(() => toast.classList.remove("show"), 3000);
      setTimeout(() => toast.remove(), 4500);
    };
  
    const showUltraToast = (msg = "✅ Cart Updated!") => {
      const toast = document.getElementById("ultra-toast");
      if (toast) {
        toast.querySelector(".toast-text").textContent = msg;
        toast.classList.add("show");
        setTimeout(() => toast.classList.remove("show"), 2500);
      }
    };
  
    
    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', async function () {
        const row = this.closest('tr');
        const itemId = row.dataset.id;
        const change = parseInt(this.dataset.change);
        const qtyElem = row.querySelector('.qty-number');
        const price = parseFloat(row.querySelector('.price').dataset.price);
        let currentQty = parseInt(qtyElem.textContent);
  
        const newQty = currentQty + change;
        if (newQty < 1) return;
  
        const formData = new URLSearchParams();
        formData.append('item_id', itemId);
        formData.append('quantity', newQty);
  
        try {
          const res = await fetch('update_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
          });
  
          const data = await res.json();
          if (data.status === 'success') {
            qtyElem.textContent = newQty;
            row.querySelector('.subtotal').textContent = (price * newQty).toFixed(2);
  
            let total = 0;
            document.querySelectorAll('.subtotal').forEach(td => {
              total += parseFloat(td.textContent);
            });
            document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);
            showUltraToast();
          } else {
            showToast(data.message || '❌ Update failed');
          }
        } catch {
          showToast("⚠️ Network error");
        }
      });
    });
  
    document.querySelectorAll('.remove-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        // Fallback form submission is handled in PHP.
        // If you want to do AJAX remove without reload, let me know.
      });
    });
  });
  