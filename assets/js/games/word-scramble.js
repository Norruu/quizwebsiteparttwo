/**
 * Word Scramble Game
 * Unscramble words before time runs out!
 */

let gameCanvas, ctx;
let gameState = 'ready';
let score = 0;
let currentWord = '';
let scrambledWord = '';
let userInput = '';
let timeLeft = 60;
let wordsCompleted = 0;
let streak = 0;
let gameConfig;
let timerInterval;

// Word lists by difficulty
const wordLists = {
    easy: ['CAT', 'DOG', 'SUN', 'RUN', 'HAT', 'BAT', 'CUP', 'PEN', 'BED', 'BOX', 'CAR', 'KEY', 'MAP', 'NET', 'PIE'],
    medium: ['APPLE', 'BEACH', 'CLOUD', 'DANCE', 'EARTH', 'FLAME', 'GRAPE', 'HOUSE', 'JUICE', 'KNIFE', 'LEMON', 'MUSIC', 'NIGHT', 'OCEAN', 'PIANO'],
    hard: ['ABSTRACT', 'BEAUTIFUL', 'CHALLENGE', 'DANGEROUS', 'ELABORATE', 'FANTASTIC', 'GORGEOUS', 'HARMONY', 'IMPORTANT', 'KNOWLEDGE', 'ADVENTURE', 'BRILLIANT', 'CHAMPION', 'DELICIOUS', 'EXCELLENT']
};

let words = [];
let usedWords = [];

function initGame(container, config) {
    gameConfig = config;
    
    // Set words based on difficulty
    words = [...wordLists[config.difficulty] || wordLists.medium];
    
    // Create canvas
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'word-scramble-canvas';
    gameCanvas.style.width = '100%';
    gameCanvas.style.height = '100%';
    container.appendChild(gameCanvas);
    ctx = gameCanvas.getContext('2d');
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    // Setup input handlers
    setupInputHandlers();
    
    showStartScreen();
}

function resizeCanvas() {
    const rect = gameCanvas.parentElement.getBoundingClientRect();
    gameCanvas.width = rect.width;
    gameCanvas.height = rect.height;
    
    if (gameState === 'playing') {
        render();
    }
}

function setupInputHandlers() {
    document.addEventListener('keydown', (e) => {
        if (gameState === 'ready' && e.key === ' ') {
            startGame();
            return;
        }
        
        if (gameState !== 'playing') return;
        
        if (e.key === 'Backspace') {
            userInput = userInput.slice(0, -1);
            render();
        } else if (e.key === 'Enter') {
            checkAnswer();
        } else if (e.key.length === 1 && /[a-zA-Z]/.test(e.key)) {
            if (userInput.length < currentWord.length) {
                userInput += e.key.toUpperCase();
                render();
            }
        }
    });
    
    gameCanvas.addEventListener('click', () => {
        if (gameState === 'ready') startGame();
        if (gameState === 'ended') restartGame();
    });
}

function showStartScreen() {
    gameState = 'ready';
    
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('📝 Word Scramble!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Unscramble the letters to form words!', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
    ctx.fillText('Type your answer and press ENTER', gameCanvas.width / 2, gameCanvas.height / 2 + 20);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
    
    ctx.font = '18px Nunito, sans-serif';
    ctx.fillStyle = '#FFD93D';
    ctx.fillText(`Difficulty: ${gameConfig.difficulty.toUpperCase()}`, gameCanvas.width / 2, gameCanvas.height / 2 + 150);
}

function startGame() {
    gameState = 'playing';
    score = 0;
    timeLeft = 60;
    wordsCompleted = 0;
    streak = 0;
    usedWords = [];
    userInput = '';
    
    nextWord();
    startTimer();
}

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft--;
        
        if (timeLeft <= 0) {
            endGame();
        } else {
            render();
        }
    }, 1000);
}

function nextWord() {
    // Get unused word
    const availableWords = words.filter(w => !usedWords.includes(w));
    
    if (availableWords.length === 0) {
        // Reset if all words used
        usedWords = [];
        availableWords.push(...words);
    }
    
    currentWord = availableWords[Math.floor(Math.random() * availableWords.length)];
    usedWords.push(currentWord);
    scrambledWord = scrambleWord(currentWord);
    userInput = '';
    
    render();
}

function scrambleWord(word) {
    let scrambled = word.split('');
    
    // Fisher-Yates shuffle
    for (let i = scrambled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [scrambled[i], scrambled[j]] = [scrambled[j], scrambled[i]];
    }
    
    // Make sure it's actually scrambled
    if (scrambled.join('') === word) {
        return scrambleWord(word);
    }
    
    return scrambled.join('');
}

function checkAnswer() {
    if (userInput.toUpperCase() === currentWord.toUpperCase()) {
        // Correct!
        streak++;
        const basePoints = currentWord.length * 10;
        const streakBonus = streak > 1 ? streak * 5 : 0;
        const timeBonus = Math.floor(timeLeft / 10) * 5;
        const wordScore = basePoints + streakBonus + timeBonus;
        
        score += wordScore;
        wordsCompleted++;
        
        // Add bonus time for correct answer
        timeLeft = Math.min(timeLeft + 5, 90);
        
        document.getElementById('current-score').textContent = score;
        
        showFeedback(true, `+${wordScore} points!`);
        
        setTimeout(() => {
            nextWord();
        }, 800);
    } else {
        // Wrong!
        streak = 0;
        showFeedback(false, 'Try again!');
        userInput = '';
        render();
    }
}

function showFeedback(correct, message) {
    render();
    
    ctx.fillStyle = correct ? 'rgba(107, 203, 119, 0.9)' : 'rgba(255, 71, 87, 0.9)';
    ctx.fillRect(gameCanvas.width / 2 - 150, gameCanvas.height / 2 + 80, 300, 60);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px Nunito, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(correct ? '✓ ' + message : '✗ ' + message, gameCanvas.width / 2, gameCanvas.height / 2 + 120);
}

function render() {
    // Background gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#2c3e50');
    gradient.addColorStop(1, '#3498db');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    // Timer bar
    const timerWidth = (timeLeft / 60) * (gameCanvas.width - 40);
    const timerColor = timeLeft > 20 ? '#6BCB77' : timeLeft > 10 ? '#FFD93D' : '#FF4757';
    
    ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
    ctx.fillRect(20, 20, gameCanvas.width - 40, 20);
    ctx.fillStyle = timerColor;
    ctx.fillRect(20, 20, timerWidth, 20);
    
    // Stats
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`⏱️ ${timeLeft}s`, 20, 65);
    ctx.textAlign = 'center';
    ctx.fillText(`🔥 Streak: ${streak}`, gameCanvas.width / 2, 65);
    ctx.textAlign = 'right';
    ctx.fillText(`📊 Score: ${score}`, gameCanvas.width - 20, 65);
    
    // Words completed
    ctx.textAlign = 'center';
    ctx.font = '16px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText(`Words: ${wordsCompleted}`, gameCanvas.width / 2, 90);
    
    // Scrambled word
    ctx.font = 'bold 64px Fredoka One, sans-serif';
    ctx.fillStyle = '#FFD93D';
    ctx.textAlign = 'center';
    
    // Draw each letter with spacing
    const letterSpacing = 60;
    const startX = gameCanvas.width / 2 - (scrambledWord.length - 1) * letterSpacing / 2;
    
    for (let i = 0; i < scrambledWord.length; i++) {
        const x = startX + i * letterSpacing;
        const y = gameCanvas.height / 2 - 40;
        
        // Letter background
        ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.beginPath();
        ctx.roundRect(x - 25, y - 45, 50, 60, 10);
        ctx.fill();
        
        // Letter
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(scrambledWord[i], x, y);
    }
    
    // User input boxes
    const inputY = gameCanvas.height / 2 + 60;
    const inputStartX = gameCanvas.width / 2 - (currentWord.length - 1) * letterSpacing / 2;
    
    for (let i = 0; i < currentWord.length; i++) {
        const x = inputStartX + i * letterSpacing;
        
        // Input box
        ctx.fillStyle = userInput[i] ? 'rgba(77, 150, 255, 0.3)' : 'rgba(255, 255, 255, 0.1)';
        ctx.strokeStyle = userInput[i] ? '#4D96FF' : 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.roundRect(x - 25, inputY - 45, 50, 60, 10);
        ctx.fill();
        ctx.stroke();
        
        // User input letter
        if (userInput[i]) {
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 48px Fredoka One, sans-serif';
            ctx.fillText(userInput[i], x, inputY);
        }
    }
    
    // Hint text
    ctx.font = '18px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Type the word and press ENTER', gameCanvas.width / 2, gameCanvas.height - 40);
}

function endGame() {
    gameState = 'ended';
    clearInterval(timerInterval);
    
    // Submit score
    submitScore(score, {
        words_completed: wordsCompleted,
        highest_streak: streak,
        difficulty: gameConfig.difficulty
    }).then(result => {
        // Draw game over screen
        ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('⏰ TIME\'S UP!', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Final Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 30);
        
        ctx.font = '24px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText(`Words Completed: ${wordsCompleted}`, gameCanvas.width / 2, gameCanvas.height / 2 + 20);
        
        if (result.success && result.data.points_earned > 0) {
            ctx.fillStyle = '#6BCB77';
            ctx.fillText(`+${result.data.points_earned} Points Earned!`, gameCanvas.width / 2, gameCanvas.height / 2 + 60);
        }
        
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Click to Play Again', gameCanvas.width / 2, gameCanvas.height / 2 + 120);
    });
}

function restartGame() {
    location.reload();
}

window.restartGame = restartGame;