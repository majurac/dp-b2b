#!/bin/bash

echo "🚀 B2B Partner Importer - Instalacija Dependencies"
echo "=================================================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null
then
    echo "❌ Composer nije instaliran."
    echo ""
    echo "Molimo instaliraj Composer prvo:"
    echo "curl -sS https://getcomposer.org/installer | php"
    echo "sudo mv composer.phar /usr/local/bin/composer"
    echo ""
    exit 1
fi

echo "✅ Composer je instaliran"
echo ""

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ composer.json nije pronađen."
    echo "Molimo pokreni skriptu iz root direktorija plugina."
    exit 1
fi

echo "📦 Instaliram PhpSpreadsheet..."
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Instalacija uspješna!"
    echo ""
    echo "📁 Dependencies instalirani u: includes/vendor/"
    echo ""
    echo "🎉 Plugin je spreman za korištenje!"
    echo ""
    echo "Sljedeći koraci:"
    echo "1. Uploadaj cijeli plugin folder u /wp-content/plugins/"
    echo "2. Aktiviraj plugin u WordPress admin panelu"
    echo "3. Idi na Tools → Import B2B Partnera"
else
    echo ""
    echo "❌ Greška pri instalaciji dependencies."
    echo "Molimo provjeri error poruke iznad."
    exit 1
fi
