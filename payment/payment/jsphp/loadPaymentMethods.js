async function loadPaymentMethods() {
    const methodsContainer = document.getElementById('saved-methods');
    methodsContainer.innerHTML = '<div class="loader"></div>';
    
    try {
        const result = await getPaymentMethodsFromDB();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to load payment methods');
        }
        
        const methods = result.methods;
        methodsContainer.innerHTML = '';
        
        if (methods.length === 0) {
            methodsContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No saved payment methods found.</p>';
            return;
        }
        
        methods.forEach(method => {
            const methodDiv = document.createElement('div');
            methodDiv.style.background = 'linear-gradient(to right, rgba(0,0,0,0.02), white)';
            methodDiv.style.padding = '15px';
            methodDiv.style.borderRadius = '8px';
            methodDiv.style.marginBottom = '20px';
            methodDiv.style.border = '1px solid rgba(0,0,0,0.05)';
            
            methodDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: bold;">
                            <img src="https://logo.clearbit.com/${method.card_type.toLowerCase()}.com?size=24" class="card-logo" onerror="this.style.display='none'">
                            ${method.card_type} •••• ${method.last_four}
                        </div>
                        <div style="font-size: 0.9rem; color: var(--gray);">Expires ${method.expiry}</div>
                        <div style="font-size: 0.9rem; color: var(--gray);">${method.card_name}</div>
                    </div>
                    <button class="btn btn-danger" onclick="removePaymentMethod(${method.id})">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </div>
            `;
            
            methodsContainer.appendChild(methodDiv);
        });
    } catch (error) {
        console.error('Error loading payment methods:', error);
        methodsContainer.innerHTML = `<p style="text-align: center; padding: 20px; color: var(--danger);">Error loading payment methods: ${error.message}</p>`;
    }
}