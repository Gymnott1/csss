from flask import Flask, request, jsonify
import os

app = Flask(__name__)

@app.route('/ping', methods=['GET'])
def ping_host():
    host = request.args.get('host')

    if not host:
        return jsonify({"error": "Host is required"}), 400

    #  Vulnerable command execution
    command = f"ping -c 2 {host}"
    print(f"Executing: {command}")

    response = os.popen(command).read()

    return jsonify({
        "host": host,
        "output": response
    })

@app.route('/download', methods=['GET'])
def download_file():
    filename = request.args.get('file')

    #  Directory traversal + command injection combo
    path = f"/var/www/files/{filename}"
    command = f"cat {path}"

    result = os.popen(command).read()

    return jsonify({"data": result})

if __name__ == '__main__':
    app.run(debug=True)