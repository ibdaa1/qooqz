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
