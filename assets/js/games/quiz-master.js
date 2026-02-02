/**
 * Quiz Master Game
 * Answer trivia questions to earn points!
 */

let gameCanvas, ctx;
let gameState = 'ready';
let score = 0;
let currentQuestion = 0;
let timeLeft = 15;
let correctAnswers = 0;
let gameConfig;
let timerInterval;
let selectedAnswer = null;
let showingResult = false;

// Quiz questions
const questions = [
    {
        question: "What is the capital of France?",
        options: ["London", "Berlin", "Paris", "Madrid"],
        correct: 2,
        category: "Geography"
    },
    {
        question: "Which planet is known as the Red Planet?",
        options: ["Venus", "Mars", "Jupiter", "Saturn"],
        correct: 1,
        category: "Science"
    },
    {
        question: "What is 15 × 7?",
        options: ["95", "105", "115", "125"],
        correct: 1,
        category: "Math"
    },
    {
        question: "Who painted the Mona Lisa?",
        options: ["Van Gogh", "Picasso", "Da Vinci", "Michelangelo"],
        correct: 2,
        category: "Art"
    },
    {
        question: "What is the largest ocean on Earth?",
        options: ["Atlantic", "Indian", "Arctic", "Pacific"],
        correct: 3,
        category: "Geography"
    },
    {
        question: "In what year did World War II end?",
        options: ["1943", "1944", "1945", "1946"],
        correct: 2,
        category: "History"
    },
    {
        question: "What is the chemical symbol for Gold?",
        options: ["Go", "Gd", "Au", "Ag"],
        correct: 2,
        category: "Science"
    },
    {
        question: "Which country is home to the kangaroo?",
        options: ["New Zealand", "South Africa", "Australia", "Brazil"],
        correct: 2,
        category: "Geography"
    },
    {
        question: "How many sides does a hexagon have?",
        options: ["5", "6", "7", "8"],
        correct: 1,
        category: "Math"
    },
    {
        question: "What is the fastest land animal?",
        options: ["Lion", "Cheetah", "Horse", "Gazelle"],
        correct: 1,
        category: "Nature"
    }
];

let shuffledQuestions = [];

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
    
    if (gameState === 'playing') {
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
    
    const question = shuffledQuestions[currentQuestion];
    const isCorrect = index === question.correct;
    
    if (isCorrect) {
        const timeBonus = Math.floor(timeLeft * 2);
        const questionScore = 50 + timeBonus;
        score += questionScore;
        correctAnswers++;
        document.getElementById('current-score').textContent = score;
    }
    
    render();
    
    // Show result for 2 seconds then move to next question
    setTimeout(() => {
        showingResult = false;
        selectedAnswer = null;
        currentQuestion++;
        
        if (currentQuestion >= shuffledQuestions.length) {
            endGame();
        } else {
            timeLeft = 15;
            startTimer();
            render();
        }
    }, 2000);
}

function showStartScreen() {
    gameState = 'ready';
    
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 48px Fredoka One, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('❓ Quiz Master!', gameCanvas.width / 2, gameCanvas.height / 2 - 80);
    
    ctx.font = '24px Nunito, sans-serif';
    ctx.fillStyle = '#a0a0a0';
    ctx.fillText('Test your knowledge!', gameCanvas.width / 2, gameCanvas.height / 2 - 20);
    ctx.fillText(`${questions.length} questions • 15 seconds each`, gameCanvas.width / 2, gameCanvas.height / 2 + 20);
    
    ctx.fillStyle = '#4D96FF';
    ctx.font = 'bold 28px Nunito, sans-serif';
    ctx.fillText('Click or Press SPACE to Start', gameCanvas.width / 2, gameCanvas.height / 2 + 100);
}

function startGame() {
    gameState = 'playing';
    score = 0;
    currentQuestion = 0;
    correctAnswers = 0;
    timeLeft = 15;
    selectedAnswer = null;
    showingResult = false;
    
    // Shuffle questions
    shuffledQuestions = [...questions].sort(() => Math.random() - 0.5);
    
    startTimer();
    render();
}

function startTimer() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        
        if (timeLeft <= 0) {
            // Time's up - treat as wrong answer
            clearInterval(timerInterval);
            showingResult = true;
            render();
            
            setTimeout(() => {
                showingResult = false;
                currentQuestion++;
                
                if (currentQuestion >= shuffledQuestions.length) {
                    endGame();
                } else {
                    timeLeft = 15;
                    startTimer();
                    render();
                }
            }, 1500);
        } else {
            render();
        }
    }, 1000);
}

function render() {
    // Background
    const gradient = ctx.createLinearGradient(0, 0, 0, gameCanvas.height);
    gradient.addColorStop(0, '#0f0c29');
    gradient.addColorStop(0.5, '#302b63');
    gradient.addColorStop(1, '#24243e');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
    
    const question = shuffledQuestions[currentQuestion];
    
    // Progress bar
    const progress = (currentQuestion + 1) / shuffledQuestions.length;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
    ctx.fillRect(20, 20, gameCanvas.width - 40, 10);
    ctx.fillStyle = '#6BCB77';
    ctx.fillRect(20, 20, (gameCanvas.width - 40) * progress, 10);
    
    // Question counter
    ctx.fillStyle = '#ffffff';
    ctx.font = '16px Nunito, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`Question ${currentQuestion + 1}/${shuffledQuestions.length}`, 20, 55);
    
    // Category badge
    ctx.textAlign = 'center';
    ctx.fillStyle = '#9B59B6';
    ctx.font = 'bold 14px Nunito, sans-serif';
    const categoryWidth = ctx.measureText(question.category).width + 20;
    ctx.fillRect(gameCanvas.width / 2 - categoryWidth / 2, 40, categoryWidth, 25);
    ctx.fillStyle = '#ffffff';
    ctx.fillText(question.category, gameCanvas.width / 2, 58);
    
    // Score
    ctx.textAlign = 'right';
    ctx.font = 'bold 20px Nunito, sans-serif';
    ctx.fillStyle = '#FFD93D';
    ctx.fillText(`Score: ${score}`, gameCanvas.width - 20, 55);
    
    // Timer
    const timerColor = timeLeft > 10 ? '#6BCB77' : timeLeft > 5 ? '#FFD93D' : '#FF4757';
    ctx.fillStyle = timerColor;
    ctx.font = 'bold 36px Nunito, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`⏱️ ${timeLeft}`, gameCanvas.width / 2, 110);
    
    // Question text
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px Nunito, sans-serif';
    ctx.textAlign = 'center';
    
    // Word wrap question
    const maxWidth = gameCanvas.width - 80;
    const words = question.question.split(' ');
    let line = '';
    let y = 170;
    
    for (const word of words) {
        const testLine = line + word + ' ';
        if (ctx.measureText(testLine).width > maxWidth) {
            ctx.fillText(line.trim(), gameCanvas.width / 2, y);
            line = word + ' ';
            y += 35;
        } else {
            line = testLine;
        }
    }
    ctx.fillText(line.trim(), gameCanvas.width / 2, y);
    
    // Answer options
    const optionHeight = 60;
    const optionWidth = gameCanvas.width - 80;
    const startY = gameCanvas.height / 2 - 20;
    
    for (let i = 0; i < question.options.length; i++) {
        const optionY = startY + i * (optionHeight + 15);
        
        let bgColor = 'rgba(255, 255, 255, 0.1)';
        let borderColor = 'rgba(255, 255, 255, 0.3)';
        let textColor = '#ffffff';
        
        if (showingResult) {
            if (i === question.correct) {
                bgColor = 'rgba(107, 203, 119, 0.8)';
                borderColor = '#6BCB77';
            } else if (i === selectedAnswer && i !== question.correct) {
                bgColor = 'rgba(255, 71, 87, 0.8)';
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
        ctx.fillText(question.options[i], 100, optionY + 38);
    }
    
    // Instructions
    if (!showingResult) {
        ctx.font = '14px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.textAlign = 'center';
        ctx.fillText('Click an option or press 1-4', gameCanvas.width / 2, gameCanvas.height - 30);
    }
}

function endGame() {
    gameState = 'ended';
    clearInterval(timerInterval);
    
    submitScore(score, {
        correct_answers: correctAnswers,
        total_questions: shuffledQuestions.length,
        accuracy: Math.round((correctAnswers / shuffledQuestions.length) * 100)
    }).then(result => {
        ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
        ctx.fillRect(0, 0, gameCanvas.width, gameCanvas.height);
        
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 48px Fredoka One, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🎉 Quiz Complete!', gameCanvas.width / 2, gameCanvas.height / 2 - 100);
        
        ctx.font = 'bold 36px Nunito, sans-serif';
        ctx.fillStyle = '#FFD93D';
        ctx.fillText(`Score: ${score}`, gameCanvas.width / 2, gameCanvas.height / 2 - 30);
        
        ctx.font = '24px Nunito, sans-serif';
        ctx.fillStyle = '#a0a0a0';
        ctx.fillText(`${correctAnswers}/${shuffledQuestions.length} Correct (${Math.round((correctAnswers / shuffledQuestions.length) * 100)}%)`, gameCanvas.width / 2, gameCanvas.height / 2 + 20);
        
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