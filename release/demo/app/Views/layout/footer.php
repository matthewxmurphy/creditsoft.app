<!-- Footer -->
<footer class="mt-auto py-3 border-top" style="background: linear-gradient(to right, #f8f9fa, #e9ecef);">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-4">
                <p class="mb-0 text-muted small">
                    &copy; <?= date('Y') ?> CreditSoft
                    <span class="mx-2">|</span>
                    <span class="badge bg-success-subtle text-success border border-success">v1.0.0</span>
                </p>
            </div>
            <div class="col-md-4 text-center">
                <span class="badge bg-info-subtle text-info border border-info me-1">
                    <i class="fas fa-shield-alt me-1"></i>Metro2 Compliant
                </span>
                <span class="badge bg-warning-subtle text-warning border border-warning me-1">
                    <i class="fas fa-lock me-1"></i>Secure
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary">
                    <i class="fas fa-bolt me-1"></i>Fast
                </span>
            </div>
            <div class="col-md-4 text-md-end">
                <p class="mb-0 text-muted small">
                    <i class="fas fa-heart text-danger me-1"></i>Credit Repair CRM
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Flash messages auto-dismiss
    window.setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()

    // Chart.js defaults
    Chart.defaults.color = '#6c757d';
    Chart.defaults.borderColor = '#dee2e6';
    Chart.defaults.font.family = "'Inter', sans-serif";
</script>
</body>
</html>
