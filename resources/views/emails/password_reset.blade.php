<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
            padding: 40px;
        }
        .container {
            background: white;
            max-width: 500px;
            margin: auto;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            background-color: #ff6600;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 20px;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Réinitialisation de votre mot de passe</h2>
    <p>Bonjour,</p>
    <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour le faire :</p>
    <p style="text-align:center;">
        <a href="{{ $resetUrl }}" class="btn">Réinitialiser mon mot de passe</a>
    </p>
    <p>Ce lien expirera dans <strong>60 minutes</strong>.</p>
    <p>Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.</p>
    <div class="footer">
        &copy; {{ date('Y') }} TonApplication. Tous droits réservés.
    </div>
</div>
</body>
</html>
