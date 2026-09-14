// StockTrack Main JS

// Auto dismiss alerts after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const syncStickyOffsets = function() {
        const filterToolbar = document.querySelector('.filter-toolbar');
        if (!filterToolbar) {
            document.documentElement.style.setProperty('--table-sticky-top', '56px');
            return;
        }

        const filterBottom = 104 + filterToolbar.getBoundingClientRect().height;
        document.documentElement.style.setProperty('--table-sticky-top', `${filterBottom}px`);
    };

    syncStickyOffsets();
    window.addEventListener('resize', syncStickyOffsets);

    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 3000);
    });
});
