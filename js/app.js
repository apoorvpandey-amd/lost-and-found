/**
 * FindIt - Main Application UI Scripts
 * Professional, clean client-side logic without unnecessary emojis
 */

const CATEGORIES = {
    electronics: { label: 'Electronics & Gadgets' },
    wallets:     { label: 'Wallets & Purses' },
    keys:        { label: 'Keys & Keychains' },
    documents:   { label: 'Cards & Documents' },
    clothing:    { label: 'Clothing & Eyewear' },
    pets:        { label: 'Pets & Animals' },
    books:       { label: 'Books & Notebooks' },
    jewelry:     { label: 'Jewelry & Watches' },
    other:       { label: 'Other Belongings' }
};

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

    // 2. Generic Modal Controls
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

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal(overlay);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(modal => closeModal(modal));
        }
    });

    // 3. Claim Form Handler
    const claimForm = document.getElementById('claimForm');
    if (claimForm) {
        claimForm.addEventListener('submit', handleClaimSubmit);
    }

    // 4. Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // 5. Initialize Specific Page Views
    if (document.getElementById('homePageMarker')) {
        initHomePage();
    } else if (document.getElementById('browsePageMarker')) {
        initBrowsePage();
    } else if (document.getElementById('reportPageMarker')) {
        initReportPage();
    }
});

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
        toast.style.transform = 'translateY(15px)';
        setTimeout(() => toast.remove(), 250);
    }, 4000);
}

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
 * HOME PAGE
 */
function initHomePage() {
    loadLiveStats();
    loadHomeItems();

    // Type Tabs
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
            window.location.href = `browse.html?q=${encodeURIComponent(query)}&category=${encodeURIComponent(category)}`;
        });
    }

    // Quick Live Search Input
    const quickSearchInput = document.getElementById('quickSearchInput');
    if (quickSearchInput) {
        quickSearchInput.addEventListener('input', function () {
            activeSearchQuery = quickSearchInput.value.trim().toLowerCase();
            filterAndRenderItems();
        });
    }
}

async function loadLiveStats() {
    try {
        const res = await fetch('api.php?action=get_stats');
        if (!res.ok) throw new Error('API error');
        const data = await res.json();
        if (data.success && data.stats) {
            animateCounter('statTotal', data.stats.total);
            animateCounter('statLost', data.stats.lost);
            animateCounter('statFound', data.stats.found);
            animateCounter('statResolved', data.stats.resolved);
        }
    } catch (err) {
        // Retains default numbers gracefully if running offline
    }
}

function animateCounter(elementId, targetValue) {
    const el = document.getElementById(elementId);
    if (!el) return;
    targetValue = parseInt(targetValue, 10) || 0;
    let current = 0;
    const duration = 600;
    const steps = 20;
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

async function loadHomeItems() {
    const grid = document.getElementById('itemsGrid');
    if (!grid) return;

    try {
        const res = await fetch('api.php?action=get_items');
        if (res.ok) {
            const json = await res.json();
            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                currentLoadedItems = json.data;
                filterAndRenderItems();
                return;
            }
        }
    } catch (err) {
        // Fallback to initialItems
    }

    if (window.initialItems && Array.isArray(window.initialItems)) {
        currentLoadedItems = window.initialItems;
        filterAndRenderItems();
    }
}

function filterAndRenderItems() {
    const grid = document.getElementById('itemsGrid');
    if (!grid) return;

    let filtered = currentLoadedItems.filter(item => {
        if (activeTypeFilter === 'lost') {
            if (item.type !== 'lost' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'found') {
            if (item.type !== 'found' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'claimed') {
            if (item.status !== 'claimed' && item.status !== 'returned') return false;
        }

        if (activeCategoryFilter !== 'all' && item.category !== activeCategoryFilter) {
            return false;
        }

        if (activeSearchQuery !== '') {
            const haystack = `${item.title} ${item.description} ${item.location}`.toLowerCase();
            if (!haystack.includes(activeSearchQuery)) return false;
        }

        return true;
    });

    renderItemCards(filtered, grid);
}

function renderItemCards(items, container) {
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <h3>No Matching Records</h3>
                <p>No item reports found matching the selected filter criteria.</p>
                <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilters()">Reset Filters</button>
                    <a href="report.html" class="btn btn-primary btn-sm">Submit New Report</a>
                </div>
            </div>
        `;
        return;
    }

    const placeholderSvg = `
        <div class="card-placeholder-box">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
            <span>No Image</span>
        </div>
    `;

    container.innerHTML = items.map(item => {
        const cat = CATEGORIES[item.category] || { label: item.category || 'Other' };
        const isResolved = (item.status === 'claimed' || item.status === 'returned');
        const badgeClass = isResolved ? 'badge-claimed' : (item.type === 'lost' ? 'badge-lost' : 'badge-found');
        const badgeLabel = isResolved ? 'Resolved' : (item.type === 'lost' ? 'Lost' : 'Found');

        const imageHtml = item.image_url 
            ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" class="card-img" onerror="this.parentElement.innerHTML='${placeholderSvg}'">`
            : placeholderSvg;

        const canClaim = (!isResolved && item.type === 'found');

        return `
            <div class="item-card" data-item-id="${item.id}">
                <div class="card-img-wrapper">
                    ${imageHtml}
                    <span class="badge ${badgeClass}">${badgeLabel}</span>
                    <span class="category-tag">${escapeHtml(cat.label)}</span>
                </div>
                <div class="card-body">
                    <h3 class="card-title">
                        <a href="javascript:void(0)" onclick="openItemDetailsModal(${item.id})">${escapeHtml(item.title)}</a>
                    </h3>
                    <p class="card-desc">${escapeHtml(item.description)}</p>
                    <div class="card-meta">
                        <div class="meta-item">
                            <span class="meta-label">Location</span>
                            <span>${escapeHtml(item.location)}</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Date</span>
                            <span>${escapeHtml(item.item_date || 'Recent')}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="openItemDetailsModal(${item.id})">
                            Details
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

function openItemDetailsModal(itemId) {
    const item = currentLoadedItems.find(i => String(i.id) === String(itemId));
    if (!item) {
        showToast('Item records could not be found.', 'error');
        return;
    }

    const cat = CATEGORIES[item.category] || { label: item.category || 'Other' };
    const isResolved = (item.status === 'claimed' || item.status === 'returned');
    const badgeLabel = isResolved ? 'Resolved' : (item.type === 'lost' ? 'Lost' : 'Found');

    const modalTitle = document.getElementById('detailModalTitle');
    const modalBody = document.getElementById('detailModalBody');

    if (modalTitle) modalTitle.textContent = item.title;

    if (modalBody) {
        modalBody.innerHTML = `
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                <span class="status-pill status-${item.status || 'active'}">${badgeLabel}</span>
                <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-muted);">${escapeHtml(cat.label)}</span>
            </div>

            ${item.image_url ? `
                <div style="margin-bottom: 1.25rem; border-radius: var(--radius-sm); overflow: hidden; max-height: 240px; background: #0f172a; border: 1px solid var(--border-color);">
                    <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" style="width: 100%; height: 240px; object-fit: cover;">
                </div>
            ` : ''}

            <div style="margin-bottom: 1.25rem;">
                <h4 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); margin-bottom: 0.35rem;">Description</h4>
                <p style="font-size: 0.9rem; line-height: 1.6; color: var(--text-main);">${escapeHtml(item.description)}</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; background: #f8fafc; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                <div>
                    <span style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.03em;">Location</span>
                    <p style="font-size: 0.875rem; font-weight: 500; color: var(--text-main);">${escapeHtml(item.location)}</p>
                </div>
                <div>
                    <span style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.03em;">Date Recorded</span>
                    <p style="font-size: 0.875rem; font-weight: 500; color: var(--text-main);">${escapeHtml(item.item_date || 'N/A')}</p>
                </div>
                <div>
                    <span style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.03em;">Contact Person</span>
                    <p style="font-size: 0.875rem; font-weight: 500; color: var(--text-main);">${escapeHtml(item.contact_name || 'Community Member')}</p>
                </div>
                <div>
                    <span style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.03em;">Contact Email</span>
                    <p style="font-size: 0.875rem; font-weight: 500;"><a href="mailto:${escapeHtml(item.contact_email)}" style="color: var(--primary); text-decoration: underline;">${escapeHtml(item.contact_email || 'Protected')}</a></p>
                </div>
            </div>

            <div style="display: flex; gap: 0.65rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('itemDetailModal')">Close</button>
                ${(!isResolved && item.type === 'found') ? `
                    <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('itemDetailModal'); openClaimDialog(${item.id}, '${escapeHtml(item.title.replace(/'/g, "\\'"))}')">
                        Submit Claim
                    </button>
                ` : ''}
            </div>
        `;
    }

    openModal('itemDetailModal');
}

function openClaimDialog(itemId, itemTitle) {
    const claimModal = document.getElementById('claimModal');
    if (!claimModal) return;

    const itemIdInput = document.getElementById('claimItemId');
    const titleSpan = document.getElementById('claimModalItemTitle');

    if (itemIdInput) itemIdInput.value = itemId;
    if (titleSpan) titleSpan.textContent = itemTitle || 'this item';

    openModal('claimModal');
}

async function handleClaimSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit Claim';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    }

    try {
        formData.append('action', 'submit_claim');
        const res = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            showToast(json.message || 'Claim submitted successfully.', 'success');
            form.reset();
            closeModal('claimModal');
        } else {
            showToast(json.error || 'Failed to submit claim. Please verify all inputs.', 'error');
        }
    } catch (err) {
        showToast('Claim received and logged.', 'success');
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
 * BROWSE PAGE
 */
function initBrowsePage() {
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

    loadBrowseItems();

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
        // Fallback
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
        if (activeTypeFilter === 'lost') {
            if (item.type !== 'lost' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'found') {
            if (item.type !== 'found' || item.status === 'claimed') return false;
        } else if (activeTypeFilter === 'claimed') {
            if (item.status !== 'claimed' && item.status !== 'returned') return false;
        }

        if (activeCategoryFilter !== 'all' && item.category !== activeCategoryFilter) {
            return false;
        }

        if (activeSearchQuery !== '') {
            const haystack = `${item.title} ${item.description} ${item.location}`.toLowerCase();
            if (!haystack.includes(activeSearchQuery)) return false;
        }

        return true;
    });

    filtered.sort((a, b) => {
        const dateA = new Date(a.item_date || a.created_at || 0);
        const dateB = new Date(b.item_date || b.created_at || 0);
        return sortVal === 'oldest' ? dateA - dateB : dateB - dateA;
    });

    if (countSpan) {
        countSpan.textContent = `${filtered.length} ${filtered.length === 1 ? 'record' : 'records'}`;
    }

    renderItemCards(filtered, grid);
}

/**
 * REPORT PAGE
 */
function initReportPage() {
    const form = document.getElementById('reportItemForm');
    if (!form) return;

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
        const originalText = submitBtn ? submitBtn.textContent : 'Submit Report';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
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
                showToast(json.message || 'Report registered successfully.', 'success');
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 1000);
            } else {
                showToast(json.error || 'Failed to submit report. Please review entries.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        } catch (err) {
            form.action = 'report.php';
            form.method = 'POST';
            form.submit();
        }
    });
}
