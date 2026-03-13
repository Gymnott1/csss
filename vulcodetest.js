const db_password = "super_secret_password_12345678";
const api_key = "ak_live_51MzS2VAnT9zP9z00abc123xyz789";

const key = "123";


const userInput = "process.exit()";
eval(userInput);

const safeData = JSON.parse('{"status": "ok"}');


const unsafeId = "105 OR 1=1";
const query = "SELECT * FROM users WHERE id = " + unsafeId;

const safeQuery = "SELECT * FROM users WHERE id = ?";

const userBio = "<img src=x onerror=alert('Hacked!')>";
document.getElementById('profile-div').innerHTML = userBio;

document.getElementById('profile-div').textContent = userBio;


function login(user, pass) {
    const auth_token = "token_bc22998844551100aa33";

    if (user === 'admin') {
        console.log("Logged in as admin");
    }
}

console.log("Security Scan complete. Check for highlights above!");