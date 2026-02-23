/**
 * NIR Agri-Puzzle Game (Draggable + Progressive Difficulty)
 * - Drag tiles to slide them into the empty space
 * - Each level gets harder: more shuffles, bigger grid
 * - Reference thumbnail, timer, and faded hint included
 */

let gameCanvas, ctx;
let gameState = 'ready'; // ready, loading, playing, solved, ended
let score = 0;
let moves = 0;
let gameConfig;
let startTime;
let currentLevelIndex = 0;
let loadedImage = null;
let useFallback = false;
let timerInterval;
let elapsedTime = 0;

// ─── Difficulty curve ────────────────────────────────────────────────────────
// Each entry defines how hard a level is.
// shuffles = random moves applied when scrambling
// gridSize  = N×N grid (3=easy 8-puzzle, 4=medium 15-puzzle, 5=hard 24-puzzle)
const difficultyLevels = [
    { shuffles: 20,  gridSize: 3, label: '★☆☆  Easy'   },
    { shuffles: 60,  gridSize: 3, label: '★★☆  Medium' },
    { shuffles: 150, gridSize: 4, label: '★★★  Hard'   },
    { shuffles: 300, gridSize: 4, label: '🔥🔥   Expert'  },
    { shuffles: 500, gridSize: 5, label: '💀💀💀 Insane'  },
];

// ─── Puzzle content levels ────────────────────────────────────────────────────
const puzzleLevels = [
    {
        id: 'farmers',
        imageSrc: '../assets/images/games/farmers.jpg',
        fallbackEmoji: '🧑‍🌾',
        fallbackColor1: '#d35400',
        fallbackColor2: '#e67e22',
        title: 'Hardworking Farmers',
        description: 'You revealed the Local Farmers! They are the backbone of the Negros Island Region.'
    },
    {
        id: 'rice_field',
        imageSrc: '../assets/images/games/Rice field.jpg',
        fallbackEmoji: '🌾',
        fallbackColor1: '#2ecc71',
        fallbackColor2: '#27ae60',
        title: 'Rice Fields of Negros',
        description: 'Puzzle Solved! These sprawling green fields produce the essential grain that feeds the region.'
    },
    {
        id: 'farmers',      // reuse images for extra difficulty rounds
        imageSrc: '../assets/images/games/farmers.jpg',
        fallbackEmoji: '🧑‍🌾',
        fallbackColor1: '#8e44ad',
        fallbackColor2: '#9b59b6',
        title: 'Farmers – Hard Mode',
        description: 'Amazing! You solved the hard version of the Farmers puzzle!'
    },
    {
        id: 'rice_field',
        imageSrc: '../assets/images/games/Rice field.jpg',
        fallbackEmoji: '🌾',
        fallbackColor1: '#c0392b',
        fallbackColor2: '#e74c3c',
        title: 'Rice Fields – Expert',
        description: 'Expert level cleared! The rice fields of Negros thank you.'
    },
    {
        id: 'farmers',
        imageSrc: '../assets/images/games/farmers.jpg',
        fallbackEmoji: '🧑‍🌾',
        fallbackColor1: '#1a252f',
        fallbackColor2: '#2c3e50',
        title: 'Farmers – Insane',
        description: '💀 INSANE level complete! You are a true puzzle master!'
    },
];

// ─── Runtime state ────────────────────────────────────────────────────────────
let gridSize = 3;
let tiles = [];
let emptyPos = { row: 0, col: 0 };
let tileSize = 80;
const tileGap = 2;

// ─── Drag state ───────────────────────────────────────────────────────────────
let dragState = null;
// dragState = { tile, startX, startY, offsetX, offsetY, axis, currentX, currentY }

// ─── Puzzle origin (set in render) ───────────────────────────────────────────
let puzzleOrigin = { x: 0, y: 0 };

// =============================================================================
//  INIT
// =============================================================================
function initGame(container, config) {
    gameConfig = config;
    container.innerHTML = '';

    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'sliding-puzzle-canvas';
    gameCanvas.style.width = '100%';
    gameCanvas.style.height = '100%';
    gameCanvas.style.touchAction = 'none'; // prevent scroll interference
    container.appendChild(gameCanvas);
    ctx = gameCanvas.getContext('2d');

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    setupInputHandlers();
    showStartScreen();
}

function resizeCanvas() {
    if (!gameCanvas.parentElement) return;
    const rect = gameCanvas.parentElement.getBoundingClientRect();
    gameCanvas.width  = rect.width;
    gameCanvas.height = rect.height;

    recalcTileSize();

    if (gameState !== 'ready') render();
}

function recalcTileSize() {
    const available = Math.min(gameCanvas.width - 40, gameCanvas.height - 160);
    tileSize = Math.floor((available - tileGap * (gridSize - 1)) / gridSize);
}

// =============================================================================
//  INPUT – drag (mouse + touch)
// =============================================================================
function setupInputHandlers() {
    // ── Mouse ──────────────────────────────────────────────────────────────
    gameCanvas.addEventListener('mousedown',  onPointerDown);
    gameCanvas.addEventListener('mousemove',  onPointerMove);
    gameCanvas.addEventListener('mouseup',    onPointerUp);
    gameCanvas.addEventListener('mouseleave', onPointerUp);

    // ── Touch ──────────────────────────────────────────────────────────────
    gameCanvas.addEventListener('touchstart', e => { e.preventDefault(); onPointerDown(e.touches[0]); }, { passive: false });
    gameCanvas.addEventListener('touchmove',  e => { e.preventDefault(); onPointerMove(e.touches[0]); }, { passive: false });
    gameCanvas.addEventListener('touchend',   e => { e.preventDefault(); onPointerUp(e.changedTouches[0]); }, { passive: false });

    // ── Keyboard ───────────────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (gameState === 'ready'  && e.key === ' ')      { startGame(); return; }
        if (gameState === 'solved' && e.key === 'Enter')  { nextLevel(); return; }
        if (gameState === 'ended'  && e.key === 'Enter')  { restartGame(); return; }
        if (gameState === 'playing') {
            let moved = false;
            switch (e.key) {
                case 'ArrowUp':    moved = moveTile(emptyPos.row + 1, emptyPos.col); break;
                case 'ArrowDown':  moved = moveTile(emptyPos.row - 1, emptyPos.col); break;
                case 'ArrowLeft':  moved = moveTile(emptyPos.row, emptyPos.col + 1); break;
                case 'ArrowRight': moved = moveTile(emptyPos.row, emptyPos.col - 1); break;
            }
            if (moved) e.preventDefault();
        }
    });
}

function getCanvasPos(e) {
    const rect = gameCanvas.getBoundingClientRect();
    const scaleX = gameCanvas.width  / rect.width;
    const scaleY = gameCanvas.height / rect.height;
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top)  * scaleY,
    };
}

function getTileAt(x, y) {
    const col = Math.round((x - puzzleOrigin.x) / (tileSize + tileGap));
    const row = Math.round((y - puzzleOrigin.y) / (tileSize + tileGap));
    if (row < 0 || row >= gridSize || col < 0 || col >= gridSize) return null;
    return tiles.find(t => t.row === row && t.col === col) || null;
}

function onPointerDown(e) {
    const pos = getCanvasPos(e);

    // State-machine clicks (start / next / restart)
    if (gameState === 'ready')  { startGame(); return; }
    if (gameState === 'solved') { nextLevel(); return; }
    if (gameState === 'ended')  { restartGame(); return; }
    if (gameState !== 'playing') return;

    const tile = getTileAt(pos.x, pos.y);
    if (!tile) return;

    // Only allow dragging if the tile is adjacent to the empty space
    const rowDiff = Math.abs(tile.row - emptyPos.row);
    const colDiff = Math.abs(tile.col - emptyPos.col);
    const isAdjacentRow = rowDiff === 1 && colDiff === 0;
    const isAdjacentCol = rowDiff === 0 && colDiff === 1;
    if (!isAdjacentRow && !isAdjacentCol) return;

    const tileX = puzzleOrigin.x + tile.col * (tileSize + tileGap);
    const tileY = puzzleOrigin.y + tile.row * (tileSize + tileGap);

    dragState = {
        tile,
        axis:     isAdjacentRow ? 'y' : 'x',  // direction the tile may move
        startX:   pos.x,
        startY:   pos.y,
        tileBaseX: tileX,
        tileBaseY: tileY,
        offsetX:  0,
        offsetY:  0,
    };
}

function onPointerMove(e) {
    if (!dragState || gameState !== 'playing') return;
    const pos = getCanvasPos(e);

    const dx = pos.x - dragState.startX;
    const dy = pos.y - dragState.startY;

    // Clamp drag to one tile width/height in the correct direction
    const emptyTileX = puzzleOrigin.x + emptyPos.col * (tileSize + tileGap);
    const emptyTileY = puzzleOrigin.y + emptyPos.row * (tileSize + tileGap);

    if (dragState.axis === 'x') {
        const maxDrag = emptyTileX - dragState.tileBaseX;          // signed
        dragState.offsetX = clamp(dx, Math.min(0, maxDrag), Math.max(0, maxDrag));
        dragState.offsetY = 0;
    } else {
        const maxDrag = emptyTileY - dragState.tileBaseY;
        dragState.offsetX = 0;
        dragState.offsetY = clamp(dy, Math.min(0, maxDrag), Math.max(0, maxDrag));
    }

    render();
}

function onPointerUp(e) {
    if (!dragState || gameState !== 'playing') { dragState = null; return; }

    const threshold = (tileSize + tileGap) * 0.4; // 40% of tile = commit
    const moved = (Math.abs(dragState.offsetX) >= threshold || Math.abs(dragState.offsetY) >= threshold);

    if (moved) {
        moveTile(dragState.tile.row, dragState.tile.col);
    }

    dragState = null;
    render();
}

function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

// =============================================================================
//  GAME LOGIC
// =============================================================================
function moveTile(row, col) {
    const rowDiff = Math.abs(row - emptyPos.row);
    const colDiff = Math.abs(col - emptyPos.col);
    if ((rowDiff === 1 && colDiff === 0) || (rowDiff === 0 && colDiff === 1)) {
        const idx = tiles.findIndex(t => t.row === row && t.col === col);
        if (idx !== -1) {
            tiles[idx].row = emptyPos.row;
            tiles[idx].col = emptyPos.col;
            emptyPos = { row, col };
            moves++;
            if (typeof playGameSound === 'function') playGameSound('click');
            render();
            if (isSolved()) handleLevelComplete();
            return true;
        }
    }
    return false;
}

function isSolved() {
    for (const tile of tiles) {
        if (tile.row !== Math.floor(tile.value / gridSize)) return false;
        if (tile.col !== tile.value % gridSize) return false;
    }
    return emptyPos.row === gridSize - 1 && emptyPos.col === gridSize - 1;
}

// =============================================================================
//  LEVEL / DIFFICULTY
// =============================================================================
function getDifficulty(index) {
    return difficultyLevels[Math.min(index, difficultyLevels.length - 1)];
}

function showStartScreen() {
    gameState = 'ready';
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('🌾 Farm Puzzle!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);

    ctx.font = '22px Nunito, sans-serif';
    ctx.fillStyle = '#a0c4a0';
    ctx.fillText('Drag the tiles to reveal the picture.', gameCanvas.width / 2, gameCanvas.height / 2 - 25);
    ctx.fillText('Levels get harder as you go!', gameCanvas.width / 2, gameCanvas.height / 2 + 15);

    // Difficulty preview
    let previewY = gameCanvas.height / 2 + 55;
    ctx.font = '18px Nunito, sans-serif';
    difficultyLevels.forEach((d, i) => {
        ctx.fillStyle = i === 0 ? '#6BCB77' : (i === difficultyLevels.length - 1 ? '#ff6b6b' : '#FFD93D');
        ctx.fillText(`Level ${i + 1}: ${d.label}  (${d.gridSize}×${d.gridSize})`, gameCanvas.width / 2, previewY + i * 28);
    });

    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height - 50);
}

function startGame() {
    currentLevelIndex = 0;
    score = 0;
    moves = 0;
    if (typeof playGameSound === 'function') playGameSound('click');
    loadLevel(currentLevelIndex);
}

function loadLevel(index) {
    gameState = 'loading';
    moves     = 0;
    elapsedTime = 0;
    startTime = Date.now();
    useFallback = false;
    dragState = null;

    // Apply difficulty for this level
    const diff = getDifficulty(index);
    gridSize = diff.gridSize;
    recalcTileSize();

    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        if (gameState === 'playing') {
            elapsedTime = Math.floor((Date.now() - startTime) / 1000);
            render();
        }
    }, 1000);

    const levelData = puzzleLevels[Math.min(index, puzzleLevels.length - 1)];
    render(); // show loading screen

    loadedImage = new Image();

    const loadTimeout = setTimeout(() => {
        if (gameState === 'loading') { useFallback = true; loadedImage = null; startLevelLogic(); }
    }, 1500);

    loadedImage.onload = () => {
        clearTimeout(loadTimeout);
        if (loadedImage.width === 0) { useFallback = true; loadedImage = null; }
        startLevelLogic();
    };
    loadedImage.onerror = () => {
        clearTimeout(loadTimeout);
        useFallback = true; loadedImage = null;
        startLevelLogic();
    };
    loadedImage.src = levelData.imageSrc;
}

function startLevelLogic() {
    initializePuzzle();
    const diff = getDifficulty(currentLevelIndex);
    shufflePuzzle(diff.shuffles);
    gameState = 'playing';
    render();
}

function initializePuzzle() {
    tiles = [];
    for (let i = 0; i < gridSize * gridSize - 1; i++) {
        tiles.push({ value: i, row: Math.floor(i / gridSize), col: i % gridSize });
    }
    emptyPos = { row: gridSize - 1, col: gridSize - 1 };
}

function shufflePuzzle(count) {
    let lastRow = -1, lastCol = -1;
    for (let i = 0; i < count; i++) {
        const moves = [];
        if (emptyPos.row > 0)            moves.push({ row: emptyPos.row - 1, col: emptyPos.col });
        if (emptyPos.row < gridSize - 1) moves.push({ row: emptyPos.row + 1, col: emptyPos.col });
        if (emptyPos.col > 0)            moves.push({ row: emptyPos.row, col: emptyPos.col - 1 });
        if (emptyPos.col < gridSize - 1) moves.push({ row: emptyPos.row, col: emptyPos.col + 1 });

        // Avoid immediate reversal to improve actual entropy
        const filtered = moves.filter(m => !(m.row === lastRow && m.col === lastCol));
        const pick = filtered.length ? filtered : moves;
        const m = pick[Math.floor(Math.random() * pick.length)];

        const idx = tiles.findIndex(t => t.row === m.row && t.col === m.col);
        if (idx !== -1) {
            lastRow = emptyPos.row; lastCol = emptyPos.col;
            tiles[idx].row = emptyPos.row;
            tiles[idx].col = emptyPos.col;
            emptyPos = m;
        }
    }
}

function handleLevelComplete() {
    gameState = 'solved';
    clearInterval(timerInterval);
    if (typeof playGameSound === 'function') playGameSound('success');
    const levelScore = Math.max(100, 1000 - (moves * 5) - (elapsedTime * 2));
    score += levelScore;
    const el = document.getElementById('current-score');
    if (el) el.textContent = score;
    render();
}

function nextLevel() {
    currentLevelIndex++;
    if (typeof playGameSound === 'function') playGameSound('click');
    if (currentLevelIndex < difficultyLevels.length) {
        loadLevel(currentLevelIndex);
    } else {
        endGame();
    }
}

function endGame() {
    gameState = 'ended';
    if (typeof playGameSound === 'function') playGameSound('gameover');
    clearInterval(timerInterval);

    const doRender = (result) => {
        ctx.fillStyle = 'rgba(0,0,0,0.92)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🏆 ALL LEVELS DONE!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);

        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Final Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 20);

        if (result && result.success && result.data && result.data.points_earned > 0) {
            ctx.fillStyle = '#6BCB77';
            ctx.font = '24px Nunito, sans-serif';
            ctx.fillText(`+${result.data.points_earned} Points Earned!`, gameCanvas.width / 2, gameCanvas.height / 2 + 40);
        }

        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Click or Press ENTER to Play Again', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
    };

    if (typeof submitScore === 'function') {
        submitScore(score, { levels_completed: currentLevelIndex, total_moves: moves }).then(doRender).catch(() => doRender(null));
    } else {
        doRender(null);
    }
}

function restartGame() { location.reload(); }
window.restartGame = restartGame;

// =============================================================================
//  RENDER
// =============================================================================
function render() {
    const W = gameCanvas.width, H = gameCanvas.height;

    // Background
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#2e4a19');
    bg.addColorStop(1, '#1e2b14');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    if (gameState === 'loading') {
        ctx.fillStyle = '#ffffff';
        ctx.font = '24px Nunito, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Loading Level…', W / 2, H / 2);
        return;
    }

    const levelData = puzzleLevels[Math.min(currentLevelIndex, puzzleLevels.length - 1)];
    const diff      = getDifficulty(currentLevelIndex);

    // ── Thumbnail ──────────────────────────────────────────────────────────
    if (!useFallback && loadedImage) {
        const ts = 70, tx = 10, ty = 10;
        ctx.fillStyle = '#fff';
        ctx.fillRect(tx - 2, ty - 2, ts + 4, ts + 4);
        ctx.drawImage(loadedImage, tx, ty, ts, ts);
        ctx.fillStyle = '#ddd';
        ctx.font = '11px Nunito, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Target', tx + ts / 2, ty + ts + 13);
    }

    // ── Difficulty badge ───────────────────────────────────────────────────
    ctx.fillStyle = 'rgba(0,0,0,0.45)';
    ctx.beginPath(); ctx.roundRect(10, 95, 160, 28, 8); ctx.fill();
    ctx.fillStyle = '#FFD93D';
    ctx.font = 'bold 14px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(diff.label, 16, 114);

    // ── Timer + Moves ──────────────────────────────────────────────────────
    const minutes = Math.floor(elapsedTime / 60);
    const seconds = elapsedTime % 60;
    const timeStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;

    ctx.textAlign = 'right';
    ctx.fillStyle = 'rgba(0,0,0,0.4)';
    ctx.beginPath(); ctx.roundRect(W - 125, 10, 115, 44, 10); ctx.fill();
    ctx.fillStyle = '#FFD93D';
    ctx.font = 'bold 24px monospace';
    ctx.fillText(`⏱ ${timeStr}`, W - 18, 40);
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 18px Nunito, sans-serif';
    ctx.fillText(`Moves: ${moves}`, W - 18, 80);

    // ── Title ──────────────────────────────────────────────────────────────
    ctx.textAlign = 'center';
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 26px Nunito, sans-serif';
    ctx.fillText(levelData.title, W / 2, 46);

    // ── Puzzle grid ────────────────────────────────────────────────────────
    const puzzleSize = gridSize * tileSize + (gridSize - 1) * tileGap;
    const originX = (W - puzzleSize) / 2;
    const originY = (H - puzzleSize) / 2 + 30;
    puzzleOrigin = { x: originX, y: originY };

    // Faded hint
    if (!useFallback && loadedImage) {
        ctx.globalAlpha = 0.18;
        ctx.drawImage(loadedImage, originX, originY, puzzleSize, puzzleSize);
        ctx.globalAlpha = 1.0;
    } else {
        ctx.fillStyle = 'rgba(0,0,0,0.3)';
        ctx.fillRect(originX - 5, originY - 5, puzzleSize + 10, puzzleSize + 10);
    }

    if (gameState === 'solved') {
        // Full image reveal
        if (!useFallback && loadedImage) {
            ctx.drawImage(loadedImage, originX, originY, puzzleSize, puzzleSize);
        } else {
            drawFallbackFill(originX, originY, puzzleSize, levelData);
        }
    } else {
        // Draw all non-dragged tiles first
        for (const tile of tiles) {
            if (dragState && dragState.tile === tile) continue;
            drawTile(tile, originX, originY, puzzleSize, levelData, 0, 0);
        }
        // Draw dragged tile on top
        if (dragState) {
            drawTile(dragState.tile, originX, originY, puzzleSize, levelData, dragState.offsetX, dragState.offsetY, true);
        }
    }

    // ── Solved overlay ─────────────────────────────────────────────────────
    if (gameState === 'solved') {
        ctx.fillStyle = 'rgba(0,0,0,0.88)';
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = '#6BCB77';
        ctx.font = 'bold 42px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('✅ PUZZLE SOLVED!', W / 2, H / 2 - 110);

        ctx.fillStyle = '#FFD93D';
        ctx.font = 'bold 26px Nunito, sans-serif';
        ctx.fillText(levelData.title, W / 2, H / 2 - 60);

        ctx.fillStyle = '#ffffff';
        ctx.font = '19px Nunito, sans-serif';
        wrapText(ctx, levelData.description, W / 2, H / 2 - 15, W - 60, 28);

        const isLast = currentLevelIndex >= difficultyLevels.length - 1;
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 22px Nunito, sans-serif';
        ctx.fillText(isLast ? 'Click or ENTER to Finish' : 'Click or ENTER for Next Level →', W / 2, H - 50);
    }
}

function drawFallbackFill(x, y, size, levelData) {
    const grad = ctx.createLinearGradient(x, y, x + size, y + size);
    grad.addColorStop(0, levelData.fallbackColor1);
    grad.addColorStop(1, levelData.fallbackColor2);
    ctx.fillStyle = grad;
    ctx.fillRect(x, y, size, size);
    ctx.fillStyle = '#fff';
    ctx.font = `${size * 0.4}px serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(levelData.fallbackEmoji, x + size / 2, y + size / 2);
    ctx.textBaseline = 'alphabetic';
}

function drawTile(tile, originX, originY, totalSize, levelData, offX, offY, lifted) {
    const x = originX + tile.col * (tileSize + tileGap) + (offX || 0);
    const y = originY + tile.row * (tileSize + tileGap) + (offY || 0);

    if (lifted) {
        ctx.shadowColor = 'rgba(0,0,0,0.5)';
        ctx.shadowBlur  = 18;
        ctx.shadowOffsetX = 4;
        ctx.shadowOffsetY = 4;
    }

    if (!useFallback && loadedImage) {
        const srcRow = Math.floor(tile.value / gridSize);
        const srcCol = tile.value % gridSize;
        const srcW   = loadedImage.width  / gridSize;
        const srcH   = loadedImage.height / gridSize;
        ctx.drawImage(loadedImage, srcCol * srcW, srcRow * srcH, srcW, srcH, x, y, tileSize, tileSize);
        ctx.strokeStyle = lifted ? 'rgba(255,255,100,0.9)' : 'rgba(255,255,255,0.35)';
        ctx.lineWidth = lifted ? 2 : 1;
        ctx.strokeRect(x, y, tileSize, tileSize);
    } else {
        const expRow = Math.floor(tile.value / gridSize);
        const expCol = tile.value % gridSize;
        const correct = tile.row === expRow && tile.col === expCol;

        const g = ctx.createLinearGradient(x, y, x + tileSize, y + tileSize);
        if (correct)       { g.addColorStop(0, '#6BCB77'); g.addColorStop(1, '#4CAF50'); }
        else if (lifted)   { g.addColorStop(0, '#fff176'); g.addColorStop(1, '#ffd54f'); }
        else               { g.addColorStop(0, levelData.fallbackColor1); g.addColorStop(1, levelData.fallbackColor2); }

        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.roundRect(x, y, tileSize, tileSize, 6);
        ctx.fill();

        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.font = `bold ${tileSize * 0.38}px Fredoka One, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText((tile.value + 1).toString(), x + tileSize / 2, y + tileSize / 2);
        ctx.textBaseline = 'alphabetic';
    }

    if (lifted) {
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur  = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
    }
}

// =============================================================================
//  UTILITIES
// =============================================================================
function wrapText(context, text, x, y, maxWidth, lineHeight) {
    const words = text.split(' ');
    let line = '';
    for (let n = 0; n < words.length; n++) {
        const test = line + words[n] + ' ';
        if (context.measureText(test).width > maxWidth && n > 0) {
            context.fillText(line, x, y);
            line = words[n] + ' ';
            y += lineHeight;
        } else {
            line = test;
        }
    }
    context.fillText(line, x, y);
}