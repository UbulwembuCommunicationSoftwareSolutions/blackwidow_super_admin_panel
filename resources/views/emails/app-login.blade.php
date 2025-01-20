<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f7;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f4f4f7;
            padding: 20px 0;
        }
        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: #4CAF50;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 24px;
        }
        .body {
            padding: 20px;
            color: #333333;
            font-size: 16px;
            line-height: 1.5;
        }
        .body p {
            margin: 16px 0;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #999999;
            padding: 10px 20px;
            border-top: 1px solid #dddddd;
        }
        .btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-content">
        <!-- Header -->
        <div class="header">
            Welcome to {{ $app_name }}
        </div>

        <!-- Body -->
        <div class="body">
            <p>Hi {{ $user->name }},</p>

            <p>You have been added to <strong>{{ $customer->company_name }}</strong>'s <strong>{{ $app_name }}</strong> as a user.</p>

            <p>
                Please open the following link on <strong>Chrome</strong> or <strong>Safari (iOS)</strong> to install and log into the mobile app.
            </p>

            <p>Your login details are:</p>
            <ul>
                <li><strong>Email:</strong> {{ $cellphone }}</li>
                <li><strong>Cellphone:</strong> {{ $cellphone }}</li>
                <li><strong>Password:</strong> (Use the password for your CMS account)</li>
            </ul>

            <p>
                <a href="{{ $app_install_link }}" class="btn">Install App</a>
            </p>

            <p>If you have any questions, feel free to reply to this email.</p>

            <p>Best regards,<br>The {{ $app_name }} Team</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            © {{ date('Y') }} {{ $app_name }}. All rights reserved.
        </div>
    </div>
</div>
</body>
</html>
