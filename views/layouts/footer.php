<?php
/**
 * Hospital Management System (HMS) - Layout Footer
 */
$flash = get_flash();
?>
    <!-- Flash Notification Toast -->
    <?php if ($flash): ?>
    <div id="flashToast" class="fixed bottom-5 right-5 z-50 max-w-sm w-full bg-white shadow-xl rounded-xl border border-slate-200 p-4 transition-all duration-300 transform translate-y-0 flex items-start gap-3">
        <?php if ($flash['type'] === 'success'): ?>
            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        <?php elseif ($flash['type'] === 'danger'): ?>
            <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        <?php elseif ($flash['type'] === 'warning'): ?>
            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
        <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-info"></i>
            </div>
        <?php endif; ?>
        <div class="flex-1 text-sm">
            <h4 class="font-semibold capitalize text-slate-800"><?= htmlspecialchars($flash['type']) ?></h4>
            <p class="text-xs text-slate-600 mt-0.5"><?= htmlspecialchars($flash['message']) ?></p>
        </div>
        <button onclick="document.getElementById('flashToast').remove()" class="text-slate-400 hover:text-slate-600 text-xs">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <script>
        <?php if ($flash['type'] === 'success' || $flash['type'] === 'info'): ?>
        setTimeout(() => {
            const toast = document.getElementById('flashToast');
            if (toast) {
                toast.classList.add('opacity-0', 'translate-y-4');
                setTimeout(() => toast.remove(), 300);
            }
        }, 8000);
        <?php endif; ?>
    </script>
    <?php endif; ?>

    <!-- Mobile Drawer & Global Handlers -->
    <script>
        // Mobile Sidebar Drawer Management
        (function() {
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const closeBtn = document.getElementById('closeSidebarBtn');
            const sidebar = document.getElementById('mainSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            function openMobileNav() {
                if (!sidebar) return;
                sidebar.classList.remove('-translate-x-full');
                if (backdrop) backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeMobileNav() {
                if (!sidebar) return;
                sidebar.classList.add('-translate-x-full');
                if (backdrop) backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            if (mobileBtn) mobileBtn.addEventListener('click', openMobileNav);
            if (closeBtn) closeBtn.addEventListener('click', closeMobileNav);
            if (backdrop) backdrop.addEventListener('click', closeMobileNav);

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeMobileNav();
            });

            // Close drawer when navigating to a link on mobile
            if (sidebar) {
                sidebar.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            closeMobileNav();
                        }
                    });
                });
            }
        })();

        // Global Keyboard Shortcut: Ctrl + / focuses Universal Search
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                const searchInput = document.getElementById('globalPatientSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    </script>
<dialog id="workspaceDialog" class="workspace-dialog no-print" aria-labelledby="workspaceDialogTitle">
    <div class="workspace-dialog-head"><div><h2 id="workspaceDialogTitle">Find your workspace</h2><p class="workspace-caption">Jump straight to the task you need.</p></div><button type="button" id="workspaceClose" class="workspace-tool" aria-label="Close workspace finder">&times;</button></div>
    <div class="workspace-dialog-body"><label for="workspaceQuery" class="sr-only">Filter workspaces</label><input type="search" id="workspaceQuery" placeholder="Type a workspace name..." autocomplete="off"><p id="workspaceCount" class="workspace-caption" role="status"></p><nav id="workspaceResults" aria-label="Workspace search results"></nav><p class="workspace-caption">Use arrow keys to browse, Enter to open, and Esc to close.</p></div>
</dialog>
<script><?php readfile(__DIR__ . '/../../assets/workspace.js'); ?></script>
</body>
</html>
