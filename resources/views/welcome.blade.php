<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESL University API</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            color: white;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin: 0.5rem;
            font-size: 0.9rem;
        }
        a {
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem 2rem;
            border-radius: 8px;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s;
        }
        a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 ESL University API</h1>
        <p>École Supérieure de Libreville - Management System</p>
        <div>
            <span class="badge">Laravel {{ app()->version() }}</span>
            <span class="badge">PHP {{ phpversion() }}</span>
        </div>
        <a href="/api/documentation" target="_blank">API Documentation</a>
    </div>
</body>
</html>
