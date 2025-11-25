<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .landing-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        p {
            color: #7f8c8d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background: #3498db;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .welcome-message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <h1>🎓 Attendance Management System</h1>
        <p>Welcome to our comprehensive attendance tracking system for students and faculty.</p>
        
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <div class="welcome-message">
                <strong>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</strong><br>
                You are logged in as: <?php echo htmlspecialchars($_SESSION['role']); ?>
            </div>
            <div class="btn-group">
                <a href="php/dashboard<?php echo ucfirst($_SESSION['role']); ?>.php" class="btn btn-primary">
                    Go to Dashboard
                </a>
                <a href="php/logout.php" class="btn btn-secondary">Log Out</a>
            </div>
        <?php else: ?>
            <div class="btn-group">
                <a href="html/login.html" class="btn btn-primary">Login</a>
                <a href="html/register.html" class="btn btn-secondary">Register</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>