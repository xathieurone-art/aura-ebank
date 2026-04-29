// Filter client list by name or account number
window.filterData = function() {
    const input = document.getElementById('admin-search');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const tableBody = document.getElementById('admin-user-list');
    if (!tableBody) return;
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach(row => {
        const nameCell = row.cells[0];
        const accCell = row.cells[1];
        if (nameCell && accCell) {
            const nameText = nameCell.textContent.toLowerCase();
            const accText = accCell.textContent.toLowerCase();
            row.style.display = (nameText.includes(filter) || accText.includes(filter)) ? '' : 'none';
        }
    });
};

// Filter global logs by reference, sender, receiver, or amount
window.filterLogsByRef = function() {
    const input = document.getElementById('ref-search');
    const table = document.getElementById('table-global-logs');
    if (!input || !table) return;
    const filter = input.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const refCell = row.cells[1];
        const senderCell = row.cells[2];
        const receiverCell = row.cells[3];
        const amountCell = row.cells[4];
        const text = [
            refCell?.textContent || '',
            senderCell?.textContent || '',
            receiverCell?.textContent || '',
            amountCell?.textContent || ''
        ].join('').toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
};

// Open edit modal with user data
window.openEditUser = async function(accountNumber) {
    const modal = document.getElementById('edit-modal');
    if (!modal) return;
    try {
        const response = await fetch('./api/admin_get_users.php');
        const data = await response.json();
        const user = data.users.find(u => u.account_number === accountNumber);
        if (user) {
            document.getElementById('edit-user-id').value = user.account_number;
            document.getElementById('edit-fname').value = user.first_name || '';
            document.getElementById('edit-mname').value = user.middle_name || '';
            document.getElementById('edit-lname').value = user.last_name || '';
            document.getElementById('edit-suffix').value = user.suffix || '';
            document.getElementById('edit-gender').value = user.gender || '';
            document.getElementById('edit-email').value = user.email || '';
            document.getElementById('edit-phone').value = user.phone || '';
            modal.classList.remove('hidden');
        }
    } catch (e) {
        console.error(e);
    }
};

// Close edit modal
window.closeEditModal = function() {
    const modal = document.getElementById('edit-modal');
    if (modal) modal.classList.add('hidden');
};

// Save edited user profile
window.saveUserEdit = async function(e) {
    e.preventDefault();
    const payload = {
        action: 'edit_user',
        target_acc: document.getElementById('edit-user-id').value,
        first_name: document.getElementById('edit-fname').value,
        middle_name: document.getElementById('edit-mname').value,
        last_name: document.getElementById('edit-lname').value,
        suffix: document.getElementById('edit-suffix').value,
        gender: document.getElementById('edit-gender').value,
        email: document.getElementById('edit-email').value,
        phone: document.getElementById('edit-phone').value
    };
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('User updated!');
            window.closeEditModal();
            loadAdminUserList();
        } else {
            alert('Update Failed: ' + data.message);
        }
    } catch (e) {
        console.error(e);
    }
};

// Reverse/undo a transaction (admin only)
window.undoTransaction = async function(transactionId) {
    if (!confirm('Revert this transaction? Recipient must have balance.')) return;
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'undo_transaction', transaction_id: transactionId })
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            loadGlobalLogs();
            loadAdminStats();
        }
    } catch (e) {
        console.error('Undo Error:', e);
        alert('System error.');
    }
};

// Permanently delete a user account
window.deleteAccount = async function(accountNumber) {
    if (!confirm(`PERMANENTLY delete ${accountNumber}?`)) return;
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_user', target_acc: accountNumber })
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            loadAdminUserList();
            loadAdminStats();
        }
    } catch (e) {
        console.error(e);
    }
};

// Reset user password with strength validation
window.resetPassword = async function(accountNumber) {
    let newPassword = prompt(`New password for ${accountNumber} (8+ chars, Upper/Lower/Number/Symbol):`);
    if (!newPassword?.trim()) return;
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!regex.test(newPassword)) {
        alert('Password too weak!');
        return;
    }
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_password', target_acc: accountNumber, new_password: newPassword })
        });
        const data = await res.json();
        alert(data.message);
    } catch (e) {
        console.error(e);
    }
};

// Toggle user account freeze/unfreeze
window.toggleFreeze = async function(accountNumber, currentStatus) {
    if (!confirm(`Account will be ${currentStatus === 'frozen' ? 'unfrozen' : 'frozen'}.`)) return;
    try {
        const res = await fetch('./api/admin_toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_acc: accountNumber, current_status: currentStatus })
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) loadAdminUserList();
    } catch (e) {
        console.error('Toggle Error:', e);
        alert('Network error.');
    }
};

// Load and display all client users
async function loadAdminUserList() {
    const tableBody = document.getElementById('admin-user-list');
    if (!tableBody) return;
    const user = JSON.parse(sessionStorage.getItem('user'));
    const isAdmin = user && user.role === 'admin';
    
    try {
        const response = await fetch('./api/admin_get_users.php');
        const data = await response.json();
        if (data.success) {
            tableBody.innerHTML = data.users.map(userItem => {
                const fullName = `${userItem.last_name}, ${userItem.first_name} ${userItem.suffix || ''}`.trim();
                return `
                <tr>
                    <td>${fullName}</td>
                    <td>${userItem.account_number}</td>
                    <td>₱${parseFloat(userItem.balance).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td><span class="badge badge-${userItem.status}">${userItem.status.toUpperCase()}</span></td>
                    <td class="management-actions">
                        <i class="fas fa-edit" onclick="openEditUser('${userItem.account_number}')" title="Edit"></i>
                        <i class="fas fa-key" onclick="resetPassword('${userItem.account_number}')" title="Reset Password"></i>
                        <i class="fas ${userItem.status === 'frozen' ? 'fa-unlock' : 'fa-snowflake'}" onclick="toggleFreeze('${userItem.account_number}', '${userItem.status}')" title="${userItem.status === 'frozen' ? 'Unfreeze' : 'Freeze'}" style="color: ${userItem.status === 'frozen' ? 'var(--success)' : 'var(--warning)'}"></i>
                        ${isAdmin ? `<i class="fas fa-trash" onclick="deleteAccount('${userItem.account_number}')" title="Delete" style="color: var(--danger);"></i>` : ''}
                    </td>
                </tr>`;
            }).join('');
            window.filterData();
        }
    } catch (e) {
        console.error(e);
    }
}

// Load global transaction logs for admin
async function loadGlobalLogs() {
    const list = document.getElementById('admin-global-logs');
    if (!list) return;
    const user = JSON.parse(sessionStorage.getItem('user'));
    const isAdmin = user && user.role === 'admin';
    
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_global_logs' })
        });
        const data = await res.json();
        if (data.success) {
            list.innerHTML = data.logs.map(log => {
                const isReversed = log.description?.toLowerCase().includes('reversed');
                const isDepositOrWithdraw = log.description === 'Deposit Transaction' || log.description === 'Withdraw Transaction';
                const isAlreadyReversed = log.description?.toLowerCase().includes('(reversed)');
                
                let actionHtml = '';
                
                // Determine action button based on transaction type
                if (isReversed || isAlreadyReversed) {
                    actionHtml = '<span class="badge badge-reversed">REVERSED</span>';
                } else if (isDepositOrWithdraw) {
                    actionHtml = '<span class="badge badge-active">NON-REVERSIBLE</span>';
                } else if (isAdmin) {
                    actionHtml = `<button onclick="undoTransaction('${log.id}')" class="undo-btn"><i class="fas fa-undo"></i> UNDO</button>`;
                } else {
                    actionHtml = '<span class="badge badge-active">READ ONLY</span>';
                }
                
                return `
                <tr>
                    <td>${new Date(log.created_at).toLocaleString()}</td>
                    <td style="font-family: monospace; color: var(--primary); font-weight: bold;">${log.reference_number || 'N/A'}</td>
                    <td>${log.sender_acc || 'System'}</td>
                    <td>${log.receiver_acc || 'External'}</td>
                    <td>₱${parseFloat(log.amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td><i>${log.description}</i></td>
                    <td>${actionHtml}</td>
                </tr>`;
            }).join('');
            window.filterLogsByRef();
        }
    } catch (e) {
        console.error(e);
    }
}

// Load admin statistics (total money and users)
async function loadAdminStats() {
    const statsSection = document.getElementById('admin-stats-section');
    const user = JSON.parse(sessionStorage.getItem('user'));
    
    // Hide stats for non-admin users
    if (!user || user.role !== 'admin') {
        if (statsSection) {
            statsSection.style.display = 'none';
            statsSection.style.visibility = 'hidden';
        }
        return;
    }
    
    if (statsSection) {
        statsSection.style.display = 'grid';
        statsSection.style.visibility = 'visible';
    }
    
    try {
        const res = await fetch('./api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_stats' })
        });
        const data = await res.json();
        if (data.success && user.role === 'admin') {
            const capitalEl = document.getElementById('total-capital');
            const usersEl = document.getElementById('total-users');
            if (capitalEl) capitalEl.innerText = `₱${parseFloat(data.stats.total_money).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
            if (usersEl) usersEl.innerText = data.stats.total_users;
        }
    } catch (e) {
        console.error(e);
    }
}

// Load pending loan applications
async function loadPendingLoans() {
    const list = document.getElementById('pending-loans-list');
    if (!list) return;
    
    try {
        const response = await fetch('./api/get_loans.php');
        const loans = await response.json();
        
        const pendingLoans = loans.filter(loan => loan.status === 'Pending');
        
        if (pendingLoans.length === 0) {
            list.innerHTML = '<tr><td colspan="7" style="text-align:center;">No pending loan applications</td></tr>';
            return;
        }
        
        let html = '';
        for (let i = 0; i < pendingLoans.length; i++) {
            const loan = pendingLoans[i];
            const date = new Date(loan.created_at).toLocaleString();
            const amount = parseFloat(loan.amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
            html += `
                <tr>
                    <td>${date}</td>
                    <td>${loan.first_name} ${loan.last_name}</td>
                    <td>${loan.account_number}</td>
                    <td>${loan.loan_type}</td>
                    <td>₱${amount}</td>
                    <td><span class="badge badge-warning">PENDING</span></td>
                    <td class="management-actions">
                        <i class="fas fa-check-circle" onclick="approveLoan(${loan.id})" title="Approve" style="color: var(--success); cursor: pointer; font-size: 1.2rem;"></i>
                        <i class="fas fa-times-circle" onclick="declineLoan(${loan.id})" title="Decline" style="color: var(--danger); cursor: pointer; font-size: 1.2rem;"></i>
                    </td>
                </tr>
            `;
        }
        list.innerHTML = html;
    } catch (err) {
        console.error('Error loading loans:', err);
        list.innerHTML = '<tr><td colspan="7" style="text-align:center;">Error loading loans</td></tr>';
    }
}

// Approve loan and credit to user balance
window.approveLoan = async function(loanId) {
    if (!confirm('Approve this loan application? The amount will be credited to the user\'s balance.')) return;
    try {
        const response = await fetch('./api/approve_loan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ loan_id: loanId })
        });
        const data = await response.json();
        alert(data.message);
        if (data.success) {
            loadPendingLoans();
            loadAdminStats();
        }
    } catch (err) {
        console.error('Error:', err);
        alert('System error');
    }
};

// Decline loan application
window.declineLoan = async function(loanId) {
    if (!confirm('Decline this loan application?')) return;
    try {
        const response = await fetch('./api/decline_loan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ loan_id: loanId })
        });
        const data = await response.json();
        alert(data.message);
        if (data.success) loadPendingLoans();
    } catch (err) {
        console.error('Error:', err);
        alert('System error');
    }
};

// Switch between admin tabs (Clients, Loans, Logs)
window.adminTab = function(tab) {
    const clientsSec = document.getElementById('admin-clients-sec');
    const loansSec = document.getElementById('admin-loans-sec');
    const logsSec = document.getElementById('admin-logs-sec');
    
    if (clientsSec) clientsSec.classList.toggle('hidden', tab !== 'clients');
    if (loansSec) loansSec.classList.toggle('hidden', tab !== 'loans');
    if (logsSec) logsSec.classList.toggle('hidden', tab !== 'logs');
    
    document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
    const activeTab = document.getElementById('admin-tab-' + tab);
    if (activeTab) activeTab.classList.add('active');
    
    // Load data based on selected tab
    if (tab === 'logs') loadGlobalLogs();
    else if (tab === 'loans') loadPendingLoans();
    else loadAdminUserList();
};

// Filter loan applications by name, account, or loan type
window.filterLoanData = function() {
    const input = document.getElementById('loan-search');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const tableBody = document.getElementById('pending-loans-list');
    if (!tableBody) return;
    const rows = tableBody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const nameCell = row.cells[1];
        const accCell = row.cells[2];
        const loanTypeCell = row.cells[3];
        
        if (nameCell && accCell && loanTypeCell) {
            const nameText = nameCell.textContent.toLowerCase();
            const accText = accCell.textContent.toLowerCase();
            const loanTypeText = loanTypeCell.textContent.toLowerCase();
            
            if (nameText.includes(filter) || accText.includes(filter) || loanTypeText.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
};