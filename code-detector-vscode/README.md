# 🟢 Code Detector — VS Code Extension v2

Detects the programming language of any open file and shows **3 visual layers** directly inside the editor.

---

## What you'll see

### 1. CodeLens Badge (inside the editor, above line 1)
```
⚡  JavaScript  ·  Born 1995  ·  Web, servers, mobile    ≡ 142 lines  {} 138 non-empty  💬 12 comments
```
These are **clickable** — the language badge opens the info panel, the stats badge copies stats to clipboard.

### 2. Gutter Highlight
A colored left border + subtle background tint on every line, using the language's brand color (yellow for JS, blue for TS, etc.)

### 3. Status Bar (bottom right)
`👁 ⚡ JavaScript` — click to open the info panel.

### 4. Info Panel (opens beside your editor)
Full details: language icon, paradigm, birth year, use cases, 6 file stats, and a fun fact.

---

## Installation

1. Unzip the folder
2. Open VS Code → **File → Open Folder** → select `code-detector-vscode`
3. Press **`F5`** — a new *Extension Development Host* window opens
4. Open any code file → the CodeLens badge appears above line 1

> **Note:** CodeLens must be enabled in VS Code. It is on by default.  
> If not showing: `Settings → Editor: Code Lens → ✓ Enable`

---

## Commands (`Ctrl+Shift+P`)

| Command | Action |
|---|---|
| `Code Detector: Show Language Info` | Open rich info panel |
| `Code Detector: Toggle Gutter Decorations` | Show/hide the colored left border |
| `Code Detector: Copy File Stats` | Copy stats to clipboard |

---

## Supported Languages (20)
JavaScript · TypeScript · Python · Java · C/C++ · Rust · Go · Ruby · PHP · Swift · Kotlin · SQL · Shell/Bash · HTML · CSS · JSON · YAML · Markdown · C# · XML
