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
    
    // Sidebar toggle
    (function() {
        const sidebar = document.getElementById('sidebar');
        const adminLayout = document.querySelector('.admin-layout');
        const desktopToggle = document.getElementById('desktopSidebarToggle');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        
        if (!sidebar) return;
        
        // Restore sidebar state
        const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
        if (savedState && window.innerWidth >= 992) {
            sidebar.classList.add('collapsed');
            if (adminLayout) adminLayout.classList.add('sidebar-collapsed');
        }
        
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const isCollapsed = sidebar.classList.toggle('collapsed');
            if (adminLayout) adminLayout.classList.toggle('sidebar-collapsed', isCollapsed);
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        }
        
        if (desktopToggle) {
            desktopToggle.addEventListener('click', toggleSidebar);
        }
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('open');
            });
        }
    })();
    </script>
</body>
</html>
