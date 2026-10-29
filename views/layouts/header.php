<?php
/**
 * Hospital Management System (HMS) - Layout Header
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Auth.php';

$currentUser = Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';
$hProfile = hospital_profile();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean_input($pageTitle) ?> — <?= htmlspecialchars($hProfile['hospital_name']) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        medical: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        },
                        navy: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Unified Form Controls */
        .form-input {
            display: block;
            width: 100%;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            padding: 0.45rem 0.75rem;
            background-color: #ffffff;
            color: #1e293b;
            font-size: 0.75rem;
            line-height: 1.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            outline: none;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }
        select.form-input {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.65rem center;
            background-repeat: no-repeat;
            background-size: 1.15em 1.15em;
            padding-right: 2.25rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        select.form-input option {
            color: #1e293b;
            background-color: #ffffff;
            padding: 6px 10px;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.9rem;
            border-radius: 0.5rem;
            background-color: #0d9488;
            color: #ffffff;
            font-weight: 600;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary:hover {
            background-color: #0f766e;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.9rem;
            border-radius: 0.5rem;
            background-color: #ffffff;
            color: #334155;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; }
        }

        /* Mobile Touch & Table Usability */
        * {
            -webkit-tap-highlight-color: transparent;
        }
        .custom-scrollbar, .overflow-x-auto, .overflow-y-auto {
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 768px) {
            .form-input {
                font-size: 16px !important; /* Prevents auto-zoom on iOS inputs */
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex h-screen overflow-hidden">
