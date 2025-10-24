<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de Vérification - ScanFy</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .email-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }
        
        .logo-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .content {
            padding: 40px 30px;
            text-align: center;
        }
        
        .title {
            color: #2d3748;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .description {
            color: #718096;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .otp-container {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 10px 25px rgba(245, 87, 108, 0.3);
        }
        
        .otp-code {
            font-size: 3rem;
            font-weight: 700;
            color: white;
            letter-spacing: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .expiry-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .expiry-text {
            color: #856404;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .security-note {
            background: #e8f5e8;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .security-text {
            color: #2e7d32;
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .footer {
            background: #f7fafc;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer-text {
            color: #718096;
            font-size: 0.8rem;
            line-height: 1.5;
        }
        
        .brand {
            color: #667eea;
            font-weight: 600;
        }
        
        .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        @media (max-width: 480px) {
            .email-container {
                margin: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .otp-code {
                font-size: 2.5rem;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">ScanFy</div>
            <div class="logo-subtitle">Votre solution de codes QR intelligents</div>
        </div>
        
        <div class="content">
            <div class="icon">🔒</div>
            <h1 class="title">Vérification de Sécurité</h1>
            <p class="description">
                Utilisez le code ci-dessous pour vérifier votre identité et accéder à votre compte ScanFy.
            </p>
            
            <div class="otp-container">
                <div class="otp-code">{{ $otp }}</div>
            </div>
            
            <div class="expiry-notice">
                <p class="expiry-text">⏰ Ce code expire dans 10 minutes</p>
            </div>
            
            <div class="security-note">
                <p class="security-text">
                    🔒 Pour votre sécurité, ne partagez jamais ce code avec qui que ce soit. 
                    L'équipe ScanFy ne vous demandera jamais votre code de vérification.
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p class="footer-text">
                Si vous n'avez pas demandé ce code, veuillez ignorer cet email.<br>
                © 2025 <span class="brand">ScanFy</span>. Tous droits réservés.
            </p>
        </div>
    </div>
</body>
</html>