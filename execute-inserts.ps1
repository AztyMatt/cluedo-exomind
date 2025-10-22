#######################################################
# Script d'exécution des fichiers INSERT SQL (PowerShell)
# Exécute tous les fichiers SQL du dossier sql/inserts
#######################################################

# Informations de connexion (depuis db-connection.php)
$DB_NAME = "cluedo"
$DB_USER = "admin"
$DB_PASSWORD = "hall0w33n"
$INSERTS_DIR = "sql\inserts"
$DB_CONTAINER = "cluedo-exomind-db-1"

# Vérification que le dossier d'inserts existe
if (-not (Test-Path $INSERTS_DIR)) {
    Write-Host "Erreur: Le dossier $INSERTS_DIR n'existe pas!" -ForegroundColor Red
    exit 1
}

Write-Host "Exécution des fichiers INSERT SQL via Docker..." -ForegroundColor Cyan
Write-Host "Configuration:" -ForegroundColor Yellow
Write-Host "  Container: $DB_CONTAINER"
Write-Host "  Database: $DB_NAME"
Write-Host "  User: $DB_USER"
Write-Host "  Dossier: $INSERTS_DIR"
Write-Host ""

# Vérification que Docker est lancé
try {
    docker ps *>$null
    if ($LASTEXITCODE -ne 0) { throw }
} catch {
    Write-Host "Erreur: Docker n'est pas lancé ou n'est pas accessible!" -ForegroundColor Red
    Write-Host "Veuillez démarrer Docker et réessayer." -ForegroundColor Yellow
    exit 1
}

# Vérification que le container MySQL existe et est en cours d'exécution
$containers = docker ps --format "{{.Names}}"
if ($containers -notcontains $DB_CONTAINER) {
    Write-Host "Erreur: Le container MySQL '$DB_CONTAINER' n'est pas en cours d'exécution!" -ForegroundColor Red
    Write-Host "Démarrez les services avec: docker-compose up -d" -ForegroundColor Yellow
    exit 1
}

# Vérification de la connexion MySQL
Write-Host "Vérification de la connexion à MySQL..." -ForegroundColor Yellow
try {
    docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" -e "SELECT 1;" *>$null
    if ($LASTEXITCODE -ne 0) { throw }
} catch {
    Write-Host "Erreur: Impossible de se connecter à MySQL!" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Connexion réussie!" -ForegroundColor Green
Write-Host ""

# Compteurs
$successCount = 0
$errorCount = 0

# Exécution de tous les fichiers SQL dans le dossier inserts
Write-Host "Exécution des fichiers INSERT..." -ForegroundColor Yellow
Get-ChildItem -Path $INSERTS_DIR -Filter *.sql | Sort-Object Name | ForEach-Object {
    $filename = $_.Name
    Write-Host "→ Exécution de $filename..." -ForegroundColor Cyan
    
    Get-Content $_.FullName | docker exec -i $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" $DB_NAME 2>&1 | Where-Object { $_ -notmatch "Using a password" } | Out-Null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ $filename exécuté avec succès" -ForegroundColor Green
        $successCount++
    } else {
        Write-Host "  ✗ Erreur lors de l'exécution de $filename" -ForegroundColor Red
        $errorCount++
    }
}

Write-Host ""
Write-Host "Résumé:" -ForegroundColor Yellow
Write-Host "  Succès: $successCount fichier(s)"
Write-Host "  Erreurs: $errorCount fichier(s)"
Write-Host ""

if ($errorCount -eq 0) {
    Write-Host "✓ Tous les fichiers INSERT ont été exécutés avec succès!" -ForegroundColor Green
    
    # Affichage des données dans la table groups
    Write-Host ""
    Write-Host "Données dans la table 'groups':" -ForegroundColor Yellow
    docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" $DB_NAME -e "SELECT * FROM groups;" 2>&1 | Where-Object { $_ -notmatch "Using a password" }
    
    exit 0
} else {
    Write-Host "✗ Des erreurs sont survenues lors de l'exécution!" -ForegroundColor Red
    exit 1
}

