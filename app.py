import streamlit as st
import base64
import bcrypt 
from database import SessionLocal, User, CustomVulnerability, hash_password 
import pandas as pd
st.set_page_config(page_title="CSSS Admin Dashboard", layout="wide")

def get_base64_img(path):
    with open(path, "rb") as img_file:
        return base64.b64encode(img_file.read()).decode()

if 'authenticated' not in st.session_state:
    st.session_state['authenticated'] = False
    st.session_state['role'] = None
    st.session_state['username'] = None

def login_page():
    logo_path = "/home/gymnott/2025_2026/sem2/csss_backend/logo/icon128.png"
    img_base64 = get_base64_img(logo_path)

    st.markdown("""
        <style>
        [data-testid="stVerticalBlock"] > div:has(div.login-card) {
            display: flex;
            justify-content: center;
        }
        
        

        /* Container for Logo + Title */
        .brand-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 5px;
        }

        .brand-header h1 {
            margin: 0;
            padding: 0;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 2.5rem;
        }
        
        .stButton > button {
            width: 100%;
            border-radius: 8px;
            height: 3em;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border: none;
            transition: 0.3s;
        }
        
        .stButton > button:hover {
            background-color: #0056b3;
            color: white;
        }

        .subtitle {
            text-align: center;
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 2rem;
            margin-top: 0;
        }
        </style>
    """, unsafe_allow_html=True)

    col1, col2, col3 = st.columns([1, 2, 1])

    with col2:
        st.markdown('<div class="login-card">', unsafe_allow_html=True)
        
        st.markdown(f"""
            <div class="brand-header">
                <img src="data:image/png;base64,{img_base64}" width="50">
                <h1>CSSS</h1>
            </div>
        """, unsafe_allow_html=True)
        
        st.markdown('<p class="subtitle">Code Security Scan System Admin Portal</p>', unsafe_allow_html=True)
        
        username = st.text_input("Username", placeholder="Enter your username")
        password = st.text_input("Password", type="password", placeholder="Enter your password")
        
        st.markdown("<br>", unsafe_allow_html=True)
        
        if st.button("Access Dashboard"):
            db = SessionLocal()
            user = db.query(User).filter_by(username=username).first()
            
            if user and bcrypt.checkpw(password.encode('utf-8'), user.password_hash.encode('utf-8')):
                st.session_state['authenticated'] = True
                st.session_state['role'] = user.role
                st.session_state['username'] = user.username
                st.success("Access Granted. Redirecting...")
                st.rerun()
            else:
                st.error("Access Denied: Invalid Credentials")
            db.close()
        
        st.markdown('</div>', unsafe_allow_html=True)
        
        st.markdown("<p style='text-align: center; font-size: 0.7rem; color: #555; margin-top: 2rem;'>© 2025 Code Security Scan System | Secure AI-Assisted Development</p>", unsafe_allow_html=True)

# --- MAIN DASHBOARD ---
def main():
  
    logo_path = "/home/gymnott/2025_2026/sem2/csss_backend/logo/icon128.png"
    img_base64 = get_base64_img(logo_path)

    st.sidebar.markdown(
        f"""
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
            <img src="data:image/png;base64,{img_base64}" width="50">
            <h1 style="margin: 0; font-size: 2.2rem;">CSSS</h1>
        </div>
        """,
        unsafe_allow_html=True
    )

    st.sidebar.markdown(f"**Welcome, {st.session_state['username']}**")
    st.sidebar.info(f"Role: {st.session_state['role'].upper()}")
    st.sidebar.markdown("---")
    
    menu = []
    if st.session_state['role'] in ['super_admin', 'admin']:
        menu.append("Vulnerability DB")
    if st.session_state['role'] == 'super_admin':
        menu.append("User Management")

    if not menu:
        st.info("Welcome to CSSS. Your account is active for extension scanning.")
    
    choice = st.sidebar.selectbox("Navigation", menu)

    if choice == "User Management":
        manage_users()
    elif choice == "Vulnerability DB":
        manage_vulnerabilities()

    st.sidebar.markdown("<br><br>", unsafe_allow_html=True)
    if st.sidebar.button("Logout", use_container_width=True):
        st.session_state['authenticated'] = False
        st.rerun()

# --- USER MANAGEMENT (SUPER ADMIN ONLY) ---
def manage_users():
    st.header("👥 User Management")
    db = SessionLocal()
    
    with st.expander("Add New User"):
        new_user = st.text_input("New Username")
        new_pass = st.text_input("New Password", type="password")
        new_role = st.selectbox("Role", ["admin", "user"])
        if st.button("Register User"):
            hashed = hash_password(new_pass) 
            db.add(User(username=new_user, password_hash=hashed, role=new_role))
            db.commit()
            st.success("User added!")

    users = db.query(User).all()
    df = pd.DataFrame([(u.user_id, u.username, u.role) for u in users], columns=['ID', 'Username', 'Role'])
    st.table(df)
    db.close()

def manage_vulnerabilities():
    if st.session_state['role'] == 'user':
        st.markdown("""
            <div style="background-color: #ff4d4d20; padding: 2rem; border-radius: 10px; border: 1px solid #ff4d4d; text-align: center;">
                <h2 style="color: #ff4d4d;">🚫 Access Denied</h2>
                <p style="color: #ccc;">You do not have the required permissions to access the Vulnerability Database.</p>
                <p style="font-size: 0.8rem; color: #888;">Please contact your System Administrator to request elevated privileges.</p>
            </div>
        """, unsafe_allow_html=True)
        return 



    st.header("@ Custom Vulnerability Database")
    db = SessionLocal()

    if st.session_state['role'] in ['super_admin', 'admin']:
        with st.expander("+ Define New Vulnerability Pattern"):
            col1, col2 = st.columns(2)
            with col1:
                title = st.text_input("Vuln Title (e.g., Hardcoded AWS Key)")
                severity = st.selectbox("Severity", ["Critical", "High", "Medium", "Low"])
            with col2:
                pattern = st.text_input("Detection Pattern (Regex/String)")
            
            desc = st.text_area("Detailed Description", help="Explain why this is a risk.")
            rem = st.text_area("Remediation Suggestion", help="Provide a secure code example.")
            
            if st.button("Save to Database"):
                if title and pattern:
                    db.add(CustomVulnerability(title=title, pattern=pattern, severity=severity, description=desc, remediation=rem))
                    db.commit()
                    st.success("Pattern Saved Successfully!")
                    st.rerun()
                else:
                    st.error("Title and Pattern are required!")

    st.subheader("Database Overview")
    vulns = db.query(CustomVulnerability).all()
    
    if vulns:
        
        df_v = pd.DataFrame([
            (v.vuln_id, v.title, v.severity, v.pattern) for v in vulns
        ], columns=['ID', 'Title', 'Severity', 'Pattern'])
        
        st.dataframe(df_v, use_container_width=True, hide_index=True)

        st.markdown("---")
        st.subheader(" Vulnerability Inspector")
        
        vuln_titles = {f"{v.vuln_id}: {v.title}": v for v in vulns}
        selected_key = st.selectbox("Select a vulnerability to view full details:", vuln_titles.keys())
        
        if selected_key:
            selected_vuln = vuln_titles[selected_key]
            
            v_col1, v_col2 = st.columns([1, 1])
            with v_col1:
                st.markdown(f"**Title:** {selected_vuln.title}")
                st.markdown(f"**Severity:** `{selected_vuln.severity}`")
            with v_col2:
                st.markdown(f"**Detection Pattern:** `{selected_vuln.pattern}`")

            st.info(f"**Description:**\n\n{selected_vuln.description}")
            
            st.success("**Remediation / Secure Code Suggestion:**")
            st.code(selected_vuln.remediation, language="python") 

            if st.session_state['role'] in ['super_admin', 'admin']:
                if st.button(f" Delete ID: {selected_vuln.vuln_id}", type="secondary"):
                    db.delete(selected_vuln)
                    db.commit()
                    st.rerun()
    else:
        st.info("No vulnerabilities defined in the database yet.")
    
    db.close()

if __name__ == "__main__":
    if not st.session_state['authenticated']:
        login_page()
    else:
        main()