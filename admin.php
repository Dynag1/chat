<?php
session_set_cookie_params([
    'lifetime' => 60*60*24*30, // 30 jours
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require 'config.php';

// Vérification de la connexion et du statut admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT admin, pseudo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['admin'] != 1) {
    http_response_code(403);
    echo "<h1>Accès refusé</h1><p>Vous n'avez pas les droits pour accéder à cette page.</p>";
    exit();
}

// Gestion de la promotion/dégradation admin
if (
    isset($_POST['toggle_admin']) && 
    isset($_POST['user_id']) && 
    ctype_digit($_POST['user_id']) && 
    $_POST['user_id'] != $_SESSION['user_id']
) {
    $target_id = intval($_POST['user_id']);
    $stmt = $pdo->prepare("SELECT admin FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $current = $stmt->fetchColumn();
    if ($current !== false) {
        $new_status = $current ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET admin = ? WHERE id = ?");
        $stmt->execute([$new_status, $target_id]);
        $message = $new_status ? "Utilisateur promu admin." : "Statut admin retiré.";
    }
}

// Gestion du blocage/déblocage
if (
    isset($_POST['toggle_block']) && 
    isset($_POST['user_id']) && 
    ctype_digit($_POST['user_id']) && 
    $_POST['user_id'] != $_SESSION['user_id']
) {
    $target_id = intval($_POST['user_id']);
    $stmt = $pdo->prepare("SELECT blocked FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $current = $stmt->fetchColumn();
    if ($current !== false) {
        $new_status = $current ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET blocked = ? WHERE id = ?");
        $stmt->execute([$new_status, $target_id]);
        $message = $new_status ? "Utilisateur bloqué." : "Utilisateur débloqué.";
    }
}

// --- GESTION DU BROADCAST ---
if (
    isset($_POST['broadcast_message']) &&
    trim($_POST['broadcast_message']) !== ''
) {
    $broadcast_message = trim($_POST['broadcast_message']);
    $stmt = $pdo->prepare("INSERT INTO broadcasts (message, created_at) VALUES (?, NOW())");
    $stmt->execute([$broadcast_message]);
    $message = "Message broadcast envoyé à tous les utilisateurs.";
}

// Récupération de la liste des utilisateurs
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT id, pseudo, email, ip_address, admin, blocked, created_at 
            FROM users 
            WHERE pseudo LIKE ? OR email LIKE ?
            ORDER BY created_at DESC");
        $stmt->execute(['%'.$search.'%', '%'.$search.'%']);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT id, pseudo, email, ip_address, admin, blocked, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $stmt = $pdo->query("SELECT id, pseudo, email, ip_address, admin, blocked, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#ffd700">
    <title>Administration - Chat Pirate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Pirata+One&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #1e202c 0%, #232946 100%);
            color: #fff;
            min-height: 100vh;
            margin: 0;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://www.transparenttextures.com/patterns/pirate.png');
            opacity: 0.06;
            z-index: 0;
            pointer-events: none;
        }
        .admin-card {
            max-width: 980px;
            margin: 2.5rem auto;
            padding: 2rem 1.5rem;
            border-radius: 18px;
            background: rgba(34, 34, 51, 0.97);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            position: relative;
            z-index: 1;
        }
        .pirate-logo {
            width: 64px;
            display: block;
            margin: 0 auto 1rem auto;
            filter: drop-shadow(0 2px 8px #0009);
        }
        .pirate-title {
            font-family: 'Pirata One', cursive, sans-serif;
            color: #ffd700;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px #0004;
            font-size: 2rem;
            text-align: center;
        }
        h1, h3 {
            color: #ffd700;
            font-family: 'Pirata One', cursive, sans-serif;
        }
        .btn-admin {
            background: linear-gradient(90deg,#e94560 60%, #ffd700 100%);
            color: #232946;
            border: none;
            font-weight: bold;
            border-radius: 8px;
            font-size: 1rem;
            transition: background 0.2s, color 0.2s;
        }
        .btn-admin:hover, .btn-admin:focus {
            background: linear-gradient(90deg,#ffd700 60%, #e94560 100%);
            color: #232946;
        }
        .btn-block {
            background: #6c757d;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-block:hover {
            background: #495057;
        }
        .btn-warning {
            background: linear-gradient(90deg,#ffd700 60%, #e94560 100%);
            color: #232946;
            border: none;
            font-weight: bold;
            border-radius: 8px;
        }
        .btn-warning:hover {
            background: linear-gradient(90deg,#e94560 60%, #ffd700 100%);
            color: #232946;
        }
        .btn-primary, .btn-secondary {
            border-radius: 8px;
        }
        .table-dark th, .table-dark td {
            color: #fff;
            vertical-align: middle;
        }
        .table thead th {
            font-size: 1rem;
        }
        .alert-info {
            background: #ffd700cc;
            color: #232946;
            border: none;
            border-radius: 10px;
            font-weight: bold;
        }
        .input-group .form-control {
            border-radius: 8px 0 0 8px;
        }
        .input-group .btn {
            border-radius: 0 8px 8px 0;
        }
        @media (max-width: 900px) {
            .admin-card { max-width: 99vw; padding: 1rem 0.2rem; }
            .pirate-logo { width: 48px; }
            .pirate-title { font-size: 1.3rem; }
            .table { font-size: 0.95rem; }
        }
        @media (max-width: 600px) {
            .admin-card { margin: 1.2rem 0.2rem; padding: 1rem 0.2rem; }
            .pirate-logo { width: 36px; }
            .pirate-title { font-size: 1.1rem; }
            .table { font-size: 0.92rem; }
        }
    </style>
</head>
<body>
    <div class="admin-card">
        <img src="https://images.icon-icons.com/1061/PNG/512/pirate_icon-icons.com_76799.png" alt="Drapeau Pirate" class="pirate-logo" />
        <h1 class="pirate-title mb-3">👑 Administration</h1>
        <p class="text-center">Bienvenue, <strong><?= htmlspecialchars($user['pseudo']) ?></strong> (admin) !</p>
        <hr>
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="mb-4">
            <h3>Envoyer un message broadcast</h3>
            <form method="post" class="d-flex flex-column flex-md-row gap-2">
                <input type="text" name="broadcast_message" class="form-control" placeholder="Votre message à tous..." required>
                <button type="submit" class="btn btn-warning">Envoyer</button>
            </form>
        </div>

        <h3>Utilisateurs inscrits</h3>
        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Rechercher par pseudo ou email" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary" type="submit">🔍 Rechercher</button>
                <?php if ($search !== ''): ?>
                    <a href="admin.php" class="btn btn-secondary">Réinitialiser</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
        <table class="table table-dark table-striped mt-3 align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pseudo</th>
                    <th>Email</th>
                    <th>IP</th>
                    <th>Admin</th>
                    <th>Bloqué</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['id']) ?></td>
                        <td><?= htmlspecialchars($u['pseudo']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['ip_address']) ?></td>
                        <td><?= $u['admin'] ? "Oui" : "Non" ?></td>
                        <td><?= $u['blocked'] ? "<span class='text-danger'>Oui</span>" : "Non" ?></td>
                        <td><?= htmlspecialchars($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="toggle_admin" class="btn btn-sm btn-admin mb-1">
                                        <?= $u['admin'] ? "Retirer admin" : "Promouvoir admin" ?>
                                    </button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="toggle_block" class="btn btn-sm btn-block mb-1">
                                        <?= $u['blocked'] ? "Débloquer" : "Bloquer" ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-warning">Vous</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <a href="index.php" class="btn btn-secondary mt-3">Retour au site</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
