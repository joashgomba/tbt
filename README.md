# NTB Portal — National Non-Tariff Barriers Platform

**Languages / Langues:** [English](#english) · [Français](#français)

---

<a id="english"></a>

## English

Standalone Laravel 12 + Vue 3 (Vuexy) application for reporting and managing non-tariff barriers on the Ethiopia–Djibouti trade corridor.

Authentication is handled **entirely within this application** using Laravel sessions and **Spatie Laravel Permission** for role-based access control. There is no external auth service or ERP hub dependency.

### Requirements

| Tool | Version |
|------|---------|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 20+ |
| npm | 10+ (bundled with Node) |
| Database | MySQL 8+ (recommended) or SQLite (local dev only) |
| Flutter (mobile app) | 3.19+ stable — see [mobile/README.md](mobile/README.md) |

PHP extensions required by Laravel: `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `Tokenizer`, `XML`.

### Installation

These steps are the same on every platform after you have PHP, Composer, Node.js, and a database installed.

```bash
# 1. Clone the repository and enter the project directory
git clone <repository-url> tbt
cd tbt

# 2. Install PHP dependencies
composer install

# 3. Environment file
cp .env.example .env
php artisan key:generate

# 4. Configure your database in .env (see examples below), then run migrations and seeders
php artisan migrate:fresh --seed
php artisan storage:link

# 5. Install and build frontend assets
npm install
npm run build
```

#### Database configuration (`.env`)

**MySQL (recommended):**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tbtsystem
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database before migrating:

```sql
CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**SQLite (quick local setup):**

```env
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD are not used
```

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

Set `APP_URL` in `.env` to match how you will access the site (see platform sections below).

#### Windows

**1. Install prerequisites**

Choose one PHP stack:

**Option A — [Laragon](https://laragon.org/) (recommended)**  
Includes PHP, MySQL, Composer, and Node.js in one installer. Enable **MySQL** and **Node.js** during setup.

**Option B — Manual install**

1. [PHP 8.2+ for Windows](https://windows.php.net/download/) — add PHP to your `PATH`
2. [Composer](https://getcomposer.org/download/)
3. [Node.js 20 LTS](https://nodejs.org/)
4. [MySQL 8](https://dev.mysql.com/downloads/installer/) or [MariaDB](https://mariadb.org/download/)

Verify in **PowerShell** or **Command Prompt**:

```powershell
php -v
composer -V
node -v
npm -v
mysql --version
```

**2. Run the application**

*With Laragon*

1. Place the project in Laragon’s `www` folder (e.g. `C:\laragon\www\tbt`)
2. In Laragon: **Menu → Quick app → Link** (or right-click → **Apache → Add virtual host**)
3. Set in `.env`:

   ```env
   APP_URL=http://tbt.test
   ```

4. Open http://tbt.test/

*With PHP’s built-in server*

```powershell
cd C:\path\to\tbt
php artisan serve
```

Set in `.env`:

```env
APP_URL=http://127.0.0.1:8000
```

Open http://127.0.0.1:8000/

**3. Windows notes**

- Run terminal commands from the project root (`tbt`).
- If `composer` or `php` is not found, add their install directories to the system **PATH**.
- On first run, allow Windows Firewall access for PHP/MySQL if prompted.
- For development with hot reload, use a second terminal: `npm run dev` (keep `php artisan serve` running in the first).

#### macOS

**1. Install prerequisites**

*Option A — [Homebrew](https://brew.sh/) (recommended)*

```bash
brew install php@8.2 composer node mysql
brew services start mysql
```

Link PHP if needed:

```bash
brew link php@8.2
```

*Option B — [Laravel Valet](https://laravel.com/docs/valet)* (best for `.test` domains)

```bash
brew install php composer node mysql
composer global require laravel/valet
valet install
```

Verify:

```bash
php -v
composer -V
node -v
mysql --version
```

**2. Create the database**

```bash
mysql -u root -e "CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**3. Run the application**

*With Valet (recommended)*

```bash
cd /path/to/tbt
valet link tbt
```

Set in `.env`:

```env
APP_URL=http://tbt.test
```

Open http://tbt.test/

*With PHP’s built-in server*

```bash
cd /path/to/tbt
php artisan serve
```

Set in `.env`:

```env
APP_URL=http://127.0.0.1:8000
```

Open http://127.0.0.1:8000/

#### Linux

**1. Install prerequisites**

*Ubuntu / Debian*

```bash
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl unzip curl git mysql-server
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Install Node.js 20 (via [NodeSource](https://github.com/nodesource/distributions)):

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

*Fedora / RHEL*

```bash
sudo dnf install php php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip php-bcmath php-gd
sudo dnf install composer nodejs npm mysql-server
```

Start MySQL and create the database:

```bash
sudo systemctl enable --now mysql    # or mariadb on some distros
sudo mysql -e "CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Verify:

```bash
php -v
composer -V
node -v
mysql --version
```

**2. Run the application**

*With PHP’s built-in server (simplest)*

```bash
cd /path/to/tbt
php artisan serve --host=0.0.0.0 --port=8000
```

Set in `.env`:

```env
APP_URL=http://127.0.0.1:8000
```

Open http://127.0.0.1:8000/

*With Nginx or Apache*

Point the web server document root to the project’s `public/` directory. Set `APP_URL` to your virtual host URL (e.g. `http://tbt.local`). Ensure `storage/` and `bootstrap/cache/` are writable by the web server user:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

#### One-command setup (all platforms)

After prerequisites are installed and `.env` database settings are correct:

```bash
composer setup
php artisan migrate:fresh --seed
php artisan storage:link
```

The `composer setup` script runs `composer install`, creates `.env`, generates the app key, migrates, installs npm packages, and builds assets. Use `migrate:fresh --seed` when you need demo data.

#### Application URLs

| Page | Path |
|------|------|
| Public portal | `/` |
| Admin login | `/login` |
| Admin dashboard | `/admin` |

Replace the host with your `APP_URL` (e.g. `http://tbt.test/login` or `http://127.0.0.1:8000/login`).

#### Demo users

Password for all: `password`

| Email | Role | Admin login |
|-------|------|-------------|
| sysadmin@ntb.dj | System Administrator | Yes |
| mttadmin@ntb.dj | MTT Administrator | Yes |
| focal@ntb.dj | National Focal Point | Yes |
| instadmin@customs.dj | Institutional Administrator | Yes |
| agent@customs.dj | Institutional Officer | Yes |
| nmc@ntb.dj | NMC Member | Yes |
| analyst@ntb.dj | Analyst | Yes |
| auditor@ntb.dj | Auditor | Yes |
| operator@trade.dj | Economic Operator (public portal only) | No — use `/report` |

You may sign in with **email or username** (e.g. `focalpoint` / `focal@ntb.dj`).

#### Development

```bash
# Full dev stack (server, queue, logs, Vite hot reload)
composer dev

# Or run separately:
php artisan serve
npm run dev
```

For production-like assets (recommended when not using `npm run dev`):

```bash
npm run build
```

**Important:** If you see redirects to a wrong host (e.g. `auth-service.test`), delete `public/hot` and run `npm run build` so the app serves built assets instead of a stale Vite dev server URL.

Useful Artisan commands:

```bash
php artisan migrate:fresh --seed   # Reset DB with demo data
php artisan config:clear           # Clear cached config after .env changes
php artisan cache:clear
php artisan test                   # Run PHPUnit tests
```

#### Auth & permissions

- Login: `POST /api/v1/auth/login` (session cookie)
- Logout: `POST /api/v1/auth/logout`
- Current user: `GET /api/v1/auth/me`
- Roles/permissions managed via Spatie (`spatie/laravel-permission`)

MFA is disabled by default for local development (`NTB_MFA_ENABLED=false` in `.env`).

#### Troubleshooting

| Issue | Fix |
|-------|-----|
| `500` / permission errors | `chmod -R ug+rwx storage bootstrap/cache` (Linux/macOS) |
| Blank page / missing styles | Run `npm run build`; remove `public/hot` if present |
| Database connection refused | Check MySQL is running and `.env` credentials match |
| `Vite manifest not found` | Run `npm install && npm run build` |
| Wrong redirect URL | Set `APP_URL` in `.env` to match your local URL, then `php artisan config:clear` |

---

<a id="français"></a>

## Français

Application autonome Laravel 12 + Vue 3 (Vuexy) pour signaler et gérer les obstacles non tarifaires (ONT) sur le corridor commercial Éthiopie–Djibouti.

L’authentification est gérée **entièrement dans cette application** via les sessions Laravel et **Spatie Laravel Permission** pour le contrôle d’accès basé sur les rôles. Aucun service d’authentification externe ni dépendance ERP n’est requis.

### Prérequis

| Outil | Version |
|-------|---------|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 20+ |
| npm | 10+ (inclus avec Node) |
| Base de données | MySQL 8+ (recommandé) ou SQLite (développement local uniquement) |

Extensions PHP requises par Laravel : `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `Tokenizer`, `XML`.

### Installation

Ces étapes sont identiques sur toutes les plateformes une fois PHP, Composer, Node.js et une base de données installés.

```bash
# 1. Cloner le dépôt et entrer dans le répertoire du projet
git clone <url-du-depot> tbt
cd tbt

# 2. Installer les dépendances PHP
composer install

# 3. Fichier d'environnement
cp .env.example .env
php artisan key:generate

# 4. Configurer la base de données dans .env (voir exemples ci-dessous), puis exécuter migrations et seeders
php artisan migrate:fresh --seed
php artisan storage:link

# 5. Installer et compiler les ressources frontend
npm install
npm run build
```

#### Configuration de la base de données (`.env`)

**MySQL (recommandé) :**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tbtsystem
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe
```

Créez la base de données avant de migrer :

```sql
CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**SQLite (configuration locale rapide) :**

```env
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD ne sont pas utilisés
```

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

Définissez `APP_URL` dans `.env` selon la façon dont vous accéderez au site (voir les sections par plateforme ci-dessous).

#### Windows

**1. Installer les prérequis**

Choisissez une stack PHP :

**Option A — [Laragon](https://laragon.org/) (recommandé)**  
Inclut PHP, MySQL, Composer et Node.js dans un seul installateur. Activez **MySQL** et **Node.js** lors de l’installation.

**Option B — Installation manuelle**

1. [PHP 8.2+ pour Windows](https://windows.php.net/download/) — ajoutez PHP au `PATH`
2. [Composer](https://getcomposer.org/download/)
3. [Node.js 20 LTS](https://nodejs.org/)
4. [MySQL 8](https://dev.mysql.com/downloads/installer/) ou [MariaDB](https://mariadb.org/download/)

Vérifiez dans **PowerShell** ou l’**Invite de commandes** :

```powershell
php -v
composer -V
node -v
npm -v
mysql --version
```

**2. Lancer l’application**

*Avec Laragon*

1. Placez le projet dans le dossier `www` de Laragon (ex. `C:\laragon\www\tbt`)
2. Dans Laragon : **Menu → Quick app → Link** (ou clic droit → **Apache → Add virtual host**)
3. Définissez dans `.env` :

   ```env
   APP_URL=http://tbt.test
   ```

4. Ouvrez http://tbt.test/

*Avec le serveur intégré de PHP*

```powershell
cd C:\chemin\vers\tbt
php artisan serve
```

Définissez dans `.env` :

```env
APP_URL=http://127.0.0.1:8000
```

Ouvrez http://127.0.0.1:8000/

**3. Notes Windows**

- Exécutez les commandes depuis la racine du projet (`tbt`).
- Si `composer` ou `php` est introuvable, ajoutez leurs répertoires d’installation au **PATH** système.
- Lors du premier lancement, autorisez l’accès au pare-feu Windows pour PHP/MySQL si demandé.
- Pour le développement avec rechargement à chaud, utilisez un second terminal : `npm run dev` (en laissant `php artisan serve` actif dans le premier).

#### macOS

**1. Installer les prérequis**

*Option A — [Homebrew](https://brew.sh/) (recommandé)*

```bash
brew install php@8.2 composer node mysql
brew services start mysql
```

Liez PHP si nécessaire :

```bash
brew link php@8.2
```

*Option B — [Laravel Valet](https://laravel.com/docs/valet)* (idéal pour les domaines `.test`)

```bash
brew install php composer node mysql
composer global require laravel/valet
valet install
```

Vérifiez :

```bash
php -v
composer -V
node -v
mysql --version
```

**2. Créer la base de données**

```bash
mysql -u root -e "CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**3. Lancer l’application**

*Avec Valet (recommandé)*

```bash
cd /chemin/vers/tbt
valet link tbt
```

Définissez dans `.env` :

```env
APP_URL=http://tbt.test
```

Ouvrez http://tbt.test/

*Avec le serveur intégré de PHP*

```bash
cd /chemin/vers/tbt
php artisan serve
```

Définissez dans `.env` :

```env
APP_URL=http://127.0.0.1:8000
```

Ouvrez http://127.0.0.1:8000/

#### Linux

**1. Installer les prérequis**

*Ubuntu / Debian*

```bash
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl unzip curl git mysql-server
```

Installez Composer :

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Installez Node.js 20 (via [NodeSource](https://github.com/nodesource/distributions)) :

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

*Fedora / RHEL*

```bash
sudo dnf install php php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip php-bcmath php-gd
sudo dnf install composer nodejs npm mysql-server
```

Démarrez MySQL et créez la base de données :

```bash
sudo systemctl enable --now mysql    # ou mariadb sur certaines distributions
sudo mysql -e "CREATE DATABASE tbtsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Vérifiez :

```bash
php -v
composer -V
node -v
mysql --version
```

**2. Lancer l’application**

*Avec le serveur intégré de PHP (le plus simple)*

```bash
cd /chemin/vers/tbt
php artisan serve --host=0.0.0.0 --port=8000
```

Définissez dans `.env` :

```env
APP_URL=http://127.0.0.1:8000
```

Ouvrez http://127.0.0.1:8000/

*Avec Nginx ou Apache*

Pointez la racine web (`document root`) vers le répertoire `public/` du projet. Définissez `APP_URL` selon votre hôte virtuel (ex. `http://tbt.local`). Assurez-vous que `storage/` et `bootstrap/cache/` sont inscriptibles par l’utilisateur du serveur web :

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

#### Installation en une commande (toutes plateformes)

Une fois les prérequis installés et la base de données configurée dans `.env` :

```bash
composer setup
php artisan migrate:fresh --seed
php artisan storage:link
```

Le script `composer setup` exécute `composer install`, crée `.env`, génère la clé d’application, migre la base, installe les paquets npm et compile les ressources. Utilisez `migrate:fresh --seed` pour charger les données de démonstration.

#### URLs de l’application

| Page | Chemin |
|------|--------|
| Portail public | `/` |
| Connexion admin | `/login` |
| Tableau de bord admin | `/admin` |

Remplacez l’hôte par votre `APP_URL` (ex. `http://tbt.test/login` ou `http://127.0.0.1:8000/login`).

#### Utilisateurs de démonstration

Mot de passe pour tous : `password`

| E-mail | Rôle | Connexion admin |
|--------|------|-----------------|
| sysadmin@ntb.dj | Administrateur système | Oui |
| mttadmin@ntb.dj | Administrateur MTT | Oui |
| focal@ntb.dj | Point focal national | Oui |
| instadmin@customs.dj | Administrateur institutionnel | Oui |
| agent@customs.dj | Agent institutionnel | Oui |
| nmc@ntb.dj | Membre CNM | Oui |
| analyst@ntb.dj | Analyste | Oui |
| auditor@ntb.dj | Auditeur | Oui |
| operator@trade.dj | Opérateur économique (portail public) | Non — utiliser `/report` |

Connexion avec **e-mail ou nom d’utilisateur** (ex. `focalpoint` / `focal@ntb.dj`).

#### Développement

```bash
# Stack de développement complet (serveur, file d'attente, journaux, rechargement Vite)
composer dev

# Ou exécuter séparément :
php artisan serve
npm run dev
```

Pour des ressources de type production (recommandé sans `npm run dev`) :

```bash
npm run build
```

**Important :** Si vous êtes redirigé vers un mauvais hôte (ex. `auth-service.test`), supprimez `public/hot` et exécutez `npm run build` afin que l’application serve les ressources compilées au lieu d’une URL Vite obsolète.

Commandes Artisan utiles :

```bash
php artisan migrate:fresh --seed   # Réinitialiser la BDD avec les données de démo
php artisan config:clear           # Vider le cache de config après modification de .env
php artisan cache:clear
php artisan test                   # Exécuter les tests PHPUnit
```

#### Authentification et permissions

- Connexion : `POST /api/v1/auth/login` (cookie de session)
- Déconnexion : `POST /api/v1/auth/logout`
- Utilisateur courant : `GET /api/v1/auth/me`
- Rôles et permissions gérés via Spatie (`spatie/laravel-permission`)

L’AMF (authentification multifacteur) est désactivée par défaut en développement local (`NTB_MFA_ENABLED=false` dans `.env`).

#### Dépannage

| Problème | Solution |
|----------|----------|
| Erreur `500` / permissions | `chmod -R ug+rwx storage bootstrap/cache` (Linux/macOS) |
| Page blanche / styles manquants | Exécutez `npm run build` ; supprimez `public/hot` si présent |
| Connexion à la base refusée | Vérifiez que MySQL est démarré et que les identifiants `.env` sont corrects |
| `Vite manifest not found` | Exécutez `npm install && npm run build` |
| Mauvaise URL de redirection | Définissez `APP_URL` dans `.env` selon votre URL locale, puis `php artisan config:clear` |

---

## License / Licence

MIT
