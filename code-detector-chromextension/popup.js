document.addEventListener('DOMContentLoaded', async() => {

    const countEl = document.getElementById('codeCount');
    const unitEl = document.getElementById('codeUnit');
    const statusEl = document.getElementById('statusText');
    const rescanBtn = document.getElementById('rescanBtn');
    const visibilityToggle = document.getElementById('visibilityToggle');


    const mainView = document.getElementById('mainView');
    const authView = document.getElementById('authView');
    const openAuthBtn = document.getElementById('openAuthBtn');
    const closeAuthBtn = document.getElementById('closeAuthBtn');
    const authSubmit = document.getElementById('authSubmit');
    const toggleAuthMode = document.getElementById('toggleAuthMode');
    const authTitle = document.getElementById('authTitle');
    const userStatus = document.getElementById('userStatus');


    const usernameInp = document.getElementById('username');
    const passwordInp = document.getElementById('password');
    const confirmInp = document.getElementById('confirmPassword');
    const authMsg = document.getElementById('authMsg');

    let mode = 'login';
    chrome.storage.local.get(['username'], (res) => {
        if (res.username) {
            userStatus.textContent = `👤 ${res.username}`;
            openAuthBtn.textContent = '⏻';
            openAuthBtn.title = 'Logout';
        } else {
            userStatus.textContent = 'v3.0.0 · active';
            openAuthBtn.textContent = '👤';
            openAuthBtn.title = 'Login';
        }
    });

    openAuthBtn.addEventListener('click', () => {
        chrome.storage.local.get(['username'], (res) => {
            if (res.username) {

                if (confirm(`Logout from ${res.username}?`)) {
                    chrome.storage.local.remove(['username'], () => {
                        location.reload();
                    });
                }
            } else {

                mainView.style.display = 'none';
                authView.style.display = 'block';
            }
        });
    });

    closeAuthBtn.addEventListener('click', () => {
        authView.style.display = 'none';
        mainView.style.display = 'block';
        authMsg.textContent = "";
    });

    toggleAuthMode.addEventListener('click', () => {
        mode = (mode === 'login') ? 'register' : 'login';
        authTitle.textContent = (mode === 'login') ? 'Security Login' : 'Create Account';
        authSubmit.textContent = (mode === 'login') ? 'Login' : 'Register';
        confirmInp.style.display = (mode === 'login') ? 'none' : 'block';

        toggleAuthMode.innerHTML = (mode === 'login') ?
            "Don't have an account? <span>Register</span>" :
            "Already have an account? <span>Login</span>";
    });

    authSubmit.addEventListener('click', async() => {
        const user = usernameInp.value.trim();
        const pass = passwordInp.value.trim();
        const conf = confirmInp.value.trim();

        if (!user || !pass) {
            authMsg.textContent = "Please fill in all fields";
            return;
        }

        authMsg.style.color = "#4ade80";
        authMsg.textContent = (mode === 'login') ? "Verifying..." : "Creating Account...";

        try {
            const response = await fetch("http://localhost:8000/api/auth", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    username: user,
                    password: pass,
                    confirm_password: (mode === 'register' ? conf : pass),
                    login_type: mode
                })
            });

            const data = await response.json();

            if (response.ok) {

                chrome.storage.local.set({ username: user }, () => {
                    location.reload();
                });
            } else {

                authMsg.style.color = "#ff4d4d";
                authMsg.textContent = data.detail || "Authentication Failed";
            }
        } catch (err) {
            authMsg.style.color = "#ff4d4d";
            authMsg.textContent = "Backend Offline (Port 8000)";
        }
    });


    async function getActiveTab() {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        return tab;
    }


    async function fetchStats() {
        try {
            const tab = await getActiveTab();
            if (!tab || !tab.id) return;

            const response = await chrome.tabs.sendMessage(tab.id, { action: 'getStats' });
            if (response) {
                const count = response.count || 0;
                countEl.textContent = count;
                unitEl.style.display = 'inline';
                statusEl.textContent = count > 0 ?
                    `${count} code block${count !== 1 ? 's' : ''} found` :
                    'No code detected on page';
            }
        } catch (e) {
            countEl.textContent = '—';
            statusEl.textContent = 'Script not ready (Refresh page)';
        }
    }


    rescanBtn.addEventListener('click', async() => {
        rescanBtn.textContent = '⟳ Scanning...';
        rescanBtn.disabled = true;
        try {
            const tab = await getActiveTab();
            if (!tab || !tab.id) return;

            const response = await chrome.tabs.sendMessage(tab.id, { action: 'rescan' });
            if (response) {
                const count = response.count || 0;
                countEl.textContent = count;
                unitEl.style.display = 'inline';
                statusEl.textContent = `${count} block${count !== 1 ? 's' : ''} updated`;
            }
        } catch (e) {
            statusEl.textContent = 'Error rescanning page';
        } finally {
            rescanBtn.innerHTML = '<span class="btn-icon">⟳</span> Re-scan Page';
            rescanBtn.disabled = false;
        }
    });


    visibilityToggle.addEventListener('change', async() => {
        const visible = visibilityToggle.checked;
        try {
            const tab = await getActiveTab();
            if (!tab || !tab.id) return;
            await chrome.tabs.sendMessage(tab.id, { action: 'toggleVisibility', visible });
        } catch (e) {
            console.error('Visibility toggle failed:', e);
        }
    });


    await fetchStats();
});