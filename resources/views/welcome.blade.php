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
    <link rel="alternate" hreflang="fr" href="{{ $seo['canonicalUrl'] }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seo['canonicalUrl'] }}">

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
    <script type="application/ld+json">{!! $seo['jsonLd'] !!}</script>
    @endif

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#0f172a">
    <meta name="theme-color" content="#0f172a">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>

    @production
    <script defer src="https://umami.wowplanet.fr/script.js" data-website-id="70643c68-1a19-40ef-9a24-dc944c9a6110"></script>
    @endproduction
</head>

<body class="antialiased">
    <div id="app"></div>
</body>

</html>
