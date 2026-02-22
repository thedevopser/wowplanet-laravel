<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance en cours - WowPlanet</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#0f172a">
    <meta name="theme-color" content="#0f172a">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(to bottom right, #0f172a, #1e293b, #0f172a);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            max-width: 28rem;
            width: 100%;
        }

        .logo {
            width: 5rem;
            height: 5rem;
            border-radius: 1rem;
            filter: drop-shadow(0 0 16px rgba(59, 130, 246, 0.3));
        }

        .card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 1.25rem;
            padding: 2.5rem 2rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 0 30px -10px rgba(59, 130, 246, 0.1);
        }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(168, 85, 247, 0.15));
            margin-bottom: 1.5rem;
            animation: pulse 3s ease-in-out infinite;
        }

        .icon-wrapper svg {
            width: 2rem;
            height: 2rem;
            color: #60a5fa;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            background: linear-gradient(to right, #93c5fd, #c4b5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .message {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(51, 65, 85, 0.6);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 40%;
            background: linear-gradient(to right, #3b82f6, #a855f7);
            border-radius: 9999px;
            animation: progress 2s ease-in-out infinite;
        }

        .footer {
            text-align: center;
            color: #475569;
            font-size: 0.75rem;
            line-height: 1.6;
            margin-top: 1rem;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.85; }
        }

        @keyframes progress {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(200%); }
            100% { transform: translateX(200%); }
        }

        @media (min-width: 640px) {
            .logo { width: 6rem; height: 6rem; }
            .container { max-width: 32rem; }
            .card { padding: 3rem 2.5rem; }
            h1 { font-size: 1.75rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/images/logo.png" alt="WowPlanet" class="logo">

        <div class="card">
            <div class="icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.049.58.025 1.193-.14 1.743" />
                </svg>
            </div>

            <h1>Maintenance en cours</h1>

            <p class="message">
                Nous effectuons une mise à jour pour améliorer votre expérience.
                Le site sera de retour très bientôt&nbsp;!
            </p>

            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} WowPlanet. Tous droits réservés.</p>
            <p>Site fan non officiel, sans lien ni affiliation avec Blizzard Entertainment.</p>
        </div>
    </div>
</body>
</html>
