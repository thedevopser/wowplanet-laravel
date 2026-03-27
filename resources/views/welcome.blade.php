<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $seo['title'] }}</title>

    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="max-image-preview:large">
    <meta name="content-language" content="fr">
    <link rel="canonical" href="{{ $seo['canonicalUrl'] }}">

    <meta property="og:type" content="{{ $seo['ogType'] }}">
    <meta property="og:title" content="{{ $seo['ogTitle'] }}">
    <meta property="og:description" content="{{ $seo['ogDescription'] }}">
    <meta property="og:image" content="{{ $seo['ogImage'] }}">
    <meta property="og:url" content="{{ $seo['ogUrl'] }}">
    <meta property="og:site_name" content="WowPlanet">
    <meta property="og:locale" content="fr_FR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
    <meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">

    @if(!empty($seo['jsonLd']))
    <script type="application/ld+json">
        {
            !!$seo['jsonLd'] !!
        }
    </script>
    @endif

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#0f172a">
    <meta name="theme-color" content="#0f172a">

    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" as="style">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @production
    <script defer src="https://umami.wowplanet.fr/script.js" data-website-id="be8977fd-a0fe-4a4c-867d-d75f83101232"></script>
    @endproduction

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        #seo-content {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>

<body class="antialiased">
    <div id="app">
        @if(!empty($seo['serverHtml']))
        <div id="seo-content">{!! $seo['serverHtml'] !!}</div>
        @endif
    </div>
    <noscript>
        <div style="max-width:900px;margin:2rem auto;padding:1rem;color:#94a3b8;font-family:sans-serif">
            <h1>WowPlanet</h1>
            <p>{{ $seo['description'] }}</p>
            <p>Ce site nécessite JavaScript pour fonctionner pleinement.</p>
        </div>
    </noscript>
</body>

</html>