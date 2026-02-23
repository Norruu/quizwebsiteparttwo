<?php
/**
 * Page Footer Template
 */
?>
    </main>
    
    <footer class="bg-gray-900 text-white mt-16">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-friv-orange to-friv-yellow rounded-xl flex items-center justify-center">
                            <span class="text-white text-xl">🎮</span>
                        </div>
                        <span class="font-game text-2xl text-white"><?= APP_NAME ?></span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        Play fun games, earn points, and redeem amazing rewards! Your ultimate gaming destination.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="<?= baseUrl('/games/') ?>" class="text-gray-400 hover:text-friv-yellow transition-colors">All Games</a></li>
                        <li><a href="<?= baseUrl('/leaderboard/') ?>" class="text-gray-400 hover:text-friv-yellow transition-colors">Leaderboard</a></li>
                        <li><a href="<?= baseUrl('/rewards/') ?>" class="text-gray-400 hover:text-friv-yellow transition-colors">Rewards</a></li>
                    </ul>
                </div>
                
                <div>
                   <h4 class="font-bold text-lg mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-friv-yellow transition-colors">FAQ</a></li>
                        <li><a href="<?= baseUrl('/about.php') ?>" class="text-gray-400 hover:text-friv-yellow transition-colors">About Us</a></li>
                        <li><a href="<?= baseUrl('/about.php') ?>" class="inline-block mt-1 bg-friv-yellow text-gray-900 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-yellow-400 transition">Contact Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-friv-yellow transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-friv-yellow transition-colors">Privacy Policy</a></li>
                    </ul>
                                    </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-4">Platform Stats</h4>
                    <div class="space-y-3">
                        <?php
                        $stats = [
                            'games' => Database::fetch("SELECT COUNT(*) as count FROM games WHERE status = 'active'")['count'] ?? 0,
                            'players' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE role = 'player'")['count'] ?? 0,
                            'plays' => Database::fetch("SELECT COUNT(*) as count FROM scores")['count'] ?? 0,
                        ];
                        ?>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🎮</span>
                            <div>
                                <p class="font-bold text-xl text-friv-yellow"><?= formatNumber($stats['games']) ?></p>
                                <p class="text-gray-400 text-sm">Games</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">👥</span>
                            <div>
                                <p class="font-bold text-xl text-friv-green"><?= formatNumber($stats['players']) ?></p>
                                <p class="text-gray-400 text-sm">Players</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🏆</span>
                            <div>
                                <p class="font-bold text-xl text-friv-blue"><?= formatNumber($stats['plays']) ?></p>
                                <p class="text-gray-400 text-sm">Games Played</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="border-gray-700 my-8">
            
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <p class="text-gray-400 text-sm">
                    © <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                </p>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 text-sm">Made with ❤️ for gamers</span>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        const SITE_URL = "<?= baseUrl('/') ?>";
    </script>

    <script src="<?= asset('js/utils.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>