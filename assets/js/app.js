// Main site JS for UI effects and utilities

// Play click sound on all .btn and .click-sound elements
document.addEventListener('DOMContentLoaded', function() {
    var clickSound = window.audioClick || null;
    
    // Use the same path logic as utils.js
    const base = (typeof SITE_URL !== 'undefined') ? SITE_URL : '/';
    const clickPath = base.replace(/\/$/, '') + '/assets/sounds/click.mp3';

    try {
        clickSound = new Audio(clickPath);
        clickSound.volume = 0.4;
    } catch {}

    document.querySelectorAll('.btn, .click-sound').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (clickSound) {
                clickSound.currentTime = 0;
                clickSound.play().catch(()=>{});
            }
        });
    });

    // Toast notifications
    window.flash = function(message, type="success") {
        let toast = document.createElement('div');
        toast.className = 'fixed top-6 left-1/2 transform -translate-x-1/2 z-50 p-3 px-6 rounded-xl shadow-xl font-bold ' +
            (type === 'success' ? 'bg-friv-green text-white ' :
            type === 'error' ? 'bg-red-500 text-white ' :
            'bg-friv-blue text-white ');
        toast.innerHTML = message;
        document.body.appendChild(toast);
        setTimeout(()=>{ toast.remove(); }, 2800);
    };
});