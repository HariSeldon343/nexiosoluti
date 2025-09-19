// =============================================
// NEXIO COLLABORA - Admin Dashboard JavaScript
// =============================================

// API Configuration - Use centralized APIConfig if available
const ADMIN_API_BASE = window.APIConfig ? window.APIConfig.getApiBaseUrl() : '/collabora/api';

// =============================================
// USER MANAGEMENT
// =============================================

function initializeUserManagement() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            filterUsers(this.value);
        }, 300));
    }

    // Role filter
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            filterUsersByRole(this.value);
        });
    }

    // User form submission
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', handleUserSubmit);
    }
}

// Filter users by search term
function filterUsers(searchTerm) {
    const rows = document.querySelectorAll('#usersTable tbody tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        const email = row.cells[2].textContent.toLowerCase();
        const tenant = row.cells[4].textContent.toLowerCase();

        if (name.includes(term) || email.includes(term) || tenant.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Filter users by role
function filterUsersByRole(role) {
    const rows = document.querySelectorAll('#usersTable tbody tr');

    rows.forEach(row => {
        const roleCell = row.querySelector('.role-badge');
        if (!role || roleCell.classList.contains(role)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Open user modal
function openUserModal(userData = null) {
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('userForm');

    if (userData) {
        modalTitle.textContent = 'Modifica Utente';
        document.getElementById('userId').value = userData.id;
        document.getElementById('userName').value = userData.name;
        document.getElementById('userEmail').value = userData.email;
        document.getElementById('userRole').value = userData.role;
        document.getElementById('userActive').checked = userData.is_active == 1;
        document.getElementById('userPassword').removeAttribute('required');
    } else {
        modalTitle.textContent = 'Nuovo Utente';
        form.reset();
        document.getElementById('userId').value = '';
        document.getElementById('userPassword').setAttribute('required', 'required');
    }

    modal.classList.add('active');
}

// Edit user
function editUser(userData) {
    openUserModal(userData);
}

// Close user modal
function closeUserModal() {
    const modal = document.getElementById('userModal');
    modal.classList.remove('active');
}

// Handle user form submission
async function handleUserSubmit(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const userId = formData.get('userId');
    const action = userId ? 'update_user' : 'create_user';

    const userData = {
        action: action,
        id: userId,
        name: formData.get('name'),
        email: formData.get('email'),
        password: formData.get('password'),
        role: formData.get('role'),
        is_active: formData.get('is_active') ? 1 : 0,
        tenants: formData.getAll('tenants[]')
    };

    try {
        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('users.php', userData);
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/users.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            data = await response.json();
        }

        if (data.success) {
            window.authV2.showToast('success', 'Successo',
                userId ? 'Utente aggiornato con successo' : 'Utente creato con successo');
            closeUserModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.authV2.showToast('error', 'Errore', data.error || 'Operazione fallita');
        }
    } catch (error) {
        console.error('User operation error:', error);
        window.authV2.showToast('error', 'Errore', 'Si è verificato un errore');
    }
}

// Delete user
async function deleteUser(userId) {
    if (!confirm('Sei sicuro di voler eliminare questo utente?')) {
        return;
    }

    try {
        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('users.php', {
                action: 'delete_user',
                id: userId
            });
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/users.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_user',
                    id: userId
                })
            });
            data = await response.json();
        }

        if (data.success) {
            window.authV2.showToast('success', 'Successo', 'Utente eliminato con successo');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.authV2.showToast('error', 'Errore', data.error || 'Impossibile eliminare l\'utente');
        }
    } catch (error) {
        console.error('Delete user error:', error);
        window.authV2.showToast('error', 'Errore', 'Si è verificato un errore');
    }
}

// =============================================
// TENANT MANAGEMENT
// =============================================

function initializeTenantManagement() {
    // Tenant form submission
    const tenantForm = document.getElementById('tenantForm');
    if (tenantForm) {
        tenantForm.addEventListener('submit', handleTenantSubmit);
    }

    // Auto-generate tenant code
    const tenantName = document.getElementById('tenantName');
    const tenantCode = document.getElementById('tenantCode');
    if (tenantName && tenantCode) {
        tenantName.addEventListener('input', function() {
            if (!tenantCode.dataset.manual) {
                const code = generateTenantCode(this.value);
                tenantCode.value = code;
            }
        });

        tenantCode.addEventListener('input', function() {
            this.dataset.manual = 'true';
        });
    }
}

// Generate tenant code from name
function generateTenantCode(name) {
    return name
        .replace(/[^a-zA-Z0-9]/g, '')
        .substring(0, 10)
        .toUpperCase();
}

// Open tenant modal
function openTenantModal(tenantData = null) {
    const modal = document.getElementById('tenantModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('tenantForm');

    if (tenantData) {
        modalTitle.textContent = 'Modifica Tenant';
        document.getElementById('tenantId').value = tenantData.id;
        document.getElementById('tenantName').value = tenantData.name;
        document.getElementById('tenantCode').value = tenantData.code;
        document.getElementById('tenantCode').setAttribute('readonly', 'readonly');
        document.getElementById('maxUsers').value = tenantData.max_users || '';
        document.getElementById('maxStorage').value = tenantData.max_storage || '';
        document.getElementById('tenantActive').checked = tenantData.is_active == 1;
        document.getElementById('allowWebdav').checked = tenantData.settings?.allow_webdav !== false;
        document.getElementById('allowSharing').checked = tenantData.settings?.allow_sharing !== false;
        document.getElementById('allowExternalSharing').checked = tenantData.settings?.allow_external_sharing === true;
    } else {
        modalTitle.textContent = 'Nuovo Tenant';
        form.reset();
        document.getElementById('tenantId').value = '';
        document.getElementById('tenantCode').removeAttribute('readonly');
        document.getElementById('tenantCode').dataset.manual = '';
    }

    modal.classList.add('active');
}

// Edit tenant
function editTenant(tenantData) {
    openTenantModal(tenantData);
}

// Close tenant modal
function closeTenantModal() {
    const modal = document.getElementById('tenantModal');
    modal.classList.remove('active');
}

// Handle tenant form submission
async function handleTenantSubmit(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const tenantId = formData.get('tenantId');
    const action = tenantId ? 'update_tenant' : 'create_tenant';

    const tenantData = {
        action: action,
        id: tenantId,
        name: formData.get('name'),
        code: formData.get('code'),
        max_users: formData.get('max_users') || 0,
        max_storage: formData.get('max_storage') || 0,
        is_active: formData.get('is_active') ? 1 : 0,
        settings: {
            allow_webdav: formData.get('allow_webdav') ? true : false,
            allow_sharing: formData.get('allow_sharing') ? true : false,
            allow_external_sharing: formData.get('allow_external_sharing') ? true : false
        }
    };

    try {
        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('tenants.php', tenantData);
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/tenants.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(tenantData)
            });
            data = await response.json();
        }

        if (data.success) {
            window.authV2.showToast('success', 'Successo',
                tenantId ? 'Tenant aggiornato con successo' : 'Tenant creato con successo');
            closeTenantModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.authV2.showToast('error', 'Errore', data.error || 'Operazione fallita');
        }
    } catch (error) {
        console.error('Tenant operation error:', error);
        window.authV2.showToast('error', 'Errore', 'Si è verificato un errore');
    }
}

// View tenant details
function viewTenantDetails(tenantId) {
    // TODO: Implement tenant details view
    window.authV2.showToast('info', 'In sviluppo', 'Funzionalità in fase di sviluppo');
}

// Delete tenant
async function deleteTenant(tenantId) {
    if (!confirm('Sei sicuro di voler eliminare questo tenant? Questa azione eliminerà anche tutti i dati associati.')) {
        return;
    }

    try {
        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('tenants.php', {
                action: 'delete_tenant',
                id: tenantId
            });
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/tenants.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_tenant',
                    id: tenantId
                })
            });
            data = await response.json();
        }

        if (data.success) {
            window.authV2.showToast('success', 'Successo', 'Tenant eliminato con successo');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            window.authV2.showToast('error', 'Errore', data.error || 'Impossibile eliminare il tenant');
        }
    } catch (error) {
        console.error('Delete tenant error:', error);
        window.authV2.showToast('error', 'Errore', 'Si è verificato un errore');
    }
}

// =============================================
// STATISTICS & CHARTS
// =============================================

function initializeCharts() {
    // Initialize any charts on the page
    const charts = document.querySelectorAll('[data-chart]');
    charts.forEach(chart => {
        const type = chart.dataset.chart;
        const data = JSON.parse(chart.dataset.chartData || '{}');
        createChart(chart, type, data);
    });
}

function createChart(element, type, data) {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

    const ctx = element.getContext('2d');
    new Chart(ctx, {
        type: type,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: type !== 'line',
                    position: 'bottom'
                }
            }
        }
    });
}

// =============================================
// BACKUP & RESTORE
// =============================================

async function performBackup() {
    if (!confirm('Vuoi eseguire un backup completo del sistema?')) {
        return;
    }

    try {
        window.authV2.showToast('info', 'Backup in corso', 'Il backup è stato avviato...');

        let data;

        if (window.APIConfig) {
            data = await APIConfig.post('backup.php', {
                action: 'backup'
            });
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/backup.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'backup'
                })
            });
            data = await response.json();
        }

        if (data.success) {
            window.authV2.showToast('success', 'Backup completato', 'Il backup è stato completato con successo');
            if (data.download_url) {
                window.location.href = data.download_url;
            }
        } else {
            window.authV2.showToast('error', 'Errore', data.error || 'Backup fallito');
        }
    } catch (error) {
        console.error('Backup error:', error);
        window.authV2.showToast('error', 'Errore', 'Si è verificato un errore durante il backup');
    }
}

// =============================================
// ACTIVITY LOGS
// =============================================

async function loadActivityLogs(page = 1, filters = {}) {
    try {
        const params = new URLSearchParams({
            page: page,
            ...filters
        });

        let data;

        if (window.APIConfig) {
            data = await APIConfig.get('logs.php', Object.fromEntries(params));
        } else {
            const response = await fetch(`${ADMIN_API_BASE}/logs.php?${params}`, {
                method: 'GET'
            });
            data = await response.json();
        }

        if (data.success) {
            renderActivityLogs(data.logs);
            renderPagination(data.pagination);
        } else {
            console.error('Failed to load logs');
        }
    } catch (error) {
        console.error('Load logs error:', error);
    }
}

function renderActivityLogs(logs) {
    const container = document.getElementById('logsContainer');
    if (!container) return;

    if (logs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p>Nessun log trovato</p>
            </div>
        `;
        return;
    }

    // Render logs table or list
    let html = '<table class="activity-table"><thead><tr>';
    html += '<th>Data/Ora</th><th>Utente</th><th>Azione</th><th>Dettagli</th><th>IP</th>';
    html += '</tr></thead><tbody>';

    logs.forEach(log => {
        html += `<tr>
            <td>${formatDateTime(log.created_at)}</td>
            <td>${log.user_name || 'Sistema'}</td>
            <td><span class="action-badge ${log.action}">${log.action}</span></td>
            <td>${log.details || '-'}</td>
            <td>${log.ip_address || '-'}</td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

function renderPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    if (!container) return;

    let html = '';

    // Previous button
    html += `<button class="pagination-btn" ${pagination.current_page === 1 ? 'disabled' : ''}
             onclick="loadActivityLogs(${pagination.current_page - 1})">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
    </button>`;

    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<button class="pagination-btn active">${i}</button>`;
        } else if (
            i === 1 ||
            i === pagination.total_pages ||
            (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)
        ) {
            html += `<button class="pagination-btn" onclick="loadActivityLogs(${i})">${i}</button>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += '<span>...</span>';
        }
    }

    // Next button
    html += `<button class="pagination-btn" ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}
             onclick="loadActivityLogs(${pagination.current_page + 1})">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    </button>`;

    container.innerHTML = html;
}

// =============================================
// UTILITY FUNCTIONS
// =============================================

function formatDateTime(dateString) {
    const date = new Date(dateString);
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('it-IT', options);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export functions for global use
window.adminFunctions = {
    openUserModal,
    editUser,
    deleteUser,
    closeUserModal,
    openTenantModal,
    editTenant,
    deleteTenant,
    closeTenantModal,
    viewTenantDetails,
    performBackup,
    loadActivityLogs
};