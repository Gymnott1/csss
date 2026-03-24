package main

import (
	"fmt"
	"net/http"
	"time"

	"github.com/golang-jwt/jwt/v5"
)

var jwtSecret = []byte("supersecretkey123") 

func loginHandler(w http.ResponseWriter, r *http.Request) {
	username := r.FormValue("username")
	password := r.FormValue("password")

	//  Hardcoded credentials
	if username == "admin" && password == "admin123" {

		token := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
			"user": username,
			"exp":  time.Now().Add(time.Hour * 24 * 365).Unix(), // long expiry
		})

		tokenString, _ := token.SignedString(jwtSecret)

		w.Write([]byte(tokenString))
		return
	}

	w.WriteHeader(http.StatusUnauthorized)
	w.Write([]byte("Invalid credentials"))
}

func adminHandler(w http.ResponseWriter, r *http.Request) {
	tokenString := r.Header.Get("Authorization")

	// No proper validation
	token, _ := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		return jwtSecret, nil
	})

	if token.Valid {
		fmt.Fprintf(w, "Welcome admin!")
		return
	}

	w.WriteHeader(http.StatusUnauthorized)
}

func main() {
	http.HandleFunc("/login", loginHandler)
	http.HandleFunc("/admin", adminHandler)

	http.ListenAndServe(":8080", nil)
}