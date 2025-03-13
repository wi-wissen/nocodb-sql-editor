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

// Login-Funktion
function loginUser($email, $password) {
    $db = connectDB();
    $stmt = $db->prepare("SELECT id, email, password, salt, roles FROM nc_users_v2 WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Benutzer nicht gefunden'];
    }

    // Überprüfen, ob Passwort korrekt ist
    if ($password === $user['password'] || password_verify($password, $user['password'])) {
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
        SELECT id, table_name, title, type, meta
        FROM nc_models_v2
        WHERE project_id = ? AND deleted IS NULL AND type = 'table'
        ORDER BY title
    ");
    $stmt->execute([$projectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Spaltennamen für eine Tabelle abrufen
function getTableColumns($tableName) {
    $db = connectDB();
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$tableName`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        return [];
    }
}

// SQL-Abfrage sicherstellen (verhindert Zugriff auf nc_-Tabellen)
function secureSql($sql) {
    // Prüfen auf nc_-Tabellen 
    if (preg_match('/\b(nc_[a-zA-Z0-9_]+)\b/i', $sql, $matches)) {
        throw new Exception("Zugriff auf Systemtabellen (nc_*) ist nicht erlaubt");
    }
    return $sql;
}

// SQL-Abfrage ausführen (mit Tabellenname-Ersetzung)
function executeSQL($sql, $tables) {
    $db = connectDB();
    
    try {
        // Sicherheitscheck
        $sql = secureSql($sql);
        
        // Ersetze Tabellentitel durch echte Tabellennamen
        foreach ($tables as $table) {
            $sql = str_replace($table['title'], $table['table_name'], $sql);
        }
        
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
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'SQL-Fehler: ' . $e->getMessage()
        ];
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// Verarbeite Login-Formular
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $result = loginUser($_POST['email'], $_POST['password']);
        if ($result['success']) {
            header('Location: /');
            exit;
        } else {
            $loginError = $result['message'];
        }
    } else {
        $loginError = 'Bitte E-Mail und Passwort eingeben';
    }
}

// Zurück zur Projektauswahl
if (isset($_GET['projects'])) {
    unset($_SESSION['current_project']);
    unset($_SESSION['project_tables']);
    header('Location: /');
    exit;
}

// Projekt auswählen
if (isset($_GET['project'])) {
    $_SESSION['current_project'] = $_GET['project'];
    // Hole Tabellen für das gewählte Projekt
    if (isset($_SESSION['user_id'])) {
        $tables = getProjectTables($_SESSION['current_project']);
        
        // Füge Spalteninformationen hinzu
        foreach ($tables as &$table) {
            $columns = getTableColumns($table['table_name']);
            $table['columns'] = $columns;
        }
        
        $_SESSION['project_tables'] = $tables;
    }
    header('Location: /');
    exit;
}

// SQL ausführen
$sqlResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_sql') {
    if (!empty($_POST['sql']) && isset($_SESSION['project_tables'])) {
        $sqlResult = executeSQL($_POST['sql'], $_SESSION['project_tables']);
        // SQL-Query in Session speichern, damit sie bestehen bleibt
        $_SESSION['last_sql'] = $_POST['sql'];
    }
}

// Ergebnisse löschen aber SQL-Input behalten
if (isset($_GET['clear_results'])) {
    // SQL-Text aus Session speichern, falls vorhanden
    if (isset($_POST['sql'])) {
        $_SESSION['last_sql'] = $_POST['sql'];
    }
    $sqlResult = null;
    header('Location: /');
    exit;
}

// HTML-Header einbinden
include('header.php');
?>

<main class="container">
    <header class="app-header">
        <h1>
            <a href="/" class="logo-link">
                <img src="apple-touch-icon.png" alt="NocoDB SQL Editor" class="logo-image">
                <span>NocoDB SQL Editor</span>
            </a>
        </h1>
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-actions">
            <span class="user-email"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
            <a href="?logout=1" role="button" class="secondary btn-sm">Abmelden</a>
        </div>
        <?php endif; ?>
    </header>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Login-Formular -->
        <article class="login-form">
            <h2>Anmelden</h2>
            <?php if ($loginError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
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
        <?php if (!isset($_SESSION['current_project'])): ?>
            <!-- Projekt auswählen -->
            <h2>Projekt auswählen</h2>
            <div class="projects-grid">
                <?php 
                $projects = getUserProjects($_SESSION['user_id']);
                if ($projects): 
                    foreach ($projects as $project): 
                ?>
                    <a href="?project=<?= htmlspecialchars($project['id']) ?>" class="project-card">
                        <article>
                            <header><?= htmlspecialchars($project['title']) ?></header>
                            <p><?= htmlspecialchars($project['description'] ?? 'Keine Beschreibung') ?></p>
                        </article>
                    </a>
                <?php 
                    endforeach;
                else: 
                ?>
                    <p>Keine Projekte gefunden.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- SQL-Editor Bereich -->
            <div class="single-column">
                <article class="sql-editor">                    
                    <form method="post" action="">
                        <input type="hidden" name="action" value="execute_sql">
                        <textarea name="sql" rows="6" placeholder="SELECT * FROM TabellenName LIMIT 10;"><?= $_POST['sql'] ?? $_SESSION['last_sql'] ?? '' ?></textarea>
                        <button type="submit">Ausführen</button>
                    </form>
                </article>
                
                <?php if ($sqlResult): ?>
                    <article class="results-container">
                        <div class="results-header">
                            <h2>Ergebnisse</h2>
                            <a href="?clear_results=1" class="btn-close" title="Ergebnisse löschen">×</a>
                        </div>
                        
                        <?php if ($sqlResult['success']): ?>
                            <?php if (isset($sqlResult['data'])): ?>
                                <div class="alert alert-success"><?= $sqlResult['rowCount'] ?> Ergebnis(se)</div>
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
                            <?php else: ?>
                                <div class="alert alert-success"><?= htmlspecialchars($sqlResult['message']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($sqlResult['message']) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>
                
                <article class="tables-list">
                    <div class="table-header">
                        <h2>Tabellen</h2>
                        <a href="?projects=1" role="button" class="secondary btn-sm">Projektauswahl</a>
                    </div>
                    <div class="tables-simple">
                        <?php foreach ($_SESSION['project_tables'] as $table): ?>
                            <div class="table-item">
                                <span class="table-title"><?= htmlspecialchars($table['title']) ?></span>
                                <span class="table-columns">(
                                    <?php if (!empty($table['columns'])): ?>
                                        <?= htmlspecialchars(implode(', ', $table['columns'])) ?>
                                    <?php else: ?>
                                        <em>Keine Spalten gefunden</em>
                                    <?php endif; ?>
                                )</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include('footer.php'); ?>