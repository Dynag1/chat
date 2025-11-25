<?php
require_once __DIR__ . '/../src/session_config.php';
require_once __DIR__ . '/../src/Admin.php';
require_once __DIR__ . '/../src/Security.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$admin = new Admin();
$reports = $admin->getReports();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Atypi Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .report-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-success {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .status-blocked {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Administration</h1>
            <a href="chat.php" class="btn-secondary">Retour au chat</a>
        </header>

        <h2>Signalements</h2>
        
        <?php if (empty($reports)): ?>
            <p>Aucun signalement à traiter.</p>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="report-card" id="report-<?php echo $report['id']; ?>">
                    <div class="report-header">
                        <div>
                            <strong>Signalé par :</strong> <?php echo htmlspecialchars($report['reporter_username']); ?>
                        </div>
                        <div>
                            <strong>Date :</strong> <?php echo $report['created_at']; ?>
                        </div>
                    </div>
                    
                    <div class="report-content">
                        <p><strong>Utilisateur signalé :</strong> <?php echo htmlspecialchars($report['reported_username']); ?> 
                        <?php if ($report['reported_is_blocked']): ?>
                            <span class="status-blocked">(BLOQUÉ)</span>
                        <?php endif; ?>
                        </p>
                        <p><strong>Raison :</strong> <?php echo htmlspecialchars($report['reason']); ?></p>
                    </div>

                    <div class="report-actions">
                        <?php if (!$report['reported_is_blocked']): ?>
                            <button onclick="blockUser(<?php echo $report['reported_id']; ?>, <?php echo $report['id']; ?>)" class="btn-danger">Bloquer l'utilisateur</button>
                        <?php else: ?>
                            <button onclick="unblockUser(<?php echo $report['reported_id']; ?>, <?php echo $report['id']; ?>)" class="btn-success">Débloquer l'utilisateur</button>
                        <?php endif; ?>
                        
                        <button onclick="dismissReport(<?php echo $report['id']; ?>)" class="btn-secondary">Ignorer le signalement</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        async function blockUser(userId, reportId) {
            if (!confirm('Êtes-vous sûr de vouloir bloquer cet utilisateur ?')) return;
            
            try {
                const response = await fetch('api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'block', user_id: userId })
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Utilisateur bloqué.');
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            } catch (e) {
                alert('Erreur de connexion');
            }
        }

        async function unblockUser(userId, reportId) {
            if (!confirm('Êtes-vous sûr de vouloir débloquer cet utilisateur ?')) return;
            
            try {
                const response = await fetch('api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'unblock', user_id: userId })
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Utilisateur débloqué.');
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            } catch (e) {
                alert('Erreur de connexion');
            }
        }

        async function dismissReport(reportId) {
            if (!confirm('Voulez-vous supprimer ce signalement ?')) return;
            
            try {
                const response = await fetch('api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'dismiss', report_id: reportId })
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('report-' + reportId).remove();
                } else {
                    alert('Erreur : ' + data.message);
                }
            } catch (e) {
                alert('Erreur de connexion');
            }
        }
    </script>
</body>
</html>
