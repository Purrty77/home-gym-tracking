# Muscu

Application personnelle de suivi de musculation, conçue pour un Raspberry Pi et accessible uniquement sur le réseau local via `http://muscu.local`.

Version actuelle : **1.0.0**.

## Essai rapide sur Windows

Dans PowerShell, à la racine du projet :

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1
```

Ce script démarre MariaDB et PHP en arrière-plan puis ouvre l’application. Il peut être relancé sans créer de doublon. Pour tout arrêter :

```powershell
powershell -ExecutionPolicy Bypass -File .\stop.ps1
```

## Fonctionnalités actuelles

- saisie d’une séance avec plusieurs exercices et séries ;
- catalogue d’exercices personnalisable et chronomètre de repos ;
- programmes A, B, C et Jambes préremplis automatiquement selon le jour ;
- distinction échauffement, montée en charge et séries de travail ;
- historique filtrable de chaque exercice ;
- suivi des charges, tendances de progression et records personnels ;
- historique des mensurations et du poids ;
- graphiques Chart.js servis localement ;
- tableau de bord et rappel mensuel ;
- suppression sécurisée des séances et mensurations erronées ;
- interface responsive adaptée au téléphone ;
- mode d'entraînement guidé, une série à la fois, avec reprise automatique ;
- chronomètre de repos automatique (pause, ignorer et +30 secondes) ;
- sauvegarde immédiate de chaque série et détection des records personnels ;
- 50 messages de motivation quotidiens et 102 succès basés sur les données ;
- état de la séance du jour, heatmap de régularité et tendances de poids.

## Prérequis de développement

- PHP 8.2 ou supérieur avec PDO MySQL ;
- Composer 2 ;
- Node.js 20 ou supérieur ;
- MariaDB 10.11 ou supérieur.

```bash
cp .env.example .env
composer install
npm install
npm run build
```

Créez ensuite la base :

```bash
mariadb -u root -p < database/schema.sql
mariadb -u root -p muscu < database/seeds/exercises.sql
mariadb -u root -p muscu < database/seeds/workout_templates.sql
mariadb -u root -p muscu < database/seeds/motivational_messages.sql
# Facultatif : données de démonstration
mariadb -u root -p muscu < database/seeds/demo_data.sql
```

Renseignez les identifiants dans `.env`, puis démarrez un serveur de développement :

```bash
php -S localhost:8000 -t public
```

## Installation sur Raspberry Pi OS

Les commandes suivantes ciblent Raspberry Pi OS Bookworm ou une version ultérieure.

## Workflow GitHub

Après avoir créé un dépôt GitHub vide :

```bash
git add .
git commit -m "Version 1.0.0"
git branch -M main
git remote add origin https://github.com/VOTRE-COMPTE/VOTRE-DEPOT.git
git push -u origin main
```

Le fichier `.env` n’est jamais inclus dans le commit. Sur le Raspberry Pi, utilisez `git clone` lors de l’installation initiale, puis `./scripts/deploy.sh` pour les mises à jour suivantes.

### 1. Installer les paquets

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php php php-cli php-mysql php-mbstring php-xml php-curl mariadb-server git composer avahi-daemon
sudo a2enmod rewrite
sudo systemctl enable --now apache2 mariadb avahi-daemon
```

### 2. Sécuriser MariaDB et créer l’utilisateur applicatif

```bash
sudo mariadb-secure-installation
sudo mariadb
```

Dans MariaDB :

```sql
CREATE DATABASE muscu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'muscu_app'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_LONG_ET_UNIQUE';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON muscu.* TO 'muscu_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Cloner l’application

```bash
sudo git clone https://github.com/VOTRE-COMPTE/VOTRE-DEPOT.git /var/www/muscu
sudo chown -R "$USER":www-data /var/www/muscu
sudo chmod -R 775 /var/www/muscu/storage
cd /var/www/muscu
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
nano .env
```

Le fichier `.env` doit contenir le mot de passe créé précédemment. Il est ignoré par Git.

### 4. Initialiser la base

Le fichier `schema.sql` sélectionne lui-même la base `muscu`. Utilisez un compte administrateur pour la création initiale :

```bash
sudo mariadb < database/schema.sql
sudo mariadb muscu < database/seeds/exercises.sql
sudo mariadb muscu < database/seeds/workout_templates.sql
sudo mariadb muscu < database/seeds/motivational_messages.sql
```

Les données de test sont facultatives :

```bash
sudo mariadb muscu < database/seeds/demo_data.sql
```

### 5. Configurer Apache sur le port 80

```bash
sudo cp deploy/apache-muscu.conf /etc/apache2/sites-available/muscu.conf
sudo a2dissite 000-default.conf
sudo a2ensite muscu.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Apache expose uniquement `/var/www/muscu/public`. Le `.env`, les sauvegardes et le code PHP interne ne sont pas accessibles directement.

### 6. Configurer `muscu.local` avec Avahi/mDNS

Le nom mDNS est basé sur le nom d’hôte du Raspberry Pi :

```bash
sudo hostnamectl set-hostname muscu
sudo systemctl restart avahi-daemon
sudo reboot
```

Après le redémarrage, ouvrez `http://muscu.local`. Avahi annonce automatiquement le nom d’hôte sur le réseau local. Linux, macOS, iOS et la majorité des systèmes Windows récents savent résoudre les noms `.local`. Sur un ancien Windows, Bonjour peut être nécessaire.

Si vous préférez `gym.local`, utilisez `sudo hostnamectl set-hostname gym` et modifiez `ServerName` dans la configuration Apache.

### 7. Mettre l’application à jour

```bash
cd /var/www/muscu
./scripts/deploy.sh
```

Le script effectue un `git pull --ff-only`, réinstalle les dépendances PHP de production puis recharge Apache. Les assets CSS et JavaScript compilés sont versionnés : Node.js n’est pas nécessaire sur le Raspberry Pi.

Pour une installation créée avant l’ajout des programmes préremplis, exécutez une seule fois :

```bash
cd /var/www/muscu
sudo mariadb muscu < database/migrations/002_workout_templates.sql
sudo mariadb muscu < database/seeds/workout_templates.sql
```

Pour appliquer la limitation à une mensuration par mois sur une base existante :

```bash
sudo mariadb muscu < database/migrations/003_monthly_measurements.sql
```

Pour migrer une base existante vers l’interface et les noms anglais :

```bash
sudo mariadb muscu < database/migrations/004_english_interface.sql
```

To enable the guided Workout Mode and achievement system on an existing database:

```bash
sudo mariadb muscu < database/migrations/005_workout_mode.sql
sudo mariadb muscu < database/migrations/006_exercise_workout_controls.sql
sudo mariadb muscu < database/migrations/007_appearance_settings.sql
sudo mariadb muscu < database/migrations/008_dynamic_sets_body_weight.sql
sudo mariadb muscu < database/migrations/009_replace_cable_fly.sql
sudo mariadb muscu < database/migrations/010_exercise_dataset_metadata.sql
sudo mariadb muscu < database/migrations/011_workout_mode_companion.sql
sudo mariadb muscu < database/migrations/012_parallel_exercises_finish_early.sql
sudo mariadb muscu < database/seeds/motivational_messages.sql
```

Migration `011` adds the per-exercise warm-up choice, drop-set segments, persistent exercise ordering, and optional workout-plan updates. The warm-up default can be changed under **Settings → Warm-up sets**.

Migration `012` adds independent per-exercise progress and rest timers, enabling parallel exercises and supersets, plus explicit skipped-exercise tracking when a workout is finished early.

### Selected exercise dataset

The exercise library importer is intentionally limited to 80 reviewed exercises from `hasaneyldrm/exercises-dataset`. It never imports the complete 1,324-record catalogue and never changes workout targets, rest settings, increments, history, templates, or achievements.

Fetch only the selected source records and their associated media, preview the import, then apply it:

```bash
php scripts/fetch_exercise_dataset.php
php scripts/import_exercises.php --dry-run --selected-only --update-existing --with-media
php scripts/import_exercises.php --selected-only --update-existing --with-media
```

Import reports are written to `storage/import-reports/`. Exercise media stays local under `public/media/exercises/` and is excluded from Git. The media attribution supplied by the source dataset is displayed on each enriched exercise page and in Workout Mode.

## Sauvegarde MariaDB

```bash
cd /var/www/muscu
chmod +x scripts/*.sh
./scripts/backup-database.sh
```

Les archives sont placées dans `storage/backups`, hors de Git, et celles de plus de 60 jours sont supprimées. Copiez régulièrement ce dossier vers une autre machine : une sauvegarde conservée uniquement sur la carte SD ne protège pas d’une panne de celle-ci.

Pour automatiser une sauvegarde quotidienne à 03:15 :

```bash
crontab -e
```

Ajoutez :

```cron
15 3 * * * /var/www/muscu/scripts/backup-database.sh >> /var/www/muscu/storage/logs/backup.log 2>&1
```

Restauration :

```bash
gunzip -c storage/backups/muscu-AAAAMMJJ-HHMMSS.sql.gz | sudo mariadb muscu
```

## Architecture

- `app/Core` : routeur, base de données, vues et sécurité CSRF ;
- `app/Controllers` : traitement des requêtes ;
- `app/Models` : accès aux données ;
- `app/Services` : statistiques métier ;
- `resources/views` : pages PHP ;
- `public` : racine web et assets compilés ;
- `database` : schéma et données initiales ;
- `scripts` : déploiement et sauvegardes.

## Tests

```bash
composer test
php tests/integration.php
```

Le premier script vérifie les validations métier sans base. Le second utilise la base définie dans `.env` et exécute le CRUD, l’historique et les statistiques. Ne le lancez pas sur une base contenant des données importantes.

## Sécurité et réseau

L’application n’intègre pas de compte utilisateur. Elle doit rester sur le réseau local et ne doit faire l’objet d’aucune redirection de port sur la box. Le routeur ne doit pas exposer le port 80 du Raspberry Pi vers Internet.
