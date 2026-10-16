/**
 * FindIt - Main Application UI Scripts
 * Handles mobile nav, modals, toasts, and dynamic UI interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Menu Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');

    if (mobileBtn && navMenu) {
        mobileBtn.addEventListener('click', function () {
            navMenu.classList.toggle('open');
            mobileBtn.classList.toggle('active');
        });
    }

    // 2. Generic Modal Open/Close Controls
    const openModalBtns = document.querySelectorAll('[data-open-modal]');
    openModalBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const modalId = btn.getAttribute('data-open-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    const closeModalBtns = document.querySelectorAll('[data-close-modal]');
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal on click outside modal-box
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    });

    // 3. Delete Confirmation Helper
    const deleteButtons = document.querySelectorAll('.confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const itemTitle = btn.getAttribute('data-title') || 'this item';
            if (!confirm(`Are you sure you want to permanently delete "${itemTitle}"? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 6000);
    });
});

/**
 * Global Toast Notification Helper
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
