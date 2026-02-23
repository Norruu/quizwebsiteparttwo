/**
 * NIR Agri-Quiz Game
 * Answer trivia questions about Negros Island Region agriculture!
 * Features a Leveling System and Health/Lives.
 */

let gameCanvas, ctx;
let gameState = 'ready'; // ready, playing, levelup, ended
let score = 0;
let lives = 3;
let level = 1;
let currentQuestionIndex = 0;
let questionsAnsweredInLevel = 0;
let timeLeft = 15;
let correctAnswers = 0;
let gameConfig;
let timerInterval;
let selectedAnswer = null;
let showingResult = false;

// NIR Agriculture & Production Questions Grouped by Difficulty
const questionBank = {
    easy: [
        { question: "Which crop makes Negros the 'Sugarbowl of the Philippines'?", options: ["Corn", "Rice", "Sugarcane", "Coconut"], correct: 2, category: "Agriculture" },
        { question: "What animal is commonly raised in local backyards for its pork?", options: ["Cow", "Goat", "Pig (Swine)", "Sheep"], correct: 2, category: "Livestock" },
        { question: "What staple grain is primarily grown in the irrigated flatlands of the region?", options: ["Wheat", "Rice", "Oats", "Barley"], correct: 1, category: "Agriculture" },
        { question: "What sweet tropical fruit from Guimaras and Negros is heavily exported?", options: ["Papaya", "Mango", "Durian", "Lanzones"], correct: 1, category: "Production" },
        { question: "Bangus is heavily farmed in coastal aquaculture. What is its English name?", options: ["Milkfish", "Tilapia", "Catfish", "Salmon"], correct: 0, category: "Aquaculture" },
        { question: "What is the primary commercial product extracted from sugarcane?", options: ["Flour", "Salt", "Sugar", "Vinegar"], correct: 2, category: "Production" },
        { question: "What farm animal is raised in large poultry houses for eggs and meat?", options: ["Duck", "Quail", "Turkey", "Chicken"], correct: 3, category: "Livestock" },
    ],
    medium: [
        { question: "What sweet local flatbread is primarily made using muscovado sugar?", options: ["Pandesal", "Piaya", "Ensaymada", "Hopia"], correct: 1, category: "Production" },
        { question: "Which elevated NIR city is heavily known for its vegetable terraces?", options: ["Bacolod", "Dumaguete", "Canlaon", "Sipalay"], correct: 2, category: "Geography" },
        { question: "Copra is the dried meat of which widely grown agricultural product?", options: ["Mango", "Cacao", "Coconut", "Papaya"], correct: 2, category: "Agriculture" },
        { question: "What raw material grown in local farms is fermented and processed into Tablea?", options: ["Coffee Beans", "Sugarcane", "Cacao Pods", "Cassava"], correct: 2, category: "Production" },
        { question: "What farming method avoids synthetic pesticides and is heavily championed in Negros?", options: ["Hydroponics", "Slash-and-burn", "Organic Farming", "Monoculture"], correct: 2, category: "Science" },
        { question: "Saba is a widely grown local variety of which fruit used for Turon?", options: ["Mango", "Banana", "Pineapple", "Guava"], correct: 1, category: "Agriculture" },
        { question: "Which city is known as the 'Rice Granary of Negros Occidental'?", options: ["Bago City", "Kabankalan", "Victorias", "Silay"], correct: 0, category: "Geography" },
    ],
    hard: [
        { question: "What is the traditional term for large agricultural sugarcane estates in Negros?", options: ["Plantation", "Hacienda", "Ranch", "Orchard"], correct: 1, category: "History" },
        { question: "What is the fibrous residue left after sugarcane stalks are crushed, often used for fuel?", options: ["Bagasse", "Molasses", "Mudpress", "Ash"], correct: 0, category: "Science" },
        { question: "What local harvest season is traditionally referred to as 'Tiempo Suerte'?", options: ["Rice Harvest", "Mango Season", "Sugarcane Harvest", "Fishing Season"], correct: 2, category: "Culture" },
        { question: "Which hardy coffee bean variety thrives in the lower mountain elevations of the region?", options: ["Arabica", "Robusta", "Liberica", "Excelsa"], correct: 1, category: "Agriculture" },
        { question: "What specific agricultural pest bores into the stems of local rice and sugarcane?", options: ["Locust", "Aphid", "Stem Borer", "Weevil"], correct: 2, category: "Science" },
        { question: "What is the term for healthy, unrefined local sugar that retains its natural molasses?", options: ["Refined White", "Muscovado", "Confectioners", "Brown Sugar"], correct: 1, category: "Production" },
        { question: "What byproduct of sugarcane milling is commonly fermented to produce bioethanol?", options: ["Bagasse", "Molasses", "Mudpress", "Juice"], correct: 1, category: "Science" },
    ]
};

let currentLevelQuestions = [];
let currentQuestion = null;

function initGame(container, config) {
    gameConfig = config;
    
    gameCanvas = document.createElement('canvas');
    gameCanvas.id = 'quiz-master-canvas';
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
    
    if (gameState === 'playing' || gameState === 'levelup') {
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
        
        if (gameState === 'playing' && !showingResult) {
            handleClick(e);
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (gameState === 'ready' && e.key === ' ') {
            startGame();
            return;
        }
        
        if (gameState === 'playing' && !showingResult) {
            const keyNum = parseInt(e.key);
            if (keyNum >= 1 && keyNum <= 4) {
                selectAnswer(keyNum - 1);
            }
        }
    });
}

function handleClick(e) {
    const rect = gameCanvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    // Check if clicked on an answer option
    const optionHeight = 60;
    const optionWidth = gameCanvas.width - 80;
    const startY = gameCanvas.height / 2 - 20;
    
    for (let i = 0; i < 4; i++) {
        const optionY = startY + i * (optionHeight + 15);
        
        if (x >= 40 && x <= 40 + optionWidth &&
            y >= optionY && y <= optionY + optionHeight) {
            selectAnswer(i);
            break;
        }
    }
}

function selectAnswer(index) {
    if (showingResult) return;
    
    selectedAnswer = index;
    showingResult = true;
    clearInterval(timerInterval);
    
    const isCorrect = index === currentQuestion.correct;
    
    if (isCorrect) {
        if (typeof playGameSound === 'function') playGameSound('success');
        const timeBonus = Math.floor(timeLeft * 2);
        const questionScore = (level * 50) + timeBonus; // Higher levels give more base points
        score += questionScore;
        correctAnswers++;
        questionsAnsweredInLevel++;
        
        const scoreElement = document.getElementById('current-score');
        if(scoreElement) scoreElement.textContent = score;
        
    } else {
        lives--; // Wrong answer costs a life
        if (typeof playGameSound === 'function') playGameSound('fail');
    }
    
    render();
    
    // Show result for 1.5 seconds then evaluate next step
    setTimeout(() => {
        showingResult = false;
        selectedAnswer = null;
        
        if (lives <= 0) {
            endGame(false); // Game Over (Lost)
            return;
        }
        
        // Level up condition: 5 correct answers per level
        if (questionsAnsweredInLevel >= 5) {
            if (level === 3) {
                endGame(true); // Game Over (Won!)
            } else {
                levelUp();
            }
        } else {
            currentQuestionIndex++;
            if (currentQuestionIndex >= currentLevelQuestions.length) {
                // Failsafe: if we run out of questions in the array, shuffle and restart array
                currentLevelQuestions = [...currentLevelQuestions].sort(() => Math.random() - 0.5);
                currentQuestionIndex = 0;
            }
            prepareNextQuestion();
        }
    }, 1500);
}

function showStartScreen() {
    gameState = 'ready';
    
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('❓ NIR Agri-Quiz!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Test your local farming knowledge!', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
    
    ctx.fillStyle = '#FF4757';
    ctx.fillText('❤️ 3 Lives • 3 Levels', gameCanvas.width / 2, gameCanvas.height / 2 + 20);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
}

function startGame() {
    score = 0;
    lives = 3;
    level = 1;
    correctAnswers = 0;
    if (typeof playGameSound === 'function') playGameSound('click');
    loadLevel(level);
}

function loadLevel(lvl) {
    gameState = 'playing';
    questionsAnsweredInLevel = 0;
    currentQuestionIndex = 0;
    
    // Select questions based on level
    let pool = [];
    if (lvl === 1) pool = questionBank.easy;
    else if (lvl === 2) pool = questionBank.medium;
    else pool = questionBank.hard;
    
    // Shuffle the pool for this level
    currentLevelQuestions = [...pool].sort(() => Math.random() - 0.5);
    
    prepareNextQuestion();
}

function prepareNextQuestion() {
    currentQuestion = currentLevelQuestions[currentQuestionIndex];
    showingResult = false;
    
    // Set time limits based on level difficulty
    if (level === 1) timeLeft = 15;
    else if (level === 2) timeLeft = 12;
    else timeLeft = 10;
    
    startTimer();
    render();
}

function levelUp() {
    gameState = 'levelup';
    if (typeof playGameSound === 'function') playGameSound('levelup');
    clearInterval(timerInterval);
    level++;
    
    render(); // Draw the level up screen
    
    setTimeout(() => {
        loadLevel(level);
    }, 2500);
}

function startTimer() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        
        if (timeLeft <= 0) {
            // Time's up - treat as wrong answer
            clearInterval(timerInterval);
            showingResult = true;
            lives--; // Timeout costs a life
            if (typeof playGameSound === 'function') playGameSound('fail');
            render();
            
            setTimeout(() => {
                showingResult = false;
                
                if (lives <= 0) {
                    endGame(false);
                    return;
                }
                
                currentQuestionIndex++;
                if (currentQuestionIndex >= currentLevelQuestions.length) {
                    currentLevelQuestions = [...currentLevelQuestions].sort(() => Math.random() - 0.5);
                    currentQuestionIndex = 0;
                }
                prepareNextQuestion();
            }, 1500);
        } else {
            render();
        }
    }, 1000);
}

function render() {
    // Level Up Screen Overlay
    if (gameState === 'levelup') {
        ctx.fillStyle = 'rgba(39, 174, 96, 0.9)'; // Green Agri theme
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 56px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('LEVEL UP! 🌟', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
        
        ctx.font = 'bold 28px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Entering Level ${level}`, gameCanvas.width / 2, gameCanvas.height / 2 + 40);
        
        let msg = level === 2 ? "Questions are harder! Time limit: 12s" : "Maximum Difficulty! Time limit: 10s";
        ctx.font = '20px Nunito, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.fillText(msg, gameCanvas.width / 2, gameCanvas.height / 2 + 80);
        return;
    }

    // Normal Gameplay Background
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#0f0c29');
    gradient.addColorStop(0.5, '#302b63');
    gradient.addColorStop(1, '#24243e');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    if (!currentQuestion) return;

    // Progress bar for the current level (5 questions per level)
    const progress = questionsAnsweredInLevel / 5;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
    ctx.fillRect(20, 20, gameCanvas.width - 40, 10);
    ctx.fillStyle = '#6BCB77';
    ctx.fillRect(20, 20, (gameCanvas.width - 40) * progress, 10);
    
    // Stats Header (Level, Lives, Score)
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 18px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`Level ${level}`, 20, 55);
    
    ctx.textAlign = 'center';
    ctx.fillText(`Lives: ${'❤️'.repeat(lives)}${'🖤'.repeat(3 - lives)}`, gameCanvas.width / 2, 55);
    
    ctx.textAlign = 'right';
    ctx.fillStyle = '#FFD93D';
    ctx.fillText(`Score: ${score}`, gameCanvas.width - 20, 55);
    
    // Category badge
    ctx.textAlign = 'center';
    ctx.fillStyle = '#27ae60'; // Agricultural green
    ctx.font = 'bold 14px Nunito, sans-serif';
    const categoryWidth = ctx.measureText(currentQuestion.category).width + 20;
    ctx.fillRect(gameCanvas.width / 2 - categoryWidth / 2, 70, categoryWidth, 25);
    ctx.fillStyle = '#ffffff';
    ctx.fillText(currentQuestion.category, gameCanvas.width / 2, 88);
    
    // Timer
    const timerColor = timeLeft > (level === 1 ? 8 : 5) ? '#6BCB77' : timeLeft > 3 ? '#FFD93D' : '#FF4757';
    ctx.fillStyle = timerColor;
    ctx.font = 'bold 36px Nunito, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`⏱️ ${timeLeft}`, gameCanvas.width / 2, 130);
    
    // Question text (Word wrapped)
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 22px Nunito, sans-serif';
    ctx.textAlign = 'center';
    
    const maxWidth = gameCanvas.width - 60;
    const words = currentQuestion.question.split(' ');
    let line = '';
    let y = 180;
    
    for (const word of words) {
        const testLine = line + word + ' ';
        if (ctx.measureText(testLine).width > maxWidth) {
            ctx.fillText(line.trim(), gameCanvas.width / 2, y);
            line = word + ' ';
            y += 32;
        } else {
            line = testLine;
        }
    }
    ctx.fillText(line.trim(), gameCanvas.width / 2, y);
    
    // Answer options
    const optionHeight = 60;
    const optionWidth = gameCanvas.width - 80;
    const startY = gameCanvas.height / 2 - 10;
    
    for (let i = 0; i < currentQuestion.options.length; i++) {
        const optionY = startY + i * (optionHeight + 15);
        
        let bgColor = 'rgba(255, 255, 255, 0.1)';
        let borderColor = 'rgba(255, 255, 255, 0.3)';
        let textColor = '#ffffff';
        
        if (showingResult) {
            if (i === currentQuestion.correct) {
                bgColor = 'rgba(107, 203, 119, 0.8)'; // Green if correct
                borderColor = '#6BCB77';
            } else if (i === selectedAnswer && i !== currentQuestion.correct) {
                bgColor = 'rgba(255, 71, 87, 0.8)'; // Red if selected wrong
                borderColor = '#FF4757';
            }
        } else if (selectedAnswer === i) {
            bgColor = 'rgba(77, 150, 255, 0.5)';
            borderColor = '#4D96FF';
        }
        
        // Option background
        ctx.fillStyle = bgColor;
        ctx.strokeStyle = borderColor;
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.roundRect(40, optionY, optionWidth, optionHeight, 15);
        ctx.fill();
        ctx.stroke();
        
        // Option number
        ctx.fillStyle = textColor;
        ctx.font = 'bold 18px Nunito, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(`${i + 1}`, 60, optionY + 38);
        
        // Option text
        ctx.font = '18px Nunito, sans-serif';
        ctx.fillText(currentQuestion.options[i], 100, optionY + 38);
    }
    
    // Instructions
    if (!showingResult) {
        ctx.font = '14px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.textAlign = 'center';
        ctx.fillText('Click an option or press 1-4', gameCanvas.width / 2, gameCanvas.height - 20);
    }
}

function endGame(won) {
    gameState = 'ended';
    if (typeof playGameSound === 'function') playGameSound(won ? 'success' : 'gameover');
    clearInterval(timerInterval);
    
    // 5 questions per level * 3 levels max = 15 total possible correct
    let maxPossible = 15; 
    let accuracy = Math.round((correctAnswers / (correctAnswers + (3 - lives))) * 100) || 0;
    
    submitScore(score, {
        correct_answers: correctAnswers,
        level_reached: level,
        accuracy: accuracy
    }).then(result => {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        
        if (won) {
            ctx.fillStyle = '#FFD93D';
            ctx.fillText('🏆 QUIZ MASTER!', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        } else {
            ctx.fillStyle = '#FF4757';
            ctx.fillText('💔 GAME OVER', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        }
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.fillText(`Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 30);
        
        ctx.font = '24px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText(`Reached Level ${level} • ${correctAnswers} Correct`, gameCanvas.width / 2, gameCanvas.height / 2 + 20);
        
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