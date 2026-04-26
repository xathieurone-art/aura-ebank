const components = {
    'login': 'components/auth.html',
    'register': 'components/auth.html',
    'client-dashboard': 'components/client-dashboard.html',
    'admin-dashboard': 'components/admin-dashboard.html'
};

window.renderView = async function(viewId) {
    const app = document.getElementById('app');
    if (!app) return;

    await loadComponent('components/modals.html');

    const compPath = components[viewId];
    if (compPath) {
        try {
            const resp = await fetch(compPath);
            const html = await resp.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const template = doc.querySelector('template#' + 'tpl-' + viewId);
            if (template) {
                app.innerHTML = '';
                app.appendChild(template.content.cloneNode(true));
            }
        } catch (e) {
            console.error('Component load error:', e);
            return;
        }
    }

    const savedUser = sessionStorage.getItem('user');
    if (savedUser) {
        const user = JSON.parse(savedUser);
        if (user.status === 'frozen' && user.role !== 'admin' && user.role !== 'staff') {
            alert("Account frozen. Contact admin.");
            logout();
            return;
        }
        if (viewId === 'client-dashboard') {
            if (window.populateClientData) populateClientData(user);
        } else if (viewId === 'admin-dashboard') {
            const adminNameEl = document.getElementById('admin-display-name');
            const roleBadgeEl = document.getElementById('admin-role-badge');
            if (adminNameEl) {
                const first = user.first_name || '';
                const last = user.last_name || '';
                const suffix = user.suffix || '';
                const rolePrefix = user.role === 'staff' ? '[STAFF] ' : '';
                adminNameEl.innerText = rolePrefix + `${first} ${last} ${suffix}`.trim() || (user.role === 'staff' ? 'Aura Staff' : 'Aura Administrator');
            }
            if (roleBadgeEl) {
                if (user.role === 'admin') {
                    roleBadgeEl.innerText = 'AURA ADMIN';
                    roleBadgeEl.style.color = 'var(--primary)';
                } else if (user.role === 'staff') {
                    roleBadgeEl.innerText = 'AURA STAFF';
                    roleBadgeEl.style.color = 'var(--success)';
                }
                roleBadgeEl.style.fontSize = '11px';
                roleBadgeEl.style.marginTop = '5px';
                roleBadgeEl.style.display = 'block';
            }
            if (window.loadAdminStats) loadAdminStats();
            if (window.loadAdminUserList) loadAdminUserList();
        }
    }
};

async function loadComponent(path) {
    try {
        const resp = await fetch(path);
        const html = await resp.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        document.body.append(...doc.body.childNodes);
    } catch (e) {
        console.error('Modal/component load error:', e);
    }
}