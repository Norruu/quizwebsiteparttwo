/**
 * Sliding Puzzle Game
 * Arrange tiles in order by sliding them!
 */

let gameCanvas, ctx;
let gameState = 'ready';
let score = 0;
let moves = 0;
let gameConfig;
let startTime;

// Puzzle setup
const gridSize = 4; // 4x4 grid (15 puzzle)
let tiles = [];
let emptyPos = { row: 3, col: 3 };

// Tile dimensions
let tileSize = 80;
let tileGap = 4;

function initGame(container, config) {
    gameConfig = config;
    
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'sliding-puzzle-canvas';
    gameCanvas.style.width = '100%';
    gameCanvas.style.height = '100%';
    container.appendChild(gameCanvas);
    ctx = gameCanvas.getContext('2d');
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    setupInputHandlers();
    showStartScreen();
}

function resizeCanvas() {
    const rect = gameCanvas.parentElement.getBoundingClientRect();
    gameCanvas.width = rect.width;
    gameCanvas.height = rect.height;
    
    // Adjust tile size
    const availableSize = Math.min(gameCanvas.width - 80, gameCanvas.height - 180);
    tileSize = Math.floor((availableSize - tileGap * (gridSize - 1)) / gridSize);
    
    if (gameState !== 'ready') {
        render();
    }
}

function setupInputHandlers() {
    gameCanvas.addEventListener('click', (e) => {
        if (gameState === 'ready') {
            startGame();
            return;
        }
        
        if (gameState === 'ended') {
            restartGame();
            return;
        }
        
        if (gameState === 'playing') {
            handleClick(e);
        }
    });
    
    // Keyboard controls
    document.addEventListener('keydown', (e) => {
        if (gameState === 'ready' && e.key === ' ') {
            startGame();
            return;
        }
        
        if (gameState === 'playing') {
            let moved = false;
            
            switch (e.key) {
                case 'ArrowUp':
                    moved = moveTile(emptyPos.row + 1, emptyPos.col);
                    break;
                case 'ArrowDown':
                    moved = moveTile(emptyPos.row - 1, emptyPos.col);
                    break;
                case 'ArrowLeft':
                    moved = moveTile(emptyPos.row, emptyPos.col + 1);
                    break;
                case 'ArrowRight':
                    moved = moveTile(emptyPos.row, emptyPos.col - 1);
                    break;
            }
            
            if (moved) {
                e.preventDefault();
            }
        }
    });
}

function handleClick(e) {
    const rect = gameCanvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    // Calculate grid position
    const puzzleSize = gridSize * tileSize + (gridSize - 1) * tileGap;
    const startX = (gameCanvas.width - puzzleSize) / 2;
    const startY = (gameCanvas.height - puzzleSize) / 2 + 30;
    
    const col = Math.floor((x - startX) / (tileSize + tileGap));
    const row = Math.floor((y - startY) / (tileSize + tileGap));
    
    if (row >= 0 && row < gridSize && col >= 0 && col < gridSize) {
        moveTile(row, col);
    }
}

function moveTile(row, col) {
    // Check if tile is adjacent to empty space
    const rowDiff = Math.abs(row - emptyPos.row);
    const colDiff = Math.abs(col - emptyPos.col);
    
    if ((rowDiff === 1 && colDiff === 0) || (rowDiff === 0 && colDiff === 1)) {
        // Swap tile with empty space
        const tileIndex = tiles.findIndex(t => t.row === row && t.col === col);
        
        if (tileIndex !== -1) {
            tiles[tileIndex].row = emptyPos.row;
            tiles[tileIndex].col = emptyPos.col;
            emptyPos = { row, col };
            moves++;
            
            render();
            
            // Check if solved
            if (isSolved()) {
                endGame();
            }
            
            return true;
        }
    }
    
    return false;
}

function showStartScreen() {
    gameState = 'ready';
    
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('🧩 Sliding Puzzle!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Arrange tiles in order (1-15)', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
    ctx.fillText('Click tiles or use arrow keys', gameCanvas.width / 2, gameCanvas.height / 2 + 20);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
}

function startGame() {
    gameState = 'playing';
    moves = 0;
    startTime = Date.now();
    
    initializePuzzle();
    shufflePuzzle();
    render();
}

function initializePuzzle() {
    tiles = [];
    
    for (let i = 0; i < gridSize * gridSize - 1; i++) {
        tiles.push({
            value: i + 1,
            row: Math.floor(i / gridSize),
            col: i % gridSize
        });
    }
    
    emptyPos = { row: gridSize - 1, col: gridSize - 1 };
}

function shufflePuzzle() {
    // Make random valid moves to shuffle
    const moveCount = 100 + Math.floor(Math.random() * 100);
    
    for (let i = 0; i < moveCount; i++) {
        const possibleMoves = [];
        
        if (emptyPos.row > 0) possibleMoves.push({ row: emptyPos.row - 1, col: emptyPos.col });
        if (emptyPos.row < gridSize - 1) possibleMoves.push({ row: emptyPos.row + 1, col: emptyPos.col });
        if (emptyPos.col > 0) possibleMoves.push({ row: emptyPos.row, col: emptyPos.col - 1 });
        if (emptyPos.col < gridSize - 1) possibleMoves.push({ row: emptyPos.row, col: emptyPos.col + 1 });
        
        const move = possibleMoves[Math.floor(Math.random() * possibleMoves.length)];
        const tileIndex = tiles.findIndex(t => t.row === move.row && t.col === move.col);
        
        if (tileIndex !== -1) {
            tiles[tileIndex].row = emptyPos.row;
            tiles[tileIndex].col = emptyPos.col;
            emptyPos = move;
        }
    }
}

function isSolved() {
    for (const tile of tiles) {
        const expectedRow = Math.floor((tile.value - 1) / gridSize);
        const expectedCol = (tile.value - 1) % gridSize;
        
        if (tile.row !== expectedRow || tile.col !== expectedCol) {
            return false;
        }
    }
    
    return emptyPos.row === gridSize - 1 && emptyPos.col === gridSize - 1;
}

function render() {
    // Background
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#0f3460');
    gradient.addColorStop(1, '#16213e');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    // Stats
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px Nunito, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`Moves: ${moves}`, gameCanvas.width / 2, 50);
    
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    ctx.fillText(`Time: ${minutes}:${seconds.toString().padStart(2, '0')}`, gameCanvas.width / 2, 85);
    
    // Draw puzzle
    const puzzleSize = gridSize * tileSize + (gridSize - 1) * tileGap;
    const startX = (gameCanvas.width - puzzleSize) / 2;
    const startY = (gameCanvas.height - puzzleSize) / 2 + 30;
    
    // Puzzle background
    ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
    ctx.beginPath();
    ctx.roundRect(startX - 10, startY - 10, puzzleSize + 20, puzzleSize + 20, 15);
    ctx.fill();
    
    // Draw tiles
    for (const tile of tiles) {
        drawTile(tile, startX, startY);
    }
    
    // Instructions
    ctx.font = '14px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.textAlign = 'center';
    ctx.fillText('Click adjacent tiles or use arrow keys to move', gameCanvas.width / 2, gameCanvas.height - 30);
}

function drawTile(tile, startX, startY) {
    const x = startX + tile.col * (tileSize + tileGap);
    const y = startY + tile.row * (tileSize + tileGap);
    
    // Check if tile is in correct position
    const expectedRow = Math.floor((tile.value - 1) / gridSize);
    const expectedCol = (tile.value - 1) % gridSize;
    const isCorrect = tile.row === expectedRow && tile.col === expectedCol;
    
    // Tile gradient
    let tileGradient;
    if (isCorrect) {
        tileGradient = ctx.createLinearGradient(x, y, x + tileSize, y + tileSize);
        tileGradient.addColorStop(0, '#6BCB77');
        tileGradient.addColorStop(1, '#4CAF50');
    } else {
        tileGradient = ctx.createLinearGradient(x, y, x + tileSize, y + tileSize);
        tileGradient.addColorStop(0, '#4D96FF');
        tileGradient.addColorStop(1, '#2575fc');
    }
    
    // Tile shadow
    ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
    ctx.beginPath();
    ctx.roundRect(x + 3, y + 3, tileSize, tileSize, 10);
    ctx.fill();
    
        // Tile background
    ctx.fillStyle = tileGradient;
    ctx.beginPath();
    ctx.roundRect(x, y, tileSize, tileSize, 10);
    ctx.fill();
    
    // Tile border
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
    ctx.lineWidth = 2;
    ctx.stroke();
    
    // Tile number
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold ${tileSize * 0.4}px Fredoka One, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(tile.value.toString(), x + tileSize / 2, y + tileSize / 2);
}

function endGame() {
    gameState = 'ended';
    
    const playTime = Math.floor((Date.now() - startTime) / 1000);
    
    // Calculate score based on moves and time
    // Lower moves and time = higher score
    const baseScore = 10000;
    const movePenalty = moves * 50;
    const timePenalty = playTime * 10;
    score = Math.max(100, baseScore - movePenalty - timePenalty);
    
    document.getElementById('current-score').textContent = score;
    
    submitScore(score, {
        moves: moves,
        play_time: playTime,
        grid_size: gridSize
    }).then(result => {
        // Victory animation
        render();
        
        ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎉 Puzzle Solved!', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 30);
        
        ctx.font = '20px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        const minutes = Math.floor(playTime / 60);
        const seconds = playTime % 60;
        ctx.fillText(`Completed in ${moves} moves • ${minutes}:${seconds.toString().padStart(2, '0')}`, gameCanvas.width / 2, gameCanvas.height / 2 + 15);
        
        if (result.success && result.data.points_earned > 0) {
            ctx.fillStyle = '#6BCB77';
            ctx.font = '24px Nunito, sans-serif';
            ctx.fillText(`+${result.data.points_earned} Points Earned!`, gameCanvas.width / 2, gameCanvas.height / 2 + 55);
        }
        
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Click to Play Again', gameCanvas.width / 2, gameCanvas.height / 2 + 110);
    });
}

function restartGame() {
    location.reload();
}

window.restartGame = restartGame;