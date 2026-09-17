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
        setTimeout(() => {
            const toast = document.getElementById('flashToast');
            if (toast) {
                toast.classList.add('opacity-0', 'translate-y-4');
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <!-- Global Keyboard Shortcut: Ctrl + / focuses Universal Search -->
    <script>
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
</body>
</html>
