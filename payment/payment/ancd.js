// Add these functions to your existing JavaScript code

// Process payment with database
async function processPaymentToDatabase(paymentData) {
    try {
        const response = await fetch('process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(paymentData)
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Error:', error);
        return { success: false, message: 'Network error' };
    }
}

// Get payment history from database
async function getPaymentHistory() {
    try {
        const response = await fetch('get_payment_history.php');
        const history = await response.json();
        return history;
    } catch (error) {
        console.error('Error:', error);
        return [];
    }
}

// Update your existing processPayment function to use the database
async function processPayment() {
    // ... existing validation code ...
    
    // Prepare payment data
    const paymentData = {
        orderId: orderId,
        amount: parseFloat(amount),
        paymentMethod: paymentMethod,
        cardNumber: document.getElementById('card-number').value.replace(/\s/g, '')
    };
    
    // Process payment with database
    const result = await processPaymentToDatabase(paymentData);
    
    if (result.success) {
        // Show success message and update UI
        document.getElementById('payment-processing').classList.add('hidden');
        document.getElementById('payment-success').classList.remove('hidden');
        
        // Update receipt details
        document.getElementById('receipt-order-id').textContent = orderId;
        document.getElementById('receipt-amount').textContent = `RM${parseFloat(amount).toFixed(2)}`;
        document.getElementById('receipt-total').textContent = `RM${parseFloat(amount).toFixed(2)}`;
        document.getElementById('receipt-method').textContent = paymentMethod;
        
        // Add current date to receipt
        const now = new Date();
        const options = { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        document.getElementById('receipt-date').textContent = 
            now.toLocaleDateString('en-GB', options);
        
        // Create confetti effect
        createConfetti();
    } else {
        // Show error message
        showAlert('payment', 'Payment failed: ' + result.message, 'danger');
        document.getElementById('payment-processing').classList.add('hidden');
    }
}

// Update loadPaymentHistory to use database
async function loadPaymentHistory() {
    const historyList = document.getElementById('history-list');
    historyList.innerHTML = '<div class="loader"></div>';
    
    const history = await getPaymentHistory();
    
    historyList.innerHTML = '';
    
    if (history.length === 0) {
        historyList.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No payment history found.</p>';
        return;
    }
    
    history.forEach(payment => {
        const item = document.createElement('div');
        item.className = 'history-item';
        item.innerHTML = `
            <div>
                <div style="font-weight: bold;">Order ${payment.order_id}</div>
                <div style="font-size: 0.9rem; color: var(--gray);">${new Date(payment.payment_date).toLocaleString()}</div>
                <div style="font-size: 0.9rem; color: var(--gray);">${payment.payment_method}${payment.card_last_four ? ' •••• ' + payment.card_last_four : ''}</div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: bold;">RM${payment.amount.toFixed(2)}</div>
                <div class="status status-completed">Completed</div>
            </div>
        `;
        historyList.appendChild(item);
    });
}