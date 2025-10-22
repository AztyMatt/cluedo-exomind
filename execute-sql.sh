#!/bin/bash

#######################################################
# Script d'exécution des instructions SQL
# 1. Drop et recrée la base de données
# 2. Exécute le fichier init.sql pour créer les tables
# 3. Exécute tous les fichiers d'inserts
#######################################################

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Informations de connexion (depuis db-connection.php)
DB_NAME="cluedo"
DB_USER="admin"
DB_PASSWORD="hall0w33n"
SQL_FILE="init.sql"
INSERTS_DIR="sql/inserts"
DB_CONTAINER="cluedo-exomind-db-1"

# Vérification que le fichier SQL existe
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}Erreur: Le fichier $SQL_FILE n'existe pas!${NC}"
    exit 1
fi

echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Initialisation complète de la base de données${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Configuration:${NC}"
echo "  Container: $DB_CONTAINER"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  SQL File: $SQL_FILE"
echo "  Inserts: $INSERTS_DIR"
echo ""

# Vérification que Docker est lancé
if ! docker ps > /dev/null 2>&1; then
    echo -e "${RED}Erreur: Docker n'est pas lancé ou n'est pas accessible!${NC}"
    echo -e "${YELLOW}Veuillez démarrer Docker et réessayer.${NC}"
    exit 1
fi

# Vérification que le container MySQL existe et est en cours d'exécution
if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo -e "${RED}Erreur: Le container MySQL '$DB_CONTAINER' n'est pas en cours d'exécution!${NC}"
    echo -e "${YELLOW}Démarrage des services Docker...${NC}"
    docker-compose up -d
    echo -e "${YELLOW}Attente du démarrage de MySQL (30 secondes)...${NC}"
    sleep 30
fi

# Fonction pour vérifier si MySQL est accessible
check_mysql_connection() {
    docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1
    return $?
}

# Vérification de la connexion MySQL
echo -e "${YELLOW}Vérification de la connexion à MySQL...${NC}"
RETRY_COUNT=0
MAX_RETRIES=10

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if check_mysql_connection; then
        echo -e "${GREEN}✓ Connexion réussie!${NC}"
        break
    else
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
            echo -e "${YELLOW}Tentative $RETRY_COUNT/$MAX_RETRIES... Nouvelle tentative dans 3 secondes...${NC}"
            sleep 3
        fi
    fi
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo -e "${RED}Erreur: Impossible de se connecter à MySQL après $MAX_RETRIES tentatives!${NC}"
    echo -e "${YELLOW}Vérifiez les logs avec: docker-compose logs db${NC}"
    exit 1
fi

echo ""

# ═══════════════════════════════════════════════════
# ÉTAPE 1: Suppression et recréation de la base de données
# ═══════════════════════════════════════════════════
echo -e "${YELLOW}[1/4] Suppression de la base de données '$DB_NAME'...${NC}"
DROP_OUTPUT=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>&1)
DROP_EXIT_CODE=$?
if [ $DROP_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Base de données supprimée${NC}"
else
    echo -e "${YELLOW}⚠ Avertissement lors de la suppression: $DROP_OUTPUT${NC}"
fi
echo ""

# ═══════════════════════════════════════════════════
# ÉTAPE 2: Création de la base de données
# ═══════════════════════════════════════════════════
echo -e "${YELLOW}[2/4] Création de la base de données '$DB_NAME'...${NC}"
CREATE_OUTPUT=$(docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1)
CREATE_EXIT_CODE=$?
if [ $CREATE_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Base de données créée${NC}"
else
    echo -e "${RED}✗ Erreur lors de la création de la base${NC}"
    echo -e "${RED}$CREATE_OUTPUT${NC}"
    exit 1
fi
echo ""

# ═══════════════════════════════════════════════════
# ÉTAPE 3: Exécution du fichier init.sql (création des tables)
# ═══════════════════════════════════════════════════
echo -e "${YELLOW}[3/4] Création des tables depuis $SQL_FILE...${NC}"
INIT_OUTPUT=$(docker exec -i "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" --default-character-set=utf8mb4 "$DB_NAME" < "$SQL_FILE" 2>&1)
INIT_EXIT_CODE=$?

if [ $INIT_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Tables créées avec succès${NC}"
    
    # Affichage des tables créées
    echo -e "${BLUE}Tables créées:${NC}"
    docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" --default-character-set=utf8mb4 "$DB_NAME" -e "SHOW TABLES;" 2>&1 | grep -v "Using a password"
else
    echo -e "${RED}✗ Erreur lors de la création des tables${NC}"
    echo -e "${RED}$(echo "$INIT_OUTPUT" | grep -v "Using a password")${NC}"
    exit 1
fi
echo ""

# ═══════════════════════════════════════════════════
# ÉTAPE 4: Exécution des fichiers d'inserts
# ═══════════════════════════════════════════════════
if [ -d "$INSERTS_DIR" ]; then
    echo -e "${YELLOW}[4/4] Insertion des données depuis $INSERTS_DIR...${NC}"
    
    # Compteurs
    SUCCESS_COUNT=0
    ERROR_COUNT=0
    
    # Exécution de tous les fichiers SQL dans le dossier inserts
    for sql_file in "$INSERTS_DIR"/*.sql; do
        if [ -f "$sql_file" ]; then
            filename=$(basename "$sql_file")
            echo -e "${BLUE}  → Exécution de $filename...${NC}"
            
            INSERT_OUTPUT=$(docker exec -i "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" --default-character-set=utf8mb4 "$DB_NAME" < "$sql_file" 2>&1)
            INSERT_EXIT_CODE=$?
            
            if [ $INSERT_EXIT_CODE -eq 0 ]; then
                echo -e "${GREEN}    ✓ $filename exécuté avec succès${NC}"
                SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
            else
                echo -e "${RED}    ✗ Erreur lors de l'exécution de $filename${NC}"
                echo -e "${RED}    $(echo "$INSERT_OUTPUT" | grep -v "Using a password")${NC}"
                ERROR_COUNT=$((ERROR_COUNT + 1))
            fi
        fi
    done
    
    echo ""
    if [ $ERROR_COUNT -eq 0 ] && [ $SUCCESS_COUNT -gt 0 ]; then
        echo -e "${GREEN}✓ Tous les fichiers d'inserts ont été exécutés avec succès ($SUCCESS_COUNT fichier(s))${NC}"
    elif [ $SUCCESS_COUNT -eq 0 ]; then
        echo -e "${YELLOW}⚠ Aucun fichier d'insert trouvé dans $INSERTS_DIR${NC}"
    else
        echo -e "${RED}✗ Des erreurs sont survenues: $ERROR_COUNT fichier(s) en erreur${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}[4/4] Aucun dossier d'inserts trouvé (ignoré)${NC}"
fi

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Initialisation de la base de données terminée!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo ""

# Affichage du contenu de la table groups si elle existe
echo -e "${YELLOW}Contenu de la table 'groups':${NC}"
docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" --default-character-set=utf8mb4 "$DB_NAME" -e "SELECT * FROM \`groups\`;" 2>&1 | grep -v "Using a password"

exit 0

