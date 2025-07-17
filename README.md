# NocoDB SQL Editor

Ein einfacher SQL-Editor für NocoDB, mit dem Sie SQL-Abfragen direkt gegen die NocoDB-Datenbank ausführen können.

## Funktionen

- Anmeldung mit existierenden NocoDB-Benutzerkonten
- Projektauswahl aus allen für den Benutzer verfügbaren Projekten
- SQL-Abfragen gegen Projekttabellen ausführen
- Übersicht aller verfügbaren Tabellen mit Spalteninformationen
- Schutz vor Zugriff auf Systemtabellen (nc_*)

## Installation

### Voraussetzungen

- Docker und Docker Compose
- Git (optional)

### Schnellstart

1. Repository klonen oder die Dateien herunterladen:
   ```bash
   git clone https://github.com/wi-wissen/nocodb-sql-editor.git
   cd nocodb-sql-editor
   ```

2. Umgebungsvariablen konfigurieren (optional):
   ```bash
   cp .env.example .env
   # Bearbeiten Sie .env nach Bedarf
   ```

3. Docker-Container starten:
   ```bash
   docker-compose up -d
   ```

4. Auf die Anwendungen zugreifen:
    - **NocoDB**: http://localhost/
    - **SQL Editor**: http://localhost/sql/
    - **phpMyAdmin**: http://localhost/phpmyadmin/

   Falls Port 80 bereits belegt ist, können Sie in der `.env`-Datei einen anderen Port setzen:
   ```bash
   HTTP_PORT=8000
   ```
   Dann wäre der Zugriff über http://localhost:8000/ möglich.

### Architektur

Das Setup verwendet **Traefik** als Reverse Proxy, der alle Services über den konfigurierbaren HTTP-Port bereitstellt:

```
Internet → Port ${HTTP_PORT} → Traefik → {
  /           → NocoDB
  /sql/       → SQL Editor
  /phpmyadmin/ → phpMyAdmin
}
```

### Sicherheitseinstellungen

Die Benutzerregistrierung kann deaktiviert werden:

1. **In der Docker-Konfiguration** ([Bug](https://github.com/nocodb/nocodb/issues/7814)):
   ```bash
   # In .env setzen:
   NC_INVITE_ONLY_SIGNUP=1
   ```

2. **Über die NocoDB-Benutzeroberfläche**:
   - Melden Sie sich als Super-Admin an
   - Deaktivieren Sie die Option "Enable Signup" 
   - Weitere Informationen: [NocoDB Dokumentation](https://docs.nocodb.com/account-settings/oss-specific-details/#enable--disable-signup)

### Umgebungsvariablen

| Variable | Beschreibung | Standardwert |
| --- | --- | --- |
| `HTTP_PORT` | Hauptport für Web-Zugriff | `80` |
| `TRAEFIK_DASHBOARD_PORT` | Port für Traefik Dashboard | `8080` |
| `DB_HOST` | Datenbankhost | `root_db` |
| `DB_USER` | Datenbankbenutzer | `root` |
| `DB_PASSWORD` | Datenbankpasswort | `password` |
| `DB_NAME` | Datenbankname | `nocodb` |
| `NC_ADMIN_EMAIL` | Admin-E-Mail für NocoDB | `admin@example.com` |
| `NC_ADMIN_PASSWORD` | Admin-Passwort für NocoDB | `admin@example.com` |
| `NC_PUBLIC_URL` | Öffentliche URL für NocoDB | `http://localhost` |
| `DATA_DIR` | Verzeichnis für persistente Daten | `./data` |

## Verwendung

1. Öffnen Sie NocoDB unter http://localhost/ und erstellen Sie einen Account
2. Öffnen Sie den SQL Editor unter http://localhost/sql/
3. Melden Sie sich mit Ihren NocoDB-Anmeldedaten an
4. Wählen Sie ein Projekt aus
5. Schreiben Sie SQL-Abfragen unter Verwendung der Tabellennamen aus NocoDB
6. Führen Sie die Abfragen aus und sehen Sie die Ergebnisse

### Hinweise

- Verwenden Sie die tatsächlichen **Tabellentitel** aus NocoDB in Ihren SQL-Abfragen (nicht die internen Tabellennamen)
- Der Editor ersetzt automatisch die Tabellentitel durch die tatsächlichen Tabellennamen
- Aus Sicherheitsgründen ist der Zugriff auf Systemtabellen (nc_*) nicht erlaubt

## Updates durchführen

```bash
# Services stoppen
docker-compose down

# Neue Images ziehen
docker-compose pull

# Custom Images neu bauen
docker-compose build --no-cache

# Services starten
docker-compose up -d
```

## Troubleshooting

### Services prüfen
```bash
# Status aller Container
docker-compose ps

# Logs anzeigen
docker-compose logs -f

# Traefik Dashboard für Routing-Info
# http://localhost:8080/
```

## Entwicklung

### Projektstruktur
```
nocodb-sql-editor/
├── docker-compose.yml    # Hauptkonfiguration mit Traefik
├── .env.example         # Umgebungsvariablen-Vorlage
├── php/
│   └── Dockerfile       # PHP-Webserver für SQL Editor
├── admin/               # PHP-Anwendung (SQL Editor)
│   ├── index.php
│   ├── header.php
│   └── footer.php
├── data/                # Persistente Daten (erstellt automatisch)
└── README.md
```

### Lokale Entwicklung
```bash
# Für Entwicklung: Services im Vordergrund starten
docker-compose up

# Einzelne Services neu starten
docker-compose restart sql-editor

# Logs eines Services verfolgen
docker-compose logs -f sql-editor
```

## Mitwirken

Beiträge sind willkommen! Erstellen Sie einen Issue oder Pull Request.

## Lizenz

Dieses Projekt ist derzeit ohne Lizenz veröffentlicht.