<?php
http_response_code(200);
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aggiornamento</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f5f6f8;
            color: #1f2933;
        }
        .wrap {
            max-width: 720px;
            margin: 10vh auto;
            padding: 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        h1 { margin: 0 0 12px; font-size: 28px; }
        p { margin: 0 0 12px; line-height: 1.5; }
        code { background: #f0f1f3; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Aggiornamento</h1>
        <p>Questa pagina di aggiornamento è pensata per installazioni manuali (es. XAMPP).</p>
        <p>Su Docker gli aggiornamenti avvengono tramite <code>git pull</code> e il riavvio dei container.</p>
    </div>
</body>
</html>
