async function savePaymentMethod() {
    // ... (keep all the validation code)

    if (!isValid) {
        return;
    }
    
    // Determine card type (simplified)
    const cardType = cardNumber.startsWith('4') ? 'VISA' : 
                    cardNumber.startsWith('5') ? 'Mastercard' : 
                    cardNumber.startsWith('3') ? 'American Express' : 'Card';
    
    try {
        // Save to database
        const methodData = {
            card_type: cardType,
            last_four: cardNumber.slice(-4),
            expiry: expiry,
            card_name: name
        };
        
        const result = await savePaymentMethodToDB(methodData);
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to save payment method');
        }
        
        // Reload methods
        await loadPaymentMethods();
        
        // Clear form
        document.getElementById('new-card-number').value = '';
        document.getElementById('new-expiry').value = '';
        document.getElementById('new-cvv').value = '';
        document.getElementById('new-card-name').value = '';
        
        showAlert('methods-alert', 'Payment method added successfully', 'success');
    } catch (error) {
        console.error('Error saving payment method:', error);
        showAlert('methods-alert', `Failed to save payment method: ${error.message}`, 'danger');
    }
}