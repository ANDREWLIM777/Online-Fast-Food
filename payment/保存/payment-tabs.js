// Data storage for the application
const appData = {
    paymentHistory: [
      {
        orderId: 'FF-2023-05642',
        date: '01-Apr-2023 14:30',
        amount: 25.90,
        status: 'completed',
        method: 'VISA •••• 3456'
      },
      {
        orderId: 'FF-2023-04891',
        date: '28-Mar-2023 18:15',
        amount: 18.50,
        status: 'completed',
        method: 'Mastercard •••• 5678'
      },
      {
        orderId: 'FF-2023-04236',
        date: '22-Mar-2023 12:45',
        amount: 32.75,
        status: 'failed',
        method: 'Maybank'
      }
    ],
    paymentMethods: [
      {
        id: 1,
        type: 'card',
        lastFour: '1234',
        cardType: 'VISA',
        expiry: '05/25',
        name: 'John Doe'
      },
      {
        id: 2,
        type: 'card',
        lastFour: '5678',
        cardType: 'Mastercard',
        expiry: '11/24',
        name: 'John Doe'
      }
    ],
    refundRequests: [],
    bankCredentials: {
      maybank: { username: 'user123', password: 'maybank123' },
      cimb: { username: 'user123', password: 'cimb123' },
      public: { username: 'user123', password: 'public123' },
      rhb: { username: 'user123', password: 'rhb123' },
      hongleong: { username: 'user123', password: 'hongleong123' }
    },
    walletCredentials: {
      grabpay: { phone: '0123456789', pin: '123456' },
      tng: { phone: '0123456789', pin: '123456' },
      boost: { phone: '0123456789', pin: '123456' },
      shopeepay: { phone: '0123456789', pin: '123456' }
    },
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
  function authenticateBank() {
    const bankSelect = document.getElementById('bank-select').value;
    const username = document.getElementById('bank-username').value;
    const password = document.getElementById('bank-password').value;
    const otp = document.getElementById('bank-otp').value;
    
    // Simple validation - in real app this would call bank API
    const validCredentials = appData.bankCredentials[bankSelect];
    
    if (username === validCredentials.username && password === validCredentials.password) {
      appData.bankAuthenticated = true;
      showAlert('bank-details', 'Bank authentication successful! You can now proceed with payment.', 'success');
    } else {
      showAlert('bank-details', 'Invalid username or password. Please try again.', 'danger');
    }
  }
  
  // Authenticate wallet credentials
  function authenticateWallet() {
    const walletSelect = document.getElementById('wallet-select').value;
    const phone = document.getElementById('wallet-phone').value;
    const pin = document.getElementById('wallet-pin').value;
    
    // Simple validation - in real app this would call wallet API
    const validCredentials = appData.walletCredentials[walletSelect];
    
    if (phone === validCredentials.phone && pin === validCredentials.pin) {
      appData.walletAuthenticated = true;
      showAlert('wallet-details', 'Wallet authentication successful! You can now proceed with payment.', 'success');
    } else {
      showAlert('wallet-details', 'Invalid phone number or PIN. Please try again.', 'danger');
    }
  }