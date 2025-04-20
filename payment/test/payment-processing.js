// Format card number with spaces
function formatCardNumber(input) {
  // Remove all non-digit characters
  let value = input.value.replace(/\D/g, '');
  
  // Add spaces every 4 digits
  value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
  
  // Update the input value
  input.value = value;
  
  // Update card type icon
  updateCardTypeIcon(value, input.id.includes('new') ? 'new-card-type-icon' : 'card-type-icon');
}

// Update card type icon based on first digit
function updateCardTypeIcon(cardNumber, iconId) {
  const icon = document.getElementById(iconId);
  if (!icon) return;
  
  // Remove all classes that start with 'fa-cc-'
  Array.from(icon.classList).forEach(className => {
    if (className.startsWith('fa-cc-')) {
      icon.classList.remove(className);
    }
  });
  
  // Determine card type based on first digit
  const firstDigit = cardNumber.charAt(0);
  let cardType = '';
  
  if (firstDigit === '4') {
    cardType = 'visa';
  } else if (firstDigit === '5') {
    cardType = 'mastercard';
  } else if (firstDigit === '3') {
    cardType = 'amex';
  } else if (firstDigit === '6') {
    cardType = 'discover';
  }
  
  if (cardType) {
    icon.classList.add(`fa-cc-${cardType}`);
  }
}

// Format expiry date
function formatExpiryDate(input) {
  let value = input.value.replace(/\D/g, '');
  
  if (value.length > 2) {
    value = value.substring(0, 2) + '/' + value.substring(2, 4);
  }
  
  input.value = value;
}

// Validate number input (prevent letters)
function validateNumberInput(input, maxLength = null) {
  // Remove any non-digit characters
  input.value = input.value.replace(/\D/g, '');
  
  // Limit length if specified
  if (maxLength && input.value.length > maxLength) {
    input.value = input.value.substring(0, maxLength);
  }
}

// Validate amount input
function validateAmount(input) {
  // Allow only numbers and one decimal point
  input.value = input.value.replace(/[^0-9.]/g, '');
  
  // Ensure only one decimal point
  const decimalCount = (input.value.match(/\./g) || []).length;
  if (decimalCount > 1) {
    input.value = input.value.substring(0, input.value.lastIndexOf('.'));
  }
  
  // Limit to 2 decimal places
  if (input.value.includes('.')) {
    const parts = input.value.split('.');
    if (parts[1].length > 2) {
      input.value = parts[0] + '.' + parts[1].substring(0, 2);
    }
  }
}

// Process payment
async function processPayment() {
  // Reset error states
  document.querySelectorAll('.error-message').forEach(el => {
    el.style.display = 'none';
  });
  document.querySelectorAll('input, select').forEach(el => {
    el.classList.remove('error');
  });
  
  // Validate inputs
  let isValid = true;
  const orderId = document.getElementById('order-id').value.trim();
  const amount = document.getElementById('payment-amount').value.trim();
  
  if (!orderId) {
    document.getElementById('order-id-error').textContent = 'Please enter an Order ID';
    document.getElementById('order-id-error').style.display = 'block';
    document.getElementById('order-id').classList.add('error');
    isValid = false;
  }
  
  if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
    document.getElementById('amount-error').textContent = 'Please enter a valid amount';
    document.getElementById('amount-error').style.display = 'block';
    document.getElementById('payment-amount').classList.add('error');
    isValid = false;
  }
  
  // Validate payment method specific fields
  const activeMethod = document.querySelector('.payment-method.active').textContent;
  if (activeMethod.includes('Credit/Debit')) {
    const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
    const expiry = document.getElementById('expiry-date').value;
    const cvv = document.getElementById('cvv').value;
    const cardName = document.getElementById('card-name').value.trim();
    
    if (!cardNumber || cardNumber.length < 16) {
      document.getElementById('card-number-error').textContent = 'Please enter a valid card number';
      document.getElementById('card-number-error').style.display = 'block';
      document.getElementById('card-number').classList.add('error');
      isValid = false;
    }
    
    if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
      document.getElementById('expiry-error').textContent = 'Please enter a valid expiry date (MM/YY)';
      document.getElementById('expiry-error').style.display = 'block';
      document.getElementById('expiry-date').classList.add('error');
      isValid = false;
    }
    
    if (!cvv || cvv.length < 3) {
      document.getElementById('cvv-error').textContent = 'Please enter a valid CVV';
      document.getElementById('cvv-error').style.display = 'block';
      document.getElementById('cvv').classList.add('error');
      isValid = false;
    }
    
    if (!cardName) {
      document.getElementById('card-name-error').textContent = 'Please enter the name on card';
      document.getElementById('card-name-error').style.display = 'block';
      document.getElementById('card-name').classList.add('error');
      isValid = false;
    }
  } else if (activeMethod.includes('Online Banking')) {
    const bank = document.getElementById('bank-select').value;
    if (!bank) {
      document.getElementById('bank-error').textContent = 'Please select a bank';
      document.getElementById('bank-error').style.display = 'block';
      document.getElementById('bank-select').classList.add('error');
      isValid = false;
    } else if (!appData.bankAuthenticated) {
      document.getElementById('bank-error').textContent = 'Please authenticate with your bank first';
      document.getElementById('bank-error').style.display = 'block';
      isValid = false;
    }
  } else if (activeMethod.includes('e-Wallet')) {
    const wallet = document.getElementById('wallet-select').value;
    if (!wallet) {
      document.getElementById('wallet-error').textContent = 'Please select an e-Wallet';
      document.getElementById('wallet-error').style.display = 'block';
      document.getElementById('wallet-select').classList.add('error');
      isValid = false;
    } else if (!appData.walletAuthenticated) {
      document.getElementById('wallet-error').textContent = 'Please authenticate with your e-Wallet first';
      document.getElementById('wallet-error').style.display = 'block';
      isValid = false;
    }
  }
  
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
      card_number: cardNumber.replace(/\s/g, ''),
      expiry: document.getElementById('expiry-date').value,
      cvv: document.getElementById('cvv').value,
      name: document.getElementById('card-name').value.trim()
    };
  } else if (activeMethod.includes('Online Banking')) {
    paymentMethod = document.getElementById('bank-select').selectedOptions[0].text;
    paymentDetails = {
      type: 'bank',
      bank: document.getElementById('bank-select').value,
      authenticated: appData.bankAuthenticated
    };
  } else if (activeMethod.includes('e-Wallet')) {
    paymentMethod = document.getElementById('wallet-select').selectedOptions[0].text;
    paymentDetails = {
      type: 'wallet',
      wallet: document.getElementById('wallet-select').value,
      authenticated: appData.walletAuthenticated
    };
  }
  
  // Show processing screen
  document.getElementById('payment-processing').classList.remove('hidden');
  document.getElementById('payment-success').classList.add('hidden');
  
  try {
    const response = await fetch('payments_api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId,
        amount: parseFloat(amount),
        method: paymentDetails,
        payment_method_display: paymentMethod
      })
    });
    
    if (!response.ok) throw new Error('Payment failed');
    
    const result = await response.json();
    
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
    
    // Hide payment form, show confirmation
    document.querySelector('#payment.tab-content').classList.add('hidden');
    document.getElementById('confirmation').classList.remove('hidden');
    
    // Create confetti effect
    createConfetti();
    
    // Reload payment history
    await loadPaymentHistory();
  } catch (error) {
    console.error('Error processing payment:', error);
    document.getElementById('payment-processing').classList.add('hidden');
    showAlert('payment-alert', 'Payment failed. Please try again.', 'danger');
  }
}