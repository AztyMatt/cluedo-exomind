#!/bin/bash

#######################################################
# Script d'exécution des fichiers INSERT SQL
# Exécute tous les fichiers SQL du dossier sql/inserts
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
INSERTS_DIR="sql/inserts"
DB_CONTAINER="cluedo-exomind-db-1"

# Vérification que le dossier d'inserts existe
if [ ! -d "$INSERTS_DIR" ]; then
    echo -e "${RED}Erreur: Le dossier $INSERTS_DIR n'existe pas!${NC}"
    exit 1
fi

echo -e "${BLUE}Exécution des fichiers INSERT SQL via Docker...${NC}"
echo -e "${YELLOW}Configuration:${NC}"
echo "  Container: $DB_CONTAINER"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  Dossier: $INSERTS_DIR"
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
    echo -e "${YELLOW}Démarrez les services avec: docker-compose up -d${NC}"
    exit 1
fi

# Fonction pour vérifier si MySQL est accessible
check_mysql_connection() {
    docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1
    return $?
}

# Vérification de la connexion MySQL
echo -e "${YELLOW}Vérification de la connexion à MySQL...${NC}"
if ! check_mysql_connection; then
    echo -e "${RED}Erreur: Impossible de se connecter à MySQL!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Connexion réussie!${NC}"
echo ""

# Compteurs
SUCCESS_COUNT=0
ERROR_COUNT=0

# Exécution de tous les fichiers SQL dans le dossier inserts
echo -e "${YELLOW}Exécution des fichiers INSERT...${NC}"
for sql_file in "$INSERTS_DIR"/*.sql; do
    if [ -f "$sql_file" ]; then
        filename=$(basename "$sql_file")
        echo -e "${BLUE}→ Exécution de $filename...${NC}"
        
        if docker exec -i "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$sql_file" 2>&1 | grep -v "Using a password on the command line"; then
            echo -e "${GREEN}  ✓ $filename exécuté avec succès${NC}"
            SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
        else
            echo -e "${RED}  ✗ Erreur lors de l'exécution de $filename${NC}"
            ERROR_COUNT=$((ERROR_COUNT + 1))
        fi
    fi
done

echo ""
echo -e "${YELLOW}Résumé:${NC}"
echo "  Succès: $SUCCESS_COUNT fichier(s)"
echo "  Erreurs: $ERROR_COUNT fichier(s)"
echo ""

if [ $ERROR_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓ Tous les fichiers INSERT ont été exécutés avec succès!${NC}"
    
    # Affichage des données dans la table groups
    echo ""
    echo -e "${YELLOW}Données dans la table 'groups':${NC}"
    docker exec "$DB_CONTAINER" mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT * FROM groups;" 2>&1 | grep -v "Using a password on the command line"
    
    exit 0
else
    echo -e "${RED}✗ Des erreurs sont survenues lors de l'exécution!${NC}"
    exit 1
fi

