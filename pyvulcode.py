#!/usr/bin/env python3
"""
EDUCATIONAL PURPOSES ONLY
Demonstrates common Python security vulnerabilities.
Do NOT use in production.
"""

import sqlite3
import subprocess
import pickle
import os

# ─────────────────────────────────────────
# 1. SQL INJECTION
# ─────────────────────────────────────────
def login(username, password):
    conn = sqlite3.connect("users.db")
    cursor = conn.cursor()
    query = f"SELECT * FROM users WHERE username='{username}' AND password='{password}'"
    cursor.execute(query)
    return cursor.fetchone()

# Exploit: login("admin' --", "anything")


# ─────────────────────────────────────────
# 2. COMMAND INJECTION
# ─────────────────────────────────────────
def ping_host(host):
    # VULNERABLE: shell=True with unsanitized input
    result = subprocess.run(f"ping -c 1 {host}", shell=True, capture_output=True, text=True)
    return result.stdout

# Exploit: ping_host("8.8.8.8; rm -rf /tmp/test")


# ─────────────────────────────────────────
# 3. PATH TRAVERSAL
# ─────────────────────────────────────────
def read_file(filename):
    base_dir = "/var/www/files/"
    # VULNERABLE: no path sanitization
    with open(base_dir + filename, "r") as f:
        return f.read()

# Exploit: read_file("../../etc/passwd")


# ─────────────────────────────────────────
# 4. INSECURE DESERIALIZATION
# ─────────────────────────────────────────
def load_user_data(data: bytes):
    # VULNERABLE: pickle can execute arbitrary code
    return pickle.loads(data)

# Exploit: craft a pickle payload with __reduce__ to run os.system()


# ─────────────────────────────────────────
# 5. HARDCODED CREDENTIALS
# ─────────────────────────────────────────
DB_PASSWORD = "supersecret123"
API_KEY = "sk-abc123hardcoded"

def connect_db():
    return sqlite3.connect(f"db_{DB_PASSWORD}.db")


# ─────────────────────────────────────────
# 6. EVAL / CODE INJECTION
# ─────────────────────────────────────────
def calculate(expression):
    # VULNERABLE: eval executes arbitrary Python code
    return eval(expression)

# Exploit: calculate("__import__('os').system('whoami')")


# ─────────────────────────────────────────
# 7. OPEN REDIRECT
# ─────────────────────────────────────────
def redirect_user(request_url):
    # VULNERABLE: blindly redirects to user-supplied URL
    redirect_target = request_url.get("next")
    return f"302 Redirect -> {redirect_target}"

# Exploit: ?next=https://evil.com


# ─────────────────────────────────────────
# 8. INSECURE RANDOM (for secrets)
# ─────────────────────────────────────────
import random

def generate_token():
    # VULNERABLE: random is not cryptographically secure
    return str(random.randint(100000, 999999))


# ─────────────────────────────────────────
# 9. SENSITIVE DATA IN LOGS
# ─────────────────────────────────────────
import logging

def process_payment(card_number, cvv):
    # VULNERABLE: logs sensitive data in plaintext
    logging.info(f"Processing payment for card: {card_number}, CVV: {cvv}")
    return "Payment processed"


# ─────────────────────────────────────────
# 10. XML EXTERNAL ENTITY (XXE)
# ─────────────────────────────────────────
from xml.etree import ElementTree as ET

def parse_xml(xml_string):
    # VULNERABLE: default parser resolves external entities
    tree = ET.fromstring(xml_string)
    return tree.find("username").text

# Exploit: inject <!DOCTYPE with SYSTEM file:///etc/passwd>
