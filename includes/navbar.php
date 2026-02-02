<?php
/**
 * Navigation Bar Component
 * Responsive navbar with Friv-style design
 */
?>
<nav class="fixed top-0 left-0 right-0 z-40 bg-white/90 backdrop-blur-md shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            
            <!-- Logo -->
            <a href="<?= baseUrl() ?>" class="flex items-center gap-2 group">
                <div class="w-10 h-10 bg-gradient-to-br from-friv-orange to-friv-yellow rounded-xl flex items-center justify-center transform group-hover:rotate-12 transition-transform">
                    <span class="text-white text-xl">🎮</span>
                </div>
                <span class="font-game text-2xl gradient-text hidden sm:block"><?= APP_NAME ?></span>
            </a>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-6">
                <a href="<?= baseUrl('/games/') ?>" class="text-gray-700 hover:text-friv-orange font-semibold transition-colors flex items-center gap-1">
                    <span>🎯</span> Games
                </a>
                <a href="<?= baseUrl('/leaderboard/') ?>" class="text-gray-700 hover:text-friv-orange font-semibold transition-colors flex items-center gap-1">
                    <span>🏆</span> Leaderboard
                </a>
                <a href="<?= baseUrl('/rewards/') ?>" class="text-gray-700 hover:text-friv-orange font-semibold transition-colors flex items-center gap-1">
                    <span>🎁</span> Rewards
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <!-- Wallet Balance -->
                    <div class="bg-gradient-to-r from-friv-yellow to-friv-orange text-white px-4 py-2 rounded-full font-bold flex items-center gap-2">
                        <span>💰</span>
                        <span id="navbar-balance"><?= formatNumber($currentUser['wallet_balance'] ?? 0) ?></span>
                    </div>
                    
                    <!-- User Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 rounded-full pl-1 pr-4 py-1 transition-colors">
                            <img src="<?= asset('images/avatars/' . ($currentUser['avatar'] ?? 'default-avatar.png')) ?>" 
                                 alt="Avatar" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-friv-blue">
                            <span class="font-semibold text-gray-700"><?= e($currentUser['username']) ?></span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                            <a href="<?= baseUrl('/dashboard.php') ?>" class="block px-4 py-2 text-gray-700 hover:bg-friv-blue/10 hover:text-friv-blue transition-colors">
                                📊 Dashboard
                            </a>
                            <a href="<?= baseUrl('/profile/') ?>" class="block px-4 py-2 text-gray-700 hover:bg-friv-blue/10 hover:text-friv-blue transition-colors">
                                👤 My Profile
                            </a>
                            <a href="<?= baseUrl('/profile/wallet.php') ?>" class="block px-4 py-2 text-gray-700 hover:bg-friv-blue/10 hover:text-friv-blue transition-colors">
                                💰 Wallet
                            </a>
                            <?php if ($isAdmin): ?>
                                <hr class="my-2 border-gray-100">
                                <a href="<?= baseUrl('/admin/') ?>" class="block px-4 py-2 text-friv-purple hover:bg-friv-purple/10 transition-colors font-semibold">
                                    ⚙️ Admin Panel
                                </a>
                            <?php endif; ?>
                            <hr class="my-2 border-gray-100">
                            <a href="<?= baseUrl('/auth/logout.php') ?>" class="block px-4 py-2 text-red-500 hover:bg-red-50 transition-colors">
                                🚪 Logout
                            </a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <a href="<?= baseUrl('/auth/login.php') ?>" class="text-gray-700 hover:text-friv-blue font-semibold transition-colors">
                        Login
                    </a>
                    <a href="<?= baseUrl('/auth/register.php') ?>" class="bg-gradient-to-r from-friv-blue to-friv-purple text-white px-6 py-2 rounded-full font-bold hover:shadow-lg hover:scale-105 transition-all">
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Button -->
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" id="mobile-menu-btn">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Mobile Menu -->
    <div class="md:hidden hidden bg-white border-t border-gray-100" id="mobile-menu">
        <div class="px-4 py-4 space-y-3">
            <?php if ($isLoggedIn): ?>
                <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <img src="<?= asset('images/avatars/' . ($currentUser['avatar'] ?? 'default-avatar.png')) ?>" 
                         alt="Avatar" 
                         class="w-12 h-12 rounded-full object-cover border-2 border-friv-blue">
                    <div>
                        <p class="font-bold text-gray-800"><?= e($currentUser['username']) ?></p>
                        <p class="text-sm text-friv-orange font-semibold">💰 <?= formatNumber($currentUser['wallet_balance'] ?? 0) ?> points</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <a href="<?= baseUrl('/games/') ?>" class="block py-2 text-gray-700 font-semibold">🎯 Games</a>
            <a href="<?= baseUrl('/leaderboard/') ?>" class="block py-2 text-gray-700 font-semibold">🏆 Leaderboard</a>
            <a href="<?= baseUrl('/rewards/') ?>" class="block py-2 text-gray-700 font-semibold">🎁 Rewards</a>
            
            <?php if ($isLoggedIn): ?>
                <hr class="border-gray-100">
                <a href="<?= baseUrl('/dashboard.php') ?>" class="block py-2 text-gray-700 font-semibold">📊 Dashboard</a>
                <a href="<?= baseUrl('/profile/') ?>" class="block py-2 text-gray-700 font-semibold">👤 My Profile</a>
                <?php if ($isAdmin): ?>
                    <a href="<?= baseUrl('/admin/') ?>" class="block py-2 text-friv-purple font-bold">⚙️ Admin Panel</a>
                <?php endif; ?>
                <hr class="border-gray-100">
                <a href="<?= baseUrl('/auth/logout.php') ?>" class="block py-2 text-red-500 font-semibold">🚪 Logout</a>
            <?php else: ?>
                <hr class="border-gray-100">
                <a href="<?= baseUrl('/auth/login.php') ?>" class="block py-2 text-friv-blue font-semibold">Login</a>
                <a href="<?= baseUrl('/auth/register.php') ?>" class="block py-2 text-white bg-gradient-to-r from-friv-blue to-friv-purple rounded-lg text-center font-bold">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Alpine.js for dropdowns -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
</script>