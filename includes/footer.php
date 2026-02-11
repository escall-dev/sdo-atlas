            </div><!-- .content-wrapper -->
            
            <footer class="admin-footer">
                <p>&copy; <?php echo date('Y'); ?> SDO ATLAS - Schools Division Office of San Pedro City<br>
                Authority to Travel and Locator Approval System</p>
                <p>Department of Education</p>
            </footer>
        </main>
    </div>

    <script src="<?php echo ADMIN_URL; ?>/assets/js/admin.js"></script>
    <script>
    // Store token for AJAX requests
    const ATLAS_TOKEN = '<?php echo $currentToken ?? ''; ?>';
    
    // Helper to add token to URLs
    function addToken(url) {
        if (!ATLAS_TOKEN) return url;
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'token=' + ATLAS_TOKEN;
    }
    
    // Override fetch to add token header
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        options.headers = options.headers || {};
        if (ATLAS_TOKEN) {
            options.headers['X-Auth-Token'] = ATLAS_TOKEN;
        }
        return originalFetch(url, options);
    };
    
    </script>
</body>
</html>
