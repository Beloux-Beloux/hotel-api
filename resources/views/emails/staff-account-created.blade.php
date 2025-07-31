<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre compte a été créé</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .credentials {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bienvenue chez {{ $hotelName }} !</h1>
    </div>
    
    <div class="content">
        <p>Bonjour {{ $userName }},</p>
        
        <p>Un compte a été créé pour vous sur la plateforme de gestion hôtelière de <strong>{{ $hotelName }}</strong>.</p>
        
        <p>Pour activer votre compte et confirmer votre adresse email, veuillez cliquer sur le bouton ci-dessous :</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button" style="display: inline-block; padding: 12px 30px; background-color: #2563eb; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold;">Vérifier mon email</a>
        </div>
        
        <div class="credentials">
            <h3>Vos informations de connexion :</h3>
            <p><strong>Email :</strong> {{ $email }}</p>
            <p><strong>Mot de passe temporaire :</strong> {{ $temporaryPassword }}</p>
        </div>
        
        <div class="warning">
            <p><strong>⚠️ Important :</strong></p>
            <ul>
                <li>Ce lien de vérification expire dans 24 heures</li>
                <li>Nous vous recommandons de changer votre mot de passe après votre première connexion</li>
                <li>Conservez ces informations en lieu sûr</li>
            </ul>
        </div>
        
        <p>Si vous n'avez pas demandé la création de ce compte, veuillez ignorer cet email.</p>
        
        <p>Cordialement,<br>
        L'équipe de {{ $hotelName }}</p>
    </div>
    
    <div class="footer">
        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        <p>© <?php echo date('Y'); ?> {{ $hotelName }}. Tous droits réservés.</p>
    </div>
</body>
</html>