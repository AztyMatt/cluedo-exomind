@echo off
setlocal enabledelayedexpansion

REM Configuration
set DB_NAME=cluedo
set DB_USER=admin
set DB_PASSWORD=hall0w33n
set INSERTS_DIR=sql\inserts
set DB_CONTAINER=cluedo-exomind-db-1

REM Verification dossier
if not exist "%INSERTS_DIR%" (
    echo [ERREUR] Dossier %INSERTS_DIR% introuvable
    pause
    exit /b 1
)

echo Execution fichiers INSERT SQL
echo Configuration:
echo   Container: %DB_CONTAINER%
echo   Database: %DB_NAME%
echo   User: %DB_USER%
echo   Dossier: %INSERTS_DIR%
echo.

REM Verification Docker
docker ps >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Docker n'est pas lance
    pause
    exit /b 1
)

REM Verification container
docker ps --format "{{.Names}}" | findstr /c:"%DB_CONTAINER%" >nul
if errorlevel 1 (
    echo [ERREUR] Container %DB_CONTAINER% non demarre
    echo Demarrez avec: docker-compose up -d
    pause
    exit /b 1
)

REM Test connexion
echo Verification connexion MySQL...
docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Connexion MySQL impossible
    pause
    exit /b 1
)
echo [OK] Connexion reussie
echo.

REM Execution inserts
echo Execution fichiers INSERT...
set SUCCESS=0
set ERRORS=0

for %%F in ("%INSERTS_DIR%\*.sql") do (
    echo - %%~nxF...
    type "%%F" | docker exec -i %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% %DB_NAME% >nul 2>&1
    if errorlevel 1 (
        echo   [ERREUR]
        set /a ERRORS+=1
    ) else (
        echo   [OK]
        set /a SUCCESS+=1
    )
)

echo.
echo Resume:
echo   Succes: !SUCCESS! fichiers
echo   Erreurs: !ERRORS! fichiers
echo.

if !ERRORS! EQU 0 (
    echo [OK] Tous les fichiers executes avec succes
    echo.
    echo Donnees table groups:
    docker exec %DB_CONTAINER% mysql -u%DB_USER% -p%DB_PASSWORD% %DB_NAME% -e "SELECT * FROM groups;" 2>nul
) else (
    echo [ATTENTION] Des erreurs se sont produites
)

echo.
pause
exit /b 0
