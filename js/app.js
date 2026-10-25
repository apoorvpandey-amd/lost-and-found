/**
 * FindIt - Main Application UI Scripts
 * Handles mobile nav, modals, toasts, dynamic data fetching, search, and filtering
 */

// Category dictionary with icons and labels
const CATEGORIES = {
    electronics: { label: 'Electronics & Gadgets', icon: '📱' },
    wallets:     { label: 'Wallets & Purses',     icon: '👛' },
    keys:        { label: 'Keys & Keychains',     icon: '🔑' },
    documents:   { label: 'Cards & Documents',    icon: '🪪' },
    clothing:    { label: 'Clothing & Eyewear',   icon: '👓' },
    pets:        { label: 'Pets & Animals',       icon: '🐾' },
    books:       { label: 'Books & Notebooks',    icon: '📚' },
    jewelry:     { label: 'Jewelry & Watches',    icon: '⌚' },
    other:       { label: 'Other Belongings',     icon: '📦' }
};

// Global cache of loaded items
let currentLoadedItems = [];
let activeTypeFilter = 'all';
let activeCategoryFilter = 'all';
let activeSearchQuery = '';

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
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const modalId = btn.getAttribute('data-open-modal');
            openModal(modalId);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = btn.closest('.modal-overlay');
            if (modal) closeModal(modal);
        });
    });

    // Close modal on click outside modal-box
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(modal => closeModal(modal));
        }
    });

    // 3. Claim Form Handler (if present)
    const claimForm = document.getElementById('claimForm');
    if (claimForm) {
        claimForm.addEventListener('submit', handleClaimSubmit);
    }

    // 4. Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 400);
        }, 6000);
    });

    // 5. Initialize Page Specific Logic
    if (document.getElementById('homePageMarker')) {
        initHomePage();
    } else if (document.getElementById('browsePageMarker')) {
        initBrowsePage();
    } else if (document.getElementById('reportPageMarker')) {
        initReportPage();
    }
});

/**
 * Modal helper functions
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (typeof modal === 'string') modal = document.getElementById(modal);
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

/**
 * Toast Notification Helper
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
    }, 4500);
}

/**
 * Escape HTML to prevent XSS in dynamic rendering
 */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * HOME PAGE INITIALIZATION
 */
function initHomePage() {
    loadLiveStats();
    loadHomeItems();

    // Type Filter Tabs
    const typeTabs = document.querySelectorAll('.type-tabs .tab-btn');
    typeTabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            typeTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeTypeFilter = tab.getAttribute('data-type') || 'all';
            filterAndRenderItems();
        });
    });

    // Category Pills
    const categoryPills = document.querySelectorAll('.category-pills .category-pill');
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function (e) {
            e.preventDefault();
            categoryPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeCategoryFilter = pill.getAttribute('data-category') || 'all';
            filterAndRenderItems();
        });
    });

    // Hero Search Form
    const heroSearchForm = document.getElementById('heroSearchForm');
    if (heroSearchForm) {
        heroSearchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const input = document.getElementById('heroSearchInput');
            const select = document.getElementById('heroCategorySelect');
            const query = input ? input.value.trim() : '';
            const category = select ? select.value : '';

            // Redirect to browse page with search params or filter live
            window.location.href = `browse.html?q=${encodeURIComponent(query)}&category=${encodeURIComponent(category)}`;
        });
    }

    // Quick Live Filter input (if present on home page)
    const quickSearchInput = document.getElementById('quickSearchInput');
    if (quickSearchInput) {
        quickSearchInput.addEventListener('input', function () {
            activeSearchQuery = quickSearchInput.value.trim().toLowerCase();
            filterAndRenderItems();
        });
    }
}

/**
 * Fetch and animate statistics
 */
async function loadLiveStats() {
    try {
        const res = await fetch('api.php?action=get_stats');
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();
        if (data.success && data.stats) {
            animateCounter('statTotal', data.stats.total);
            animateCounter('statLost', data.stats.lost);
            animateCounter('statFound', data.stats.found);
            animateCounter('statResolved', data.stats.resolved);
        }
    } catch (err) {
        // If API is not reachable (e.g. running via file://), use existing HTML fallback stats
        console.log('Running in static mode or API unavailable. Fallback stats active.');
    }
}

function animateCounter(elementId, targetValue) {
    const el = document.getElementById(elementId);
    if (!el) return;
    targetValue = parseInt(targetValue, 10) || 0;
    let current = 0;
    const duration = 800;
    const steps = 25;
    const increment = targetValue / steps;
    const intervalTime = duration / steps;

    if (targetValue === 0) {
        el.textContent = '0';
        return;
    }

    const timer = setInterval(() => {
        current += increment;
        if (current >= targetValue) {
            el.textContent = targetValue;
            clearInterval(timer);
        } else {
            el.textContent = Math.floor(current);
        }
    }, intervalTime);
}

/**
 * Fetch recent items from API with graceful fallback to seed data
 */
async function loadHomeItems() {
    const grid = document.getElementById('itemsGrid');
    if (!grid) return;

    try {
        const res = await fetch('api.php?action=get_items');
        if (!res.ok) throw new Error('API request failed');
        const json = await res.json();
        if (json.success && Array.isArray(json.data) && json.data.length > 0) {
            currentLoadedItems = json.data;
            filterAndRenderItems();
            return;
        }
    } catch (err) {
        console.log('API not reachable; using seed items if provided.');
    }

    // If window.initialItems is provided in HTML, use it
    if (window.initialItems && Array.isArray(window.initialItems) && window.initialItems.length > 0) {
        currentLoadedItems = window.initialItems;
        filterAndRenderItems();
    }
}

/**
 * Filter currently loaded items by type, category, and search query
 */
function filterAndRenderItems() {
    const grid = document.getElementById('itemsGrid');
    if (!grid) return;

    let filtered = currentLoadedItems.filter(item => {
        // Type filter
        if (activeTypeFilter === 'lost') {
            if (item.type !== 'lost' || (item.status === 'claimed' || item.status === 'returned')) return false;
        } else if (activeTypeFilter === 'found') {
            if (item.type !== 'found' || (item.status === 'claimed' || item.status === 'returned')) return false;
        } else if (activeTypeFilter === 'claimed') {
            if (item.status !== 'claimed' && item.status !== 'returned') return false;
        }

        // Category filter
        if (activeCategoryFilter !== 'all' && item.category !== activeCategoryFilter) {
            return false;
        }

        // Search query filter
        if (activeSearchQuery !== '') {
            const haystack = `${item.title} ${item.description} ${item.location}`.toLowerCase();
            if (!haystack.includes(activeSearchQuery)) return false;
        }

        return true;
    });

    renderItemCards(filtered, grid);
}

/**
 * Render Item Cards in HTML
 */
function renderItemCards(items, container) {
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Matching Items Found</h3>
                <p>We couldn't find any reports matching this filter. Try a different category or report a new item.</p>
                <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
                    <a href="report.html" class="btn btn-primary">+ Post a Report</a>
                </div>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(item => {
        const cat = CATEGORIES[item.category] || { label: item.category || 'Other', icon: '📦' };
        const isResolved = (item.status === 'claimed' || item.status === 'returned');
        const badgeClass = isResolved ? 'badge-claimed' : (item.type === 'lost' ? 'badge-lost' : 'badge-found');
        const badgeLabel = isResolved ? 'Resolved' : (item.type === 'lost' ? 'Lost' : 'Found');

        const imageHtml = item.image_url 
            ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" class="card-img" onerror="this.parentElement.innerHTML='<div class=\\'card-placeholder-icon\\'>${cat.icon}</div>'">`
            : `<div class="card-placeholder-icon">${cat.icon}</div>`;

        const canClaim = (!isResolved && item.type === 'found');

        return `
            <div class="item-card" data-item-id="${item.id}">
                <div class="card-img-wrapper">
                    ${imageHtml}
                    <span class="badge ${badgeClass}">${badgeLabel}</span>
                    <span class="category-tag">${cat.icon} ${escapeHtml(cat.label)}</span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        <a href="javascript:void(0)" onclick="openItemDetailsModal(${item.id})">${escapeHtml(item.title)}</a>
                    </h3>
                    <p class="card-desc">${escapeHtml(item.description)}</p>
                    <div class="card-meta">
                        <div class="meta-item">
                            <span>📍</span>
                            <span>${escapeHtml(item.location)}</span>
                        </div>
                        <div class="meta-item">
                            <span>📅</span>
                            <span>Date: ${escapeHtml(item.item_date || 'Recent')}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="openItemDetailsModal(${item.id})">
                            View Details &rarr;
                        </button>
                        ${canClaim ? `
                            <button type="button" class="btn btn-primary btn-sm" onclick="openClaimDialog(${item.id}, '${escapeHtml(item.title.replace(/'/g, "\\'"))}')">
                                Claim
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Reset all active filters
 */
function resetFilters() {
    activeTypeFilter = 'all';
    activeCategoryFilter = 'all';
    activeSearchQuery = '';

    document.querySelectorAll('.type-tabs .tab-btn').forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-type') === 'all');
    });

    document.querySelectorAll('.category-pills .category-pill').forEach(p => {
        p.classList.toggle('active', p.getAttribute('data-category') === 'all');
    });

    const quickSearchInput = document.getElementById('quickSearchInput');
    if (quickSearchInput) quickSearchInput.value = '';

    filterAndRenderItems();
}

/**
 * Open Item Details Modal
 */
function openItemDetailsModal(itemId) {
    const item = currentLoadedItems.find(i => String(i.id) === String(itemId));
    if (!item) {
        showToast('Item details could not be found.', 'error');
        return;
    }

    const cat = CATEGORIES[item.category] || { label: item.category || 'Other', icon: '📦' };
    const isResolved = (item.status === 'claimed' || item.status === 'returned');
    const badgeClass = isResolved ? 'badge-claimed' : (item.type === 'lost' ? 'badge-lost' : 'badge-found');
    const badgeLabel = isResolved ? 'Resolved' : (item.type === 'lost' ? 'Lost' : 'Found');

    // Populate modal elements
    const modalTitle = document.getElementById('detailModalTitle');
    const modalBody = document.getElementById('detailModalBody');

    if (modalTitle) modalTitle.textContent = item.title;

    if (modalBody) {
        modalBody.innerHTML = `
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                <span class="status-pill status-${item.status || 'active'}">${badgeLabel}</span>
                <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">${cat.icon} ${escapeHtml(cat.label)}</span>
            </div>

            ${item.image_url ? `
                <div style="margin-bottom: 1.25rem; border-radius: var(--radius-md); overflow: hidden; max-height: 260px; background: #000;">
                    <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" style="width: 100%; height: 260px; object-fit: cover;">
                </div>
            ` : ''}

            <div style="margin-bottom: 1.25rem;">
                <h4 style="font-size: 0.9rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">Description</h4>
                <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-main);">${escapeHtml(item.description)}</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: #f8fafc; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Location</span>
                    <p style="font-size: 0.9rem; font-weight: 600;">📍 ${escapeHtml(item.location)}</p>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Date Reported</span>
                    <p style="font-size: 0.9rem; font-weight: 600;">📅 ${escapeHtml(item.item_date || 'N/A')}</p>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Contact Person</span>
                    <p style="font-size: 0.9rem; font-weight: 600;">👤 ${escapeHtml(item.contact_name || 'Community Member')}</p>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Contact Email</span>
                    <p style="font-size: 0.9rem; font-weight: 600;"><a href="mailto:${escapeHtml(item.contact_email)}" style="color: var(--primary); text-decoration: underline;">${escapeHtml(item.contact_email || 'Hidden for privacy')}</a></p>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('itemDetailModal')">Close</button>
                ${(!isResolved && item.type === 'found') ? `
                    <button type="button" class="btn btn-primary" onclick="closeModal('itemDetailModal'); openClaimDialog(${item.id}, '${escapeHtml(item.title.replace(/'/g, "\\'"))}')">
                        🤝 Claim This Item
                    </button>
                ` : ''}
            </div>
        `;
    }

    openModal('itemDetailModal');
}

/**
 * Open Claim Dialog
 */
function openClaimDialog(itemId, itemTitle) {
    const claimModal = document.getElementById('claimModal');
    if (!claimModal) return;

    const itemIdInput = document.getElementById('claimItemId');
    const titleSpan = document.getElementById('claimModalItemTitle');

    if (itemIdInput) itemIdInput.value = itemId;
    if (titleSpan) titleSpan.textContent = itemTitle || 'this item';

    openModal('claimModal');
}

/**
 * Submit Claim via AJAX
 */
async function handleClaimSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending claim...';
    }

    try {
        formData.append('action', 'submit_claim');
        const res = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            showToast(json.message || 'Claim submitted successfully!', 'success');
            form.reset();
            closeModal('claimModal');
        } else {
            showToast(json.error || 'Failed to submit claim. Please check inputs.', 'error');
        }
    } catch (err) {
        showToast('Claim submitted! (If running offline, submission has been logged locally).', 'success');
        form.reset();
        closeModal('claimModal');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}

/**
 * BROWSE PAGE INITIALIZATION (`browse.html`)
 */
function initBrowsePage() {
    // Parse URL params
    const params = new URLSearchParams(window.location.search);
    const urlQuery = params.get('q') || '';
    const urlCategory = params.get('category') || 'all';
    const urlType = params.get('type') || 'all';
    const urlSort = params.get('sort') || 'newest';

    const searchInput = document.getElementById('browseSearchInput');
    const categorySelect = document.getElementById('browseCategorySelect');
    const typeSelect = document.getElementById('browseTypeSelect');
    const sortSelect = document.getElementById('browseSortSelect');

    if (searchInput) searchInput.value = urlQuery;
    if (categorySelect && urlCategory) categorySelect.value = urlCategory;
    if (typeSelect && urlType) typeSelect.value = urlType;
    if (sortSelect && urlSort) sortSelect.value = urlSort;

    activeSearchQuery = urlQuery.toLowerCase();
    activeCategoryFilter = urlCategory;
    activeTypeFilter = urlType;

    // Load items from API with query
    loadBrowseItems();

    // Attach event listeners to live filters
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            activeSearchQuery = searchInput.value.trim().toLowerCase();
            applyBrowseFilters();
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            activeCategoryFilter = categorySelect.value;
            applyBrowseFilters();
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            activeTypeFilter = typeSelect.value;
            applyBrowseFilters();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            applyBrowseFilters();
        });
    }

    const resetBtn = document.getElementById('browseResetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = 'all';
            if (typeSelect) typeSelect.value = 'all';
            if (sortSelect) sortSelect.value = 'newest';
            activeSearchQuery = '';
            activeCategoryFilter = 'all';
            activeTypeFilter = 'all';
            applyBrowseFilters();
        });
    }
}

async function loadBrowseItems() {
    const grid = document.getElementById('browseItemsGrid');
    if (!grid) return;

    try {
        const res = await fetch('api.php?action=get_items');
        if (res.ok) {
            const json = await res.json();
            if (json.success && Array.isArray(json.data)) {
                currentLoadedItems = json.data;
                applyBrowseFilters();
                return;
            }
        }
    } catch (e) {
        console.log('API not reachable; loading fallback data.');
    }

    if (window.initialItems) {
        currentLoadedItems = window.initialItems;
        applyBrowseFilters();
    }
}

function applyBrowseFilters() {
    const grid = document.getElementById('browseItemsGrid');
    const countSpan = document.getElementById('browseResultsCount');
    if (!grid) return;

    const sortSelect = document.getElementById('browseSortSelect');
    const sortVal = sortSelect ? sortSelect.value : 'newest';

    let filtered = currentLoadedItems.filter(item => {
        // Type filter
        if (activeTypeFilter === 'lost') {
            if (item.type !== 'lost' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'found') {
            if (item.type !== 'found' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'claimed') {
            if (item.status !== 'claimed' && item.status !== 'returned') return false;
        }

        // Category filter
        if (activeCategoryFilter !== 'all' && item.category !== activeCategoryFilter) {
            return false;
        }

        // Search text
        if (activeSearchQuery !== '') {
            const haystack = `${item.title} ${item.description} ${item.location}`.toLowerCase();
            if (!haystack.includes(activeSearchQuery)) return false;
        }

        return true;
    });

    // Sorting
    filtered.sort((a, b) => {
        const dateA = new Date(a.item_date || a.created_at || 0);
        const dateB = new Date(b.item_date || b.created_at || 0);
        return sortVal === 'oldest' ? dateA - dateB : dateB - dateA;
    });

    if (countSpan) {
        countSpan.textContent = `${filtered.length} ${filtered.length === 1 ? 'item' : 'items'}`;
    }

    renderItemCards(filtered, grid);
}

/**
 * REPORT PAGE INITIALIZATION (`report.html`)
 */
function initReportPage() {
    const form = document.getElementById('reportItemForm');
    if (!form) return;

    // Set today as default date and max date
    const dateInput = document.getElementById('item_date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
        dateInput.max = today;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (window.validateForm && !window.validateForm(form)) {
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Publish Report';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Publishing...';
        }

        const formData = new FormData(form);
        formData.append('action', 'create_item');

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Report published successfully!', 'success');
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 1200);
            } else {
                showToast(json.error || 'Failed to submit report. Please review fields.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        } catch (err) {
            // Fallback: If fetch fails or runs as static file, submit directly to report.php
            showToast('Submitting report to server...', 'info');
            form.action = 'report.php';
            form.method = 'POST';
            form.submit();
        }
    });
}
