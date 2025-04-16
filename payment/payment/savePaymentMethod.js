// Replace your existing savePaymentMethod function with this:
async function savePaymentMethod() {
    // ... existing validation code ...

    // Determine card type
    const firstDigit = cardNumber.charAt(0);
    let cardType = '';
    if (firstDigit === '4') cardType = 'VISA';
    else if (firstDigit === '5') cardType = 'Mastercard';
    else if (firstDigit === '3') cardType = 'American Express';
    else cardType = 'Card';

    try {
        const response = await fetch('save_payment_method.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cardNumber: cardNumber,
                cardType: cardType,
                expiry: expiry,
                name: name
            })
        });

        const result = await response.json();
        
        if (result.success) {
            loadPaymentMethods(); // Refresh the list
            showAlert('methods-alert', 'Payment method added successfully', 'success');
            
            // Clear form
            document.getElementById('new-card-number').value = '';
            document.getElementById('new-expiry').value = '';
            document.getElementById('new-cvv').value = '';
            document.getElementById('new-card-name').value = '';
        } else {
            showAlert('methods-alert', 'Error saving payment method: ' + result.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('methods-alert', 'Network error saving payment method', 'danger');
    }
}

// Replace your existing loadPaymentMethods function with this:
async function loadPaymentMethods() {
    const methodsContainer = document.getElementById('saved-methods');
    methodsContainer.innerHTML = '<div class="loader"></div>';

    try {
        const response = await fetch('get_payment_methods.php');
        const methods = await response.json();

        methodsContainer.innerHTML = '';

        if (methods.error) {
            methodsContainer.innerHTML = `<p style="text-align: center; padding: 20px; color: var(--danger);">${methods.error}</p>`;
            return;
        }

        if (methods.length === 0) {
            methodsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No saved payment methods found.</p>';
            return;
        }

        methods.forEach(method => {
            const methodDiv = document.createElement('div');
            methodDiv.style.background = 'linear-gradient(to right, rgba(0,0,0,0.02), white)';
            methodDiv.style.padding = '15px';
            methodDiv.style.borderRadius = '8px';
            methodDiv.style.marginBottom = '20px';
            methodDiv.style.border = '1px solid rgba(0,0,0,0.05)';
            
            methodDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: bold;">
                            <img src="https://logo.clearbit.com/${method.card_type.toLowerCase()}.com?size=24" class="card-logo" onerror="this.style.display='none'">
                            ${method.card_type} •••• ${method.card_last_four}
                        </div>
                        <div style="font-size: 0.9rem; color: var(--gray);">Expires ${method.expiry}</div>
                        <div style="font-size: 0.9rem; color: var(--gray);">${method.name_on_card}</div>
                    </div>
                    <button class="btn btn-danger" onclick="removePaymentMethod(${method.method_id})">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </div>
            `;
            
            methodsContainer.appendChild(methodDiv);
        });
    } catch (error) {
        console.error('Error:', error);
        methodsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--danger);">Error loading payment methods</p>';
    }
}