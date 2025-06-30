document.addEventListener("DOMContentLoaded", () => {
    const showToast = (message = "Done!") => {
      const existingToast = document.querySelector(".toast-msg");
      if (existingToast) existingToast.remove();

      const toast = document.createElement("div");
      toast.className = "toast-msg";
      toast.innerText = message;
      document.body.appendChild(toast);

      setTimeout(() => toast.classList.add("show"), 10);
      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 500);
      }, 3000);
    };

    const showUltraToast = (msg = "✅ Cart Updated!") => {
      // Remove any existing ultra-toast to prevent stacking
      const existingUltraToast = document.querySelector(".ultra-toast");
      if (existingUltraToast) existingUltraToast.remove();

      // Create new ultra-toast element
      const toast = document.createElement("div");
      toast.className = "ultra-toast";
      toast.innerHTML = `
        <div class="checkmark-animation">
          <svg viewBox="0 0 52 52">
            <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark-check" fill="none" d="M14 27l8 8 16-16"/>
          </svg>
        </div>
        <div class="toast-text">${msg}</div>
      `;

      // Append to body
      document.body.appendChild(toast);

      // Trigger animation
      setTimeout(() => toast.classList.add("show"), 10);

      // Remove after animation
      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 500);
      }, 3000);
    };

    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', async function () {
        const row = this.closest('tr');
        const itemId = row.dataset.id;
        const input = row.querySelector('.qty-input');
        const change = parseInt(this.dataset.change);
        let newQty = parseInt(input.value) + change;

        if (newQty < 1) {
          showToast("Quantity of foods can't be less than 1");
          return;
        }
        if (newQty > 30) {
          showToast("Quantity of foods can't be more than 30");
          return;
        }

        input.value = newQty;

        const price = parseFloat(row.querySelector('.price').dataset.price);

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
            row.querySelector('.subtotal').textContent = (price * newQty).toFixed(2);

            let total = 0;
            document.querySelectorAll('.subtotal').forEach(td => {
              total += parseFloat(td.textContent);
            });
            document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);
            showUltraToast();
          } else {
            showToast(data.message || '❌ Update failed');
            input.value = data.quantity || 1;
          }
        } catch {
          showToast("⚠️ Network error");
          input.value = 1;
        }
      });
    });

    document.querySelectorAll('.qty-input').forEach(input => {
      input.addEventListener('change', async function () {
        const row = this.closest('tr');
        const itemId = this.dataset.id;
        let newQty = parseInt(this.value);

        if (isNaN(newQty) || newQty < 1) {
          showToast("Quantity of foods can't be less than 1");
          newQty = 1;
          this.value = newQty;
        } else if (newQty > 30) {
          showToast("Quantity of foods can't be more than 30");
          newQty = 20;
          this.value = newQty;
        }
        
              // If somehow a decimal got in, round it down
      if (this.value.includes('.')) {
        this.value = Math.floor(parseFloat(this.value));
      }
 // Prevent typing "." key directly
    input.addEventListener('keypress', function (e) {
      if (e.key === "." || e.key === "," || e.key === "e") {
        e.preventDefault();
      }
    });

        const price = parseFloat(row.querySelector('.price').dataset.price);

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
            row.querySelector('.subtotal').textContent = (price * newQty).toFixed(2);

            let total = 0;
            document.querySelectorAll('.subtotal').forEach(td => {
              total += parseFloat(td.textContent);
            });
            document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);
            showUltraToast();
          } else {
            showToast(data.message || '❌ Update failed');
            this.value = data.quantity || 1;
          }
        } catch {
          showToast("⚠️ Network error");
          this.value = 1;
        }
      });

      input.addEventListener('input', function () {
        if (this.value < 1) {
          this.value = 1;
        }
        if (this.value > 30) {
          this.value = 30;
        }
      });
    });

    document.querySelectorAll('.remove-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        // Fallback form submission is handled in PHP.

      });
    });
});