// Reusable utility functions for Game Library

function clamp(val, min, max) {
    return Math.max(min, Math.min(max, val));
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// Format seconds as MM:SS or H:MM:SS
function formatTime(seconds) {
    let h = Math.floor(seconds / 3600);
    let m = Math.floor((seconds % 3600) / 60);
    let s = Math.floor(seconds % 60);
    if (h > 0)
        return h + ":" + m.toString().padStart(2, '0') + ":" + s.toString().padStart(2, '0');
    return m + ":" + s.toString().padStart(2, '0');
}

// Show a dialog
function showDialog(message, type="info") {
    if (window.flash) {
        flash(message, type);
    } else {
        alert(message);
    }
}

// --- Sound Manager ---
let isGameMuted = localStorage.getItem('game_muted') === 'true';

// Helper to get correct path based on SITE_URL defined in footer
function getAudioPath(filename) {
    // If SITE_URL is defined (from footer), use it. Otherwise assume root.
    const base = (typeof SITE_URL !== 'undefined') ? SITE_URL : '/';
    // Remove trailing slash from base and leading slash from filename to avoid double slashes
    return base.replace(/\/$/, '') + '/assets/sounds/' + filename;
}

const gameSounds = {
    success: new Audio(getAudioPath('success.mp3')),
    fail: new Audio(getAudioPath('fail.mp3')),
    gameover: new Audio(getAudioPath('gameover.mp3')),
    levelup: new Audio(getAudioPath('levelup.mp3')),
    click: new Audio(getAudioPath('click.mp3'))
};

// Preload sounds & Error Logging
for (let key in gameSounds) {
    gameSounds[key].volume = 0.5;
    
    // Add error listener to help debug missing files
    gameSounds[key].addEventListener('error', (e) => {
        console.warn(`Audio file failed to load: ${key}`, gameSounds[key].src);
    });

    gameSounds[key].load();
}

function playGameSound(type) {
    if (isGameMuted) return; // Don't play if muted
    
    const audio = gameSounds[type];
    if (audio) {
        // Reset time to allow overlapping sounds (e.g., rapid clicking)
        audio.currentTime = 0;
        
        // Play and catch errors (like user not interacting with document yet)
        audio.play().catch(e => {
            // This is normal if the user hasn't clicked anything on the page yet
        });
    }
}

function setGameSoundMute(muted) {
    isGameMuted = muted;
    localStorage.setItem('game_muted', muted);
}