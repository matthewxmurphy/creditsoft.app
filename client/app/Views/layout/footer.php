<!-- Footer -->
<footer class="mt-5 py-3 border-top">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <p class="text-muted mb-0">
                    &copy; <?= date('Y') ?> Credit Error Identifier System
                    <span class="mx-2">|</span>
                    Version 1.0.0
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-0">
                    Powered by Metro2 Data Standards
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
