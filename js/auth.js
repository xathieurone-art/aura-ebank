// Auth API object - handles all backend communication
const Auth = {
    // Login API call
    async login(email, password) {
        const response = await fetch('./api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        return await response.json();
    },

    // Registration API call
    async register(formData) {
        const response = await fetch('./api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        return await response.json();
    },

    // Money transfer API call
    async transfer(transferData) {
        const response = await fetch('./api/transfer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(transferData)
        });
        return await response.json();
    }
};

// Global form submission handler for login, register, and transfer
document.addEventListener('submit', async (e) => {
    const id = e.target.id;
    if (!['login-form', 'register-form', 'transfer-form'].includes(id)) return;

    e.preventDefault();

    // Handle Login
    if (id === 'login-form') {
        const email = e.target.querySelector('[name="email"]').value;
        const pass = e.target.querySelector('[name="password"]').value;
        
        try {
            const result = await Auth.login(email, pass);
            if (result.success) {
                // Block frozen accounts from logging in
                if (result.user.status === 'frozen') {
                    alert("Login Failed: Your account has been frozen.");
                    return;
                }
                sessionStorage.setItem('user', JSON.stringify(result.user));
                // Redirect based on user role
                window.renderView((result.user.role === 'admin' || result.user.role === 'staff') ? 'admin-dashboard' : 'client-dashboard');
            } else { 
                alert(result.message); 
            }
        } catch (err) { console.error("Login Error:", err); }
    }

    // Handle Registration
    if (id === 'register-form') {
        const formData = {
            first_name: e.target.querySelector('[name="first_name"]').value,
            middle_name: e.target.querySelector('[name="middle_name"]').value,
            last_name: e.target.querySelector('[name="last_name"]').value,
            suffix: e.target.querySelector('[name="suffix"]').value,
            gender: e.target.querySelector('[name="gender"]').value,
            email: e.target.querySelector('[name="email"]').value,
            phone: e.target.querySelector('[name="phone"]').value,
            address: e.target.querySelector('[name="address"]').value,
            birthdate: e.target.querySelector('[name="birthdate"]').value,
            password: e.target.querySelector('[name="password"]').value
        };
        
        try {
            const res = await Auth.register(formData);
            alert(res.message);
            if (res.success) window.renderView('login');
        } catch (err) { console.error("Registration Error:", err); }
    }

    // Handle Money Transfer
    if (id === 'transfer-form') {
        const tData = {
            target_acc: e.target.querySelector('[name="target_acc"]').value,
            amount: e.target.querySelector('[name="amount"]').value,
            description: e.target.querySelector('[name="description"]')?.value || ""
        };
        
        // Confirm transfer with user
        if(!confirm(`Confirm transfer of ₱${parseFloat(tData.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}?`)) return;
        
        try {
            const result = await Auth.transfer(tData);
            alert(result.message);
            if (result.success) {
                // Update local user balance
                const user = JSON.parse(sessionStorage.getItem('user'));
                user.balance = result.new_balance;
                sessionStorage.setItem('user', JSON.stringify(user));
                
                // Refresh UI
                if (typeof window.populateClientData === 'function') {
                    window.populateClientData(user);
                }
                
                e.target.reset();
                if (typeof window.loadUserHistory === 'function') {
                    window.loadUserHistory();
                }
            }
        } catch (err) { console.error("Transfer Error:", err); }
    }
});

// Fallback handlers to prevent default form behavior
window.handleLogin = (e) => { if(e && e.preventDefault) e.preventDefault(); };
window.handleRegister = (e) => { if(e && e.preventDefault) e.preventDefault(); };
window.handleTransfer = (e) => { if(e && e.preventDefault) e.preventDefault(); };