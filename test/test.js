const express = require('express');
const mysql = require('mysql');
const app = express();

const db = mysql.createConnection({
  host: "localhost",
  user: "root",
  password: "password",
  database: "app_db"
});

app.use(express.json());

app.get('/login', (req, res) => {
  const username = req.query.username;
  const password = req.query.password;

  //  Vulnerable query (SQL Injection)
  const query = "SELECT * FROM users WHERE username = '" + username +
                "' AND password = '" + password + "'";

  console.log("Executing query:", query);

  db.query(query, (err, results) => {
    if (err) {
      return res.status(500).send("Database error");
    }

    if (results.length > 0) {
      res.send("Login successful");
    } else {
      res.send("Invalid credentials");
    }
  });
});

app.listen(3000, () => console.log("Server running on port 3000"));