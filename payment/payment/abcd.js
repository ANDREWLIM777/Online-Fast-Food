function processPayment() {
    // ... existing validation code ...
    
    // Send data to server
    fetch('payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=process_payment&order_id=${encodeURIComponent(orderId)}&amount=${amount}&payment_method=${encodeURIComponent(paymentMethod)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update UI with server response
            document.getElementById('receipt-order-id').textContent = data.payment.order_id;
            document.getElementById('receipt-amount').textContent = `RM${parseFloat(data.payment.amount).toFixed(2)}`;
            document.getElementById('receipt-total').textContent = `RM${parseFloat(data.payment.amount).toFixed(2)}`;
            document.getElementById('receipt-method').textContent = data.payment.payment_method;
            
            // Add date to receipt
            document.getElementById('receipt-date').textContent = 
                new Date(data.payment.created_at).toLocaleDateString('en-GB', options);
            
            // Show success UI
            document.getElementById('payment-processing').classList.add('hidden');
            document.getElementById('payment-success').classList.remove('hidden');
            
            // Create confetti effect
            createConfetti();
        } else {
            alert(data.message);
            backToHome();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Payment processing failed');
        backToHome();
    });
}