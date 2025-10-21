#######################################################
# Script d'exécution des instructions SQL (PowerShell)
# 1. Drop et recrée la base de données
# 2. Exécute le fichier init.sql pour créer les tables
# 3. Exécute tous les fichiers d'inserts
#######################################################

# Informations de connexion (depuis db-connection.php)
$DB_NAME = "cluedo"
$DB_USER = "admin"
$DB_PASSWORD = "hall0w33n"
$SQL_FILE = "init.sql"
$INSERTS_DIR = "sql\inserts"
$DB_CONTAINER = "cluedo-exomind-db-1"

# Vérification que le fichier SQL existe
if (-not (Test-Path $SQL_FILE)) {
    Write-Host "Erreur: Le fichier $SQL_FILE n'existe pas!" -ForegroundColor Red
    exit 1
}

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "  Initialisation complète de la base de données" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Configuration:" -ForegroundColor Yellow
Write-Host "  Container: $DB_CONTAINER"
Write-Host "  Database: $DB_NAME"
Write-Host "  User: $DB_USER"
Write-Host "  SQL File: $SQL_FILE"
Write-Host "  Inserts: $INSERTS_DIR"
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
    Write-Host "Démarrage des services Docker..." -ForegroundColor Yellow
    docker-compose up -d
    Write-Host "Attente du démarrage de MySQL (30 secondes)..." -ForegroundColor Yellow
    Start-Sleep -Seconds 30
}

# Fonction pour vérifier si MySQL est accessible
function Test-MySQLConnection {
    try {
        docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" -e "SELECT 1;" *>$null
        return $LASTEXITCODE -eq 0
    } catch {
        return $false
    }
}

# Vérification de la connexion MySQL
Write-Host "Vérification de la connexion à MySQL..." -ForegroundColor Yellow
$retryCount = 0
$maxRetries = 10

while ($retryCount -lt $maxRetries) {
    if (Test-MySQLConnection) {
        Write-Host "✓ Connexion réussie!" -ForegroundColor Green
        break
    } else {
        $retryCount++
        if ($retryCount -lt $maxRetries) {
            Write-Host "Tentative $retryCount/$maxRetries... Nouvelle tentative dans 3 secondes..." -ForegroundColor Yellow
            Start-Sleep -Seconds 3
        }
    }
}

if ($retryCount -eq $maxRetries) {
    Write-Host "Erreur: Impossible de se connecter à MySQL après $maxRetries tentatives!" -ForegroundColor Red
    Write-Host "Vérifiez les logs avec: docker-compose logs db" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# ===================================================
# ÉTAPE 1: Suppression et recréation de la base de données
# ===================================================
Write-Host "[1/4] Suppression de la base de données '$DB_NAME'..." -ForegroundColor Yellow
docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Base de données supprimée" -ForegroundColor Green
} else {
    Write-Host "⚠ Avertissement lors de la suppression" -ForegroundColor Yellow
}
Write-Host ""

# ===================================================
# ÉTAPE 2: Création de la base de données
# ===================================================
Write-Host "[2/4] Création de la base de données '$DB_NAME'..." -ForegroundColor Yellow
docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Erreur lors de la création de la base" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Base de données créée" -ForegroundColor Green
Write-Host ""

# ===================================================
# ÉTAPE 3: Exécution du fichier init.sql (création des tables)
# ===================================================
Write-Host "[3/4] Création des tables depuis $SQL_FILE..." -ForegroundColor Yellow
Get-Content $SQL_FILE | docker exec -i $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" --default-character-set=utf8mb4 $DB_NAME 2>&1 | Out-Null

if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Erreur lors de la création des tables" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Tables créées avec succès" -ForegroundColor Green

# Affichage des tables créées
Write-Host "Tables créées:" -ForegroundColor Cyan
docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" --default-character-set=utf8mb4 $DB_NAME -e "SHOW TABLES;" 2>&1 | Where-Object { $_ -notmatch "Using a password" }
Write-Host ""

# ===================================================
# ÉTAPE 4: Exécution des fichiers d'inserts
# ===================================================
if (Test-Path $INSERTS_DIR) {
    Write-Host "[4/4] Insertion des données depuis $INSERTS_DIR..." -ForegroundColor Yellow
    
    $successCount = 0
    $errorCount = 0
    
    # Exécution de tous les fichiers SQL dans le dossier inserts
    Get-ChildItem -Path $INSERTS_DIR -Filter *.sql | Sort-Object Name | ForEach-Object {
        $filename = $_.Name
        Write-Host "  → Exécution de $filename..." -ForegroundColor Cyan
        
        Get-Content $_.FullName | docker exec -i $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" --default-character-set=utf8mb4 $DB_NAME 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "    ✓ $filename exécuté avec succès" -ForegroundColor Green
            $successCount++
        } else {
            Write-Host "    ✗ Erreur lors de l'exécution de $filename" -ForegroundColor Red
            $errorCount++
        }
    }
    
    Write-Host ""
    if ($errorCount -eq 0 -and $successCount -gt 0) {
        Write-Host "✓ Tous les fichiers d'inserts ont été exécutés avec succès ($successCount fichier(s))" -ForegroundColor Green
    } elseif ($successCount -eq 0) {
        Write-Host "⚠ Aucun fichier d'insert trouvé dans $INSERTS_DIR" -ForegroundColor Yellow
    } else {
        Write-Host "✗ Des erreurs sont survenues: $errorCount fichier(s) en erreur" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[4/4] Aucun dossier d'inserts trouvé (ignoré)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "✓ Initialisation de la base de données terminée!" -ForegroundColor Green
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Affichage du contenu de la table groups si elle existe
Write-Host "Contenu de la table 'groups':" -ForegroundColor Yellow
docker exec $DB_CONTAINER mysql -u $DB_USER -p"$DB_PASSWORD" --default-character-set=utf8mb4 $DB_NAME -e "SELECT * FROM ``groups``;" 2>&1 | Where-Object { $_ -notmatch "Using a password" }

exit 0

