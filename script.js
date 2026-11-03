/* Library Desk – frontend logic. Talks to api.php (PHP + MySQL). */
const API = 'api.php';   // same folder as index.html. Change to a full URL if the API is hosted elsewhere.

const $ = (s) => document.querySelector(s);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fmtDate = (d) => (d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—');
const initials = (n) => String(n || '?').split(/\s+/).map((w) => w[0]).join('').slice(0, 2).toUpperCase();

let books = [];

/* ---------- helpers ---------- */
async function api(action, { method = 'GET', body, query = '' } = {}) {
    const res = await fetch(`${API}?action=${action}${query}`, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });
    let data;
    try { data = await res.json(); } catch { throw new Error('Server did not return valid data. Check the API/database setup.'); }
    if (!data.success) throw new Error(data.message || 'Something went wrong.');
    return data;
}

function toast(msg, isError = false) {
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

const run = async (fn) => { try { await fn(); } catch (e) { toast(e.message, true); } };

const statusBadge = (s) => `<span class="status ${s.toLowerCase() === 'issued' ? 'issued' : s.toLowerCase() === 'returned' ? 'returned' : 'available'}">${esc(s)}</span>`;
const emptyRow = (cols, text) => `<tr class="empty-row"><td colspan="${cols}">${text}</td></tr>`;

/* ---------- login / logout ---------- */
function showApp(loggedIn) {
    $('#login').classList.toggle('hidden', loggedIn);
    $('#dashboard').classList.toggle('hidden', !loggedIn);
    if (loggedIn) loadAll();
}

$('#loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    $('#loginError').textContent = '';
    try {
        await api('login', { method: 'POST', body: { username: $('#loginUser').value, password: $('#loginPass').value } });
        sessionStorage.setItem('ld_auth', '1');
        $('#loginPass').value = '';
        showApp(true);
    } catch (err) {
        $('#loginError').textContent = err.message;
    }
});

$('#logoutBtn').addEventListener('click', (e) => {
    e.preventDefault();
    sessionStorage.removeItem('ld_auth');
    showApp(false);
});

/* ---------- loading data ---------- */
async function loadAll() {
    const h = new Date().getHours();
    $('#greeting').textContent = `Good ${h < 12 ? 'Morning' : h < 17 ? 'Afternoon' : 'Evening'}, Admin!`;
    await run(async () => {
        const [stats, bks, rdrs, recs] = await Promise.all([api('stats'), api('books'), api('readers'), api('records')]);
        renderStats(stats.data);
        books = bks.data;
        renderRecent();
        renderGenres();
        renderIndex();
        renderReaders(rdrs.data);
        renderRecords(recs.data);
    });
}

/* ---------- rendering ---------- */
function renderStats(s) {
    $('#statTotal').textContent = s.total_books;
    $('#statIssued').textContent = s.issued_books;
    $('#statAvailable').textContent = s.available_books;
    $('#statReaders').textContent = s.readers;
}

function renderRecent() {
    $('#recentBody').innerHTML = books.slice(0, 5).map((b) => `
        <tr>
            <td><div class="reader">${b.reader_name ? `<span>${esc(initials(b.reader_name))}</span>${esc(b.reader_name)}` : '—'}</div></td>
            <td>${esc(b.book_name)}</td><td>${esc(b.genre)}</td>
            <td>${statusBadge(b.status)}</td><td>${fmtDate(b.issue_date)}</td>
        </tr>`).join('') || emptyRow(5, 'No books yet. Add your first book above.');
}

function renderGenres() {
    const sel = $('#genreFilter'), cur = sel.value;
    const genres = [...new Set(books.map((b) => b.genre))].sort();
    sel.innerHTML = '<option value="">All Genres</option>' + genres.map((g) => `<option>${esc(g)}</option>`).join('');
    sel.value = genres.includes(cur) ? cur : '';
}

function renderIndex() {
    const q = $('#searchInput').value.trim().toLowerCase();
    const g = $('#genreFilter').value, st = $('#statusFilter').value;
    const list = books.filter((b) =>
        (!q || b.book_name.toLowerCase().includes(q) || b.author.toLowerCase().includes(q)) &&
        (!g || b.genre === g) && (!st || b.status === st));
    $('#indexBody').innerHTML = list.map((b) => `
        <tr>
            <td>B${String(b.book_id).padStart(3, '0')}</td><td>${esc(b.book_name)}</td><td>${esc(b.author)}</td>
            <td>${esc(b.genre)}</td><td>${statusBadge(b.status)}</td>
            <td>${b.status === 'Available'
                ? `<button class="mini-btn issue" data-act="issue" data-id="${b.book_id}">Issue</button><button class="mini-btn delete" data-act="delete" data-id="${b.book_id}">Delete</button>`
                : `<button class="mini-btn return" data-act="return" data-id="${b.book_id}">Return</button>`}</td>
        </tr>`).join('') || emptyRow(6, 'No books found.');

    const issued = books.filter((b) => b.status === 'Issued');
    $('#issuedBody').innerHTML = issued.map((b) => `
        <tr><td>${esc(b.book_name)}</td><td>${esc(b.reader_name)}</td><td>${fmtDate(b.issue_date)}</td>
        <td><button class="mini-btn return" data-act="return" data-id="${b.book_id}">Return</button></td></tr>`).join('')
        || emptyRow(4, 'No books are currently issued.');
}

function renderReaders(list) {
    $('#readersBody').innerHTML = list.map((r) => `
        <tr><td>${r.reader_id}</td><td><div class="reader"><span>${esc(initials(r.reader_name))}</span>${esc(r.reader_name)}</div></td>
        <td>${esc(r.email) || '—'}</td><td>${esc(r.phone) || '—'}</td><td>${r.books_issued}</td></tr>`).join('')
        || emptyRow(5, 'No readers registered yet.');
}

function renderRecords(list) {
    $('#recordsBody').innerHTML = list.map((r) => `
        <tr><td>${r.issue_id}</td><td>${esc(r.book_name)}</td><td>${esc(r.reader_name)}</td>
        <td>${fmtDate(r.issue_date)}</td><td>${fmtDate(r.return_date)}</td><td>${statusBadge(r.status)}</td></tr>`).join('')
        || emptyRow(6, 'No transactions yet.');
}

/* ---------- actions ---------- */
$('#bookForm').addEventListener('submit', (e) => {
    e.preventDefault();
    run(async () => {
        const reader = $('#fReader').value.trim();
        const added = await api('add-book', { method: 'POST', body: {
            book_name: $('#fBook').value, author: $('#fAuthor').value.trim() || 'Not specified', genre: $('#fGenre').value,
        } });
        if (reader) await api('issue-book', { method: 'POST', body: { book_id: added.book_id, reader_name: reader } });
        e.target.reset();
        toast(reader ? 'Book added and issued.' : 'Book added.');
        await loadAll();
    });
});

$('#readerForm').addEventListener('submit', (e) => {
    e.preventDefault();
    run(async () => {
        await api('add-reader', { method: 'POST', body: { reader_name: $('#rName').value, email: $('#rEmail').value, phone: $('#rPhone').value } });
        e.target.reset();
        toast('Reader registered.');
        await loadAll();
    });
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    run(async () => {
        if (btn.dataset.act === 'issue') {
            const name = prompt('Reader name for this book:');
            if (!name || !name.trim()) return;
            await api('issue-book', { method: 'POST', body: { book_id: id, reader_name: name.trim() } });
            toast('Book issued.');
        } else if (btn.dataset.act === 'return') {
            await api('return-book', { method: 'POST', body: { book_id: id } });
            toast('Book returned.');
        } else if (btn.dataset.act === 'delete') {
            if (!confirm('Delete this book permanently?')) return;
            await api('delete-book', { method: 'DELETE', query: `&book_id=${id}` });
            toast('Book deleted.');
        }
        await loadAll();
    });
});

['searchInput', 'genreFilter', 'statusFilter'].forEach((id) => $('#' + id).addEventListener('input', renderIndex));

/* ---------- start ---------- */
showApp(sessionStorage.getItem('ld_auth') === '1');
