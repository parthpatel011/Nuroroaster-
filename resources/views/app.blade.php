<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NurseRoster</title>
    @vite(['resources/css/app.css', 'frontend/src/main.tsx'])
</head>
<body class="bg-paper text-ink">
    <div id="root"></div>
</body>
</html>
