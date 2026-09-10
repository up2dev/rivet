# Intégration dans un projet Laravel

Exemple complet, de zéro : un projet Laravel neuf avec Rivet installé,
une entité `Article` générée, et un premier appel API authentifié.

## 1. Installation

```bash
composer create-project laravel/laravel my-api
cd my-api
composer require up2dev/rivet
```

Les providers (`LaravelServiceProvider`, `EventServiceProvider`) sont
enregistrés automatiquement via l'auto-discovery de Laravel — rien à
ajouter dans `bootstrap/providers.php`.

## 2. Base de données

```bash
php artisan migrate
```

Les migrations de Rivet (users, tokens, RBAC, 2FA, storage, mailing...)
s'exécutent avec celles de l'application. Aucun fichier de config à
publier : `mergeConfigFrom()` complète la config de l'application avec
les valeurs par défaut de Rivet, sans écraser ce qui existe déjà — pour
personnaliser une clé, il suffit de créer le fichier `config/xxx.php`
correspondant dans l'application et d'y mettre la clé voulue.

## 3. Deux réglages que Rivet ne peut pas faire à ta place

### Le guard Sanctum

Une application Laravel fraîche déclare déjà sa propre clé `guards`
dans `config/auth.php` (pour `web`) — `mergeConfigFrom()` ne fusionne
pas à l'intérieur d'une clé déjà présente, donc le guard `sanctum` de
Rivet n'est jamais injecté automatiquement dans ce cas. À ajouter à la
main, dans `config/auth.php` :

```php
'guards' => [
    'web' => [ /* ... */ ],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

### CORS

Rivet ne gère pas le CORS lui-même — c'est le middleware natif
`HandleCors` de Laravel qui s'en charge, via `config/cors.php`. Si ce
fichier n'existe pas encore dans le projet :

```bash
php artisan config:publish cors
```

(Laravel < 11 : le fichier existe déjà par défaut dans le squelette du
projet.) Puis ajuster `allowed_origins` selon les domaines qui doivent
pouvoir appeler l'API :

```php
// config/cors.php
'paths' => [ 'api/*', 'sanctum/csrf-cookie' ],
'allowed_origins' => [ 'https://mon-frontend.exemple.com' ],
```

## 4. Générer un CRUD complet

En supposant une table `articles` déjà migrée (`id`, `title`, `body`,
`is_published`, `author_id`, `created_at`, `updated_at`) :

```bash
php artisan rivet:make:crud articles
```

Génère `app/Data/Models/Article.php`, `app/Data/Repositories/ArticleRepository.php`,
`app/Http/Controllers/ArticleController.php`, et
`app/Data/Validators/ArticleValidator.php` — avec `$fillable`, `$casts`,
la relation `author()` (déduite de la clé étrangère `author_id`),
`$filters` pré-rempli, et les règles de validation. La commande affiche
un extrait de route en fin d'exécution ; coller ceci dans
`routes/api.php` :

```php
use App\Http\Controllers\ArticleController;

Route::prefix('articles')->controller(ArticleController::class)
    ->middleware([ 'lpfauth:sanctum', 'dataValidation:Article' ])->group(function () {
        Route::get('/', 'list');
        Route::get('{uid}', 'show')->where([ 'uid' => '[0-9]+' ]);
        Route::post('/', 'add');
        Route::put('{uid}', 'edit');
        Route::delete('{uid}', 'remove')->where([ 'uid' => '[0-9]+' ]);
    });
```

## 5. Créer un premier utilisateur et se connecter

```bash
php artisan rightsmanagement --action=create-role \
    --roleuid=ADMIN --rolename=Administrateur --withdefaultpermissions=true

php artisan rightsmanagement --action=create-user \
    --roleuid=ADMIN --email=admin@exemple.com --password=secret \
    --name=Admin --surname=Rivet
```

```bash
curl -X POST http://localhost:8000/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"login":"admin@exemple.com","password":"secret"}'
```

Réponse (2FA non enrôlé, `force_enrollment` désactivé par défaut) :

```json
{
    "meta": { "success": true, "status": 200, "message": "OK" },
    "data": { "token": "1|abc...", "token_type": "bearer", "expires_at": null, "user": { "...": "..." } }
}
```

## 6. Utiliser le token

```bash
curl http://localhost:8000/api/articles \
    -H "Authorization: Bearer 1|abc..." \
    -H "Accept: application/json"

# Filtrer et trier
curl "http://localhost:8000/api/articles?filters=is_published:ist(1)&sort=-created_at" \
    -H "Authorization: Bearer 1|abc..." \
    -H "Accept: application/json"
```

Pour le détail de chaque module, le DSL de filtres complet, et la
référence des variables d'environnement : voir
[`docs/USAGE.md`](USAGE.md).
