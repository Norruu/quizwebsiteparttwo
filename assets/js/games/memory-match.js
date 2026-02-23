/**
 * NIR Agri-Match Game (Lives & Timer Edition)
 * Match the local crops and livestock!
 * Features:
 * - Levels 1-4 with increasing difficulty
 * - Health/Lives system (Per round)
 * - Countdown Timer
 * - Educational theme
 * - Preview Mode: cards flip face-up one by one, then flip back to start
 * - Smooth 3D flip animation on every card turn
 */

let gameCanvas, ctx;
let gameState = 'ready'; // ready, preview, playing, levelup, ended, gameover
let score = 0;
let moves = 0;
let level = 1;
let lives = 0;
let timeLeft = 0;
let matchedPairs = 0;
let totalPairs = 0;
let gameConfig;
let timerInterval;
let previewTimeout;
let animFrameId;

// ── Level Configuration ───────────────────────────────────────────────────────
const levels = [
    { level: 1, name: 'Seedling', rows: 2, cols: 3, time: 30, lives: 5  },
    { level: 2, name: 'Sprout',   rows: 3, cols: 4, time: 45, lives: 8  },
    { level: 3, name: 'Harvest',  rows: 4, cols: 4, time: 60, lives: 10 },
    { level: 4, name: 'Hacienda', rows: 4, cols: 5, time: 90, lives: 12 }
];

// ── Card Theme ────────────────────────────────────────────────────────────────
const cardEmojis = [
    '🌾', '🥥', '🥭', '🍌', '🐟', '🐖', '☕', '🍫',
    '🌽', '🥔', '🍍', '🐔', '🐮', '🚜', '👩‍🌾'
];

let cards = [];
let flippedCards = [];
let canFlip = true;

// ── Card Dimensions ───────────────────────────────────────────────────────────
let cardWidth  = 80;
let cardHeight = 100;
let cardGap    = 15;
let gridRows   = 4;
let gridCols   = 4;

// ── Flip Animation ────────────────────────────────────────────────────────────
// Each card has a `flip` value: 0 = face-down, 1 = face-up
// During animation it tweens between 0↔1.
// We also track a queue of pending flips per card.
const FLIP_SPEED = 0.12; // fraction per frame  (~8 frames for half turn)
let needsRender  = false;

// =============================================================================
//  INIT
// =============================================================================
function initGame(container, config) {
    gameConfig = config;
    container.innerHTML = '';

    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'memory-match-canvas';
    gameCanvas.style.width  = '100%';
    gameCanvas.style.height = '100%';
    container.appendChild(gameCanvas);
    ctx = gameCanvas.getContext('2d');

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    setupInputHandlers();
    showStartScreen();
    startRenderLoop();
}

function resizeCanvas() {
    if (!gameCanvas.parentElement) return;
    const rect = gameCanvas.parentElement.getBoundingClientRect();
    gameCanvas.width  = rect.width;
    gameCanvas.height = rect.height;
    calculateCardDimensions();
    if (gameState !== 'ready') updateCardPositions();
    needsRender = true;
}

function calculateCardDimensions() {
    const cfg = levels[level - 1] || levels[0];
    gridRows = cfg.rows;
    gridCols = cfg.cols;
    const availW = gameCanvas.width  - 60;
    const availH = gameCanvas.height - 180;
    const maxW = (availW - (gridCols - 1) * cardGap) / gridCols;
    const maxH = (availH - (gridRows - 1) * cardGap) / gridRows;
    cardWidth  = Math.min(80, Math.min(maxW, maxH * 0.8));
    cardHeight = cardWidth * 1.25;
}

// =============================================================================
//  RENDER LOOP  (runs every RAF; only draws when something changes)
// =============================================================================
function startRenderLoop() {
    function loop() {
        animFrameId = requestAnimationFrame(loop);

        // Advance flip animations
        let animating = false;
        for (const card of cards) {
            if (card.flip !== card.targetFlip) {
                const dir = card.targetFlip > card.flip ? 1 : -1;
                card.flip += dir * FLIP_SPEED;
                if (dir === 1 && card.flip >= card.targetFlip) card.flip = card.targetFlip;
                if (dir === -1 && card.flip <= card.targetFlip) card.flip = card.targetFlip;
                animating = true;
                needsRender = true;
            }
        }

        if (needsRender) {
            render();
            needsRender = false;
        }
    }
    loop();
}

// =============================================================================
//  INPUT
// =============================================================================
function setupInputHandlers() {
    gameCanvas.addEventListener('click', e => {
        if (gameState === 'ready')                           { startGame(); return; }
        if (gameState === 'ended' || gameState === 'gameover') { restartGame(); return; }
        if (gameState === 'playing' && canFlip)              { handleClick(e); }
    });

    document.addEventListener('keydown', e => {
        if (gameState === 'ready' && e.key === ' ') startGame();
    });
}

function handleClick(e) {
    const rect = gameCanvas.getBoundingClientRect();
    const scaleX = gameCanvas.width  / rect.width;
    const scaleY = gameCanvas.height / rect.height;
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top)  * scaleY;

    for (let i = 0; i < cards.length; i++) {
        const card = cards[i];
        if (x >= card.x && x <= card.x + cardWidth &&
            y >= card.y && y <= card.y + cardHeight) {
            if (!card.flipped && !card.matched && flippedCards.length < 2) {
                flipCard(i);
            }
            break;
        }
    }
}

// =============================================================================
//  FLIP LOGIC
// =============================================================================
/**
 * Animate a card flip.
 * `toFaceUp` true  → flip to show emoji (targetFlip = 1)
 * `toFaceUp` false → flip to show back  (targetFlip = 0)
 */
function animateFlip(card, toFaceUp) {
    card.targetFlip = toFaceUp ? 1 : 0;
}

function flipCard(index) {
    const card = cards[index];
    card.flipped = true;
    animateFlip(card, true);
    flippedCards.push(index);

    if (typeof playGameSound === 'function') playGameSound('click');

    if (flippedCards.length === 2) {
        moves++;
        canFlip = false;

        const c1 = cards[flippedCards[0]];
        const c2 = cards[flippedCards[1]];

        if (c1.emoji === c2.emoji) {
            // Match!
            setTimeout(() => {
                c1.matched = true;
                c2.matched = true;
                matchedPairs++;

                if (typeof playGameSound === 'function') playGameSound('success');

                const bonus = 50 + (level * 10);
                score += bonus;
                updateScoreDisplay();

                flippedCards = [];
                canFlip = true;
                needsRender = true;

                if (matchedPairs === totalPairs) handleLevelComplete();
            }, 600);
        } else {
            // No match – flip back after a pause
            setTimeout(() => {
                lives--;
                if (typeof playGameSound === 'function') playGameSound('fail');

                if (lives <= 0) { gameOver('Out of Lives!'); return; }

                animateFlip(c1, false);
                animateFlip(c2, false);
                // Mark as face-down only after animation completes
                // (done via a short extra delay matching flip duration)
                setTimeout(() => {
                    c1.flipped = false;
                    c2.flipped = false;
                    flippedCards = [];
                    canFlip = true;
                    needsRender = true;
                }, Math.ceil(1 / FLIP_SPEED) * 16 + 50); // ~ animation duration
            }, 900);
        }
    }
}

// =============================================================================
//  GAME FLOW
// =============================================================================
function startGame() {
    score = 0;
    level = 1;
    if (typeof playGameSound === 'function') playGameSound('click');
    startLevel();
}

function startLevel() {
    const cfg = levels[level - 1];

    gameState   = 'preview';
    moves       = 0;
    matchedPairs = 0;
    flippedCards = [];
    canFlip     = false;

    timeLeft = cfg.time;
    lives    = cfg.lives;

    gridRows = cfg.rows;
    gridCols = cfg.cols;
    totalPairs = (gridRows * gridCols) / 2;

    // Build deck
    const shuffledEmojis = [...cardEmojis].sort(() => Math.random() - 0.5);
    const selected = shuffledEmojis.slice(0, totalPairs);
    const deck = [...selected, ...selected];
    for (let i = deck.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [deck[i], deck[j]] = [deck[j], deck[i]];
    }

    // Create cards, all face-down to start
    cards = deck.map((emoji, idx) => ({
        emoji,
        flipped:     false,
        matched:     false,
        x: 0, y: 0,
        index:       idx,
        flip:        0,       // animation progress: 0=back, 1=front
        targetFlip:  0,
    }));

    calculateCardDimensions();
    updateCardPositions();
    needsRender = true;

    if (previewTimeout) clearTimeout(previewTimeout);

    // ── Staggered flip-in: each card flips face-up with a short delay ──────
    const flipInDelay  = 80;  // ms between each card's flip
    const holdDuration = 1800; // ms to hold everything face-up

    cards.forEach((card, i) => {
        setTimeout(() => {
            card.flipped    = true;
            card.targetFlip = 1;
            if (typeof playGameSound === 'function' && i === 0) playGameSound('click');
        }, i * flipInDelay);
    });

    // ── After all cards flipped + hold time, flip them all back ─────────────
    const totalRevealTime = cards.length * flipInDelay + holdDuration;
    previewTimeout = setTimeout(() => {
        // Flip back with reverse stagger for extra flair
        cards.forEach((card, i) => {
            setTimeout(() => {
                card.flipped    = false;
                card.targetFlip = 0;
            }, i * flipInDelay);
        });

        // Enable play once the flip-back animation finishes
        const flipBackDone = cards.length * flipInDelay + Math.ceil(1 / FLIP_SPEED) * 16 + 200;
        setTimeout(() => {
            gameState = 'playing';
            canFlip   = true;
            needsRender = true;
            startTimer();
        }, flipBackDone);
    }, totalRevealTime);
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        if (gameState === 'playing') {
            timeLeft--;
            needsRender = true;
            if (timeLeft <= 0) gameOver("Time's Up!");
        }
    }, 1000);
}

function updateCardPositions() {
    const totalW = gridCols * cardWidth  + (gridCols - 1) * cardGap;
    const totalH = gridRows * cardHeight + (gridRows - 1) * cardGap;
    const startX = (gameCanvas.width  - totalW) / 2;
    const startY = (gameCanvas.height - totalH) / 2 + 40;

    for (let i = 0; i < cards.length; i++) {
        cards[i].x = startX + (i % gridCols) * (cardWidth  + cardGap);
        cards[i].y = startY + Math.floor(i / gridCols) * (cardHeight + cardGap);
    }
}

function updateScoreDisplay() {
    const el = document.getElementById('current-score');
    if (el) el.textContent = score;
}

function handleLevelComplete() {
    clearInterval(timerInterval);
    if (previewTimeout) clearTimeout(previewTimeout);

    if (level < levels.length) {
        gameState = 'levelup';
        if (typeof playGameSound === 'function') playGameSound('levelup');

        const bonus = 100 * level + timeLeft * 10;
        score += bonus;
        updateScoreDisplay();
        needsRender = true;

        setTimeout(() => { level++; startLevel(); }, 2500);
    } else {
        endGame();
    }
}

function gameOver(reason) {
    gameState = 'gameover';
    if (typeof playGameSound === 'function') playGameSound('gameover');
    clearInterval(timerInterval);
    if (previewTimeout) clearTimeout(previewTimeout);
    needsRender = true;
    render(reason);
}

function endGame() {
    gameState = 'ended';
    if (typeof playGameSound === 'function') playGameSound('success');
    clearInterval(timerInterval);
    if (previewTimeout) clearTimeout(previewTimeout);

    const finish = (result) => {
        const W = gameCanvas.width, H = gameCanvas.height;
        ctx.fillStyle = 'rgba(0,0,0,0.9)';
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎉 MASTER FARMER!', W / 2, H / 2 - 80);

        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Final Score: ${score}`, W / 2, H / 2 - 20);

        ctx.font = '20px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText('You cleared all 4 levels!', W / 2, H / 2 + 20);

        if (result && result.success && result.data && result.data.points_earned > 0) {
            ctx.fillStyle = '#6BCB77';
            ctx.font = '24px Nunito, sans-serif';
            ctx.fillText(`+${result.data.points_earned} Points Earned!`, W / 2, H / 2 + 60);
        }

        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Click to Play Again', W / 2, H / 2 + 110);
    };

    if (typeof submitScore === 'function') {
        submitScore(score, { levels_completed: level, total_pairs: totalPairs }).then(finish).catch(() => finish(null));
    } else {
        finish(null);
    }
}

function restartGame() { location.reload(); }
window.restartGame = restartGame;

// =============================================================================
//  RENDER
// =============================================================================
function showStartScreen() {
    gameState = 'ready';
    needsRender = true;
}

function render(customMessage = '') {
    const W = gameCanvas.width, H = gameCanvas.height;

    // 1. Background
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#1a1a2e');
    bg.addColorStop(1, '#16213e');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // ── Start screen ──────────────────────────────────────────────────────
    if (gameState === 'ready') {
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🧠 Agri-Match!', W / 2, H / 2 - 80);

        ctx.font = '24px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText('Match the crops before time runs out!', W / 2, H / 2 - 20);

        ctx.fillStyle = '#FF4757';
        ctx.fillText('❤️ Watch your lives!', W / 2, H / 2 + 20);

        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 28px Nunito, sans-serif';
        ctx.fillText('Click or Press SPACE to Start', W / 2, H / 2 + 100);
        return;
    }

    // ── HUD ───────────────────────────────────────────────────────────────
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    ctx.fillRect(0, 0, W, 60);

    const lvlName = levels[level - 1].name;
    ctx.textAlign = 'left';
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.fillText(`Lvl ${level}: ${lvlName}`, 20, 38);

    ctx.textAlign = 'center';
    if (gameState === 'preview') {
        ctx.fillStyle = '#FFD93D';
        ctx.font = 'bold 20px Nunito, sans-serif';
        ctx.fillText('👀 MEMORIZE!', W * 0.35, 38);
    } else {
        const timerColor = timeLeft < 10 ? '#FF4757' : '#ffffff';
        ctx.fillStyle = timerColor;
        ctx.font = 'bold 20px Nunito, sans-serif';
        ctx.fillText(`⏱️ ${timeLeft}s`, W * 0.35, 38);
    }

    ctx.fillStyle = '#FF4757';
    ctx.fillText(`❤️ ${lives}`, W * 0.65, 38);

    ctx.textAlign = 'right';
    ctx.fillStyle = '#FFD93D';
    ctx.fillText(`⭐ ${score}`, W - 20, 38);

    // ── State overlays ────────────────────────────────────────────────────
    if (gameState === 'levelup') {
        drawCards(); // show cards behind overlay
        drawOverlay('LEVEL COMPLETE!', `Next: ${levels[level].name}`, '#6BCB77');
        return;
    }
    if (gameState === 'gameover') {
        drawCards();
        drawOverlay('GAME OVER', customMessage, '#FF4757');
        return;
    }
    if (gameState === 'ended') return;

    // ── Cards ─────────────────────────────────────────────────────────────
    drawCards();
}

function drawCards() {
    for (const card of cards) drawCard(card);
}

function drawOverlay(title, subtitle, color) {
    ctx.fillStyle = 'rgba(0,0,0,0.85)';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);

    ctx.fillStyle = color;
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(title, gameCanvas.width / 2, gameCanvas.height / 2 - 20);

    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#ffffff';
    ctx.fillText(subtitle, gameCanvas.width / 2, gameCanvas.height / 2 + 30);

    if (gameState === 'gameover') {
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 20px Nunito, sans-serif';
        ctx.fillText('Click to Try Again', gameCanvas.width / 2, gameCanvas.height / 2 + 80);
    }
}

// =============================================================================
//  DRAW CARD  –  3-D flip effect via ctx.scale
// =============================================================================
/**
 * The flip value goes 0 → 1.
 * We map it to a cosine so:
 *   0   → scaleX =  1  (full back face visible)
 *   0.5 → scaleX =  0  (edge-on, swap face shown)
 *   1   → scaleX = -1  (front face, mirrored back to normal via negative scale)
 *
 * During the first half  (flip < 0.5) we show the BACK.
 * During the second half (flip >= 0.5) we show the FRONT (emoji side).
 */
function drawCard(card) {
    const cx = card.x + cardWidth  / 2;  // card centre X
    const cy = card.y + cardHeight / 2;  // card centre Y

    // scaleX goes from 1 → 0 → -1
    const scaleX  = Math.cos(card.flip * Math.PI);
    const absScale = Math.abs(scaleX);
    const showFront = card.flip >= 0.5;

    // Shadow (not scaled)
    ctx.save();
    ctx.fillStyle = `rgba(0,0,0,${0.15 + (1 - absScale) * 0.2})`;
    ctx.beginPath();
    ctx.roundRect(card.x + 4 + (1 - absScale) * 2, card.y + 4 + (1 - absScale) * 4,
                  cardWidth * absScale, cardHeight, 10);
    ctx.fill();
    ctx.restore();

    // Apply horizontal scale around card centre
    ctx.save();
    ctx.translate(cx, cy);
    ctx.scale(scaleX, 1);
    ctx.translate(-cx, -cy);

    if (showFront || card.matched) {
        // ── Front face ────────────────────────────────────────────────────
        if (card.matched) {
            ctx.globalAlpha = 0.5;
            ctx.fillStyle   = '#6BCB77';
            ctx.strokeStyle = '#27ae60';
        } else {
            ctx.globalAlpha = 1;
            ctx.fillStyle   = '#ffffff';
            ctx.strokeStyle = '#27ae60';
        }

        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.roundRect(card.x, card.y, cardWidth, cardHeight, 10);
        ctx.fill();
        ctx.stroke();

        // Shine highlight
        if (!card.matched) {
            const shine = ctx.createLinearGradient(card.x, card.y, card.x + cardWidth * 0.5, card.y + cardHeight * 0.4);
            shine.addColorStop(0, 'rgba(255,255,255,0.35)');
            shine.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = shine;
            ctx.beginPath();
            ctx.roundRect(card.x, card.y, cardWidth, cardHeight, 10);
            ctx.fill();
        }

        // Emoji — note: when scaleX < 0 the text would mirror, so we flip it back
        ctx.globalAlpha = card.matched ? 0.5 : 1;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(scaleX < 0 ? -1 : 1, 1); // un-mirror text
        ctx.translate(-cx, -cy);
        ctx.font = `${cardWidth * 0.6}px serif`;
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle    = '#000';
        ctx.fillText(card.emoji, cx, cy);
        ctx.restore();

        ctx.globalAlpha = 1;
    } else {
        // ── Back face ─────────────────────────────────────────────────────
        ctx.globalAlpha = 1;
        const grad = ctx.createLinearGradient(card.x, card.y, card.x + cardWidth, card.y + cardHeight);
        grad.addColorStop(0, '#27ae60');
        grad.addColorStop(1, '#f39c12');
        ctx.fillStyle   = grad;
        ctx.strokeStyle = 'rgba(255,255,255,0.2)';
        ctx.lineWidth   = 3;
        ctx.beginPath();
        ctx.roundRect(card.x, card.y, cardWidth, cardHeight, 10);
        ctx.fill();
        ctx.stroke();

        // Subtle pattern on the back
        ctx.fillStyle = 'rgba(255,255,255,0.12)';
        for (let r = 0; r < 3; r++) {
            for (let c = 0; c < 2; c++) {
                ctx.beginPath();
                ctx.arc(
                    card.x + cardWidth  * (0.3 + c * 0.4),
                    card.y + cardHeight * (0.25 + r * 0.25),
                    cardWidth * 0.07, 0, Math.PI * 2
                );
                ctx.fill();
            }
        }

        // Back icon (tractor)
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(scaleX < 0 ? -1 : 1, 1);
        ctx.translate(-cx, -cy);
        ctx.fillStyle    = 'rgba(255,255,255,0.25)';
        ctx.font         = `${cardWidth * 0.38}px serif`;
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('🚜', cx, cy);
        ctx.restore();
    }

    ctx.restore();
}