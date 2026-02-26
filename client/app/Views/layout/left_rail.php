<!-- Left Rail Navigation -->
<div class="left-rail">
    <!-- Logo -->
    <div class="left-rail-logo">
        <i class="bi bi-credit-card-2-front"></i>
        <span>Credit System</span>
    </div>

    <!-- Navigation Items -->
    <nav class="left-rail-nav">
        <!-- Dashboard -->
        <a href="/dashboard" class="rail-item <?= uri_string() == 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>

        <!-- Clients -->
        <a href="/clients" class="rail-item <?= str_starts_with(uri_string(), 'clients') ? 'active' : '' ?>" title="Clients">
            <i class="bi bi-people"></i>
            <span>Clients</span>
            <?php if (isset($stats['clients']['this_month']) && $stats['clients']['this_month'] > 0): ?>
                <span class="badge bg-primary"><?= $stats['clients']['this_month'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Reports -->
        <a href="/reports" class="rail-item <?= str_starts_with(uri_string(), 'reports') ? 'active' : '' ?>" title="Credit Reports">
            <i class="bi bi-file-earmark-text"></i>
            <span>Reports</span>
        </a>

        <!-- Comparison -->
        <a href="/comparison" class="rail-item <?= uri_string() == 'comparison' ? 'active' : '' ?>" title="Compare Reports">
            <i class="bi bi-columns-gap"></i>
            <span>Compare</span>
        </a>

        <!-- Errors -->
        <a href="/errors" class="rail-item <?= str_starts_with(uri_string(), 'errors') ? 'active' : '' ?>" title="Error Identifier">
            <i class="bi bi-exclamation-triangle"></i>
            <span>Errors</span>
            <?php if (isset($stats['errors']['open']) && $stats['errors']['open'] > 0): ?>
                <span class="badge bg-danger"><?= $stats['errors']['open'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Analytics -->
        <a href="/analytics" class="rail-item <?= uri_string() == 'analytics' ? 'active' : '' ?>" title="Analytics">
            <i class="bi bi-graph-up"></i>
            <span>Analytics</span>
        </a>

        <!-- Disputes -->
        <a href="/disputes" class="rail-item <?= str_starts_with(uri_string(), 'disputes') ? 'active' : '' ?>" title="Dispute Letters">
            <i class="bi bi-envelope"></i>
            <span>Disputes</span>
        </a>

        <!-- CRM / Notes -->
        <a href="/crm/notes" class="rail-item <?= str_starts_with(uri_string(), 'crm') ? 'active' : '' ?>" title="CRM Notes">
            <i class="bi bi-journal-text"></i>
            <span>CRM</span>
        </a>

        <!-- Drips -->
        <a href="/drips" class="rail-item <?= str_starts_with(uri_string(), 'drips') ? 'active' : '' ?>" title="Email Drips">
            <i class="bi bi-send"></i>
            <span>Drips</span>
        </a>

        <!-- SOPs -->
        <a href="/sops" class="rail-item <?= uri_string() == 'sops' ? 'active' : '' ?>" title="SOPs">
            <i class="bi bi-book"></i>
            <span>SOPs</span>
        </a>

        <!-- Settings -->
        <a href="/settings" class="rail-item <?= uri_string() == 'settings' ? 'active' : '' ?>" title="Settings">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
    </nav>

    <!-- User Profile at Bottom -->
    <div style="padding: 10px; border-top: 1px solid var(--border-color);">
        <a href="/logout" class="rail-item" title="Logout">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Top Rail -->
<div class="top-rail">
    <div class="top-rail-left">
        <!-- Add Client Button -->
        <button class="btn-add-client" onclick="openModal('addClientModal')">
            <i class="bi bi-plus-lg"></i>
            <span>Add Client</span>
        </button>

        <!-- Search Box -->
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search clients..." id="globalSearch" onkeyup="searchClients(this.value)">
        </div>
    </div>

    <div class="top-rail-right">
        <!-- Theme Toggle -->
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
            <i class="bi bi-moon-stars"></i>
        </button>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="theme-toggle" data-bs-toggle="dropdown" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if (!empty($notifications['open_errors'])): ?>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border-2 rounded-circle">
                        <span class="visually-hidden">New alerts</span>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <?php if (!empty($notifications['open_errors'])): ?>
                    <?php foreach ($notifications['open_errors'] as $error): ?>
                        <li>
                            <a class="dropdown-item" href="/errors/<?= $error['id'] ?>">
                                <i class="bi bi-exclamation-circle text-danger"></i>
                                <?= esc($error['first_name'] . ' ' . $error['last_name']) ?>: 
                                <?= esc($error['error_name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Menu -->
        <div class="dropdown">
            <div class="user-menu" data-bs-toggle="dropdown">
                <div class="user-avatar">
                    <?= isset($user['first_name']) ? strtoupper(substr($user['first_name'], 0, 1)) : 'U' ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= esc($user['first_name'] ?? 'User') . ' ' . esc($user['last_name'] ?? '') ?></span>
                    <span class="user-role"><?= ucfirst($user['role'] ?? 'Staff') ?></span>
                </div>
                <i class="bi bi-chevron-down"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person"></i> Profile</a></li>
                <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-left"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/clients/store" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <input type="text" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-3 mb-3">
                            <select name="state" class="form-select">
                                <option value="">State</option>
                                <option value="AL">AL</option>
                                <option value="AK">AK</option>
                                <option value="AZ">AZ</option>
                                <option value="AR">AR</option>
                                <option value="CA">CA</option>
                                <option value="CO">CO</option>
                                <option value="CT">CT</option>
                                <option value="DE">DE</option>
                                <option value="FL">FL</option>
                                <option value="GA">GA</option>
                                <option value="HI">HI</option>
                                <option value="ID">ID</option>
                                <option value="IL">IL</option>
                                <option value="IN">IN</option>
                                <option value="IA">IA</option>
                                <option value="KS">KS</option>
                                <option value="KY">KY</option>
                                <option value="LA">LA</option>
                                <option value="ME">ME</option>
                                <option value="MD">MD</option>
                                <option value="MA">MA</option>
                                <option value="MI">MI</option>
                                <option value="MN">MN</option>
                                <option value="MS">MS</option>
                                <option value="MO">MO</option>
                                <option value="MT">MT</option>
                                <option value="NE">NE</option>
                                <option value="NV">NV</option>
                                <option value="NH">NH</option>
                                <option value="NJ">NJ</option>
                                <option value="NM">NM</option>
                                <option value="NY">NY</option>
                                <option value="NC">NC</option>
                                <option value="ND">ND</option>
                                <option value="OH">OH</option>
                                <option value="OK">OK</option>
                                <option value="OR">OR</option>
                                <option value="PA">PA</option>
                                <option value="RI">RI</option>
                                <option value="SC">SC</option>
                                <option value="SD">SD</option>
                                <option value="TN">TN</option>
                                <option value="TX">TX</option>
                                <option value="UT">UT</option>
                                <option value="VT">VT</option>
                                <option value="VA">VA</option>
                                <option value="WA">WA</option>
                                <option value="WV">WV</option>
                                <option value="WI">WI</option>
                                <option value="WY">WY</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="text" name="zip" class="form-control" placeholder="ZIP">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="prospect">Prospect</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Global Search Results -->
<div id="searchResults" class="position-absolute bg-white shadow-lg rounded-3 p-2" style="display: none; z-index: 2000; width: 300px; max-height: 400px; overflow-y: auto;">
</div>

<script>
    // Open modal helper
    function openModal(modalId) {
        const modal = new bootstrap.Modal(document.getElementById(modalId));
        modal.show();
    }

    // Global search functionality
    let searchTimeout;
    function searchClients(query) {
        if (query.length < 2) {
            document.getElementById('searchResults').style.display = 'none';
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch('/clients/search?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('searchResults');
                    if (data.length > 0) {
                        resultsDiv.innerHTML = data.map(client => 
                            '<a href="/clients/' + client.id + '" class="d-block p-2 text-decoration-none text-dark border-bottom">' +
                            '<strong>' + client.first_name + ' ' + client.last_name + '</strong><br>' +
                            '<small class="text-muted">' + (client.email || '') + '</small>' +
                            '</a>'
                        ).join('');
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.style.display = 'none';
                    }
                });
        }, 300);
    }

    // Close search on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) {
            document.getElementById('searchResults').style.display = 'none';
        }
    });
</script>
