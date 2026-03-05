# 🟢 Code Detector — Chrome Extension

Automatically detects code blocks on **any webpage** and overlays a sleek green badge on each one.

---

## Features

- ✅ Detects `<pre>`, `<code>`, and class-based code containers (highlight.js, Prism.js, Shiki, etc.)
- ✅ Identifies **15+ languages**: JavaScript, Python, HTML, CSS, Java, SQL, Shell, TypeScript, Ruby, Go, Rust, PHP, C, JSON, YAML, and more
- ✅ Pulsing green dot badge on every detected code block
- ✅ **Copy button** appears on hover — copies the code to clipboard
- ✅ Works on SPAs (React, Vue, Angular) — watches for dynamically added content
- ✅ Popup shows count of detected blocks on the current page
- ✅ Toggle badges on/off without reloading
- ✅ Re-scan button for manual refresh

---

## Installation (Developer Mode)

1. Open Chrome and go to `chrome://extensions/`
2. Enable **Developer mode** (top right toggle)
3. Click **"Load unpacked"**
4. Select the `code-detector-extension` folder
5. The extension is now active on all tabs!

---

## How It Works

The content script (`content.js`) runs on every page and:
1. Queries for `pre`, `code`, and common code-related class names
2. Runs language detection heuristics against the text content
3. Wraps matched elements in a relative-positioned container
4. Injects a floating green badge in the top-right corner
5. Observes DOM mutations to handle dynamically loaded content

---

## File Structure

```
code-detector-extension/
├── manifest.json      # Extension config (MV3)
├── content.js         # Page scanner + badge injector
├── styles.css         # Badge styles (injected into pages)
├── popup.html         # Extension popup UI
├── popup.js           # Popup logic
└── icons/
    ├── icon16.png
    ├── icon48.png
    └── icon128.png
```
