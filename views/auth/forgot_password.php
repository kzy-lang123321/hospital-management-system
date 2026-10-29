<?php
/**
 * Hospital Management System (HMS) - Password Recovery & Account Help
 */
require_once __DIR__ . '/../../config/app.php';
$flash = get_flash();
$hProfile = hospital_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-navy-900 to-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white text-2xl shadow-xl shadow-teal-500/20 mb-3">
                <i class="fa-solid fa-key"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Account Recovery</h1>
            <p class="text-xs text-teal-400 font-medium tracking-wide uppercase mt-0.5">Password Assistance & Security Verification</p>
        </div>

        <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 shadow-2xl">
            <?php if ($flash): ?>
            <div class="mb-4 p-3 rounded-lg text-xs <?= $flash['type'] === 'danger' ? 'bg-rose-500/20 border border-rose-500/50 text-rose-200' : 'bg-emerald-500/20 border border-emerald-500/50 text-emerald-200' ?>">
                <i class="fa-solid fa-circle-exclamation mr-1.5"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
            <?php endif; ?>

            <p class="text-xs text-slate-300 mb-5 leading-relaxed">
                Enter your registered hospital username or verified email address. If the account matches our records, a secure password reset request will be generated and logged for administrator or developer verification.
            </p>

            <form action="index.php?action=forgot_password_request" method="POST" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Registered Email or Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="text" 
                            name="identity" 
                            required 
                            placeholder="e.g. khanabbasi5642@gmail.com" 
                            class="w-full pl-9 pr-4 py-2.5 bg-slate-900/60 border border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 rounded-lg text-sm text-white placeholder-slate-500 outline-none transition-all"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-500 text-white font-semibold text-sm rounded-lg shadow-lg shadow-teal-700/30 transition-all flex items-center justify-center gap-2"
                >
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Submit Reset Request</span>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-800 text-center">
                <a href="index.php?page=login" class="text-xs text-slate-400 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Sign In
                </a>
            </div>
        </div>
    </div>

</body>
</html>
