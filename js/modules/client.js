// Switch between client dashboard tabs
window.switchTab = function(tab) {
    const sections = ['wallet', 'history', 'loan', 'bills', 'settings'];
    sections.forEach(sec => {
        const el = document.getElementById('sec-' + sec);
        if (el) el.classList.toggle('hidden', sec !== tab);
    });
    document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
    const activeTab = document.getElementById('tab-' + tab);
    if (activeTab) activeTab.classList.add('active');
    if (tab === 'history') loadUserHistory();
    if (tab === 'loan') loadUserLoans();
    if (tab === 'bills') loadUserBills();
    if (tab === 'settings') loadSettingsData();
};

// Load user data into settings form
function loadSettingsData() {
    const savedUser = sessionStorage.getItem('user');
    if (!savedUser) return;
    const user = JSON.parse(savedUser);
    const emailInput = document.getElementById('self-email');
    const phoneInput = document.getElementById('self-phone');
    if (emailInput) emailInput.value = user.email || '';
    if (phoneInput) phoneInput.value = user.phone || '';
}

// Handle profile update (email, phone, password)
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

// Load user transaction history
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
            let html = '';
            for (let i = 0; i < data.logs.length; i++) {
                const log = data.logs[i];
                const color = log.amount < 0 ? '#ff4d4d' : '#2ecc71';
                const sign = log.amount < 0 ? '-' : '+';
                const date = new Date(log.date).toLocaleString('en-PH');
                const ref = log.reference_number || '---';
                const amount = Math.abs(log.amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                html += `
                    <tr>
                        <td>${date}</td>
                        <td style="font-family: monospace; font-weight: bold;">${ref}</td>
                        <td>${log.description}</td>
                        <td style="color:${color}; font-weight: bold;">${sign}₱${amount}</td>
                    </tr>
                `;
            }
            list.innerHTML = html;
        }
    } catch (e) {
        console.error(e);
        list.innerHTML = '<tr><td colspan="4" style="text-align:center;">Error loading history.</td></tr>';
    }
}

// Handle deposit and withdrawal transactions
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

// Filter transaction history by reference or description
function filterHistory() {
    const input = document.getElementById('history-search');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const tbody = document.getElementById('user-trans-list');
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const refCell = rows[i].getElementsByTagName('td')[1];
        const descCell = rows[i].getElementsByTagName('td')[2];
        if (refCell || descCell) {
            const refText = refCell.textContent || refCell.innerText;
            const descText = descCell.textContent || descCell.innerText;
            if (refText.toLowerCase().indexOf(filter) > -1 || descText.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
}

// Submit loan application
window.handleLoanApplication = async function(e) {
    e.preventDefault();
    const loanType = document.getElementById('loan-type').value;
    const loanAmount = parseFloat(document.getElementById('loan-amount').value);
    if (!loanType) {
        alert('Please select a loan type');
        return;
    }
    if (isNaN(loanAmount) || loanAmount <= 0) {
        alert('Please enter a valid loan amount');
        return;
    }
    try {
        const response = await fetch('./api/apply_loan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                loan_type: loanType,
                amount: loanAmount
            })
        });
        const data = await response.json();
        if (data.success) {
            alert('Loan application submitted successfully! Waiting for approval.');
            document.getElementById('loan-type').value = '';
            document.getElementById('loan-amount').value = '';
            loadUserLoans();
        } else {
            alert('Error: ' + (data.message || 'Failed to submit application'));
        }
    } catch (err) {
        console.error('Loan application error:', err);
        alert('System error. Please try again.');
    }
};

// Handle bill payment
window.handleBillPayment = async function(e) {
    e.preventDefault();
    const biller = document.getElementById('biller-type').value;
    const amount = parseFloat(document.getElementById('bill-amount').value);
    if (!biller) {
        alert('Please select a biller');
        return;
    }
    if (isNaN(amount) || amount <= 0) {
        alert('Please enter a valid amount');
        return;
    }
    const user = JSON.parse(sessionStorage.getItem('user'));
    if (amount > user.balance) {
        alert('Insufficient balance! Your current balance: ₱' + user.balance.toLocaleString());
        return;
    }
    try {
        const response = await fetch('./api/pay_bill.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                biller: biller,
                amount: amount
            })
        });
        const data = await response.json();
        if (data.success) {
            alert('Bill paid successfully!');
            user.balance = data.new_balance;
            sessionStorage.setItem('user', JSON.stringify(user));
            populateClientData(user);
            loadUserBills();
            loadUserHistory();
            document.getElementById('biller-type').value = '';
            document.getElementById('bill-amount').value = '';
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        console.error('Bill payment error:', err);
        alert('System error. Please try again.');
    }
};

// Load user's loan applications
async function loadUserLoans() {
    const tbody = document.getElementById('user-loan-list');
    if (!tbody) return;
    try {
        const response = await fetch('./api/get_loans.php');
        const loans = await response.json();
        if (loans.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No loan applications found</td></tr>';
            return;
        }
        let html = '';
        for (let i = 0; i < loans.length; i++) {
            const loan = loans[i];
            let statusClass = '';
            let statusText = loan.status;
            if (loan.status === 'Pending') {
                statusClass = 'badge badge-warning';
                statusText = 'PENDING';
            } else if (loan.status === 'Approved') {
                statusClass = 'badge badge-success';
                statusText = 'APPROVED';
            } else if (loan.status === 'Denied') {
                statusClass = 'badge badge-danger';
                statusText = 'DENIED';
            }
            const createdDate = new Date(loan.created_at).toLocaleDateString();
            const amount = parseFloat(loan.amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
            html += `
                <tr>
                    <td>${loan.loan_type}</td>
                    <td>₱${amount}</td>
                    <td>${createdDate}</td>
                    <td><span class="${statusClass}">${statusText}</span></td>
                </tr>
            `;
        }
        tbody.innerHTML = html;
    } catch (err) {
        console.error('Error loading loans:', err);
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Error loading loan history</td></tr>';
    }
}

// Load user's bill payment history
async function loadUserBills() {
    const tbody = document.getElementById('user-bills-list');
    if (!tbody) return;
    try {
        const response = await fetch('./api/get_bills.php');
        const bills = await response.json();
        if (bills.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No bill payments found</td></tr>';
            return;
        }
        let html = '';
        for (let i = 0; i < bills.length; i++) {
            const bill = bills[i];
            const paymentDate = new Date(bill.created_at).toLocaleDateString();
            const amount = parseFloat(bill.amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
            html += `
                <tr>
                    <td>${bill.biller}</td>
                    <td>₱${amount}</td>
                    <td>${paymentDate}</td>
                    <td><span class="badge badge-success">PAID</span></td>
                </tr>
            `;
        }
        tbody.innerHTML = html;
    } catch (err) {
        console.error('Error loading bills:', err);
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Error loading bill history</td></tr>';
    }
}