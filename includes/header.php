<?php
/**
 * Page Header Template
 * Include this at the top of every page
 */

// Load configuration and dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

// Get current user
$currentUser = Auth::user();
$isLoggedIn = Auth::check();
$isAdmin = Auth::isAdmin();

// Page title (can be overridden before including header)
$pageTitle = $pageTitle ?? 'Bountiful Harvest';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Play fun games and earn rewards!">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'friv-orange': '#FF6B35',
                        'friv-yellow': '#FFD93D',
                        'friv-green': '#6BCB77',
                        'friv-blue': '#4D96FF',
                        'friv-purple': '#9B59B6',
                        'friv-pink': '#FF6B9D',
                        'friv-red': '#FF4757',
                        'friv-cyan': '#00D2D3',
                    },
                    fontFamily: {
                        'game': ['Fredoka One', 'cursive'],
                        'body': ['Nunito', 'sans-serif'],
                    },
                    animation: {
                        'bounce-slow': 'bounce 2s infinite',
                        'wiggle': 'wiggle 0.5s ease-in-out infinite',
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        wiggle: {
                            '0%, 100%': { transform: 'rotate(-3deg)' },
                            '50%': { transform: 'rotate(3deg)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
        .font-game {
            font-family: 'Fredoka One', cursive;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #FF6B35, #FFD93D);
            border-radius: 5px;
        }
        
        /* Game card hover effect */
        .game-card {
            transition: all 0.3s ease;
        }
        .game-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #FF6B35, #FFD93D, #6BCB77, #4D96FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body class="bg-gradient-to-br from-indigo-100 via-purple-50 to-pink-100 min-h-screen font-body <?= e($bodyClass) ?>">
    
    <?php include __DIR__ . '/navbar.php'; ?>
    
    <!-- Flash Messages -->
    <?php if ($flashes = getFlashes()): ?>
    <div class="fixed top-20 right-4 z-50 space-y-2" id="flash-messages">
        <?php foreach ($flashes as $type => $message): ?>
            <?php
            $bgColor = match($type) {
                'success' => 'bg-green-500',
                'error' => 'bg-red-500',
                'warning' => 'bg-yellow-500',
                'info' => 'bg-blue-500',
                default => 'bg-gray-500'
            };
            ?>
            <div class="<?= $bgColor ?> text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-slide-in">
                <span><?= e($message) ?></span>
                <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        // Auto-hide flash messages after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('#flash-messages > div').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateX(100%)';
                setTimeout(() => el.remove(), 300);
            });
        }, 5000);
    </script>
    <?php endif; ?>
    
    <!-- Main Content Wrapper -->
    <main class="pt-16">