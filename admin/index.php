<?php
// index.php
session_start();

// Konfiguration
$config = [
    'db_host' => getenv('MYSQL_HOST') ?: 'root_db',
    'db_user' => getenv('MYSQL_USER') ?: 'root',
    'db_pass' => getenv('MYSQL_PASSWORD') ?: 'password',
    'db_name' => getenv('MYSQL_DATABASE') ?: 'nocodb',
    'admin_password' => getenv('ADMIN_PASSWORD') ?: 'admin123'
];

// Datenbankverbindung herstellen
function connectDB() {
    global $config;
    try {
        $conn = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $conn;
    } catch (PDOException $e) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    }
}

// Passwort verifizieren
function verifyPassword($inputPassword, $hashedPassword, $salt) {
    // Verwende bcrypt zur Verifizierung
    return password_verify($inputPassword . $salt, $hashedPassword);
}

// Login-Funktion
function loginUser($email, $password) {
    $db = connectDB();
    $stmt = $db->prepare("SELECT id, email, password, salt, roles FROM nc_users_v2 WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
    }

    // Passwort überprüfen (vereinfachte Version, da NocoDB bcrypt verwendet)
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_roles'] = $user['roles'];
        return ['success' => true];
    }

    return ['success' => false, 'message' => 'Falsches Passwort'];
}

// Projekte des Benutzers abrufen
function getUserProjects($userId) {
    $db = connectDB();
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.description
        FROM nc_projects_v2 p
        JOIN nc_project_users_v2 pu ON p.id = pu.project_id
        WHERE pu.fk_user_id = ? AND p.deleted != 1
        ORDER BY p.title
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Tabellen eines Projekts abrufen
function getProjectTables($projectId) {
    $db = connectDB();
    $stmt = $db->prepare("
        SELECT id, table_name, title, type
        FROM nc_models_v2
        WHERE project_id = ? AND deleted IS NULL AND type = 'table'
        ORDER BY title
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// SQL-Abfrage ausführen (mit Tabellenname-Ersetzung)
function executeSQL($sql, $tables) {
    $db = connectDB();
    
    // Ersetze Tabellentitel durch echte Tabellennamen
    foreach ($tables as $table) {
        $sql = str_replace($table['title'], $table['table_name'], $sql);
    }
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        // Prüfen, ob es sich um eine SELECT-Abfrage handelt
        if (stripos(trim($sql), 'SELECT') === 0) {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return [
                'success' => true,
                'data' => $results,
                'rowCount' => count($results),
                'columns' => $results ? array_keys($results[0]) : []
            ];
        } else {
            return [
                'success' => true,
                'rowCount' => $stmt->rowCount(),
                'message' => $stmt->rowCount() . ' Zeilen betroffen'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'SQL-Fehler: ' . $e->getMessage()
        ];
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Verarbeite Login-Formular
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $result = loginUser($_POST['email'], $_POST['password']);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $loginError = $result['message'];
        }
    } else {
        $loginError = 'Bitte E-Mail und Passwort eingeben';
    }
}

// Projekt auswählen
if (isset($_GET['project'])) {
    $_SESSION['current_project'] = $_GET['project'];
    // Hole Tabellen für das gewählte Projekt
    if (isset($_SESSION['user_id'])) {
        $tables = getProjectTables($_SESSION['current_project']);
        $_SESSION['project_tables'] = $tables;
    }
}

// SQL ausführen
$sqlResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_sql') {
    if (!empty($_POST['sql']) && isset($_SESSION['project_tables'])) {
        $sqlResult = executeSQL($_POST['sql'], $_SESSION['project_tables']);
    }
}

// HTML-Header und Pico.css einbinden
include('header.php');
?>

<main class="container">
    <h1>NocoDB Admin Panel</h1>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Login-Formular -->
        <article>
            <h2>Anmelden</h2>
            <?php if ($loginError): ?>
                <div class="error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="action" value="login">
                <div class="grid">
                    <label for="email">
                        E-Mail
                        <input type="email" id="email" name="email" placeholder="admin@example.com" required>
                    </label>
                    
                    <label for="password">
                        Passwort
                        <input type="password" id="password" name="password" required>
                    </label>
                </div>
                <button type="submit">Anmelden</button>
            </form>
        </article>
    <?php else: ?>
        <!-- Angemeldet als -->
        <div class="user-info">
            <p>Angemeldet als: <?= htmlspecialchars($_SESSION['user_email']) ?> 
               <a href="?logout=1" role="button" class="secondary outline">Abmelden</a>
            </p>
        </div>
        
        <?php if (!isset($_SESSION['current_project'])): ?>
            <!-- Projekt auswählen -->
            <article>
                <h2>Projekt auswählen</h2>
                <?php 
                $projects = getUserProjects($_SESSION['user_id']);
                if ($projects): 
                ?>
                    <div class="grid">
                        <?php foreach ($projects as $project): ?>
                            <a href="?project=<?= htmlspecialchars($project['id']) ?>" class="project-card">
                                <article>
                                    <header><?= htmlspecialchars($project['title']) ?></header>
                                    <p><?= htmlspecialchars($project['description'] ?? 'Keine Beschreibung') ?></p>
                                </article>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Keine Projekte gefunden.</p>
                <?php endif; ?>
            </article>
        <?php else: ?>
            <!-- SQL-Ausführung -->
            <div class="grid">
                <div>
                    <article>
                        <h2>Tabellen</h2>
                        <nav>
                            <ul>
                                <?php foreach ($_SESSION['project_tables'] as $table): ?>
                                    <li>
                                        <span title="<?= htmlspecialchars($table['table_name']) ?>">
                                            <?= htmlspecialchars($table['title']) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                        <a href="index.php" role="button" class="secondary outline">Zurück zur Projektauswahl</a>
                    </article>
                </div>
                
                <div>
                    <article>
                        <h2>SQL-Abfrage</h2>
                        <p class="info">Verwende die Tabellentitel in deinen SQL-Abfragen. Sie werden automatisch in die tatsächlichen Tabellennamen umgewandelt.</p>
                        
                        <form method="post" action="">
                            <input type="hidden" name="action" value="execute_sql">
                            <textarea name="sql" rows="6" placeholder="SELECT * FROM TabellenName LIMIT 10;"><?= $_POST['sql'] ?? '' ?></textarea>
                            <button type="submit">Ausführen</button>
                        </form>
                    </article>
                    
                    <?php if ($sqlResult): ?>
                        <article>
                            <h3>Ergebnis</h3>
                            <?php if ($sqlResult['success']): ?>
                                <?php if (isset($sqlResult['data'])): ?>
                                    <div class="table-container">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <?php foreach ($sqlResult['columns'] as $column): ?>
                                                        <th><?= htmlspecialchars($column) ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($sqlResult['data']) > 0): ?>
                                                    <?php foreach ($sqlResult['data'] as $row): ?>
                                                        <tr>
                                                            <?php foreach ($row as $value): ?>
                                                                <td><?= htmlspecialchars($value !== null ? $value : 'NULL') ?></td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="<?= count($sqlResult['columns']) ?>">Keine Daten gefunden</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p><?= $sqlResult['rowCount'] ?> Ergebnis(se)</p>
                                <?php else: ?>
                                    <div class="success"><?= htmlspecialchars($sqlResult['message']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="error"><?= htmlspecialchars($sqlResult['message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include('footer.php'); ?>