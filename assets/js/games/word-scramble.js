/**
 * Agri-Scramble Game - NIR Edition
 * Unscramble words related to Negros Island Region crops and farming!
 * Features an educational explanation popup after every correct word.
 */

let gameCanvas, ctx;
let gameState = 'ready'; // ready, playing, explaining, ended
let score = 0;
let currentWordObj = null;
let currentWord = '';
let scrambledWord = '';
let userInput = '';
let timeLeft = 60;
let wordsCompleted = 0;
let streak = 0;
let gameConfig;
let timerInterval;
let lastWordScore = 0; // To show on the explanation screen

// Word lists by difficulty - Includes hints and post-success explanations!
const wordLists = {
    easy: [
        { word: 'RICE', hint: 'A staple grain grown in flatlands.', explanation: 'Negros produces significant amounts of rice in its flatlands to ensure local food security.' },
        { word: 'CORN', hint: 'A major alternative staple and poultry feed.', explanation: 'Corn is a vital crop for the local poultry and livestock feed industry in the region.' },
        { word: 'FISH', hint: 'Caught daily by local coastal communities.', explanation: 'Coastal towns in Negros rely heavily on fishing for their daily livelihood and food supply.' },
        { word: 'PIG', hint: 'Common backyard livestock.', explanation: 'Swine raising is a very common backyard industry for rural families in the region.' },
        { word: 'COW', hint: 'Cattle raised for dairy and beef.', explanation: 'Cattle are raised for beef and dairy, with some local farms producing fresh organic milk.' },
        { word: 'FARM', hint: 'A piece of land dedicated to agriculture.', explanation: 'Farms are the backbone of the Negros economy, providing jobs and food for the region.' },
        { word: 'SOIL', hint: 'The fertile earth in which crops are planted.', explanation: 'The volcanic soil near Mt. Kanlaon is incredibly fertile, making it perfect for agriculture.' },
        { word: 'CROP', hint: 'Cultivated plants grown for food or profit.', explanation: 'The region diversifies its crops to ensure farmers have income even when sugar prices drop.' },
        { word: 'MILK', hint: 'Fresh dairy product from local cattle.', explanation: 'There is a growing local dairy industry in Negros pushing for fresh, locally-sourced milk.' },
        { word: 'EGG', hint: 'Produced daily by local poultry farms.', explanation: 'Poultry farming provides a steady daily supply of fresh eggs to local markets and bakeries.' }
    ],
    medium: [
        { word: 'SUGAR', hint: 'The primary agricultural product of Negros.', explanation: 'Negros produces over half of the total sugar output of the Philippines, driving its economy.' },
        { word: 'COPRA', hint: 'Dried coconut meat.', explanation: 'Coconut farmers dry the meat into copra, which is then pressed into valuable coconut oil.' },
        { word: 'CACAO', hint: 'Pods processed locally to make tablea.', explanation: 'Local farmers are increasingly planting cacao to produce high-quality artisanal chocolates.' },
        { word: 'COFFEE', hint: 'Grown in the cool highlands of Mt. Kanlaon.', explanation: 'High-altitude areas like La Castellana produce excellent Robusta and Arabica coffee blends.' },
        { word: 'MANGO', hint: 'A sweet tropical fruit heavily exported.', explanation: 'Guimaras and Negros produce some of the sweetest export-quality mangoes in the world.' },
        { word: 'BANANA', hint: 'The Saba variety is very common locally.', explanation: 'Saba bananas are grown everywhere and are a key ingredient in local delicacies like turon.' },
        { word: 'PAPAYA', hint: 'A healthy tropical fruit grown year-round.', explanation: 'A fast-growing tropical fruit that provides farmers with a quick and steady harvest.' },
        { word: 'FARMER', hint: 'The hardworking people who grow our food.', explanation: 'Farmers are the backbone of the region, dedicating their lives to feeding the community.' },
        { word: 'CATTLE', hint: 'Livestock raised in local ranches.', explanation: 'Commercial ranches and smallholders alike raise cattle to supply the local meat markets.' },
        { word: 'POULTRY', hint: 'Farms dedicated to raising chickens.', explanation: 'The poultry industry supplies the massive demand for chicken in local restaurants like Inasal.' }
    ],
    hard: [
        { word: 'SUGARCANE', hint: "The tall grass crop that drives the economy.", explanation: "Known as the 'Sugarbowl of the Philippines', this tall grass crop dominates the Negros landscape." },
        { word: 'MUSCOVADO', hint: 'Unrefined local sugar with a rich flavor.', explanation: 'This healthy, unrefined sugar retains its natural molasses and is a specialty of Antique and Negros.' },
        { word: 'MILKFISH', hint: 'Heavily farmed in coastal ponds (Bangus).', explanation: 'Also known as Bangus, it is heavily farmed in coastal brackish water ponds across the region.' },
        { word: 'ROBUSTA', hint: 'A hardy coffee bean variety.', explanation: 'This hardy coffee bean thrives in the lower mountain elevations of the Negros Island Region.' },
        { word: 'HACIENDA', hint: 'Large agricultural estates in Negros.', explanation: 'These large agricultural estates are a historical and major part of the Negros sugar industry.' },
        { word: 'ORGANIC', hint: 'Farming without synthetic chemicals.', explanation: 'Negros Island is heavily recognized as the organic agriculture capital of the Philippines.' },
        { word: 'TILAPIA', hint: 'Freshwater fish raised in inland aquaculture.', explanation: 'A fast-growing freshwater fish that provides a cheap and rich protein source for locals.' },
        { word: 'HARVEST', hint: 'The seasonal process of gathering crops.', explanation: 'The sugarcane harvest season, known locally as "Tiempo Suerte", is a busy time for the region.' },
        { word: 'LIVESTOCK', hint: 'Farm animals raised for food and labor.', explanation: 'Raising farm animals acts as a living savings account for many rural Negrosanon families.' },
        { word: 'IRRIGATION', hint: 'Artificial application of water to crops.', explanation: 'Proper water management is crucial for local farms to survive the intense dry summer months.' }
    ]
};

let words = [];
let usedWords = [];

function initGame(container, config) {
    gameConfig = config;
    words = [...wordLists[config.difficulty] || wordLists.medium];
    
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'word-scramble-canvas';
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
    
    if (gameState === 'playing' || gameState === 'explaining') {
        render();
    }
}

function setupInputHandlers() {
    document.addEventListener('keydown', (e) => {
        if (gameState === 'ready' && e.key === ' ') {
            startGame();
            return;
        }
        
        // Handle proceeding to the next word after reading the explanation
        if (gameState === 'explaining' && e.key === 'Enter') {
            nextWord();
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
        if (gameState === 'explaining') nextWord(); // Click to continue reading
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
    ctx.fillText('🌾 Agri-Scramble!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Read the hints to unscramble NIR crops!', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
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
    
    if (typeof playGameSound === 'function') playGameSound('click');
    
    nextWord();
    startTimer();
}

function startTimer() {
    timerInterval = setInterval(() => {
        // ONLY decrement the timer if the user is actively playing. 
        // Timer pauses while reading the explanation!
        if (gameState === 'playing') {
            timeLeft--;
            
            if (timeLeft <= 0) {
                endGame();
            } else {
                render();
            }
        }
    }, 1000);
}

function nextWord() {
    gameState = 'playing'; // Resume playing state
    
    const availableWords = words.filter(w => !usedWords.includes(w.word));
    
    if (availableWords.length === 0) {
        usedWords = [];
        availableWords.push(...words);
    }
    
    currentWordObj = availableWords[Math.floor(Math.random() * availableWords.length)];
    currentWord = currentWordObj.word;
    usedWords.push(currentWord);
    scrambledWord = scrambleWord(currentWord);
    userInput = '';
    
    render();
}

function scrambleWord(word) {
    let scrambled = word.split('');
    
    for (let i = scrambled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [scrambled[i], scrambled[j]] = [scrambled[j], scrambled[i]];
    }
    
    if (scrambled.join('') === word) {
        return scrambleWord(word);
    }
    
    return scrambled.join('');
}

function checkAnswer() {
    if (userInput.toUpperCase() === currentWord.toUpperCase()) {
        // Correct!
        if (typeof playGameSound === 'function') playGameSound('success');
        streak++;
        const basePoints = currentWord.length * 10;
        const streakBonus = streak > 1 ? streak * 5 : 0;
        const timeBonus = Math.floor(timeLeft / 10) * 5;
        const wordScore = basePoints + streakBonus + timeBonus;
        
        score += wordScore;
        wordsCompleted++;
        
        // Add bonus time for correct answer
        timeLeft = Math.min(timeLeft + 5, 90);
        
        const scoreElement = document.getElementById('current-score');
        if (scoreElement) {
            scoreElement.textContent = score;
        }
        
        lastWordScore = wordScore;
        
        // Switch to explaining state instead of automatically jumping to next word
        gameState = 'explaining';
        render(); 
    } else {
        // Wrong!
        streak = 0;
        showFeedback(false, 'Try again!');
        if (typeof playGameSound === 'function') playGameSound('fail');
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

// Helper function to wrap text neatly on the canvas
function wrapText(context, text, x, y, maxWidth, lineHeight) {
    const words = text.split(' ');
    let line = '';

    for (let n = 0; n < words.length; n++) {
        const testLine = line + words[n] + ' ';
        const metrics = context.measureText(testLine);
        const testWidth = metrics.width;
        
        if (testWidth > maxWidth && n > 0) {
            context.fillText(line, x, y);
            line = words[n] + ' ';
            y += lineHeight;
        } else {
            line = testLine;
        }
    }
    context.fillText(line, x, y);
}

function render() {
    // 1. Draw Normal Game Background & Elements First
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#2c3e50');
    gradient.addColorStop(1, '#27ae60'); // Earthy green theme
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    const timerWidth = (timeLeft / 60) * (gameCanvas.width - 40);
    const timerColor = timeLeft > 20 ? '#6BCB77' : timeLeft > 10 ? '#FFD93D' : '#FF4757';
    
    ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
    ctx.fillRect(20, 20, gameCanvas.width - 40, 20);
    ctx.fillStyle = timerColor;
    ctx.fillRect(20, 20, timerWidth, 20);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`⏱️ ${timeLeft}s`, 20, 65);
    ctx.textAlign = 'center';
    ctx.fillText(`🔥 Streak: ${streak}`, gameCanvas.width / 2, 65);
    ctx.textAlign = 'right';
    ctx.fillText(`📊 Score: ${score}`, gameCanvas.width - 20, 65);
    
    ctx.textAlign = 'center';
    ctx.font = '16px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText(`Words: ${wordsCompleted}`, gameCanvas.width / 2, 90);
    
    ctx.font = 'bold 64px Fredoka One, sans-serif';
    ctx.fillStyle = '#FFD93D';
    ctx.textAlign = 'center';
    
    const letterSpacing = 60;
    const startX = gameCanvas.width / 2 - (scrambledWord.length - 1) * letterSpacing / 2;
    
    for (let i = 0; i < scrambledWord.length; i++) {
        const x = startX + i * letterSpacing;
        const y = gameCanvas.height / 2 - 50;
        
        ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.beginPath();
        ctx.roundRect(x - 25, y - 45, 50, 60, 10);
        ctx.fill();
        
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(scrambledWord[i], x, y);
    }
    
    const inputY = gameCanvas.height / 2 + 50;
    const inputStartX = gameCanvas.width / 2 - (currentWord.length - 1) * letterSpacing / 2;
    
    for (let i = 0; i < currentWord.length; i++) {
        const x = inputStartX + i * letterSpacing;
        
        ctx.fillStyle = userInput[i] ? 'rgba(77, 150, 255, 0.3)' : 'rgba(255, 255, 255, 0.1)';
        ctx.strokeStyle = userInput[i] ? '#4D96FF' : 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.roundRect(x - 25, inputY - 45, 50, 60, 10);
        ctx.fill();
        ctx.stroke();
        
        if (userInput[i]) {
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 48px Fredoka One, sans-serif';
            ctx.fillText(userInput[i], x, inputY);
        }
    }
    
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.fillStyle = '#FFD93D'; 
    ctx.fillText(`Hint: ${currentWordObj.hint}`, gameCanvas.width / 2, gameCanvas.height - 80);

    ctx.font = '16px Nunito, sans-serif';
    ctx.fillStyle = '#e0e0e0';
    ctx.fillText('Type the word and press ENTER', gameCanvas.width / 2, gameCanvas.height - 35);


    // 2. Draw the Educational Explanation Overlay if the word was just solved!
    if (gameState === 'explaining') {
        // Dark translucent overlay over the whole game
        ctx.fillStyle = 'rgba(0, 0, 0, 0.9)'; 
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#6BCB77';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('CORRECT! 🎉', gameCanvas.width / 2, gameCanvas.height / 2 - 120);
        
        ctx.fillStyle = '#FFD93D';
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillText(`${currentWordObj.word}`, gameCanvas.width / 2, gameCanvas.height / 2 - 50);
        
        ctx.fillStyle = '#a0a0a0';
        ctx.font = 'bold 20px Nunito, sans-serif';
        ctx.fillText(`+${lastWordScore} Points!`, gameCanvas.width / 2, gameCanvas.height / 2 - 15);
        
        // Draw the educational explanation using the wrap helper
        ctx.fillStyle = '#ffffff';
        ctx.font = '22px Nunito, sans-serif';
        wrapText(ctx, currentWordObj.explanation, gameCanvas.width / 2, gameCanvas.height / 2 + 50, gameCanvas.width - 80, 32);
        
        // Blinking prompt to continue
        ctx.fillStyle = '#4D96FF';
        ctx.font = 'bold 24px Nunito, sans-serif';
        ctx.fillText('Press ENTER to continue', gameCanvas.width / 2, gameCanvas.height - 50);
    }
}

function endGame() {
    gameState = 'ended';
    if (typeof playGameSound === 'function') playGameSound('gameover');
    clearInterval(timerInterval);
    
    submitScore(score, {
        words_completed: wordsCompleted,
        highest_streak: streak,
        difficulty: gameConfig.difficulty
    }).then(result => {
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