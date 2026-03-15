
```
sudo apt update
sudo apt install python3 python3-pip python3-venv

```

```
python3 --version

```

```
python3 -m pip --version

```

```
python3 -m venv venv

```

```
source venv/bin/activate

```

```
sudo apt install postgresql postgresql-contrib

```

```
sudo systemctl status postgresql
```



**System packages**
- Run:
```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip build-essential libpq-dev curl
```

**Project Python environment**
- Run:
```bash
cd /home/gymnott/Documents/2026/4p2/csss
python3 -m venv venv
source venv/bin/activate
python -m pip install --upgrade pip setuptools wheel
```

**Install required Python libs (minimal working set)**
- Run:
```bash
pip install streamlit fastapi "uvicorn[standard]" sqlalchemy psycopg2-binary bcrypt pandas python-dotenv semgrep
```

**Quick verification**
- Run:
```bash
python -c "import streamlit, fastapi, uvicorn, sqlalchemy, psycopg2, bcrypt, pandas; print('python deps OK')"
semgrep --version
```

**Next run order**
- Initialize DB tables/user:
```bash
python database.py
```
- Start backend API:
```bash
python api.py
```
- In another terminal (same venv), start dashboard:
```bash
streamlit run app.py
```

Run this privilege fix exactly:

1. Open psql as postgres  
    sudo -i -u postgres  
    psql  
    \c csss_db
    
2. Fix ownership and grants  
    ALTER DATABASE csss_db OWNER TO csss_admin;  
    ALTER SCHEMA public OWNER TO csss_admin;  
    GRANT USAGE, CREATE ON SCHEMA public TO csss_admin;
    

```
ALTER TABLE IF EXISTS users OWNER TO csss_admin;  
ALTER TABLE IF EXISTS custom_vulnerabilities OWNER TO csss_admin;

GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO csss_admin;  
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO csss_admin;

ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO csss_admin;  
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO csss_admin;
```

```
sed -i "s|/home/gymnott/2025_2026/sem2/csss_backend/logo/icon128.png|logo/icon128.png|g"
```

```
CREATE EXTENSION IF NOT EXISTS pgcrypto;

UPDATE users
SET password_hash = crypt('admin123', gen_salt('bf')),
    role = 'super_admin'
WHERE username = 'superadmin';

SELECT username, role FROM users WHERE username = 'superadmin';
```