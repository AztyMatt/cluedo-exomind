#!/usr/bin/env bash
set -euo pipefail

echo "➡️  Initialisation de la base de données Cluedo..."
echo "📊 Exécution du script upload-data-railway.php..."

# Exécuter le script d'initialisation de la base de données
php /var/www/html/upload-data-railway.php || {
    echo "⚠️  Le script d'initialisation a terminé (avec ou sans erreurs non-fatales)"
}

echo "🚀 Démarrage d'Apache sur le port ${PORT}"
exec apache2-foreground
