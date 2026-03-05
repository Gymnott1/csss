// Code Detector — VS Code Extension v4
// Approach: prominent status bar button + inline EOL annotation + CodeLens

const vscode = require('vscode');

// ── Language metadata ─────────────────────────────────────────────────────────
const LANGUAGES = [
  { id: 'javascript',  name: 'JavaScript',  icon: '⚡', color: '#f7df1e', born: '1995', paradigm: 'Multi-paradigm',       usedFor: 'Web, servers, mobile',        tip: 'Powers the interactive web. Runs in every browser natively.',                  pattern: /\b(function\s+\w+|const\s+\w+\s*=|let\s+\w+\s*=|=>\s*[\{\w]|async\s+function|await\s+\w+|require\s*\(|module\.exports|import\s+\w+\s+from)\b/ },
  { id: 'typescript',  name: 'TypeScript',  icon: '🔷', color: '#3178c6', born: '2012', paradigm: 'Typed / OOP',           usedFor: 'Large-scale web apps',        tip: 'JavaScript with static types. Compiles down to plain JS.',                      pattern: /\b(interface\s+\w+\s*\{|type\s+\w+\s*=|enum\s+\w+\s*\{|readonly\s+\w+|namespace\s+\w+|declare\s+(const|let|var|function|class))\b/ },
  { id: 'python',      name: 'Python',      icon: '🐍', color: '#4b8bbe', born: '1991', paradigm: 'Multi-paradigm',       usedFor: 'AI/ML, scripting, data',      tip: 'Famous for readability. The #1 language in AI & data science.',                pattern: /\b(def\s+\w+\s*\(|import\s+\w+|from\s+\w+\s+import|class\s+\w+[\s:(]|if\s+__name__\s*==|print\s*\(|elif\s+|self\.\w+)\b/ },
  { id: 'java',        name: 'Java',        icon: '☕', color: '#ed8b00', born: '1995', paradigm: 'Object-Oriented',       usedFor: 'Enterprise, Android',         tip: '"Write once, run anywhere." Runs on the JVM.',                                  pattern: /\b(public\s+(class|static|void|final)|private\s+\w+|@Override|System\.out\.|throws\s+\w+|extends\s+\w+)\b/ },
  { id: 'cpp',         name: 'C/C++',       icon: '⚙️', color: '#00599c', born: '1972', paradigm: 'Procedural / OOP',     usedFor: 'Systems, games, OS',          tip: 'Close to the metal. Used in OS kernels and performance-critical systems.',     pattern: /\b(#include\s*[<"]|printf\s*\(|int\s+main\s*\(|malloc\s*\(|nullptr|std::|cout\s*<<)\b/ },
  { id: 'rust',        name: 'Rust',        icon: '🦀', color: '#ce422b', born: '2010', paradigm: 'Systems / Functional', usedFor: 'Systems, WebAssembly',         tip: 'Memory-safe without a GC. Most loved language 8 years running.',              pattern: /\b(fn\s+\w+\s*[\(<]|let\s+mut\s+|use\s+\w+::|impl\s+\w+|struct\s+\w+\s*\{|match\s+\w+\s*\{|Some\(|Result<)\b/ },
  { id: 'go',          name: 'Go',          icon: '🐹', color: '#00add8', born: '2009', paradigm: 'Concurrent',           usedFor: 'Cloud, CLIs, APIs',            tip: 'Built at Google. Goroutines make concurrency simple.',                          pattern: /\b(func\s+\w+\s*\(|package\s+main|import\s*\(|fmt\.\w+\(|:=\s*|defer\s+\w+|go\s+func)\b/ },
  { id: 'ruby',        name: 'Ruby',        icon: '💎', color: '#cc342d', born: '1995', paradigm: 'Object-Oriented',       usedFor: 'Web (Rails), scripting',      tip: 'Designed for developer happiness. Everything is an object.',                   pattern: /(\bdef\s+\w+|\bend\b|\bputs\s+|\brequire\s+['"]|\battr_(reader|writer|accessor)\b|\.each\s*(do|\{)|\bnil\b)/ },
  { id: 'php',         name: 'PHP',         icon: '🐘', color: '#8892be', born: '1994', paradigm: 'Imperative / OOP',     usedFor: 'Web backends',                 tip: 'Runs ~79% of all websites. Laravel is its popular modern framework.',          pattern: /(<\?php|\$\w+\s*=|echo\s+["'\$]|\$this->|\$_GET|\$_POST|namespace\s+\w+)/ },
  { id: 'swift',       name: 'Swift',       icon: '🍎', color: '#f05138', born: '2014', paradigm: 'Multi-paradigm',       usedFor: 'iOS, macOS apps',              tip: "Apple's modern language. Replaces Objective-C for Apple platforms.",           pattern: /\b(func\s+\w+\s*\(|guard\s+let|if\s+let\s+|extension\s+\w+|protocol\s+\w+|@objc|var\s+\w+\s*:)\b/ },
  { id: 'kotlin',      name: 'Kotlin',      icon: '🟣', color: '#7f52ff', born: '2011', paradigm: 'Multi-paradigm',       usedFor: 'Android, server-side',         tip: "JetBrains' answer to Java. 100% interoperable with the JVM.",                 pattern: /\b(fun\s+\w+\s*\(|val\s+\w+\s*=|data\s+class|companion\s+object|when\s*\(|\.let\s*\{)\b/ },
  { id: 'sql',         name: 'SQL',         icon: '🗄️', color: '#e38c00', born: '1974', paradigm: 'Declarative / Query', usedFor: 'Databases, analytics',          tip: 'The language of relational data. Every database speaks SQL.',                   pattern: /\b(SELECT\s+[\w\*]+\s+FROM|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE)\b/i },
  { id: 'shellscript', name: 'Shell/Bash',  icon: '🖥️', color: '#4eaa25', born: '1989', paradigm: 'Scripting',           usedFor: 'Automation, DevOps',           tip: 'The glue of Unix. Automates deployments, backups, everything.',               pattern: /^(#!\/bin\/(bash|sh|zsh)|sudo\s+\w+|apt(-get)?\s+(install|update)|npm\s+(install|run)|pip\s+install|git\s+(clone|commit|push)|docker\s+\w+)/m },
  { id: 'html',        name: 'HTML',        icon: '🌐', color: '#e34f26', born: '1993', paradigm: 'Markup',               usedFor: 'Web page structure',           tip: 'The skeleton of every webpage. Not a programming language, but essential.',   pattern: /(<(!DOCTYPE\s+html|html[\s>]|head[\s>]|body[\s>]|div[\s>])[^>]*>|<\/\w+>)/i },
  { id: 'css',         name: 'CSS',         icon: '🎨', color: '#264de4', born: '1996', paradigm: 'Stylesheet',           usedFor: 'Web page styling',             tip: 'Styles the web. CSS Grid and Flexbox changed layout forever.',                 pattern: /([.#][\w-]+\s*\{[\s\S]*?\}|@media\s+|@keyframes\s+\w+|:root\s*\{|--[\w-]+\s*:)/ },
  { id: 'json',        name: 'JSON',        icon: '📦', color: '#cbcb41', born: '2001', paradigm: 'Data format',          usedFor: 'APIs, config, data exchange',  tip: 'The universal language of APIs. Lightweight and human-readable.',             pattern: /^\s*\{[\s\S]*"[\w\s-]+"[\s\S]*:/ },
  { id: 'yaml',        name: 'YAML',        icon: '📋', color: '#cb171e', born: '2001', paradigm: 'Data format',          usedFor: 'Config files, CI/CD',          tip: 'Human-friendly config. Powers Docker Compose, GitHub Actions & k8s.',         pattern: /^([\w-]+:\s+.+\n[\w-]+:|---\n)/m },
  { id: 'markdown',    name: 'Markdown',    icon: '📝', color: '#519aba', born: '2004', paradigm: 'Markup',               usedFor: 'Docs, READMEs, notes',         tip: 'Plain text that renders beautifully. Created by John Gruber in 2004.',        pattern: /^(#{1,6}\s+\w|\*{1,2}\w+\*{1,2}|\[.+\]\(.+\)|^```|^---)/m },
  { id: 'csharp',      name: 'C#',          icon: '🔵', color: '#178600', born: '2000', paradigm: 'Object-Oriented',      usedFor: '.NET, Unity, desktop',         tip: "Microsoft's flagship language. Powers Unity and the .NET ecosystem.",         pattern: /\b(using\s+System|namespace\s+\w+|public\s+class\s+\w+|Console\.Write|async\s+Task|IEnumerable)\b/ },
  { id: 'xml',         name: 'XML',         icon: '📄', color: '#ff6600', born: '1998', paradigm: 'Markup / Data format', usedFor: 'Data exchange, config',        tip: 'Verbose but structured. Still widely used in enterprise and Android layouts.', pattern: /(<\?xml[\s\S]*\?>|<[a-zA-Z][\w:.-]*(\s[\s\S]*?)?>[\s\S]*<\/[a-zA-Z][\w:.-]*>)/ },
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function resolveLanguage(document) {
  const byId = LANGUAGES.find(l => l.id === document.languageId);
  if (byId) return byId;
  const text = document.getText();
  if (text.trim().length < 8) return null;
  for (const lang of LANGUAGES) {
    if (lang.pattern.test(text)) return lang;
  }
  return null;
}

function getStats(text) {
  const lines    = text.split('\n');
  const nonEmpty = lines.filter(l => l.trim().length > 0).length;
  const chars    = text.replace(/\s/g, '').length;
  const words    = text.trim().split(/\s+/).filter(Boolean).length;
  const comments = lines.filter(l => /^\s*(\/\/|#|\/\*|\*|<!--|--|;)/.test(l)).length;
  return { total: lines.length, nonEmpty, chars, words, comments };
}

// ── End-of-line annotation (always visible, non-interactive) ──────────────────
// Rendered with `after` on line 0 — purely cosmetic label
let eolDecType = null;
let gutterDecType = null;

function disposeDecos() {
  eolDecType?.dispose();   eolDecType   = null;
  gutterDecType?.dispose(); gutterDecType = null;
}

function applyDecos(editor) {
  disposeDecos();
  if (!editor) return;

  const meta = resolveLanguage(editor.document);
  if (!meta) return;

  const stats = getStats(editor.document.getText());

  // End-of-line ghost text after line 0
  eolDecType = vscode.window.createTextEditorDecorationType({
    after: {
      contentText: `   ${meta.icon} ${meta.name} · ${stats.total} lines · click badge below ↓`,
      color: new vscode.ThemeColor('editorCodeLens.foreground'),
      fontStyle: 'italic',
      fontWeight: 'normal',
      margin: '0 0 0 16px',
    },
  });

  // Left gutter color stripe on every line
  gutterDecType = vscode.window.createTextEditorDecorationType({
    isWholeLine: true,
    borderWidth: '0 0 0 3px',
    borderStyle: 'solid',
    borderColor: meta.color + 'bb',
    overviewRulerColor: meta.color + 'aa',
    overviewRulerLane: vscode.OverviewRulerLane.Left,
  });

  const line0 = editor.document.lineAt(0);
  editor.setDecorations(eolDecType, [line0.range]);

  const allRanges = Array.from(
    { length: Math.min(editor.document.lineCount, 50000) },
    (_, i) => editor.document.lineAt(i).range
  );
  editor.setDecorations(gutterDecType, allRanges);
}

// ── CodeLens — styled to look like a clickable button ────────────────────────
class DetectorLensProvider {
  constructor() {
    this._emitter = new vscode.EventEmitter();
    this.onDidChangeCodeLenses = this._emitter.event;
  }
  refresh() { this._emitter.fire(); }

  provideCodeLenses(document) {
    const meta = resolveLanguage(document);
    const stats = getStats(document.getText());
    const range = new vscode.Range(0, 0, 0, 0);

    if (!meta) {
      return [new vscode.CodeLens(range, {
        title: `$(question)  Unknown language — click to open detector`,
        command: 'codeDetector.showInfo',
      })];
    }

    return [
      // Main language button
      new vscode.CodeLens(range, {
        title: `$(circle-filled)  ${meta.icon} ${meta.name}  |  Born ${meta.born}  |  ${meta.usedFor}  →  click for full info`,
        tooltip: `Language: ${meta.name}\nParadigm: ${meta.paradigm}\nBorn: ${meta.born}\nUsed for: ${meta.usedFor}\n\n▶ Click to open the info panel`,
        command: 'codeDetector.showInfo',
      }),
      // Stats button  
      new vscode.CodeLens(range, {
        title: `$(list-unordered)  ${stats.total} lines  $(dash)  ${stats.nonEmpty} non-empty  $(dash)  ${stats.comments} comments  $(dash)  ${stats.chars} chars  →  click to copy`,
        tooltip: `Click to copy all stats to clipboard`,
        command: 'codeDetector.copyStats',
      }),
    ];
  }
}

// ── Webview panel HTML ────────────────────────────────────────────────────────
function buildPanelHtml(meta, stats, fileName) {
  const c = meta.color;
  return /* html */`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline';">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${meta.name}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:var(--vscode-font-family,'SF Mono','Fira Code',monospace);
    background:var(--vscode-editor-background,#0d1117);
    color:var(--vscode-foreground,#c9d1d9);
    padding:0;min-height:100vh;
  }
  body::before{
    content:'';position:fixed;inset:0;
    background-image:
      linear-gradient(${c}08 1px,transparent 1px),
      linear-gradient(90deg,${c}08 1px,transparent 1px);
    background-size:28px 28px;pointer-events:none;z-index:0;
  }
  .wrap{position:relative;z-index:1;padding:28px 22px;animation:up .3s ease forwards}
  @keyframes up{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

  /* Header */
  .hdr{display:flex;align-items:center;gap:14px;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid ${c}22}
  .icon{font-size:46px;line-height:1;filter:drop-shadow(0 0 14px ${c}55)}
  .stripe{width:4px;align-self:stretch;border-radius:3px;background:${c};box-shadow:0 0 14px ${c}77;flex-shrink:0}
  .lang-name{font-size:28px;font-weight:700;color:#f0fff4;letter-spacing:-.02em;line-height:1.1}
  .paradigm{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:${c}99;margin-top:3px}
  .fname{margin-left:auto;text-align:right}
  .fname-lbl{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:${c}55}
  .fname-val{font-size:11px;color:${c}88;margin-top:2px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

  /* Pill */
  .pill{display:inline-flex;align-items:center;gap:7px;background:${c}18;border:1.5px solid ${c}66;border-radius:20px;padding:6px 16px 6px 10px;margin-bottom:22px;box-shadow:0 0 18px ${c}22}
  .dot{width:8px;height:8px;border-radius:50%;background:${c};box-shadow:0 0 8px ${c}cc,0 0 18px ${c}77;animation:glow 2.5s ease-in-out infinite;flex-shrink:0}
  @keyframes glow{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.25);opacity:.85}}
  .pill-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:${c}}

  /* Sections */
  .sec{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:${c}66;margin-bottom:10px;margin-top:22px}
  .rows{display:flex;flex-direction:column;gap:7px}
  .row{display:flex;align-items:center;gap:10px;padding:9px 12px;background:${c}0a;border:1px solid ${c}1a;border-radius:8px;transition:border-color .15s}
  .row:hover{border-color:${c}44}
  .rico{font-size:14px;flex-shrink:0}
  .rlbl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:${c}66;min-width:66px;flex-shrink:0}
  .rval{font-size:12px;color:#c4ffd8}

  /* Stats grid */
  .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:10px}
  .sc{background:${c}0a;border:1px solid ${c}1a;border-radius:10px;padding:13px 10px;text-align:center;transition:border-color .15s,transform .15s;cursor:default}
  .sc:hover{border-color:${c}44;transform:translateY(-1px)}
  .sn{font-size:24px;font-weight:700;color:${c};line-height:1;text-shadow:0 0 16px ${c}66}
  .sl{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:${c}55;margin-top:4px}

  /* Tip */
  .tip{margin-top:10px;padding:12px 14px;background:${c}08;border-left:3px solid ${c}66;border-radius:0 8px 8px 0;font-size:12px;color:#c4ffd8bb;line-height:1.6;font-style:italic}

  /* Buttons */
  .btns{display:flex;gap:8px;margin-top:22px}
  .btn{flex:1;padding:10px 12px;border-radius:8px;border:1px solid ${c}44;background:${c}0f;color:${c};font-family:inherit;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;cursor:pointer;transition:all .15s;outline:none}
  .btn:hover{background:${c}22;border-color:${c}88;transform:translateY(-1px)}
  .btn:active{transform:none}
  .btn.ok{background:${c}33;border-color:${c};pointer-events:none}
</style>
</head>
<body><div class="wrap">

  <div class="hdr">
    <div class="icon">${meta.icon}</div>
    <div class="stripe"></div>
    <div>
      <div class="lang-name">${meta.name}</div>
      <div class="paradigm">${meta.paradigm}</div>
    </div>
    <div class="fname">
      <div class="fname-lbl">File</div>
      <div class="fname-val" title="${fileName}">${fileName}</div>
    </div>
  </div>

  <div class="pill">
    <div class="dot"></div>
    <span class="pill-lbl">Language Detected</span>
  </div>

  <div class="sec">Language Info</div>
  <div class="rows">
    <div class="row"><span class="rico">📅</span><span class="rlbl">Born</span><span class="rval">${meta.born}</span></div>
    <div class="row"><span class="rico">🎯</span><span class="rlbl">Used for</span><span class="rval">${meta.usedFor}</span></div>
    <div class="row"><span class="rico">🏷️</span><span class="rlbl">Paradigm</span><span class="rval">${meta.paradigm}</span></div>
  </div>

  <div class="sec">File Stats</div>
  <div class="grid">
    <div class="sc"><div class="sn">${stats.total}</div><div class="sl">Lines</div></div>
    <div class="sc"><div class="sn">${stats.nonEmpty}</div><div class="sl">Non-empty</div></div>
    <div class="sc"><div class="sn">${stats.comments}</div><div class="sl">Comments</div></div>
    <div class="sc"><div class="sn">${stats.words}</div><div class="sl">Tokens</div></div>
    <div class="sc"><div class="sn">${stats.chars}</div><div class="sl">Chars</div></div>
    <div class="sc"><div class="sn">${Math.round(stats.chars / Math.max(stats.nonEmpty, 1))}</div><div class="sl">Avg len</div></div>
  </div>

  <div class="sec">Fun Fact</div>
  <div class="tip">${meta.tip}</div>

  <div class="btns">
    <button class="btn" id="copyBtn">⎘ Copy Stats</button>
    <button class="btn" id="closeBtn">✕ Close</button>
  </div>

</div><script>
  const vscode = acquireVsCodeApi();
  document.getElementById('copyBtn').onclick = function() {
    vscode.postMessage({ command: 'copyStats', text: [
      'Language : ${meta.name}',
      'File     : ${fileName}',
      'Lines    : ${stats.total}',
      'Non-empty: ${stats.nonEmpty}',
      'Comments : ${stats.comments}',
      'Tokens   : ${stats.words}',
      'Chars    : ${stats.chars}',
    ].join('\\n') });
    this.textContent = '✓ Copied!'; this.classList.add('ok');
    setTimeout(() => { this.textContent = '⎘ Copy Stats'; this.classList.remove('ok'); }, 2000);
  };
  document.getElementById('closeBtn').onclick = () => vscode.postMessage({ command: 'close' });
</script></body></html>`;
}

// ── State ─────────────────────────────────────────────────────────────────────
let statusBarItem;
let lensProvider;
let currentPanel = null;

// ── Status bar (the most reliable clickable element in VS Code) ───────────────
function updateStatusBar(document) {
  if (!statusBarItem) return;
  const meta = resolveLanguage(document);
  if (!meta) {
    statusBarItem.text            = '$(circle-slash) Code Detector';
    statusBarItem.tooltip         = 'Language not detected — click to open info';
    statusBarItem.backgroundColor = undefined;
  } else {
    statusBarItem.text            = `$(circle-filled) ${meta.icon}  ${meta.name}  — click for info`;
    statusBarItem.tooltip         = new vscode.MarkdownString(
      `**Code Detector**\n\n` +
      `**Language:** ${meta.name}\n\n` +
      `**Paradigm:** ${meta.paradigm}\n\n` +
      `**Born:** ${meta.born}\n\n` +
      `**Used for:** ${meta.usedFor}\n\n` +
      `---\n_Click to open full info panel_`
    );
    statusBarItem.backgroundColor = new vscode.ThemeColor('statusBarItem.warningBackground');
  }
  statusBarItem.show();
}

// ── Info panel ────────────────────────────────────────────────────────────────
function openInfoPanel(context, document) {
  const meta  = resolveLanguage(document);
  const stats = getStats(document.getText());
  const fname = document.fileName.split(/[/\\]/).pop();

  if (!meta) {
    vscode.window.showInformationMessage(
      `Code Detector: Could not identify the language of "${fname}". Make sure the file has content.`
    );
    return;
  }

  if (currentPanel) {
    currentPanel.reveal(vscode.ViewColumn.Beside, true);
    currentPanel.title = `${meta.icon} ${meta.name}`;
    currentPanel.webview.html = buildPanelHtml(meta, stats, fname);
    return;
  }

  currentPanel = vscode.window.createWebviewPanel(
    'codeDetectorInfo',
    `${meta.icon} ${meta.name}`,
    { viewColumn: vscode.ViewColumn.Beside, preserveFocus: true },
    { enableScripts: true, retainContextWhenHidden: true }
  );
  currentPanel.webview.html = buildPanelHtml(meta, stats, fname);

  currentPanel.webview.onDidReceiveMessage(msg => {
    if (msg.command === 'copyStats')
      vscode.env.clipboard.writeText(msg.text).then(() =>
        vscode.window.showInformationMessage('Code Detector: Stats copied!'));
    if (msg.command === 'close') currentPanel?.dispose();
  }, undefined, context.subscriptions);

  currentPanel.onDidDispose(() => { currentPanel = null; }, null, context.subscriptions);
}

// ── Activate ──────────────────────────────────────────────────────────────────
function activate(context) {
  console.log('Code Detector v4: activated');

  // ── Status bar button (left side = more prominent) ──
  statusBarItem = vscode.window.createStatusBarItem(
    vscode.StatusBarAlignment.Left, // LEFT so it's prominent, not buried
    -1                              // low priority = leftmost in left group
  );
  statusBarItem.command = 'codeDetector.showInfo';
  context.subscriptions.push(statusBarItem);

  // ── CodeLens ──
  lensProvider = new DetectorLensProvider();
  context.subscriptions.push(
    vscode.languages.registerCodeLensProvider({ scheme: 'file' },     lensProvider),
    vscode.languages.registerCodeLensProvider({ scheme: 'untitled' }, lensProvider),
  );

  // ── Refresh on editor change ──
  const refresh = (editor) => {
    if (!editor) return;
    updateStatusBar(editor.document);
    applyDecos(editor);
    lensProvider.refresh();
  };

  vscode.window.onDidChangeActiveTextEditor(refresh, null, context.subscriptions);

  vscode.workspace.onDidChangeTextDocument(e => {
    const editor = vscode.window.activeTextEditor;
    if (!editor || e.document !== editor.document) return;
    updateStatusBar(editor.document);
    applyDecos(editor);
    lensProvider.refresh();
    if (currentPanel) {
      const m = resolveLanguage(editor.document);
      const s = getStats(editor.document.getText());
      const f = editor.document.fileName.split(/[/\\]/).pop();
      if (m) currentPanel.webview.html = buildPanelHtml(m, s, f);
    }
  }, null, context.subscriptions);

  // ── Initial load ──
  const active = vscode.window.activeTextEditor;
  if (active) refresh(active);

  // ── Commands ──
  context.subscriptions.push(
    vscode.commands.registerCommand('codeDetector.showInfo', () => {
      const e = vscode.window.activeTextEditor;
      if (!e) {
        vscode.window.showWarningMessage('Code Detector: Open a file first.');
        return;
      }
      openInfoPanel(context, e.document);
    }),

    vscode.commands.registerCommand('codeDetector.toggleDecorations', () => {
      const e = vscode.window.activeTextEditor;
      if (eolDecType || gutterDecType) {
        disposeDecos();
        vscode.window.showInformationMessage('Code Detector: Decorations hidden.');
      } else {
        applyDecos(e);
        vscode.window.showInformationMessage('Code Detector: Decorations shown.');
      }
    }),

    vscode.commands.registerCommand('codeDetector.copyStats', () => {
      const e = vscode.window.activeTextEditor;
      if (!e) return;
      const meta  = resolveLanguage(e.document);
      const stats = getStats(e.document.getText());
      const fname = e.document.fileName.split(/[/\\]/).pop();
      vscode.env.clipboard.writeText([
        `Language : ${meta?.name ?? 'Unknown'}`,
        `File     : ${fname}`,
        `Lines    : ${stats.total}`,
        `Non-empty: ${stats.nonEmpty}`,
        `Comments : ${stats.comments}`,
        `Tokens   : ${stats.words}`,
        `Chars    : ${stats.chars}`,
      ].join('\n')).then(() =>
        vscode.window.showInformationMessage('Code Detector: Stats copied!')
      );
    })
  );
}

function deactivate() {
  disposeDecos();
  currentPanel?.dispose();
}

module.exports = { activate, deactivate };
