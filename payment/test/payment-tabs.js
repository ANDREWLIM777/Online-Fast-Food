// Data storage for the application
const appData = {
  currentPaymentMethod: 'card',
  bankAuthenticated: false,
  walletAuthenticated: false
};

// Tab navigation
function openTab(tabId) {
  // Hide all tab contents
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Remove active class from all tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected tab content
  document.getElementById(tabId).classList.add('active');
  
  // Add active class to clicked tab
  event.currentTarget.classList.add('active');
  
  // Load data for specific tabs when opened
  if (tabId === 'history') {
    loadPaymentHistory();
  } else if (tabId === 'refund') {
    loadRefundOrders();
  } else if (tabId === 'methods') {
    loadPaymentMethods();
  }
}

// Payment method selection
function selectPaymentMethod(method) {
  // Update UI for method selection
  document.querySelectorAll('.payment-method').forEach(el => {
    el.classList.remove('active');
  });
  event.currentTarget.classList.add('active');
  
  // Show relevant details
  document.getElementById('card-details').classList.add('hidden');
  document.getElementById('bank-details').classList.add('hidden');
  document.getElementById('wallet-details').classList.add('hidden');
  
  if (method === 'card') {
    document.getElementById('card-details').classList.remove('hidden');
    appData.currentPaymentMethod = 'card';
    appData.bankAuthenticated = false;
    appData.walletAuthenticated = false;
  } else if (method === 'bank') {
    document.getElementById('bank-details').classList.remove('hidden');
    appData.currentPaymentMethod = 'bank';
    appData.bankAuthenticated = false;
    appData.walletAuthenticated = false;
    showBankLogin();
  } else if (method === 'wallet') {
    document.getElementById('wallet-details').classList.remove('hidden');
    appData.currentPaymentMethod = 'wallet';
    appData.bankAuthenticated = false;
    appData.walletAuthenticated = false;
    showWalletLogin();
  }
}

// Show bank login form when bank is selected
function showBankLogin() {
  const bankSelect = document.getElementById('bank-select');
  const bankLogin = document.getElementById('bank-login');
  
  if (bankSelect.value) {
    bankLogin.classList.remove('hidden');
  } else {
    bankLogin.classList.add('hidden');
  }
}

// Show wallet login form when wallet is selected
function showWalletLogin() {
  const walletSelect = document.getElementById('wallet-select');
  const walletLogin = document.getElementById('wallet-login');
  
  if (walletSelect.value) {
    walletLogin.classList.remove('hidden');
  } else {
    walletLogin.classList.add('hidden');
  }
}

// Authenticate bank credentials
async function authenticateBank() {
  const bankSelect = document.getElementById('bank-select').value;
  const username = document.getElementById('bank-username').value;
  const password = document.getElementById('bank-password').value;
  const otp = document.getElementById('bank-otp').value;
  
  try {
    const response = await fetch('bank_auth.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        bank: bankSelect,
        username: username,
        password: password,
        otp: otp
      })
    });
    
    if (!response.ok) throw new Error('Authentication failed');
    
    const result = await response.json();
    
    if (result.authenticated) {
      appData.bankAuthenticated = true;
      showAlert('bank-details', 'Bank authentication successful! You can now proceed with payment.', 'success');
    } else {
      throw new Error(result.message || 'Invalid credentials');
    }
  } catch (error) {
    console.error('Bank authentication error:', error);
    showAlert('bank-details', error.message || 'Authentication failed. Please try again.', 'danger');
  }
}

// Authenticate wallet credentials
async function authenticateWallet() {
  const walletSelect = document.getElementById('wallet-select').value;
  const phone = document.getElementById('wallet-phone').value;
  const pin = document.getElementById('wallet-pin').value;
  
  try {
    const response = await fetch('wallet_auth.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        wallet: walletSelect,
        phone: phone,
        pin: pin
      })
    });
    
    if (!response.ok) throw new Error('Authentication failed');
    
    const result = await response.json();
    
    if (result.authenticated) {
      appData.walletAuthenticated = true;
      showAlert('wallet-details', 'Wallet authentication successful! You can now proceed with payment.', 'success');
    } else {
      throw new Error(result.message || 'Invalid credentials');
    }
  } catch (error) {
    console.error('Wallet authentication error:', error);
    showAlert('wallet-details', error.message || 'Authentication failed. Please try again.', 'danger');
  }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', async function() {
  // Set up event listeners for input formatting
  document.getElementById('card-number').addEventListener('input', function() {
    formatCardNumber(this);
  });
  
  document.getElementById('new-card-number').addEventListener('input', function() {
    formatCardNumber(this);
  });
  
  document.getElementById('expiry-date').addEventListener('input', function() {
    formatExpiryDate(this);
  });
  
  document.getElementById('new-expiry').addEventListener('input', function() {
    formatExpiryDate(this);
  });
  
  document.getElementById('cvv').addEventListener('input', function() {
    validateNumberInput(this, 4);
  });
  
  document.getElementById('new-cvv').addEventListener('input', function() {
    validateNumberInput(this, 4);
  });
  
  document.getElementById('payment-amount').addEventListener('input', function() {
    validateAmount(this);
  });
  
  // Initialize default payment method
  selectPaymentMethod('card');
  
  // Load initial data
  try {
    await Promise.all([
      loadPaymentHistory(),
      loadPaymentMethods(),
      loadRefundOrders()
    ]);
  } catch (error) {
    console.error('Initialization error:', error);
  }
});