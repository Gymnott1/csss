(function() {
    'use strict';

    if (window.__codeDetectorActive) return;
    window.__codeDetectorActive = true;

    const PROCESSED_ATTR = 'data-cdx-id';
    const BADGE_ATTR = 'data-cdx-badge';

    const processedIds = new Set();
    let idCounter = 0;

    const LANGUAGES = [{
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
            icon: '⚡',
            color: '#3178c6',
            born: '2012',
            paradigm: 'Typed / OOP',
            usedFor: 'Large-scale web apps',
            tip: 'JavaScript with static types. Compiles down to plain JS.',
        },
        {
            name: 'Python',
            pattern: /\b(def\s+\w+\s*\(|import\s+\w+|from\s+\w+\s+import|class\s+\w+[\s:(]|if\s+__name__\s*==|print\s*\(|elif\s+|lambda\s+[\w,]+:|self\.\w+)\b/,
            icon: '⚡',
            color: '#3776ab',
            born: '1991',
            paradigm: 'Multi-paradigm',
            usedFor: 'AI/ML, scripting, data',
            tip: 'Famous for readability. The #1 language in AI & data science.',
        },
        {
            name: 'Java',
            pattern: /\b(public\s+(class|static|void|final)|private\s+\w+|protected\s+\w+|@Override|System\.out\.|new\s+\w+\s*\(|throws\s+\w+|extends\s+\w+)\b/,
            icon: '⚡',
            color: '#ed8b00',
            born: '1995',
            paradigm: 'Object-Oriented',
            usedFor: 'Enterprise, Android',
            tip: '"Write once, run anywhere." Runs on the JVM.',
        },
        {
            name: 'C/C++',
            pattern: /\b(#include\s*[<"]|printf\s*\(|scanf\s*\(|int\s+main\s*\(|void\s+\w+\s*\(|malloc\s*\(|nullptr|std::|cout\s*<<|cin\s*>>)\b/,
            icon: '⚡',
            color: '#00599c',
            born: '1972',
            paradigm: 'Procedural / OOP',
            usedFor: 'Systems, games, OS',
            tip: 'Close to the metal. Used in OS kernels and performance-critical systems.',
        },
        {
            name: 'Rust',
            pattern: /\b(fn\s+\w+\s*[\(<]|let\s+mut\s+|use\s+\w+::|impl\s+\w+|struct\s+\w+\s*\{|enum\s+\w+\s*\{|match\s+\w+\s*\{|Some\(|None\b|Result<)\b/,
            icon: '⚡',
            color: '#ce422b',
            born: '2010',
            paradigm: 'Systems / Functional',
            usedFor: 'Systems, WebAssembly',
            tip: 'Memory-safe without a GC. Loved by devs for 8 years running.',
        },
        {
            name: 'Go',
            pattern: /\b(func\s+\w+\s*\(|package\s+main|import\s*\(|fmt\.\w+\(|:=\s*|defer\s+\w+|go\s+func|make\s*\()\b/,
            icon: '⚡',
            color: '#00add8',
            born: '2009',
            paradigm: 'Concurrent / Imperative',
            usedFor: 'Cloud, CLI tools, APIs',
            tip: 'Built at Google. Goroutines make concurrency simple.',
        },
        {
            name: 'Ruby',
            pattern: /(\bdef\s+\w+|\bend\b|\bputs\s+|\brequire\s+['"]|\battr_(reader|writer|accessor)\b|\.each\s*(do|\{)|\bnil\b)/,
            icon: '⚡',
            color: '#cc342d',
            born: '1995',
            paradigm: 'Object-Oriented',
            usedFor: 'Web (Rails), scripting',
            tip: 'Designed for developer happiness. Everything is an object.',
        },
        {
            name: 'PHP',
            pattern: /(<\?php|\$\w+\s*=|echo\s+["'\$]|\$this->|\$_GET|\$_POST|namespace\s+\w+|use\s+\w+\\\\)/,
            icon: '⚡',
            color: '#8892be',
            born: '1994',
            paradigm: 'Imperative / OOP',
            usedFor: 'Web backends (WordPress)',
            tip: 'Runs ~79% of all websites. Laravel is its popular modern framework.',
        },
        {
            name: 'Swift',
            pattern: /\b(func\s+\w+\s*\(|var\s+\w+\s*:|let\s+\w+\s*:|guard\s+let|if\s+let\s+|extension\s+\w+|protocol\s+\w+|@objc)\b/,
            icon: '⚡',
            color: '#f05138',
            born: '2014',
            paradigm: 'Multi-paradigm',
            usedFor: 'iOS, macOS apps',
            tip: 'Apple\'s modern language. Replaces Objective-C for Apple platforms.',
        },
        {
            name: 'Kotlin',
            pattern: /\b(fun\s+\w+\s*\(|val\s+\w+\s*=|var\s+\w+\s*=|data\s+class|companion\s+object|when\s*\(|\.let\s*\{|\.apply\s*\{)\b/,
            icon: '⚡',
            color: '#7f52ff',
            born: '2011',
            paradigm: 'Multi-paradigm',
            usedFor: 'Android, server-side',
            tip: 'JetBrains\' answer to Java. 100% interoperable with Java.',
        },
        {
            name: 'SQL',
            pattern: /\b(SELECT\s+[\w\*]+\s+FROM|INSERT\s+INTO\s+\w+|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+(TABLE|DATABASE|INDEX)|ALTER\s+TABLE|DROP\s+TABLE|INNER\s+JOIN|LEFT\s+JOIN)\b/i,
            icon: '⚡',
            color: '#e38c00',
            born: '1974',
            paradigm: 'Declarative / Query',
            usedFor: 'Databases, analytics',
            tip: 'The language of relational data. Every database speaks SQL.',
        },
        {
            name: 'Shell/Bash',
            pattern: /^(#!\/bin\/(bash|sh|zsh|fish)|sudo\s+\w+|apt(-get)?\s+(install|update)|npm\s+(install|run|start)|pip\s+install|git\s+(clone|commit|push)|docker\s+\w+)/m,
            icon: '⚡',
            color: '#4eaa25',
            born: '1989',
            paradigm: 'Scripting / Command',
            usedFor: 'Automation, DevOps',
            tip: 'The glue of Unix. Automates everything from deployments to backups.',
        },
        {
            name: 'HTML',
            pattern: /(<(!DOCTYPE\s+html|html[\s>]|head[\s>]|body[\s>]|div[\s>]|script[\s>]|link\s|meta\s)[^>]*>|<\/\w+>)/i,
            icon: '⚡',
            color: '#e34f26',
            born: '1993',
            paradigm: 'Markup',
            usedFor: 'Web page structure',
            tip: 'The skeleton of every webpage. Not a programming language, but essential.',
        },
        {
            name: 'CSS',
            pattern: /([.#][\w-]+(\s*,\s*[.#][\w-]+)*\s*\{[\s\S]*?\}|@media\s+|@keyframes\s+\w+|:root\s*\{|--[\w-]+\s*:)/,
            icon: '⚡',
            color: '#264de4',
            born: '1996',
            paradigm: 'Stylesheet',
            usedFor: 'Web page styling',
            tip: 'Styles the web. CSS Grid and Flexbox changed layout forever.',
        },
        {
            name: 'JSON',
            pattern: /^\s*(\{[\s\S]*"[\w\s-]+"[\s\S]*:|\[[\s\S]*\{)\s*[\}\]]/,
            icon: '⚡',
            color: '#000000',
            born: '2001',
            paradigm: 'Data format',
            usedFor: 'APIs, config, data exchange',
            tip: 'The universal language of APIs. Lightweight and human-readable.',
        },
        {
            name: 'YAML',
            pattern: /^([\w-]+:\s+.+\n[\w-]+:|---\n)/m,
            icon: '⚡',
            color: '#cb171e',
            born: '2001',
            paradigm: 'Data format',
            usedFor: 'Config files, CI/CD',
            tip: 'Human-friendly config format. Powers Docker Compose, GitHub Actions, k8s.',
        },
        {
            name: 'XML',
            pattern: /(<\?xml[\s\S]*\?>|<[a-zA-Z][\w:.-]*(\s[\s\S]*?)?>[\s\S]*<\/[a-zA-Z][\w:.-]*>)/,
            icon: '⚡',
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
            return { name: 'Code', icon: '⚡', color: '#4ade80', born: '—', paradigm: '—', usedFor: 'General purpose', tip: 'Detected code pattern but language is ambiguous.' };
        }
        return null;
    }

    function getCodeStats(code) {
        const lines = code.split('\n');
        const nonEmpty = lines.filter(l => l.trim().length > 0);
        const chars = code.replace(/\s/g, '').length;
        const words = code.trim().split(/\s+/).filter(Boolean).length;
        return {
            lines: lines.length,
            nonEmpty: nonEmpty.length,
            chars,
            words,
        };
    }

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

    function injectBadge(target, langMeta) {
        const pos = window.getComputedStyle(target).position;
        if (pos === 'static') target.style.setProperty('position', 'relative', 'important');

        const host = document.createElement('span');
        host.setAttribute(BADGE_ATTR, 'true');
        host.style.cssText = 'position:absolute; top:8px; right:8px; z-index:2147483647; display:inline-block;';

        const shadow = host.attachShadow({ mode: 'open' });
        const rawCode = target.innerText || target.textContent || '';


        shadow.innerHTML = `
      <style>
        :host { all: initial; font-family: sans-serif; }
        .badge {
          display: inline-flex; align-items: center; gap: 6px;
          background: #060e08ef; border: 1.5px solid #4ade8080;
          border-radius: 20px; padding: 4px 12px;
          font-size: 11px; font-weight: 600; color: #4ade80;
          cursor: pointer; transition: 0.2s;
        }
        .tooltip {
          position: absolute; top: 32px; right: 0; width: 280px;
          background: #0b0f0bf2; border: 1px solid #4ade8060;
          border-radius: 12px; backdrop-filter: blur(8px);
          display: none; flex-direction: column; padding: 12px;
          box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000;
        }
        .tooltip.visible { display: flex; }
        .res-box { 
          max-height: 250px; overflow-y: auto; font-size: 12px; 
          color: #ccc; margin-bottom: 12px; line-height: 1.4;
        }
        .finding {
          background: #ffffff0a; border-left: 3px solid #4ade80;
          padding: 8px; margin-top: 8px; border-radius: 4px;
        }
        .finding.err { border-left-color: #ff4d4d; }
        .scan-btn {
          background: #4ade80; color: #060e08; border: none;
          padding: 10px; border-radius: 6px; font-weight: bold;
          cursor: pointer; width: 100%;
        }
        .scan-btn:disabled { background: #333; color: #777; }
        .loader { 
          display: none; width: 12px; height: 12px; border: 2px solid #000; 
          border-top: 2px solid #fff; border-radius: 50%; animation: spin 1s linear infinite;
          margin: 0 auto;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
      </style>

      <div class="badge" id="badge_btn">
        <span>${langMeta.icon}</span> <span>${langMeta.name}</span>
      </div>

      <div class="tooltip" id="scan_tooltip">
        <div style="font-weight: bold; color: #fff; margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
          🛡️ CSSS Scan
        </div>
        <div class="res-box" id="res_box">
          Ready to scan this ${langMeta.name} snippet.
        </div>
        <button class="scan-btn" id="do_scan_btn">
          <span id="btn_txt">Scan Code</span>
          <div class="loader" id="btn_loader"></div>
        </button>
      </div>
    `;


        const badgeBtn = shadow.getElementById('badge_btn');
        const tooltip = shadow.getElementById('scan_tooltip');
        const scanBtn = shadow.getElementById('do_scan_btn');
        const resBox = shadow.getElementById('res_box');
        const btnLoader = shadow.getElementById('btn_loader');
        const btnTxt = shadow.getElementById('btn_txt');

        if (!badgeBtn || !scanBtn || !resBox) return;


        let is_open = false;
        badgeBtn.onclick = (e) => {
            e.stopPropagation();
            is_open = !is_open;
            tooltip.classList.toggle('visible', is_open);
        };


        scanBtn.onclick = async(e) => {
            e.stopPropagation();

            scanBtn.disabled = true;
            btnTxt.style.display = 'none';
            btnLoader.style.display = 'block';
            resBox.innerHTML = "<i>Analyzing code for vulnerabilities...</i>";

            try {
                const response = await fetch("http://localhost:8000/api/scan", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        code: rawCode,
                        language: langMeta.name.toLowerCase()
                    })
                });

                const data = await response.json();
                resBox.innerHTML = "";

                if (data.total_findings === 0) {
                    resBox.innerHTML = "✅ No vulnerabilities found.";
                } else {
                    data.findings.forEach(f => {
                        const isHigh = f.severity.toLowerCase().includes('error') || f.severity.toLowerCase().includes('high');
                        resBox.innerHTML += `
              <div class="finding ${isHigh ? 'err' : ''}">
                <b style="color:#fff">${f.title}</b><br/>
                <small>${f.description}</small><br/>
                <div style="color:#4ade80; font-size:10px; margin-top:4px;">Fix: ${f.remediation}</div>
              </div>
            `;
                    });
                }
            } catch (err) {
                resBox.innerHTML = "<span style='color:#ff4d4d'>❌ Backend Offline</span><br/><small>Ensure API is running on port 8000</small>";
            } finally {
                scanBtn.disabled = false;
                btnTxt.style.display = 'block';
                btnLoader.style.display = 'none';
            }
        };

        document.addEventListener('click', () => {
            is_open = false;
            tooltip.classList.remove('visible');
        }, true);

        target.appendChild(host);
    }

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

    function scanPage() {
        const selector = [
            'pre', 'code',
            '[class*="language-"]', '[class*="lang-"]',
            '[class*="hljs"]', '[class*="prism"]',
            '[class*="shiki"]', '[class*="highlight"]',
            '[class*="codeblock"]', '[class*="code-block"]',
            '[class*="CodeBlock"]', '[class*="sourceCode"]',
            '[class*="prettyprint"]',
        ].join(',');

        document.querySelectorAll(selector).forEach(el => {
            if (isCodeCandidate(el)) processElement(el);
        });
        return processedIds.size;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scanPage);
    } else {
        scanPage();
    }

    let mutationTimer = null;
    const obs = new MutationObserver((mutations) => {
        const hasNew = mutations.some(m => [...m.addedNodes].some(n => n.nodeType === 1 && !n.hasAttribute(PROCESSED_ATTR) && !n.hasAttribute(BADGE_ATTR)));
        if (hasNew) {
            clearTimeout(mutationTimer);
            mutationTimer = setTimeout(scanPage, 400);
        }
    });
    obs.observe(document.body, { childList: true, subtree: true });

    chrome.runtime.onMessage.addListener((msg, _s, sendResponse) => {
        if (msg.action === 'getStats') sendResponse({ count: processedIds.size });
        if (msg.action === 'rescan') sendResponse({ count: scanPage() });
        if (msg.action === 'toggleVisibility') {
            document.querySelectorAll(`[${BADGE_ATTR}]`).forEach(h => {
                h.style.display = msg.visible ? 'inline-block' : 'none';
            });
            sendResponse({ ok: true });
        }
        return true;
    });

})();