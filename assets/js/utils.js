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