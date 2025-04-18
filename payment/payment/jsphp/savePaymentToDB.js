// Replace the entire appData object with these functions:

async function savePaymentToDB(paymentData) {
    try {
        const response = await fetch('save_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(paymentData)
        });
        return await response.json();
    } catch (error) {
        console.error('Error saving payment:', error);
        return { success: false, error: error.message };
    }
}

async function getPaymentsFromDB() {
    try {
        const response = await fetch('get_payments.php');
        return await response.json();
    } catch (error) {
        console.error('Error fetching payments:', error);
        return { success: false, error: error.message };
    }
}

async function savePaymentMethodToDB(methodData) {
    try {
        const response = await fetch('save_payment_method.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(methodData)
        });
        return await response.json();
    } catch (error) {
        console.error('Error saving payment method:', error);
        return { success: false, error: error.message };
    }
}

async function getPaymentMethodsFromDB() {
    try {
        const response = await fetch('get_payment_methods.php');
        return await response.json();
    } catch (error) {
        console.error('Error fetching payment methods:', error);
        return { success: false, error: error.message };
    }
}