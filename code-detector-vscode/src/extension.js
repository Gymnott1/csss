const vscode = require('vscode');
const http = require('http');

// ─── 1. CONFIGURATION ────────────────────────────────────────────────────────
const BACKEND_URL = 'http://127.0.0.1:8000/api/scan';
let scanTimeout = null;
let currentPanel = null;

// Decoration Styles
const apiDecoType = vscode.window.createTextEditorDecorationType({
    isWholeLine: true,
    backgroundColor: 'rgba(255, 68, 68, 0.15)',
    border: '0 0 0 4px solid #ff4444'
});

const localDecoType = vscode.window.createTextEditorDecorationType({
    isWholeLine: true,
    border: '0 0 0 4px dashed #ffbb33'
});

// ─── 2. HELPERS ──────────────────────────────────────────────────────────────

function mapLanguageId(id) {
    const map = { 'javascriptreact': 'javascript', 'typescriptreact': 'typescript' };
    return map[id] || id;
}

/**
 * BUG FIX #1 — XSS in webview
 * Escapes HTML special characters before injecting any string into innerHTML.
 * Without this, a malicious API response (or a filename containing <script>)
 * could execute arbitrary JS inside the trusted webview context.
 */
function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ─── 3. API CALL (CLEANED BUFFER & TIMEOUT) ──────────────────────────────────
async function getApiFindings(code, language) {
    return new Promise((resolve) => {
        const payload = JSON.stringify({ code, language });
        const url = new URL(BACKEND_URL);

        const options = {
            hostname: url.hostname,
            port: url.port,
            path: url.pathname,
            method: 'POST',
            timeout: 30000,
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(payload)
            }
        };

        const req = http.request(options, (res) => {
            let body = '';
            res.on('data', (chunk) => body += chunk);
            res.on('end', () => {
                if (res.statusCode !== 200) {
                    console.error(`>> 🛡️ Shield API Error: ${res.statusCode}`);
                    return resolve(null);
                }
                try {
                    const parsed = JSON.parse(body);
                    resolve(parsed);
                } catch (e) {
                    console.error(">> 🛡️ Shield API JSON Parse Fail");
                    resolve(null);
                }
            });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve(null);
        });
        req.on('error', (e) => {
            console.error(">> 🛡️ Shield API Conn Error:", e.message);
            resolve(null);
        });

        req.write(payload);
        req.end();
    });
}

// ─── 4. AUDIT LOGIC ──────────────────────────────────────────────────────────
async function performFullAudit(editor) {
    if (!editor) return;
    const doc = editor.document;

    console.log(`>> 🛡️ Shield: Audit Triggered for ${doc.fileName}`);

    if (doc.uri.scheme !== 'file' && doc.uri.scheme !== 'untitled') {
        console.log(`>> 🛡️ Shield: Skipping scheme ${doc.uri.scheme}`);
        return;
    }

    const code = doc.getText();
    if (!code || code.length < 5) {
        console.log(`>> 🛡️ Shield: Code too short, skipping.`);
        return;
    }

    const lang = mapLanguageId(doc.languageId);

    await vscode.window.withProgress({
        location: vscode.ProgressLocation.Window,
        title: "🛡️ Shield: Scanning for Vulnerabilities...",
    }, async() => {
        console.log(`>> 🛡️ Shield: Requesting API scan for ${lang}...`);

        const apiRes = await getApiFindings(code, lang);

        const findings = apiRes ? .findings || [];
        console.log(`>> 🛡️ Shield: Backend found ${findings.length} issues.`);

        /*
         * BUG FIX #2 — Decorations never cleared
         * Always clear BOTH decoration types before applying new results.
         * Previously, passing an empty array only worked for apiDecoType on
         * the current call — localDecoType was never cleared, and apiDecoType
         * from a prior scan on a different editor was never wiped, so stale
         * red/yellow highlights accumulated indefinitely.
         */
        editor.setDecorations(apiDecoType, []);
        editor.setDecorations(localDecoType, []);

        const apiDecos = findings.map(f => {
            const lineIdx = Math.max(0, f.line - 1);
            const safeLine = Math.min(lineIdx, doc.lineCount - 1);
            return {
                range: doc.lineAt(safeLine).range,
                hoverMessage: new vscode.MarkdownString(
                    `### 🛡️ ${f.title}\n${f.description}\n\n**Remediation:** ${f.remediation}`
                )
            };
        });

        editor.setDecorations(apiDecoType, apiDecos);

        if (currentPanel) {
            updateWebviewContent(findings, apiRes ? .ai_analysis, doc.fileName);
        }
    });
}

function updateWebviewContent(findings, ai, fileName) {
    /*
     * BUG FIX #1 (applied) — all dynamic values are passed through escapeHtml()
     * before being interpolated into the HTML string, preventing XSS from
     * malicious API payloads or filenames containing HTML/script characters.
     */
    const safeFileName = escapeHtml(fileName.split('/').pop());
    const safeReview = ai ? escapeHtml(ai.review) : '';

    currentPanel.webview.html = `
        <html><body style="background:#0d1117;color:#c9d1d9;font-family:sans-serif;padding:20px;">
            <h3>🛡️ Shield Audit: ${safeFileName}</h3>
            ${ai ? `
            <div style="border:1px solid #7f52ff;padding:10px;border-radius:8px;margin-bottom:20px;background:rgba(127,82,255,0.05);">
                <div style="color:#7f52ff;font-weight:bold;">✨ AI Insight</div>
                <p style="font-size:13px;">${safeReview}</p>
            </div>` : ''}
            ${findings.map(f => `
                <div style="border-left:4px solid #ff4444;background:#161b22;padding:10px;margin-bottom:10px;border-radius:4px;">
                    <div style="font-weight:bold;">${escapeHtml(f.title)}</div>
                    <div style="font-size:11px;opacity:0.6;">Line ${escapeHtml(String(f.line))}</div>
                    <p style="font-size:13px;">${escapeHtml(f.description)}</p>
                </div>
            `).join('') || '<p>✅ No issues detected.</p>'}
        </body></html>`;
}

// ─── 5. ACTIVATION ───────────────────────────────────────────────────────────
function activate(context) {
    console.log('>> 🛡️ Shield Activated');

    const trigger = () => {
        /*
         * BUG FIX #3 — Scan race condition
         * Capture the target editor HERE (at schedule time) and re-validate it
         * at execution time. The original code captured `editor` in the outer
         * onDidChangeActiveTextEditor callback, so if the user switched files
         * during the 1-second debounce window, the scan ran against the old
         * document but decorations landed on whatever editor was active at
         * execution time — corrupting the wrong file's display.
         *
         * Fix: snapshot the active editor when the timeout is created, then
         * confirm it is still the active editor before running the audit.
         */
        const editorAtScheduleTime = vscode.window.activeTextEditor;
        if (scanTimeout) clearTimeout(scanTimeout);
        scanTimeout = setTimeout(() => {
            const editorAtExecutionTime = vscode.window.activeTextEditor;
            if (editorAtExecutionTime === editorAtScheduleTime) {
                performFullAudit(editorAtExecutionTime);
            } else {
                console.log('>> 🛡️ Shield: Editor changed during debounce, scan skipped.');
            }
        }, 1000);
    };

    context.subscriptions.push(
        vscode.workspace.onDidChangeTextDocument(e => {
            if (vscode.window.activeTextEditor?.document === e.document) trigger();
        }),
        vscode.window.onDidChangeActiveTextEditor(editor => {
            if (editor) performFullAudit(editor);
        }),
        vscode.commands.registerCommand('codeDetector.showInfo', () => {
            if (currentPanel) {
                currentPanel.reveal(vscode.ViewColumn.Beside);
            } else {
                currentPanel = vscode.window.createWebviewPanel(
                    'securityShield', 'Shield Audit',
                    vscode.ViewColumn.Beside, { enableScripts: true }
                );
                currentPanel.onDidDispose(() => currentPanel = null);
            }
            performFullAudit(vscode.window.activeTextEditor);
        })
    );

    if (vscode.window.activeTextEditor) performFullAudit(vscode.window.activeTextEditor);
}

exports.activate = activate;
exports.deactivate = () => {};