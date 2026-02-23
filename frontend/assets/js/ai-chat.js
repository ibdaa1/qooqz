const wrap = document.getElementById('chatWrap');
wrap.scrollTop = wrap.scrollHeight;

document.getElementById('qInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) document.getElementById('chatForm').submit();
    }
});

const ta = document.getElementById('qInput');
ta.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
});
ta.focus();

function ask(q) {
    document.getElementById('qInput').value = q;
    document.getElementById('chatForm').submit();
}

function showFile(type) {
    const input = type === 'image' ? document.getElementById('imageInput') : document.getElementById('docInput');
    const tag   = type === 'image' ? document.getElementById('imgTag')    : document.getElementById('docTag');
    const name  = type === 'image' ? document.getElementById('imgName')   : document.getElementById('docName');
    if (input.files.length) { name.textContent = input.files[0].name; tag.classList.add('show'); }
}
function clearFile(type) {
    const input = type === 'image' ? document.getElementById('imageInput') : document.getElementById('docInput');
    const tag   = type === 'image' ? document.getElementById('imgTag')    : document.getElementById('docTag');
    input.value = '';
    tag.classList.remove('show');
}

document.getElementById('chatForm').addEventListener('submit', function() {
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.parentElement.classList.add('sending');
});

// ===== Voice Recognition (Web Speech API) =====
(function() {
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    var micBtn = document.getElementById('micBtn');
    if (!SpeechRecognition) { micBtn.style.display = 'none'; return; }

    var recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = true;
    var isRecording = false;

    // Use language passed from PHP; default to Arabic
    recognition.lang = (typeof AI_LANG !== 'undefined' && AI_LANG === 'en') ? 'en-US' : 'ar-SA';

    recognition.onstart = function() {
        isRecording = true;
        micBtn.classList.add('recording');
        micBtn.title = micBtn.title; // keep title
    };
    recognition.onresult = function(event) {
        var transcript = '';
        for (var i = 0; i < event.results.length; i++) {
            transcript += event.results[i][0].transcript;
        }
        ta.value = transcript;
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
    };
    recognition.onend = function() {
        isRecording = false;
        micBtn.classList.remove('recording');
    };
    recognition.onerror = function() {
        isRecording = false;
        micBtn.classList.remove('recording');
    };

    micBtn.addEventListener('click', function() {
        // Update lang at click time (in case user switched languages)
        recognition.lang = (typeof AI_LANG !== 'undefined' && AI_LANG === 'en') ? 'en-US' : 'ar-SA';
        if (isRecording) {
            recognition.stop();
        } else {
            recognition.start();
        }
    });
})();
