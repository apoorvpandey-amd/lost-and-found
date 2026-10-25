<?php
/**
 * FindIt - Lost & Found Web Portal
 * Primary Entry Point: Serves the modern HTML5 frontend (index.html).
 * Keeps PHP strictly for backend services without overloading the frontend.
 */
if (file_exists(__DIR__ . '/index.html')) {
    readfile(__DIR__ . '/index.html');
} else {
    echo "<h1>FindIt Portal</h1><p>index.html not found.</p>";
}
exit;
