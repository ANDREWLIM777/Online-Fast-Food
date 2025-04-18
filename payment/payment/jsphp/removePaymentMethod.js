async function removePaymentMethod(id) {
    if (confirm('Are you sure you want to remove this payment method?')) {
        try {
            const response = await fetch('remove_payment_method.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to remove payment method');
            }
            
            await loadPaymentMethods();
            showAlert('methods-alert', 'Payment method removed successfully', 'success');
        } catch (error) {
            console.error('Error removing payment method:', error);
            showAlert('methods-alert', `Failed to remove payment method: ${error.message}`, 'danger');
        }
    }
}