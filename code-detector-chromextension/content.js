// Code Detector - Content Script v3
// Clickable badge → rich tooltip with language info + code stats

(function () {
  'use strict';

  if (window.__codeDetectorActive) return;
  window.__codeDetectorActive = true;

  const PROCESSED_ATTR = 'data-cdx-id';
  const BADGE_ATTR     = 'data-cdx-badge';

  const processedIds = new Set();
  let idCounter = 0;

  // ── Language metadata: pattern + rich info ────────────────────────────────
  const LANGUAGES = [
    {
      name: 'JavaScript',
      pattern: /\b(function\s+\w+|const\s+\w+\s*=|let\s+\w+\s*=|var\s+\w+\s*=|=>\s*[\{\w]|async\s+function|await\s+\w+|require\s*\(|module\.exports|import\s+\w+\s+from)\b/,
      icon: '⚡',
      color: '#f7df1e',
      born: '1995',
      paradigm: 'Multi-paradigm',
      usedFor: 'Web, servers, mobile',
      tip: 'Powers the interactive web. Runs in every browser natively.',
    },
    {
      name: 'TypeScript',
      pattern: /\b(interface\s+\w+\s*\{|type\s+\w+\s*=|enum\s+\w+\s*\{|readonly\s+\w+|namespace\s+\w+|declare\s+(const|let|var|function|class))\b/,
      icon: '🔷',
      color: '#3178c6',
      born: '2012',
      paradigm: 'Typed / OOP',
      usedFor: 'Large-scale web apps',
      tip: 'JavaScript with static types. Compiles down to plain JS.',
    },
    {
      name: 'Python',
      pattern: /\b(def\s+\w+\s*\(|import\s+\w+|from\s+\w+\s+import|class\s+\w+[\s:(]|if\s+__name__\s*==|print\s*\(|elif\s+|lambda\s+[\w,]+:|self\.\w+)\b/,
      icon: '🐍',
      color: '#3776ab',
      born: '1991',
      paradigm: 'Multi-paradigm',
      usedFor: 'AI/ML, scripting, data',
      tip: 'Famous for readability. The #1 language in AI & data science.',
    },
    {
      name: 'Java',
      pattern: /\b(public\s+(class|static|void|final)|private\s+\w+|protected\s+\w+|@Override|System\.out\.|new\s+\w+\s*\(|throws\s+\w+|extends\s+\w+)\b/,
      icon: '☕',
      color: '#ed8b00',
      born: '1995',
      paradigm: 'Object-Oriented',
      usedFor: 'Enterprise, Android',
      tip: '"Write once, run anywhere." Runs on the JVM.',
    },
    {
      name: 'C/C++',
      pattern: /\b(#include\s*[<"]|printf\s*\(|scanf\s*\(|int\s+main\s*\(|void\s+\w+\s*\(|malloc\s*\(|nullptr|std::|cout\s*<<|cin\s*>>)\b/,
      icon: '⚙️',
      color: '#00599c',
      born: '1972',
      paradigm: 'Procedural / OOP',
      usedFor: 'Systems, games, OS',
      tip: 'Close to the metal. Used in OS kernels and performance-critical systems.',
    },
    {
      name: 'Rust',
      pattern: /\b(fn\s+\w+\s*[\(<]|let\s+mut\s+|use\s+\w+::|impl\s+\w+|struct\s+\w+\s*\{|enum\s+\w+\s*\{|match\s+\w+\s*\{|Some\(|None\b|Result<)\b/,
      icon: '🦀',
      color: '#ce422b',
      born: '2010',
      paradigm: 'Systems / Functional',
      usedFor: 'Systems, WebAssembly',
      tip: 'Memory-safe without a GC. Loved by devs for 8 years running.',
    },
    {
      name: 'Go',
      pattern: /\b(func\s+\w+\s*\(|package\s+main|import\s*\(|fmt\.\w+\(|:=\s*|defer\s+\w+|go\s+func|make\s*\()\b/,
      icon: '🐹',
      color: '#00add8',
      born: '2009',
      paradigm: 'Concurrent / Imperative',
      usedFor: 'Cloud, CLI tools, APIs',
      tip: 'Built at Google. Goroutines make concurrency simple.',
    },
    {
      name: 'Ruby',
      pattern: /(\bdef\s+\w+|\bend\b|\bputs\s+|\brequire\s+['"]|\battr_(reader|writer|accessor)\b|\.each\s*(do|\{)|\bnil\b)/,
      icon: '💎',
      color: '#cc342d',
      born: '1995',
      paradigm: 'Object-Oriented',
      usedFor: 'Web (Rails), scripting',
      tip: 'Designed for developer happiness. Everything is an object.',
    },
    {
      name: 'PHP',
      pattern: /(<\?php|\$\w+\s*=|echo\s+["'\$]|\$this->|\$_GET|\$_POST|namespace\s+\w+|use\s+\w+\\\\)/,
      icon: '🐘',
      color: '#8892be',
      born: '1994',
      paradigm: 'Imperative / OOP',
      usedFor: 'Web backends (WordPress)',
      tip: 'Runs ~79% of all websites. Laravel is its popular modern framework.',
    },
    {
      name: 'Swift',
      pattern: /\b(func\s+\w+\s*\(|var\s+\w+\s*:|let\s+\w+\s*:|guard\s+let|if\s+let\s+|extension\s+\w+|protocol\s+\w+|@objc)\b/,
      icon: '🍎',
      color: '#f05138',
      born: '2014',
      paradigm: 'Multi-paradigm',
      usedFor: 'iOS, macOS apps',
      tip: 'Apple\'s modern language. Replaces Objective-C for Apple platforms.',
    },
    {
      name: 'Kotlin',
      pattern: /\b(fun\s+\w+\s*\(|val\s+\w+\s*=|var\s+\w+\s*=|data\s+class|companion\s+object|when\s*\(|\.let\s*\{|\.apply\s*\{)\b/,
      icon: '🟣',
      color: '#7f52ff',
      born: '2011',
      paradigm: 'Multi-paradigm',
      usedFor: 'Android, server-side',
      tip: 'JetBrains\' answer to Java. 100% interoperable with Java.',
    },
    {
      name: 'SQL',
      pattern: /\b(SELECT\s+[\w\*]+\s+FROM|INSERT\s+INTO\s+\w+|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+(TABLE|DATABASE|INDEX)|ALTER\s+TABLE|DROP\s+TABLE|INNER\s+JOIN|LEFT\s+JOIN)\b/i,
      icon: '🗄️',
      color: '#e38c00',
      born: '1974',
      paradigm: 'Declarative / Query',
      usedFor: 'Databases, analytics',
      tip: 'The language of relational data. Every database speaks SQL.',
    },
    {
      name: 'Shell/Bash',
      pattern: /^(#!\/bin\/(bash|sh|zsh|fish)|sudo\s+\w+|apt(-get)?\s+(install|update)|npm\s+(install|run|start)|pip\s+install|git\s+(clone|commit|push)|docker\s+\w+)/m,
      icon: '🖥️',
      color: '#4eaa25',
      born: '1989',
      paradigm: 'Scripting / Command',
      usedFor: 'Automation, DevOps',
      tip: 'The glue of Unix. Automates everything from deployments to backups.',
    },
    {
      name: 'HTML',
      pattern: /(<(!DOCTYPE\s+html|html[\s>]|head[\s>]|body[\s>]|div[\s>]|script[\s>]|link\s|meta\s)[^>]*>|<\/\w+>)/i,
      icon: '🌐',
      color: '#e34f26',
      born: '1993',
      paradigm: 'Markup',
      usedFor: 'Web page structure',
      tip: 'The skeleton of every webpage. Not a programming language, but essential.',
    },
    {
      name: 'CSS',
      pattern: /([.#][\w-]+(\s*,\s*[.#][\w-]+)*\s*\{[\s\S]*?\}|@media\s+|@keyframes\s+\w+|:root\s*\{|--[\w-]+\s*:)/,
      icon: '🎨',
      color: '#264de4',
      born: '1996',
      paradigm: 'Stylesheet',
      usedFor: 'Web page styling',
      tip: 'Styles the web. CSS Grid and Flexbox changed layout forever.',
    },
    {
      name: 'JSON',
      pattern: /^\s*(\{[\s\S]*"[\w\s-]+"[\s\S]*:|\[[\s\S]*\{)\s*[\}\]]/,
      icon: '📦',
      color: '#000000',
      born: '2001',
      paradigm: 'Data format',
      usedFor: 'APIs, config, data exchange',
      tip: 'The universal language of APIs. Lightweight and human-readable.',
    },
    {
      name: 'YAML',
      pattern: /^([\w-]+:\s+.+\n[\w-]+:|---\n)/m,
      icon: '📋',
      color: '#cb171e',
      born: '2001',
      paradigm: 'Data format',
      usedFor: 'Config files, CI/CD',
      tip: 'Human-friendly config format. Powers Docker Compose, GitHub Actions, k8s.',
    },
    {
      name: 'XML',
      pattern: /(<\?xml[\s\S]*\?>|<[a-zA-Z][\w:.-]*(\s[\s\S]*?)?>[\s\S]*<\/[a-zA-Z][\w:.-]*>)/,
      icon: '📄',
      color: '#ff6600',
      born: '1998',
      paradigm: 'Markup / Data format',
      usedFor: 'Data exchange, config',
      tip: 'Verbose but structured. Still used in enterprise and Android layouts.',
    },
  ];

  function detectLanguage(code) {
    const text = (code || '').trim();
    if (text.length < 8) return null;
    for (const lang of LANGUAGES) {
      if (lang.pattern.test(text)) return lang;
    }
    if (text.split('\n').length > 2 && /[{};()=><]/.test(text)) {
      return { name: 'Code', icon: '📝', color: '#4ade80', born: '—', paradigm: '—', usedFor: 'General purpose', tip: 'Detected code pattern but language is ambiguous.' };
    }
    return null;
  }

  // ── Code stats ────────────────────────────────────────────────────────────
  function getCodeStats(code) {
    const lines  = code.split('\n');
    const nonEmpty = lines.filter(l => l.trim().length > 0);
    const chars  = code.replace(/\s/g, '').length;
    const words  = code.trim().split(/\s+/).filter(Boolean).length;
    return {
      lines:    lines.length,
      nonEmpty: nonEmpty.length,
      chars,
      words,
    };
  }

  // ── Element helpers ───────────────────────────────────────────────────────
  function getBestTarget(el) {
    return el.closest('pre') || el;
  }

  function isCodeCandidate(el) {
    if (!el || el.nodeType !== 1) return false;
    if (el.hasAttribute(BADGE_ATTR)) return false;
    const tag = el.tagName.toLowerCase();
    if (tag === 'pre' || tag === 'code') return true;
    const combined = ((el.className || '') + ' ' + (el.id || '')).toLowerCase();
    return /language-|lang-|hljs|prism|shiki|highlight|codeblock|code-block|sourceCode|prettyprint|rouge|pygments/.test(combined);
  }

  // ── Shadow DOM badge + tooltip ────────────────────────────────────────────
  function injectBadge(target, langMeta) {
    const pos = window.getComputedStyle(target).position;
    if (pos === 'static') target.style.setProperty('position', 'relative', 'important');

    const host = document.createElement('span');
    host.setAttribute(BADGE_ATTR, 'true');
    host.style.cssText = [
      'position:absolute', 'top:8px', 'right:8px',
      'z-index:2147483647', 'display:inline-block',
      'pointer-events:auto', 'line-height:0',
      'border:none', 'background:none', 'padding:0', 'margin:0',
    ].join(';');

    const shadow = host.attachShadow({ mode: 'open' });
    const rawCode = target.innerText || target.textContent || '';
    const stats   = getCodeStats(rawCode);
    const c       = langMeta.color;

    shadow.innerHTML = `
      <style>
        :host { all: initial; display: inline-block; font-family: 'SF Mono','Fira Code','Cascadia Code',ui-monospace,monospace; }

        /* ── Badge pill ── */
        .badge {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          background: rgba(6,14,8,0.94);
          border: 1.5px solid rgba(74,222,128,0.5);
          border-radius: 20px;
          padding: 4px 11px 4px 7px;
          font-size: 11px;
          font-weight: 600;
          color: #4ade80;
          white-space: nowrap;
          cursor: pointer;
          user-select: none;
          box-shadow: 0 0 0 1px rgba(74,222,128,0.1), 0 2px 12px rgba(74,222,128,0.25), 0 4px 28px rgba(0,0,0,0.5);
          animation: pop 0.28s cubic-bezier(.34,1.56,.64,1) forwards;
          position: relative;
          transition: border-color .18s, box-shadow .18s, transform .12s;
        }
        .badge:hover {
          border-color: rgba(74,222,128,0.75);
          box-shadow: 0 0 0 1px rgba(74,222,128,0.2), 0 4px 18px rgba(74,222,128,0.38), 0 6px 36px rgba(0,0,0,0.55);
          transform: translateY(-1px);
        }
        .badge.open {
          border-color: rgba(74,222,128,0.85);
          border-bottom-left-radius: 4px;
          border-bottom-right-radius: 4px;
        }
        @keyframes pop {
          from { opacity:0; transform:scale(0.8) translateY(-4px); }
          to   { opacity:1; transform:scale(1)   translateY(0);    }
        }

        .dot {
          width: 7px; height: 7px;
          border-radius: 50%;
          background: #4ade80;
          flex-shrink: 0;
          box-shadow: 0 0 8px rgba(74,222,128,0.9), 0 0 16px rgba(74,222,128,0.45);
          animation: glow 2.5s ease-in-out infinite;
          transition: background .2s;
        }
        @keyframes glow {
          0%,100% { transform:scale(1);   box-shadow:0 0 6px rgba(74,222,128,.85),0 0 12px rgba(74,222,128,.4); }
          50%      { transform:scale(1.2); box-shadow:0 0 10px rgba(74,222,128,1), 0 0 22px rgba(74,222,128,.6); }
        }
        .lang-label {
          font-size: 10px;
          letter-spacing: 0.07em;
          text-transform: uppercase;
          color: #4ade80;
        }
        .chevron {
          font-size: 8px;
          color: rgba(74,222,128,0.5);
          transition: transform .2s, color .2s;
          line-height: 1;
          margin-left: -2px;
        }
        .badge.open .chevron { transform: rotate(180deg); color: rgba(74,222,128,0.85); }

        /* ── Tooltip card ── */
        .tooltip {
          position: absolute;
          top: calc(100% + 6px);
          right: 0;
          width: 240px;
          background: rgba(5, 12, 7, 0.97);
          border: 1.5px solid rgba(74,222,128,0.38);
          border-radius: 12px 0 12px 12px;
          box-shadow: 0 8px 32px rgba(0,0,0,0.7), 0 0 0 1px rgba(74,222,128,0.08), 0 2px 16px rgba(74,222,128,0.15);
          overflow: hidden;
          display: none;
          animation: slideDown 0.22s cubic-bezier(.16,1,.3,1) forwards;
          z-index: 2147483647;
        }
        .tooltip.visible { display: block; }
        @keyframes slideDown {
          from { opacity:0; transform:translateY(-8px) scale(0.96); }
          to   { opacity:1; transform:translateY(0)    scale(1);    }
        }

        /* Tooltip header */
        .tt-header {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px 14px 10px;
          background: linear-gradient(135deg, rgba(74,222,128,0.08), rgba(74,222,128,0.02));
          border-bottom: 1px solid rgba(74,222,128,0.12);
        }
        .tt-icon {
          font-size: 22px;
          line-height: 1;
          flex-shrink: 0;
        }
        .tt-name {
          font-size: 14px;
          font-weight: 700;
          color: #f0fff4;
          letter-spacing: -0.01em;
        }
        .tt-paradigm {
          font-size: 9.5px;
          color: rgba(74,222,128,0.55);
          text-transform: uppercase;
          letter-spacing: 0.06em;
          margin-top: 1px;
        }
        .tt-color-bar {
          width: 3px;
          align-self: stretch;
          border-radius: 2px;
          flex-shrink: 0;
        }

        /* Tooltip rows */
        .tt-body { padding: 10px 14px; display: flex; flex-direction: column; gap: 6px; }

        .tt-row {
          display: flex;
          align-items: flex-start;
          gap: 8px;
          font-size: 11px;
        }
        .tt-row-icon { font-size: 12px; flex-shrink: 0; margin-top: 0px; line-height: 1.5; }
        .tt-row-label { color: rgba(74,222,128,0.45); flex-shrink: 0; min-width: 48px; line-height: 1.5; }
        .tt-row-val   { color: #c4ffd8; line-height: 1.5; }

        /* Stats grid */
        .tt-stats {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 6px;
          padding: 0 14px 10px;
        }
        .stat-box {
          background: rgba(74,222,128,0.05);
          border: 1px solid rgba(74,222,128,0.12);
          border-radius: 8px;
          padding: 7px 10px;
          text-align: center;
        }
        .stat-num {
          font-size: 16px;
          font-weight: 700;
          color: #4ade80;
          line-height: 1;
          text-shadow: 0 0 12px rgba(74,222,128,0.4);
        }
        .stat-lbl {
          font-size: 9px;
          color: rgba(74,222,128,0.4);
          text-transform: uppercase;
          letter-spacing: 0.06em;
          margin-top: 2px;
        }

        /* Tip row */
        .tt-tip {
          margin: 0 14px 10px;
          padding: 8px 10px;
          background: rgba(74,222,128,0.04);
          border-left: 2px solid rgba(74,222,128,0.4);
          border-radius: 0 6px 6px 0;
          font-size: 10.5px;
          color: rgba(196,255,216,0.75);
          line-height: 1.5;
          font-style: italic;
        }

        /* Copy button at bottom */
        .tt-copy {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 6px;
          width: calc(100% - 28px);
          margin: 0 14px 12px;
          padding: 7px;
          background: rgba(74,222,128,0.07);
          border: 1px solid rgba(74,222,128,0.25);
          border-radius: 8px;
          color: #4ade80;
          font-family: inherit;
          font-size: 10px;
          font-weight: 700;
          letter-spacing: 0.06em;
          text-transform: uppercase;
          cursor: pointer;
          transition: background .15s, border-color .15s;
          outline: none;
          box-sizing: border-box;
        }
        .tt-copy:hover { background:rgba(74,222,128,0.16); border-color:rgba(74,222,128,.55); color:#86efac; }
        .tt-copy.ok    { background:rgba(74,222,128,0.22); border-color:#4ade80; color:#bbf7d0; pointer-events:none; }
      </style>

      <div class="badge" id="badge">
        <span class="dot"></span>
        <span class="lang-label">${langMeta.name}</span>
        <span class="chevron">▼</span>
      </div>

      <div class="tooltip" id="tooltip">
        <div class="tt-header">
          <span class="tt-icon">${langMeta.icon}</span>
          <div class="tt-color-bar" style="background:${c};"></div>
          <div>
            <div class="tt-name">${langMeta.name}</div>
            <div class="tt-paradigm">${langMeta.paradigm}</div>
          </div>
        </div>

        <div class="tt-body">
          <div class="tt-row">
            <span class="tt-row-icon">📅</span>
            <span class="tt-row-label">Born</span>
            <span class="tt-row-val">${langMeta.born}</span>
          </div>
          <div class="tt-row">
            <span class="tt-row-icon">🎯</span>
            <span class="tt-row-label">Used for</span>
            <span class="tt-row-val">${langMeta.usedFor}</span>
          </div>
        </div>

        <div class="tt-stats">
          <div class="stat-box">
            <div class="stat-num">${stats.lines}</div>
            <div class="stat-lbl">Lines</div>
          </div>
          <div class="stat-box">
            <div class="stat-num">${stats.nonEmpty}</div>
            <div class="stat-lbl">Non-empty</div>
          </div>
          <div class="stat-box">
            <div class="stat-num">${stats.words}</div>
            <div class="stat-lbl">Tokens</div>
          </div>
          <div class="stat-box">
            <div class="stat-num">${stats.chars}</div>
            <div class="stat-lbl">Chars</div>
          </div>
        </div>

        <div class="tt-tip">${langMeta.tip}</div>

        <button class="tt-copy" id="copyBtn">⎘ Copy code</button>
      </div>
    `;

    // ── Toggle tooltip on badge click ──
    const badge   = shadow.getElementById('badge');
    const tooltip = shadow.getElementById('tooltip');
    let open = false;

    badge.addEventListener('click', (e) => {
      e.stopPropagation();
      open = !open;
      badge.classList.toggle('open', open);
      tooltip.classList.toggle('visible', open);
    });

    // Close when clicking outside
    document.addEventListener('click', () => {
      if (open) {
        open = false;
        badge.classList.remove('open');
        tooltip.classList.remove('visible');
      }
    }, true);

    // Copy button
    shadow.getElementById('copyBtn').addEventListener('click', (e) => {
      e.stopPropagation();
      const btn = e.currentTarget;
      const text = rawCode.trim();
      const fallback = () => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
      };
      try { navigator.clipboard.writeText(text).catch(fallback); } catch (_) { fallback(); }
      btn.textContent = '✓ Copied!';
      btn.classList.add('ok');
      setTimeout(() => { btn.textContent = '⎘ Copy code'; btn.classList.remove('ok'); }, 2000);
    });

    target.appendChild(host);
  }

  // ── Process one element ───────────────────────────────────────────────────
  function processElement(el) {
    const target = getBestTarget(el);
    if (!target || target.hasAttribute(PROCESSED_ATTR)) return;

    const langMeta = detectLanguage(target.innerText || target.textContent || '');
    if (!langMeta) return;

    const id = ++idCounter;
    target.setAttribute(PROCESSED_ATTR, String(id));
    processedIds.add(id);
    injectBadge(target, langMeta);
  }

  // ── Full page scan ────────────────────────────────────────────────────────
  function scanPage() {
    const selector = [
      'pre','code',
      '[class*="language-"]','[class*="lang-"]',
      '[class*="hljs"]','[class*="prism"]',
      '[class*="shiki"]','[class*="highlight"]',
      '[class*="codeblock"]','[class*="code-block"]',
      '[class*="CodeBlock"]','[class*="sourceCode"]',
      '[class*="prettyprint"]',
    ].join(',');

    document.querySelectorAll(selector).forEach(el => {
      if (isCodeCandidate(el)) processElement(el);
    });
    return processedIds.size;
  }

  // ── Boot ──────────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanPage);
  } else {
    scanPage();
  }

  let mutationTimer = null;
  const obs = new MutationObserver((mutations) => {
    const hasNew = mutations.some(m =>
      [...m.addedNodes].some(n => n.nodeType === 1 && !n.hasAttribute(PROCESSED_ATTR) && !n.hasAttribute(BADGE_ATTR))
    );
    if (hasNew) { clearTimeout(mutationTimer); mutationTimer = setTimeout(scanPage, 400); }
  });
  obs.observe(document.body, { childList: true, subtree: true });

  // ── Popup messages ────────────────────────────────────────────────────────
  chrome.runtime.onMessage.addListener((msg, _s, sendResponse) => {
    if (msg.action === 'getStats')  sendResponse({ count: processedIds.size });
    if (msg.action === 'rescan')    sendResponse({ count: scanPage() });
    if (msg.action === 'toggleVisibility') {
      document.querySelectorAll(`[${BADGE_ATTR}]`).forEach(h => {
        h.style.display = msg.visible ? 'inline-block' : 'none';
      });
      sendResponse({ ok: true });
    }
    return true;
  });

})();
