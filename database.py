import bcrypt  
from sqlalchemy import create_engine, Column, Integer, String, Text
from sqlalchemy.orm import sessionmaker, declarative_base

DATABASE_URL = "postgresql://csss_admin:password123@127.0.0.1:5432/csss_db"
engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(bind=engine)
Base = declarative_base()

# --- MODELS ---
class User(Base):
    __tablename__ = 'users'
    user_id = Column(Integer, primary_key=True)
    username = Column(String(50), unique=True)
    password_hash = Column(String(255))
    role = Column(String(20)) 

class CustomVulnerability(Base):
    __tablename__ = 'custom_vulnerabilities'
    vuln_id = Column(Integer, primary_key=True)
    title = Column(String(100))
    pattern = Column(String(255)) 
    severity = Column(String(20))
    description = Column(Text)
    remediation = Column(Text)


def hash_password(password: str):
    salt = bcrypt.gensalt()
    return bcrypt.hashpw(password.encode('utf-8'), salt).decode('utf-8')

def init_db():
    Base.metadata.create_all(engine)
    session = SessionLocal()
    if not session.query(User).filter_by(username="superadmin").first():
        hashed_pw = hash_password("admin123")
        new_user = User(username="superadmin", password_hash=hashed_pw, role="super_admin")
        session.add(new_user)
        session.commit()
    session.close()

if __name__ == "__main__":
    init_db()
    print("Database Initialized Successfully!")