<?php
/**
 * Global Footer Component
 */
$categories = get_categories();
?>
    <!-- Global Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-col">
                <div class="footer-logo">
                    <span class="logo-icon">F</span>
                    <strong>FindIt Portal</strong>
                </div>
                <p class="footer-tagline">
                    An open community platform dedicated to helping people recover lost items, report discovered belongings, and connect securely.
                </p>
            </div>

            <div class="footer-col">
                <h4>Quick Navigation</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home Feed</a></li>
                    <li><a href="browse.php">Search & Browse Catalog</a></li>
                    <li><a href="report.php">Report Lost/Found Item</a></li>
                    <li><a href="dashboard.php">User Dashboard</a></li>
                    <li><a href="admin.php">Admin Management</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Top Categories</h4>
                <ul class="footer-links">
                    <?php 
                    $counter = 0;
                    foreach ($categories as $slug => $cat): 
                        if (++$counter > 5) break;
                    ?>
                        <li>
                            <a href="browse.php?category=<?= urlencode($slug) ?>">
                                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Safety & Security</h4>
                <p class="footer-text">
                    Always arrange exchanges in public, well-lit spaces like campus security desks or police station lobbies. Verify ownership before handing over valuables.
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-container">
                <p>&copy; <?= date('Y') ?> FindIt Lost &amp; Found Portal. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="browse.php?type=lost">Lost Items</a>
                    <a href="browse.php?type=found">Found Items</a>
                    <a href="browse.php?type=claimed">Resolved</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global App & Validation Script -->
    <script src="js/validation.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
