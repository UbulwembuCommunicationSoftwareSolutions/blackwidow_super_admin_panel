<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set your password</title>
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
        .fallback-link {
            font-size: 12px;
            color: #666666;
            word-break: break-all;
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-content">
        <!-- Header -->
        <div class="header">
            Set your {{ $app_name }} password
        </div>

        <!-- Body -->
        <div class="body">
            <p>Hi{{ $name !== '' ? ' '.$name : '' }},</p>

            @if ($company_name)
                <p>You have been given access to <strong>{{ $company_name }}</strong>'s <strong>{{ $app_name }}</strong>.</p>
            @else
                <p>You have been given access to <strong>{{ $app_name }}</strong>.</p>
            @endif

            <p>Use the button below to choose a password and log in. You sign in with <strong>{{ $email }}</strong>.</p>

            <p>
                <a href="{{ $reset_url }}" class="btn">Set Password</a>
            </p>

            <p>This link expires in {{ $expires_in_minutes }} minutes. If it has expired, use the "Forgot Password?" link on the login page to request a new one.</p>

            <p class="fallback-link">If the button does not work, copy and paste this link into your browser:<br>{{ $reset_url }}</p>

            <p>If you were not expecting this email you can safely ignore it.</p>

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
