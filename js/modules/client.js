window.switchTab = function(tab) {
    const sections = ['wallet', 'history', 'settings'];
    sections.forEach(sec => {
        const el = document.getElementById('sec-' + sec);
        if (el) el.classList.toggle('hidden', sec !== tab);
    });
    document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
    const activeTab = document.getElementById('tab-' + tab);
    if (activeTab) activeTab.classList.add('active');
    if (tab === 'history') loadUserHistory();
    if (tab === 'settings') loadSettingsData();
};

function loadSettingsData() {
    const savedUser = sessionStorage.getItem('user');
    if (!savedUser) return;
    const user = JSON.parse(savedUser);
    const emailInput = document.getElementById('self-email');
    const phoneInput = document.getElementById('self-phone');
    if (emailInput) emailInput.value = user.email || '';
    if (phoneInput) phoneInput.value = user.phone || '';
}

window.handleSelfUpdate = async function(e) {
    e.preventDefault();
    const passField = document.getElementById('self-pass');
    if (passField.value) {
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*?&])[A-Za-z0-9@$!%*?&]{8,}$/;
        if (!regex.test(passField.value)) {
            alert('Password too weak! 8+ chars, Upper/Lower/Number/Symbol.');
            return;
        }
    }
    const formData = new FormData(e.target);
    const entries = Object.fromEntries(formData.entries());
    const user = JSON.parse(sessionStorage.getItem('user'));
    entries.account_number = user.account_number;
    try {
        const response = await fetch('./api/update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(entries)
        });
        const result = await response.json();
        if (result.success) {
            alert('Profile updated!');
            user.email = entries.email;
            user.phone = entries.phone;
            sessionStorage.setItem('user', JSON.stringify(user));
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error(error);
    }
};

async function loadUserHistory() {
    const list = document.getElementById('user-trans-list');
    if (!list) return;
    try {
        const res = await fetch('./api/get_history_safe.php');
        const data = await res.json();
        if (data.success) {
            if (data.logs.length === 0) {
                list.innerHTML = '<tr><td colspan="4" style="text-align:center;">No history available.</td></tr>';
                return;
            }
            list.innerHTML = data.logs.map(log => {
                const color = log.amount < 0 ? '#ff4d4d' : '#2ecc71';
                const sign = log.amount < 0 ? '-' : '+';
                return `
                    <tr>
                        <td>${new Date(log.date).toLocaleString('en-PH')}</td>
                        <td style="font-family: monospace; font-weight: bold;">${log.reference_number || '---'}</td>
                        <td>${log.description}</td>
                        <td style="color:${color}; font-weight: bold;">${sign}₱${Math.abs(log.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    </tr>`;
            }).join('');
        }
    } catch (e) {
        console.error(e);
    }
}

window.handleTransaction = async function(e, type) {
    e.preventDefault();
    const amountInput = e.target.querySelector('input[type="number"]');
    const amount = parseFloat(amountInput.value);
    const user = JSON.parse(sessionStorage.getItem('user'));
    if (isNaN(amount) || amount <= 0) return alert('Valid amount required.');
    if (type === 'withdraw' && amount > user.balance) {
        return alert(`Insufficient funds! Balance: ₱${user.balance}`);
    }
    try {
        const response = await fetch('./api/transaction_complete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: type,
                account_number: user.account_number,
                amount: amount
            })
        });
        const data = await response.json();
        if (data.success) {
            alert(`${type.toUpperCase()} Successful!`);
            user.balance = type === 'deposit' 
                ? parseFloat(user.balance) + amount 
                : parseFloat(user.balance) - amount;
            sessionStorage.setItem('user', JSON.stringify(user));
            populateClientData(user);
            loadUserHistory();
            e.target.reset();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        console.error('Transaction failed:', err);
        alert('System error.');
    }
};

function filterHistory() {

    const input = document.getElementById('history-search');
    const filter = input.value.toLowerCase();
    const tbody = document.getElementById('user-trans-list');
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const refCell = rows[i].getElementsByTagName('td')[1];
        const descCell = rows[i].getElementsByTagName('td')[2];
        if (refCell || descCell) {
            const refText = refCell.textContent || refCell.innerText;
            const descText = descCell.textContent || descCell.innerText;
            if (refText.toLowerCase().indexOf(filter) > -1 || 
                descText.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = ""; // Show row
            } else {
                rows[i].style.display = "none"; // Hide row
            }
        }
    }
}