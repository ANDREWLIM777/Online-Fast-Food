async function removePaymentMethod(id) {
    if (confirm('Are you sure you want to remove this payment method?')) {
        try {
            const response = await fetch(`delete_payment_method.php?id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                loadPaymentMethods();
                showAlert('methods-alert', 'Payment method removed successfully', 'success');
            } else {
                showAlert('methods-alert', 'Error removing payment method: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('methods-alert', 'Network error removing payment method', 'danger');
        }
    }
}