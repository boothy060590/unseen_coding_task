<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Address</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #ffffff; padding: 30px; border: 1px solid #dee2e6; }
        .button { display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 0.9em; color: #6c757d; }
        .url-fallback { background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
    </div>
    
    <div class="content">
        <h2>Verify Your Email Address</h2>
        
        <p>Hello {{ $user->first_name }},</p>
        
        <p>Thank you for registering with our Customer Management System. Please verify your email address by clicking the button below.</p>
        
        <p style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
        </p>
        
        <p>If you did not create an account, no further action is required.</p>
        
        <p>This verification link will expire in {{ config('auth.verification.expire', 60) }} minutes.</p>
        
        <div class="url-fallback">
            <p><strong>If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:</strong></p>
            <p>{{ $verificationUrl }}</p>
        </div>
    </div>
    
    <div class="footer">
        <p>Thanks,<br>{{ config('app.name') }}</p>
    </div>
</body>
</html>