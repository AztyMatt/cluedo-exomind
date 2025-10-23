@echo off
setlocal enabledelayedexpansion

REM Configuration
set DB_NAME=cluedo
set DB_USER=admin
set DB_PASSWORD=hall0w33n
set SQL_FILE=init.sql
set INSERTS_DIR=sql\inserts
set DB_CONTAINER=cluedo-exomind-db-1

REM Verification fichier SQL
if not exist "%SQL_FILE%" (
    echo [ERREUR] Le fichier %SQL_FILE% n'existe pas!
    exit /b 1
)

echo ===================================================
echo   Initialisation complete de la base de donnees
echo ===================================================
echo.
echo Configuration:
echo   Container: %DB_CONTAINER%
echo   Database: %DB_NAME%
echo   User: %DB_USER%
echo   SQL File: %SQL_FILE%
echo   Inserts: %INSERTS_DIR%
echo.

REM Verification Docker
docker ps >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Docker n'est pas lance!
    echo Veuillez demarrer Docker Desktop.
    pause
    exit /b 1
)

REM Verification container
docker ps --format "{{.Names}}" | findstr /c:"%DB_CONTAINER%" >nul
if errorlevel 1 (
    echo [ERREUR] Container %DB_CONTAINER% non demarre
    echo Demarrage avec docker-compose...
    docker-compose up -d
    echo Attente 30 secondes...
    timeout /t 30 /nobreak >nul
)

REM Test connexion MySQL
echo Verification connexion MySQL...
set RETRY=0
:RETRY_MYSQL
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    set /a RETRY+=1
    if !RETRY! LSS 10 (
        echo Tentative !RETRY!/10...
        timeout /t 3 /nobreak >nul
        goto RETRY_MYSQL
    )
    echo [ERREUR] Impossible de se connecter a MySQL
    pause
    exit /b 1
)
echo [OK] Connexion reussie
echo.

REM ETAPE 1: Drop database
echo [1/4] Suppression base %DB_NAME%...
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% -e "DROP DATABASE IF EXISTS %DB_NAME%;" >nul 2>&1
echo [OK] Base supprimee
echo.

REM ETAPE 2: Create database
echo [2/4] Creation base %DB_NAME%...
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Creation base echouee
    pause
    exit /b 1
)
echo [OK] Base creee
echo.

REM ETAPE 3: Create tables
echo [3/4] Creation tables depuis %SQL_FILE%...
type "%SQL_FILE%" | docker exec -i %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% --default-character-set=utf8mb4 %DB_NAME% >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Creation tables echouee
    pause
    exit /b 1
)
echo [OK] Tables creees
echo.
echo Tables:
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% %DB_NAME% -e "SHOW TABLES;" 2>nul | findstr /v "Tables_in"
echo.

REM ETAPE 4: Inserts
if not exist "%INSERTS_DIR%" (
    echo [4/4] Dossier inserts introuvable
    goto FIN
)

echo [4/4] Insertion donnees depuis %INSERTS_DIR%...
set SUCCESS=0
set ERRORS=0

REM Traiter seulement les fichiers dans sql\inserts avec pattern numerique
for %%F in ("%INSERTS_DIR%\??-*.sql") do (
    echo   - %%~nxF...
    type "%%F" | docker exec -i %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% %DB_NAME% >nul 2>&1
    if errorlevel 1 (
        echo     [ERREUR]
        set /a ERRORS+=1
    ) else (
        echo     [OK]
        set /a SUCCESS+=1
    )
)

echo.
echo Fichiers executes: !SUCCESS!
echo Erreurs: !ERRORS!
echo.

if !ERRORS! GTR 0 (
    echo [ATTENTION] Des erreurs se sont produites
)

:FIN
echo ===================================================
echo [OK] Initialisation terminee
echo ===================================================
echo.
echo Contenu table groups:
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% %DB_NAME% -e "SELECT * FROM groups;" 2>nul
echo.
pause
exit /b 0
