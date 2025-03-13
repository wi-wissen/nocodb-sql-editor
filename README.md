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

2. Docker Compose-Konfiguration anpassen (optional):
   Die Datei `docker-compose.yml` enthält bereits alle nötigen Einstellungen.
   Sie können Benutzernamen, Passwörter und andere Parameter anpassen.

3. Docker-Container starten:
   ```bash
   docker-compose up -d
   ```

4. Auf die Anwendungen zugreifen:
    - NocoDB: http://localhost:80
    - SQL Editor: http://localhost:8081
    - phpMyAdmin: http://localhost:8080


5. Nginx-Konfiguration für Reverse Proxy
   Wenn Sie einen Nginx-Server als Reverse Proxy verwenden möchten, können Sie die die Konfiguration aus `nginx.conf.example` verwenden. Nutzen Sie dazu in der `docker-compose.yml` die Enviroment-Variable `PMA_ABSOLUTE_URI`.
   Dann können Sie so auf die Anwendung zugreifen:
    - NocoDB: http://localhost
    - SQL Editor: http://localhost/sql
    - phpMyAdmin: http://localhost/phpmyadmin
   

### Sicherheitseinstellungen

#### Registrierung deaktivieren

Die Benutzerregistrierung kann auf zwei Arten deaktiviert werden:

1. **In der Docker-Konfiguration** (empfohlen):
   ```yaml
   environment:
     NC_INVITE_ONLY_SIGNUP: 1
   ```

2. **Über die NocoDB-Benutzeroberfläche**:
   - Melden Sie sich als Super-Admin an
   - Gehen Sie zu "Account" → "Settings"
   - Deaktivieren Sie die Option "Enable Signup" 
   - Weitere Informationen: [NocoDB Dokumentation](https://docs.nocodb.com/account-settings/oss-specific-details/#enable--disable-signup)

## Verwendung

1. Öffnen Sie den SQL Editor unter http://localhost:8081
2. Melden Sie sich mit Ihren NocoDB-Anmeldedaten an
3. Wählen Sie ein Projekt aus
4. Schreiben Sie SQL-Abfragen unter Verwendung der Tabellennamen aus NocoDB
5. Führen Sie die Abfragen aus und sehen Sie die Ergebnisse

### Hinweise

- Verwenden Sie die tatsächlichen **Tabellentitel** aus NocoDB in Ihren SQL-Abfragen (nicht die internen Tabellennamen)
- Der Editor ersetzt automatisch die Tabellentitel durch die tatsächlichen Tabellennamen
- Aus Sicherheitsgründen ist der Zugriff auf Systemtabellen (nc_*) nicht erlaubt

## Mitwirken

Beiträge sind willkommen! Erstellen Sie einen Issue oder Pull Request.

## Lizenz

Dieses Projekt ist derzeit ohne Lizenz veröffentlicht.