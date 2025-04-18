async function processPayment() {
    // ... (keep all the validation code)

    if (!isValid) {
        return;
    }

    // Get payment method details
    let paymentMethod = '';
    let paymentDetails = {};
    
    if (activeMethod.includes('Credit/Debit')) {
        const cardNumber = document.getElementById('card-number').value;
        paymentMethod = `VISA •••• ${cardNumber.slice(-4)}`;
        paymentDetails = {
            type: 'card',
            cardNumber: cardNumber,
            expiry: document.getElementById('expiry-date').value,
            name: document.getElementById('card-name').value
        };
    } else if (activeMethod.includes('Online Banking')) {
        paymentMethod = document.getElementById('bank-select').selectedOptions[0].text;
        paymentDetails = {
            type: 'bank',
            bank: document.getElementById('bank-select').value
        };
    } else if (activeMethod.includes('e-Wallet')) {
        paymentMethod = document.getElementById('wallet-select').selectedOptions[0].text;
        paymentDetails = {
            type: 'wallet',
            wallet: document.getElementById('wallet-select').value
        };
    }

    // Show processing screen
    document.getElementById('payment-processing').classList.remove('hidden');
    document.getElementById('payment-success').classList.add('hidden');
    document.querySelector('#payment.tab-content').classList.add('hidden');
    document.getElementById('confirmation').classList.remove('hidden');

    try {
        // Save to database
        const paymentData = {
            order_id: orderId,
            amount: parseFloat(amount),
            payment_method: paymentMethod,
            payment_details: paymentDetails
        };
        
        const result = await savePaymentToDB(paymentData);
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to save payment');
        }

        // Hide processing, show success
        document.getElementById('payment-processing').classList.add('hidden');
        document.getElementById('payment-success').classList.remove('hidden');
        
        // Update confirmation message
        document.getElementById('confirm-message').textContent = 
            `Your payment of RM${parseFloat(amount).toFixed(2)} for order ${orderId} was successful.`;
        
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
    } catch (error) {
        console.error('Payment error:', error);
        document.getElementById('payment-processing').classList.add('hidden');
        showAlert('payment', `Payment failed: ${error.message}`, 'danger');
        document.querySelector('#payment.tab-content').classList.remove('hidden');
        document.getElementById('confirmation').classList.add('hidden');
    }
}