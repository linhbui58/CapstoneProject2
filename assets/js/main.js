// VCF System — Main JS

document.addEventListener('DOMContentLoaded', function () {

    // Sidebar toggle mobile
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target))
                sidebar.classList.remove('show');
        });
    }

    // Auto-dismiss alerts
    document.querySelectorAll('.alert.alert-dismissible').forEach(a => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(a)?.close(), 4500);
    });

    // Confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', function(e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // Progress bar animation
    document.querySelectorAll('.progress-bar[data-w]').forEach(bar => {
        const w = bar.dataset.w;
        setTimeout(() => bar.style.width = w + '%', 300);
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // Verification form logic
    const rejectBtn = document.getElementById('rejectBtn');
    const verifyBtn = document.getElementById('verifyBtn');
    const actionInput = document.getElementById('verificationAction');
    const rejectDiv = document.getElementById('rejectionReasonDiv');

    if (rejectBtn) {
        rejectBtn.addEventListener('click', () => {
            if (rejectDiv) rejectDiv.classList.remove('d-none');
            if (actionInput) actionInput.value = 'rejected';
        });
    }
    if (verifyBtn) {
        verifyBtn.addEventListener('click', () => {
            if (rejectDiv) rejectDiv.classList.add('d-none');
            if (actionInput) actionInput.value = 'verified';
        });
    }

    // Table search
    const ts = document.getElementById('tableSearch');
    if (ts) {
        ts.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.searchable-table tbody tr').forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Number formatter
    window.fmt = n => new Intl.NumberFormat('vi-VN').format(n) + ' VND';
});
