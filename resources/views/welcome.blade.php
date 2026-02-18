<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $seo['title'] }}</title>

    <meta name="description" content="{{ $seo['description'] }}">
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
    <script type="application/ld+json">{!! $seo['jsonLd'] !!}</script>
    @endif

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
</head>

<body class="antialiased">
    <div id="app"></div>
</body>

</html>
