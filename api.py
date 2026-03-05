import subprocess
import json
import os
import tempfile
import re
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from database import SessionLocal, CustomVulnerability

app = FastAPI(title="CSSS Advanced Analysis API")

# --- SCHEMAS ---
class Finding(BaseModel):
    title: str
    severity: str
    line: int = 0
    description: str
    remediation: str
    tool: str 

class ScanResponse(BaseModel):
    status: str
    language: str
    total_findings: int
    findings: list[Finding]
    ai_analysis: dict

# --- SEMGREP ENGINE ---
def run_semgrep_scan(code_content, language):
    ext_map = {"python": ".py", "javascript": ".js", "java": ".java", "go": ".go"}
    extension = ext_map.get(language.lower(), ".txt")

    with tempfile.NamedTemporaryFile(suffix=extension, delete=False, mode='w') as temp:
        temp.write(code_content)
        temp_path = temp.name

    try:
        # Note: --config=auto needs internet the first time to fetch rules
        # If no internet, use --config=p/security
        cmd = ["semgrep", "--config=auto", "--json", temp_path]
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if not result.stdout: return []
        raw_data = json.loads(result.stdout)
        
        findings = []
        for issue in raw_data.get("results", []):
            findings.append(Finding(
                title=issue["check_id"].split(".")[-1].replace("-", " ").title(),
                severity=issue["extra"]["severity"],
                line=issue["start"]["line"],
                description=issue["extra"]["message"],
                remediation="Review and sanitize this code block.",
                tool="Semgrep"
            ))
        return findings
    except Exception as e:
        print(f"Semgrep Error: {e}")
        return []
    finally:
        if os.path.exists(temp_path): os.remove(temp_path)

# --- ENDPOINT ---
@app.post("/api/scan", response_model=ScanResponse)
async def scan_snippet(request: dict):
    code = request.get("code", "")
    language = request.get("language", "python")
    
    all_findings = []
    
    # 1. Semgrep Scan
    semgrep_findings = run_semgrep_scan(code, language)
    all_findings.extend(semgrep_findings)

    # 2. Custom DB Scan
    db = SessionLocal()
    rules = db.query(CustomVulnerability).all()
    for rule in rules:
        if re.search(rule.pattern, code, re.IGNORECASE):
            all_findings.append(Finding(
                title=rule.title,
                severity=rule.severity,
                line=1,
                description=rule.description,
                remediation=rule.remediation,
                tool="CustomDB"
            ))
    db.close()

    return {
        "status": "success",
        "language": language,
        "total_findings": len(all_findings),
        "findings": all_findings,
        "ai_analysis": {"review": "Demo AI Scan Complete", "secure_version": "// AI Refactoring coming next"}
    }

# --- THIS PART IS CRITICAL TO START THE SERVER ---
if __name__ == "__main__":
    import uvicorn
    # This prints in your terminal so you know it started
    print("🚀 CSSS Analysis Engine starting on http://localhost:8000")
    uvicorn.run(app, host="0.0.0.0", port=8000)