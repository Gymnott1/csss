import subprocess
import json
import os
import uuid
import re
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from database import SessionLocal, CustomVulnerability
import uvicorn
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI(title="CSSS Scaling API")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)
SCAN_DIR = "scans"
if not os.path.exists(SCAN_DIR):
    os.makedirs(SCAN_DIR)

def run_semgrep_scan(code_content, language):
    
    lang = language.lower().strip()
    ext_map = {
        "python": ".py", 
        "javascript": ".js", 
        "js": ".js",
        "typescript": ".ts", 
        "java": ".java", 
        "c++": ".cpp",
        "cpp": ".cpp",
        "c": ".c",
        "go": ".go",
        "php": ".php",
        "sql": ".sql"
    }
    
    extension = ext_map.get(lang, ".txt")
    
    unique_id = str(uuid.uuid4())
    temp_path = os.path.join(SCAN_DIR, f"scan_{unique_id}{extension}")

    with open(temp_path, "w") as f:
        f.write(code_content)

    try:
        
        cmd = ["semgrep", "--config=p/security-audit", "--config=p/secrets",  "--config=p/default", "--json", temp_path ]
                
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.stderr:
            print(f"⚠️ Semgrep CLI Error: {result.stderr}")

        if not result.stdout:
            return []

        raw_data = json.loads(result.stdout)
        findings = []

        for issue in raw_data.get("results", []):
            findings.append({
                "title": issue["check_id"].split(".")[-1].title(),
                "severity": issue["extra"]["severity"],
                "line": issue["start"]["line"],
                "description": issue["extra"]["message"],
                "remediation": "Follow OWASP best practices to sanitize this input.",
                "tool": "Semgrep"
            })
        
        return findings

    except Exception as e:
        print(f"❌ Backend Scan Error: {e}")
        return []
    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)

@app.post("/api/scan")
def scan_snippet(request: dict):
    code = request.get("code", "")
    language = request.get("language", "python")

    all_findings = run_semgrep_scan(code, language)

    db = SessionLocal()
    rules = db.query(CustomVulnerability).all()
    for rule in rules:
        if re.search(rule.pattern, code, re.IGNORECASE):
            all_findings.append({
                "title": rule.title,
                "severity": rule.severity,
                "line": 1,
                "description": rule.description,
                "tool": "CustomDB"
            })
    db.close()

    return {
        "status": "success",
        "total": len(all_findings),
        "findings": all_findings
    }

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)