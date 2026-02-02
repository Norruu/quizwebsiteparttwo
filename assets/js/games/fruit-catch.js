/**
 * Fruit Catch Game
 * Catch falling fruits to score points!
 */

let gameCanvas, ctx;
let gameState = 'ready'; // ready, playing, paused, ended
let score = 0;
let lives = 3;
let level = 1;
let gameConfig;
let animationId;
let startTime;

// Game objects
let basket = { x: 0, y: 0, width: 80, height: 60, speed: 8 };
let fruits = [];
let particles = [];

// Fruit types
const fruitTypes = [
    { emoji: '🍎', points: 10, color: '#ff4757' },
    { emoji: '🍊', points: 15, color: '#ffa502' },
    { emoji: '🍋', points: 20, color: '#ffdd59' },
    { emoji: '🍇', points: 25, color: '#8e44ad' },
    { emoji: '🍓', points: 30, color: '#e84393' },
    { emoji: '💎', points: 100, color: '#00d2d3' }, // Bonus
    { emoji: '💀', points: -50, color: '#2d3436', bad: true } // Rotten
];

// Input state
let keys = { left: false, right: false };
let touchStartX = 0;

/**
 * Initialize the game
 */
function initGame(container, config) {
    gameConfig = config;
    
    // Create canvas
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'fruit-catch-canvas';
    gameCanvas.style.width = '100%';
    gameCanvas.style.height = '100%';
    container.appendChild(gameCanvas);
    ctx = gameCanvas.getContext('2d');
    
    // Set canvas size
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    // Setup input handlers
    setupInputHandlers();
    
    // Show start screen
    showStartScreen();
}

function resizeCanvas() {
    const rect = gameCanvas.parentElement.getBoundingClientRect();
    gameCanvas.width = rect.width;
    gameCanvas.height = rect.height;
    
    // Update basket position
    basket.y = gameCanvas.height - basket.height - 20;
    basket.x = (gameCanvas.width - basket.width) / 2;
}

function setupInputHandlers() {
    // Keyboard
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'a') keys.left = true;
        if (e.key === 'ArrowRight' || e.key === 'd') keys.right = true;
        if (e.key === ' ' && gameState === 'ready') startGame();
        if (e.key === 'p' && gameState === 'playing') pauseGame();
    });
    
    document.addEventListener('keyup', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'a') keys.left = false;
        if (e.key === 'ArrowRight' || e.key === 'd') keys.right = false;
    });
    
    // Touch controls
    gameCanvas.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
        if (gameState === 'ready') startGame();
    });
    
    gameCanvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        const touchX = e.touches[0].clientX;
        const rect = gameCanvas.getBoundingClientRect();
        basket.x = touchX - rect.left - basket.width / 2;
        basket.x = Math.max(0, Math.min(gameCanvas.width - basket.width, basket.x));
    });
    
    // Mouse controls (for desktop)
    gameCanvas.addEventListener('mousemove', (e) => {
        if (gameState === 'playing') {
            const rect = gameCanvas.getBoundingClientRect();
            basket.x = e.clientX - rect.left - basket.width / 2;
            basket.x = Math.max(0, Math.min(gameCanvas.width - basket.width, basket.x));
        }
    });
    
    gameCanvas.addEventListener('click', () => {
        if (gameState === 'ready') startGame();
        if (gameState === 'ended') restartGame();
    });
}

function showStartScreen() {
    gameState = 'ready';
    
    // Draw start screen
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('🧺 Fruit Catch! 🍎', gameCanvas.width / 2, gameCanvas.height / 2 - 60);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Catch fruits to score points!', gameCanvas.width / 2, gameCanvas.height / 2);
    ctx.fillText('Avoid the 💀 rotten fruits!', gameCanvas.width / 2, gameCanvas.height / 2 + 35);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
    
    // Draw controls hint
    ctx.font = '16px Nunito, sans-serif';
    ctx.fillStyle = '#666';
    ctx.fillText('← → Arrow Keys or Mouse to Move', gameCanvas.width / 2, gameCanvas.height - 40);
}

function startGame() {
    gameState = 'playing';
    score = 0;
    lives = 3;
    level = 1;
    fruits = [];
    particles = [];
    startTime = Date.now();
    
    // Start game loop
    gameLoop();
    
    // Start spawning fruits
    spawnFruit();
}

function gameLoop() {
    if (gameState !== 'playing') return;
    
    update();
    render();
    
    animationId = requestAnimationFrame(gameLoop);
}

function update() {
    // Move basket with keyboard
    if (keys.left) basket.x -= basket.speed;
    if (keys.right) basket.x += basket.speed;
    
    // Keep basket in bounds
    basket.x = Math.max(0, Math.min(gameCanvas.width - basket.width, basket.x));
    
    // Update fruits
    for (let i = fruits.length - 1; i >= 0; i--) {
        const fruit = fruits[i];
        fruit.y += fruit.speed;
        fruit.rotation += fruit.rotationSpeed;
        
        // Check if caught by basket
        if (fruit.y + fruit.size > basket.y &&
            fruit.x + fruit.size > basket.x &&
            fruit.x < basket.x + basket.width) {
            
            // Caught!
            if (fruit.bad) {
                lives--;
                createParticles(fruit.x, fruit.y, '#ff4757', 10);
                playSound('fail');
                
                if (lives <= 0) {
                    endGame();
                    return;
                }
            } else {
                score += fruit.points;
                createParticles(fruit.x, fruit.y, fruit.color, 8);
                playSound('success');
                
                // Update score display
                document.getElementById('current-score').textContent = score;
            }
            
            fruits.splice(i, 1);
            continue;
        }
        
        // Check if missed (fell off screen)
        if (fruit.y > gameCanvas.height) {
            if (!fruit.bad && !fruit.isBonus) {
                lives--;
                if (lives <= 0) {
                    endGame();
                    return;
                }
            }
            fruits.splice(i, 1);
        }
    }
    
    // Update particles
    for (let i = particles.length - 1; i >= 0; i--) {
        const p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.2; // gravity
        p.life--;
        
        if (p.life <= 0) {
            particles.splice(i, 1);
        }
    }
    
    // Increase difficulty over time
    const elapsed = (Date.now() - startTime) / 1000;
    level = Math.floor(elapsed / 30) + 1;
}

function render() {
    // Clear canvas with gradient background
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#667eea');
    gradient.addColorStop(1, '#764ba2');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    // Draw score and lives
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`Score: ${score}`, 20, 40);
    ctx.fillText(`Lives: ${'❤️'.repeat(lives)}${'🖤'.repeat(3 - lives)}`, 20, 75);
    ctx.fillText(`Level: ${level}`, 20, 110);
    
    // Draw fruits
    for (const fruit of fruits) {
        ctx.save();
        ctx.translate(fruit.x + fruit.size / 2, fruit.y + fruit.size / 2);
        ctx.rotate(fruit.rotation);
        ctx.font = `${fruit.size}px serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(fruit.emoji, 0, 0);
        ctx.restore();
    }
    
    // Draw basket
    ctx.font = `${basket.height}px serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('🧺', basket.x + basket.width / 2, basket.y + basket.height / 2);
    
    // Draw particles
    for (const p of particles) {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fillStyle = p.color;
        ctx.globalAlpha = p.life / p.maxLife;
        ctx.fill();
        ctx.globalAlpha = 1;
    }
}

function spawnFruit() {
    if (gameState !== 'playing') return;
    
    // Random fruit type
    let fruitType;
    const rand = Math.random();
    
    if (rand < 0.05) {
        // 5% chance for bonus
        fruitType = fruitTypes.find(f => f.emoji === '💎');
       } else if (rand < 0.15 + level * 0.02) {
        // Increasing chance for rotten fruit as level increases
        fruitType = fruitTypes.find(f => f.bad);
    } else {
        // Regular fruit
        const regularFruits = fruitTypes.filter(f => !f.bad && f.emoji !== '💎');
        fruitType = regularFruits[Math.floor(Math.random() * regularFruits.length)];
    }
    
    const size = 40 + Math.random() * 20;
    
    fruits.push({
        x: Math.random() * (gameCanvas.width - size),
        y: -size,
        size: size,
        speed: 2 + Math.random() * 2 + level * 0.5,
        rotation: 0,
        rotationSpeed: (Math.random() - 0.5) * 0.2,
        emoji: fruitType.emoji,
        points: fruitType.points,
        color: fruitType.color,
        bad: fruitType.bad || false,
        isBonus: fruitType.emoji === '💎'
    });
    
    // Schedule next fruit spawn (faster as level increases)
    const spawnDelay = Math.max(300, 1000 - level * 100);
    setTimeout(spawnFruit, spawnDelay + Math.random() * 500);
}

function createParticles(x, y, color, count) {
    for (let i = 0; i < count; i++) {
        particles.push({
            x: x,
            y: y,
            vx: (Math.random() - 0.5) * 8,
            vy: (Math.random() - 0.5) * 8 - 3,
            size: Math.random() * 6 + 2,
            color: color,
            life: 30,
            maxLife: 30
        });
    }
}

function pauseGame() {
    if (gameState === 'playing') {
        gameState = 'paused';
        cancelAnimationFrame(animationId);
        
        // Draw pause overlay
        ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('⏸️ PAUSED', gameCanvas.width / 2, gameCanvas.height / 2);
        
        ctx.font = '24px Nunito, sans-serif';
        ctx.fillText('Press P to Resume', gameCanvas.width / 2, gameCanvas.height / 2 + 50);
    } else if (gameState === 'paused') {
        gameState = 'playing';
        gameLoop();
    }
}

function endGame() {
    gameState = 'ended';
    cancelAnimationFrame(animationId);
    
    const playTime = Math.floor((Date.now() - startTime) / 1000);
    
    // Submit score
    submitScore(score, {
        level: level,
        play_time: playTime,
        fruits_caught: Math.floor(score / 10) // Approximate
    }).then(result => {
        // Draw game over screen
        ctx.fillStyle = 'rgba(0, 0, 0, 0.8)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎮 GAME OVER', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 20);
        
        ctx.font = '24px Nunito, sans-serif';
        ctx.fillStyle = '#6BCB77';
        if (result.success && result.data.points_earned > 0) {
            ctx.fillText(`+${result.data.points_earned} Points Earned!`, gameCanvas.width / 2, gameCanvas.height / 2 + 30);
        }
        
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Click to Play Again', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
    });
}

function restartGame() {
    // Generate new session token (requires page reload for security)
    location.reload();
}

// Sound effects (optional)
function playSound(type) {
    // Implement if sound files are available
    // const audio = new Audio(`/assets/sounds/${type}.mp3`);
    // audio.volume = 0.3;
    // audio.play().catch(() => {});
}

// Expose restart function globally
window.restartGame = restartGame;