<?php
/**
 * Reusable Game Card Component
 * 
 * Usage: 
 * $game = ['id' => 1, 'title' => 'Game', ...];
 * $color = 'bg-gradient-to-br from-friv-orange to-yellow-400';
 * include 'templates/game-card.php';
 */

$game = $game ?? [];
$color = $color ?? 'bg-gradient-to-br from-friv-blue to-cyan-400';
$size = $size ?? 'normal'; // 'small', 'normal', 'large'

$sizeClasses = [
    'small' => 'w-32',
    'normal' => '',
    'large' => 'md:col-span-2 md:row-span-2',
];
?>

<a href="<?= baseUrl('/games/play.php?game=' . ($game['slug'] ?? '')) ?>" 
   class="game-card group <?= $sizeClasses[$size] ?>">
    <div class="<?= $color ?> rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all h-full">
        <!-- Thumbnail -->
        <div class="relative aspect-square overflow-hidden">
            <img src="<?= asset('images/games/' . ($game['thumbnail'] ?? 'default-game.png')) ?>" 
                 alt="<?= e($game['title'] ?? 'Game') ?>"
                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                 loading="lazy"
                 onerror="this.src='<?= asset('images/games/default-game.png') ?>'">
            
            <!-- Hover Overlay -->
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <span class="bg-white text-gray-900 font-bold px-6 py-3 rounded-full transform scale-0 group-hover:scale-100 transition-transform">
                    ▶ PLAY
                </span>
            </div>
            
            <!-- Featured Badge -->
            <?php if (!empty($game['featured'])): ?>
                <div class="absolute top-2 left-2 bg-friv-yellow text-gray-900 text-xs font-bold px-2 py-1 rounded-full shadow">
                    ⭐ Featured
                </div>
            <?php endif; ?>
            
            <!-- Points Badge -->
            <div class="absolute bottom-2 right-2 bg-white/90 backdrop-blur text-gray-900 text-sm font-bold px-3 py-1 rounded-full flex items-center gap-1 shadow">
                <span>💰</span>
                <span><?= $game['points_reward'] ?? 0 ?></span>
            </div>
        </div>
        
        <!-- Info -->
        <div class="p-3 bg-white/90 backdrop-blur">
            <h3 class="font-bold text-gray-800 truncate" title="<?= e($game['title'] ?? '') ?>">
                <?= e($game['title'] ?? 'Unknown Game') ?>
            </h3>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs <?= difficultyColor($game['difficulty'] ?? 'medium') ?> text-white px-2 py-0.5 rounded-full">
                    <?= ucfirst($game['difficulty'] ?? 'Medium') ?>
                </span>
                <span class="text-xs text-gray-500">
                    <?= formatNumber($game['play_count'] ?? 0) ?> plays
                </span>
            </div>
        </div>
    </div>
</a>