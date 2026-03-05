// Popup script for Code Detector extension

document.addEventListener('DOMContentLoaded', async () => {
  const countEl = document.getElementById('codeCount');
  const unitEl = document.getElementById('codeUnit');
  const statusEl = document.getElementById('statusText');
  const rescanBtn = document.getElementById('rescanBtn');
  const visibilityToggle = document.getElementById('visibilityToggle');

  async function getActiveTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
  }

  async function fetchStats() {
    try {
      const tab = await getActiveTab();
      if (!tab?.id) return;

      const response = await chrome.tabs.sendMessage(tab.id, { action: 'getStats' });
      if (response) {
        const count = response.count || 0;
        countEl.textContent = count;
        unitEl.style.display = 'inline';
        statusEl.textContent = count > 0
          ? `${count} code block${count !== 1 ? 's' : ''} found`
          : 'no code detected';
      }
    } catch (e) {
      countEl.textContent = '—';
      statusEl.textContent = 'content script not loaded';
    }
  }

  // Rescan button
  rescanBtn.addEventListener('click', async () => {
    rescanBtn.textContent = '⟳ Scanning...';
    rescanBtn.disabled = true;
    try {
      const tab = await getActiveTab();
      if (!tab?.id) return;
      const response = await chrome.tabs.sendMessage(tab.id, { action: 'rescan' });
      if (response) {
        const count = response.count || 0;
        countEl.textContent = count;
        unitEl.style.display = 'inline';
        statusEl.textContent = count > 0
          ? `${count} block${count !== 1 ? 's' : ''} found`
          : 'no code detected';
      }
    } catch (e) {
      statusEl.textContent = 'error rescanning';
    } finally {
      rescanBtn.innerHTML = '<span class="btn-icon">⟳</span> Re-scan Page';
      rescanBtn.disabled = false;
    }
  });

  // Visibility toggle
  visibilityToggle.addEventListener('change', async () => {
    const visible = visibilityToggle.checked;
    try {
      const tab = await getActiveTab();
      if (!tab?.id) return;
      await chrome.tabs.sendMessage(tab.id, { action: 'toggleVisibility', visible });
    } catch (e) {
      console.error('Toggle failed:', e);
    }
  });

  // Load stats on open
  await fetchStats();
});
