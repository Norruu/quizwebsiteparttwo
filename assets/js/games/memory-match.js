/**
 * Memory Match Game
 * Find matching pairs of cards!
 */

let gameCanvas, ctx;
let gameState = 'ready';
let score = 0;
let moves = 0;
let matchedPairs = 0;
let gameConfig;
let startTime;

// Card setup
const cardEmojis = ['🍎', '🍊', '🍋', '🍇', '🍓', '🍑', '🥝', '🍒'];
let cards = [];
let flippedCards = [];
let matchedCards = [];
let canFlip = true;

// Card dimensions
let cardWidth = 80;
let cardHeight = 100;
let cardGap = 15;
let gridCols = 4;
let gridRows = 4;

function initGame(container, config) {
    gameConfig = config;
    
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'memory-match-canvas';
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
    
    // Adjust card size based on canvas
    const availableWidth = gameCanvas.width - 80;
    const availableHeight = gameCanvas.height - 150;
    
    cardWidth = Math.min(80, (availableWidth - cardGap * (gridCols - 1)) / gridCols);
    cardHeight = cardWidth * 1.25;
    
    if (gameState !== 'ready') {
        updateCardPositions();
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
        
        if (gameState === 'playing' && canFlip) {
            handleClick(e);
        }
    });
    
    document.addEventListener('keydown', (e) => {
        if (gameState === 'ready' && e.key === ' ') {
            startGame();
        }
    });
}

function handleClick(e) {
    const rect = gameCanvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
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

function flipCard(index) {
    const card = cards[index];
    card.flipped = true;
    flippedCards.push(index);
    
    render();
    
    if (flippedCards.length === 2) {
        moves++;
        canFlip = false;
        
        const card1 = cards[flippedCards[0]];
        const card2 = cards[flippedCards[1]];
        
        if (card1.emoji === card2.emoji) {
            // Match found!
            setTimeout(() => {
                card1.matched = true;
                card2.matched = true;
                matchedPairs++;
                matchedCards.push(flippedCards[0], flippedCards[1]);
                
                // Calculate score
                const matchScore = 100 - Math.min(50, moves * 2);
                score += Math.max(10, matchScore);
                document.getElementById('current-score').textContent = score;
                
                flippedCards = [];
                canFlip = true;
                render();
                
                // Check for win
                if (matchedPairs === cardEmojis.length) {
                    setTimeout(endGame, 500);
                }
            }, 300);
        } else {
            // No match
            setTimeout(() => {
                card1.flipped = false;
                card2.flipped = false;
                flippedCards = [];
                canFlip = true;
                render();
            }, 1000);
        }
    }
}

function showStartScreen() {
    gameState = 'ready';
    
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('🧠 Memory Match!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Find all matching pairs!', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
    ctx.fillText('Fewer moves = Higher score!', gameCanvas.width / 2, gameCanvas.height / 2 + 20);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
}

function startGame() {
    gameState = 'playing';
    score = 0;
    moves = 0;
    matchedPairs = 0;
    flippedCards = [];
    matchedCards = [];
    canFlip = true;
    startTime = Date.now();
    
    // Create card pairs
    const pairs = [...cardEmojis, ...cardEmojis];
    
    // Shuffle
    for (let i = pairs.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [pairs[i], pairs[j]] = [pairs[j], pairs[i]];
    }
    
    // Create card objects
    cards = pairs.map((emoji, index) => ({
        emoji: emoji,
        flipped: false,
        matched: false,
        x: 0,
        y: 0,
        index: index
    }));
    
    updateCardPositions();
    render();
}

function updateCardPositions() {
    const totalWidth = gridCols * cardWidth + (gridCols - 1) * cardGap;
    const totalHeight = gridRows * cardHeight + (gridRows - 1) * cardGap;
    const startX = (gameCanvas.width - totalWidth) / 2;
    const startY = (gameCanvas.height - totalHeight) / 2 + 30;
    
    for (let i = 0; i < cards.length; i++) {
        const row = Math.floor(i / gridCols);
        const col = i % gridCols;
        
        cards[i].x = startX + col * (cardWidth + cardGap);
        cards[i].y = startY + row * (cardHeight + cardGap);
    }
}

function render() {
    // Background
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#1a1a2e');
    gradient.addColorStop(1, '#16213e');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    // Stats
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`🎯 Moves: ${moves}`, 30, 40);
    ctx.textAlign = 'center';
    ctx.fillText(`💎 Pairs: ${matchedPairs}/${cardEmojis.length}`, gameCanvas.width / 2, 40);
    ctx.textAlign = 'right';
    ctx.fillText(`⭐ Score: ${score}`, gameCanvas.width - 30, 40);
    
    // Draw cards
    for (const card of cards) {
        drawCard(card);
    }
}

function drawCard(card) {
    // Card shadow
    ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
    ctx.beginPath();
    ctx.roundRect(card.x + 4, card.y + 4, cardWidth, cardHeight, 12);
    ctx.fill();
    
    if (card.matched) {
        // Matched card - green glow
        ctx.fillStyle = '#6BCB77';
        ctx.strokeStyle = '#4CAF50';
    } else if (card.flipped) {
        // Flipped card - white
        ctx.fillStyle = '#ffffff';
        ctx.strokeStyle = '#4D96FF';
    } else {
        // Face down - gradient
        const cardGradient = ctx.createLinearGradient(card.x, card.y, card.x + cardWidth, card.y + cardHeight);
        cardGradient.addColorStop(0, '#4D96FF');
        cardGradient.addColorStop(1, '#9B59B6');
        ctx.fillStyle = cardGradient;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
    }
    
    // Card background
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.roundRect(card.x, card.y, cardWidth, cardHeight, 12);
    ctx.fill();
    ctx.stroke();
    
    if (card.flipped || card.matched) {
        // Draw emoji
        ctx.font = `${cardWidth * 0.6}px serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(card.emoji, card.x + cardWidth / 2, card.y + cardHeight / 2);
    } else {
        // Draw card back pattern
        ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.font = `${cardWidth * 0.4}px serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('❓', card.x + cardWidth / 2, card.y + cardHeight / 2);
    }
}

function endGame() {
    gameState = 'ended';
    
    const playTime = Math.floor((Date.now() - startTime) / 1000);
    
    // Bonus for quick completion
    const timeBonus = Math.max(0, 300 - playTime * 2);
    const moveBonus = Math.max(0, 200 - moves * 5);
    score += timeBonus + moveBonus;
    
    submitScore(score, {
        moves: moves,
        play_time: playTime,
        pairs_found: matchedPairs
    }).then(result => {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎉 You Win!', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 30);
        
        ctx.font = '20px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText(`Completed in ${moves} moves • ${playTime} seconds`, gameCanvas.width / 2, gameCanvas.height / 2 + 15);
        
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