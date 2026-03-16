<?php
// video.php — Page publique de visualisation vidéo d'intervention
$file = basename($_GET['f'] ?? '');
if (!$file || !preg_match('/^\d+_\d+\.(mp4|mov|avi|webm)$/i', $file)) {
    http_response_code(404);
    die('Vidéo introuvable.');
}

$path = __DIR__ . '/uploads/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    die('Vidéo introuvable.');
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = [
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'webm' => 'video/webm',
][$ext] ?? 'video/mp4';

$videoUrl = 'uploads/' . rawurlencode($file);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Votre logement est prêt !</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      max-width: 500px;
      width: 100%;
      overflow: hidden;
    }
    .header {
      background: #2d3436;
      color: #fff;
      padding: 24px;
      text-align: center;
    }
    .header h1 {
      font-size: 1.4rem;
      font-weight: 600;
      margin-bottom: 6px;
    }
    .header p {
      font-size: 0.9rem;
      opacity: 0.8;
    }
    .video-container {
      background: #000;
      position: relative;
    }
    video {
      width: 100%;
      display: block;
      max-height: 70vh;
    }
    .footer {
      padding: 20px 24px;
      text-align: center;
      color: #636e72;
      font-size: 0.85rem;
      line-height: 1.5;
    }
    .footer a {
      color: #6c5ce7;
      text-decoration: none;
      font-weight: 600;
    }
    .logo {
      font-size: 1.1rem;
      font-weight: 700;
      color: #6c5ce7;
      margin-bottom: 8px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Votre logement est prêt ! 😊</h1>
      <p>Voici la vidéo de votre logement</p>
    </div>
    <div class="video-container">
      <video controls playsinline preload="metadata">
        <source src="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" type="<?= $mime ?>">
        Votre navigateur ne supporte pas la lecture vidéo.
        <a href="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger la vidéo</a>
      </video>
    </div>
    <div class="footer">
      <div class="logo">Frenchy Conciergerie</div>
      <p>Un souci ? Contactez-nous :<br>
        <a href="tel:+33647554678">06 47 55 46 78</a> — Raphaël
      </p>
    </div>
  </div>
</body>
</html>
