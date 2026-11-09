# Guide de démarrage et de modification du site

Ce projet est une application **Laravel 12** avec une interface **Vue 3**, construite avec **Vite**. Ce guide explique comment la lancer en local et où intervenir pour la modifier.

## Prérequis

Installez les outils suivants :

- PHP **8.2 ou supérieur** et les extensions PHP requises par Laravel ;
- [Composer](https://getcomposer.org/) ;
- Node.js et npm ;
- Git.

## Installation et lancement en local

Depuis un terminal, clonez le dépôt et entrez dans le dossier du projet :

```bash
git clone https://github.com/papito-bit/syllabus.git
cd syllabus
```

Installez les dépendances PHP et JavaScript :

```bash
composer install
npm install
```

Créez le fichier de configuration local, puis générez la clé Laravel :

```bash
cp .env.example .env
php artisan key:generate
```

> Sous Windows, vous pouvez copier `.env.example` vers `.env` avec l’Explorateur de fichiers ou la commande `copy .env.example .env` dans l’invite de commandes.

La configuration d’exemple utilise SQLite. Créez le fichier de base de données s’il n’existe pas encore :

```bash
 touch database/database.sqlite
```

Sous Windows PowerShell, utilisez plutôt :

```powershell
New-Item -ItemType File -Force database/database.sqlite
```

Lancez les migrations pour préparer la base de données :

```bash
php artisan migrate
```

Démarrez ensuite le serveur Laravel et le serveur de développement Vite dans **deux terminaux séparés**, à la racine du projet :

```bash
php artisan serve
```

```bash
npm run dev
```

Ouvrez l’adresse indiquée par `php artisan serve` — généralement <http://127.0.0.1:8000>. Laissez les deux commandes tourner pendant le développement. Vite recharge les ressources frontend lors des modifications.

Pour produire les fichiers frontend optimisés (par exemple avant un déploiement), lancez :

```bash
npm run build
```

## Où modifier le site ?

- **Pages et composants de l’interface :** `resources/js/views/` contient les vues Vue ; `resources/js/App.vue` contient le composant principal et `resources/js/app.js` initialise l’application.
- **Navigation frontend :** regardez la configuration de Vue Router dans `resources/js/` et les vues associées avant d’ajouter ou de modifier une page.
- **Appels à l’API depuis le navigateur :** `resources/js/api.js`.
- **Styles :** `resources/css/` (notamment `resources/css/app.css`).
- **Enveloppe HTML rendue par Laravel :** `resources/views/app.blade.php`.
- **Routes Laravel :** `routes/web.php` pour les pages et `routes/api.php` pour les routes API, si elles existent dans le projet.
- **Logique serveur :** `app/Http/` pour les contrôleurs et requêtes HTTP, `app/Models/` pour les modèles et `app/Services/` pour les services métier.
- **Base de données :** `database/migrations/` pour faire évoluer le schéma, `database/seeders/` pour les données initiales.
- **Images et fichiers publics :** `public/` ; les ressources compilées par Vite sont déclarées dans `vite.config.js`.

Après une modification du frontend, vérifiez le résultat dans le navigateur avec `npm run dev` actif. Après une modification de la base de données, créez une migration puis exécutez `php artisan migrate`.

## Configuration et dépannage

- Les paramètres locaux sont dans `.env`. Ne publiez pas ce fichier et ne partagez pas ses secrets. `.env.example` sert de modèle ; les modifications locales doivent généralement être faites dans `.env`.
- Si Laravel signale une erreur de clé, exécutez `php artisan key:generate`.
- Si la base SQLite est introuvable, vérifiez que `database/database.sqlite` existe et que `DB_CONNECTION=sqlite` est défini dans `.env`.
- Si les dépendances ont changé, relancez `composer install` et/ou `npm install`.
- Pour vider les caches de configuration Laravel en développement :

  ```bash
  php artisan optimize:clear
  ```

- Les tests PHP peuvent être lancés avec :

  ```bash
  php artisan test
  ```
