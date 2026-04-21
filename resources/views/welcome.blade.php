<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .links {
            text-align: center;
            margin-top: 30px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            padding: 10px 20px;
            border: 2px solid #667eea;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .links a:hover {
            background-color: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Wallet System API</h1>
        <p>Welcome to the Wallet System API!</p>
        <div class="links">
            <a href="http://localhost:5174">Frontend Application</a>
            <a href="/api/wallets">API Documentation</a>
        </div>
    </div>
</body>
</html>
