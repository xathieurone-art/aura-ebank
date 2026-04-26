window.togglePassword = function(inputId, icon) {
    const passwordInput = document.getElementById(inputId);
    if (!passwordInput) return;
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
};
function populateClientData(user) {
    const nameEl = document.getElementById('display-name');
    const accEl = document.getElementById('display-acc');
    const balEl = document.getElementById('display-balance');
    const first = user.first_name || '';
    const middle = user.middle_name ? `${user.middle_name.charAt(0)}.` : '';
    const last = user.last_name || '';
    const suffix = user.suffix || '';
    const fullName = `${first} ${middle} ${last} ${suffix}`.trim() || 'Aura User';
    if (nameEl) nameEl.innerText = fullName;
    if (accEl) accEl.innerText = `ACC: ${user.account_number || '---'}`;
    if (balEl) balEl.innerText = new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP' 
    }).format(user.balance || 0);
}

window.logout = function() {
    sessionStorage.clear();
    window.location.reload();
};
document.addEventListener('DOMContentLoaded', function() {
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.addEventListener('input', function() {
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*?&])[A-Za-z0-9@$!%*?&]{8,}$/;
            if (this.value === '') {
                this.style.border = '';
                return;
            }
            if (regex.test(this.value)) {
                this.style.border = '2px solid #2ecc71';
                this.style.boxShadow = '0 0 8px rgba(46, 204, 113, 0.3)';
            } else {
                this.style.border = '2px solid #e74c3c';
                this.style.boxShadow = '0 0 8px rgba(231, 76, 60, 0.3)';
            }
        });
    });
});
window.handleLogin = e => e?.preventDefault();
window.handleRegister = e => e?.preventDefault();
window.handleTransfer = e => e?.preventDefault();