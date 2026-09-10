# Guide d'utilisation

## Architecture générale

Rivet fournit un socle pour construire une API REST Laravel par
**convention plutôt que configuration** : un CRUD complet (Modèle,
Repository, Contrôleur, Validator) suit toujours la même correspondance de
noms, et le framework résout tout automatiquement à partir de là.

### Les quatre couches

Pour une entité `Article`, la chaîne de résolution est :

```
Http\Controllers\ArticleController
        │  résout automatiquement (ns_search)
        ▼
Data\Repositories\ArticleRepository
        │  résout automatiquement (ns_search)
        ▼
Data\Models\Article
```

- **Controller** (`Http\Controllers`) : reçoit la requête HTTP, délègue à
  sa Repository. Hérite de `BaseController`, qui fournit déjà les 8
  actions CRUD génériques (voir plus bas) — un contrôleur vide suffit dans
  le cas le plus simple.
- **Repository** (`Data\Repositories`) : construit et exécute les
  requêtes Eloquent. Hérite de `CRUD`, qui fournit `all`/`read`/`create`/
  `update`/`delete` (et leurs variantes en masse), le DSL de filtres/tri,
  et la résolution des relations.
- **Model** (`Data\Models`) : le modèle Eloquent standard, avec
  `$fillable`/`$casts`/relations.
- **Validator** (`Data\Validators`) : les règles de validation Laravel
  (`Illuminate\Support\Facades\Validator` sous le capot), branché via le
  middleware `dataValidation:`.

Cette correspondance de noms est assurée par `ns_search()`
(`src/helpers.php`) : à partir du nom d'une classe, elle substitue le
segment de namespace concerné (`Http\Controllers` → `Data\Repositories`,
`Models` → `Repositories`, etc.) et vérifie que la classe résultante
existe. S'écarter de la convention (ex. `ArticlesController` avec un `s`
qui ne matche pas `Article`) fait lever une `ClassResolutionException`
explicite au lieu d'un crash silencieux plus loin.

### Le moteur CRUD

`CRUD` (`Data\Repositories\CRUD`) est la classe abstraite au cœur du
framework. Elle est composée de quatre traits, chacun responsable d'un
aspect de la construction de requête :

| Trait | Rôle |
|---|---|
| `SortsQuery` | Applique le tri (`?sort=`), y compris sur un champ d'un modèle lié |
| `BuildsFilterConditions` | Le DSL de filtres (`?filters=`) : opérateurs, combinaison ET/OU |
| `ResolvesRelationJoins` | Détecte les relations Eloquent par réflexion et construit les jointures SQL nécessaires |
| `ScopesQueryToUser` | Restreint une requête aux lignes de l'utilisateur courant, avec liste d'exemption |

`CRUD` lui-même ne garde que : la résolution du modèle, les huit verbes
publics (`all`/`read`/`create`/`massCreate`/`update`/`massUpdate`/
`delete`/`massDelete`), l'enregistrement en masse (`defaultRegister`), et
la déclaration des relations NN/ON/NO utilisées lors de l'enregistrement.

### Cycle de vie d'une requête

```
Route (routes/api.php)
   │
   ▼
Middleware 'lpfauth:sanctum'      →  authentifie via Sanctum
   │
   ▼
Middleware 'dataValidation:X'     →  valide le corps de la requête
   │
   ▼
Middleware QueryStringToConfig    →  parse ?filters=/?sort=/?with=/...
   │                                  en un QueryContext propre à cette
   │                                  requête (voir "Le DSL de filtres")
   ▼
XController::action()             →  BaseController ou surchargé
   │
   ▼
XRepository (CRUD)                →  construit et exécute la requête
   │
   ▼
ResponseService::format()         →  enveloppe {meta, data}
```

Chaque réponse suit la même enveloppe :

```json
{
    "meta": {
        "success": true,
        "status": 200,
        "message": "OK",
        "count": 12,
        "pagination": {
            "page": 1, "last": 3, "count": 12, "count_over": 34, "limit": 12,
            "url": "...", "first_url": "...", "last_url": "...",
            "previous_url": null, "next_url": "..."
        }
    },
    "data": [ ... ]
}
```

`pagination` n'apparaît que pour `list` (une collection paginée) ;
`show`/`add`/`edit` renvoient un seul objet dans `data`.

---

## Créer un CRUD complet

Cas visé : **la table existe déjà en base** (schéma conçu/migré avant le
code PHP). Deux façons d'obtenir le même résultat : à la main, ou générée.

### À la main — 4 fichiers, toujours les mêmes

Prenons une table `articles` (`id`, `title`, `body`, `is_published`,
`author_id`, `created_at`, `updated_at`) qui existe déjà.

#### `app/Data/Models/Article.php`

```php
namespace App\Data\Models;

use Rivet\Data\Models\BaseModel;

class Article extends BaseModel
{
    public $log_uid = null; // ou 'Article' pour activer le logging auto

    protected $fillable = [ 'title', 'body', 'is_published', 'author_id' ];

    protected $casts = [ 'is_published' => 'boolean' ];

    // Détectée automatiquement par CRUD (réflexion sur le type de retour) :
    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Data\Models\Auth\User::class);
    }
}
```

#### `app/Data/Repositories/ArticleRepository.php`

```php
namespace App\Data\Repositories;

use Rivet\Data\Repositories\CRUD;

class ArticleRepository extends CRUD
{
    // Colonnes/relations exposées à ?filters= et ?sort=
    protected $filters = [
        'title'  => 'title',
        'author' => 'relation.author',
    ];

    protected function register(array $fields): bool
    {
        return $this->defaultRegister($fields);
    }
}
```

`CRUD::__construct()` résout le modèle par convention (`ArticleRepository`
→ `Article`) : rien d'autre à déclarer pour que `list`/`show`/`add`/`edit`/
`remove` fonctionnent.

#### `app/Http/Controllers/ArticleController.php`

```php
namespace App\Http\Controllers;

use Rivet\Http\Controllers\BaseController;

class ArticleController extends BaseController
{
    // static $AUTH_UNLIMITED = [ 'list', 'show' ]; // si /articles doit
    // rester visible sans filtrage par user_id, par ex. sur une ressource
    // publique
}
```

Vide : `BaseController` fournit déjà les 8 actions génériques, toutes
branchées sur `ArticleRepository` résolue automatiquement.

#### `app/Data/Validators/ArticleValidator.php`

```php
namespace App\Data\Validators;

use Rivet\Services\ValidatorService;

class ArticleValidator extends ValidatorService
{
    protected $rules = [
        'title'        => 'required|string|max:255',
        'body'         => 'required|string',
        'is_published' => 'nullable|boolean',
        'author_id'    => 'required|integer|exists:users,id',
    ];
}
```

#### Route (`routes/api.php` de l'application)

```php
Route::prefix('articles')->controller('ArticleController')
    ->middleware('dataValidation:Article')->group(function () {
        Route::get('/', 'list');
        Route::get('{uid}', 'show')->where([ 'uid' => '[0-9]+' ]);
        Route::post('/', 'add');
        Route::put('{uid}', 'edit');
        Route::delete('{uid}', 'remove')->where([ 'uid' => '[0-9]+' ]);
    });
```

`dataValidation:Article` (sans second paramètre) résout
`App\Data\Validators\ArticleValidator` — le second paramètre (`rivet`
dans les routes internes du package) ne sert que pour les Validators qui
vivent dans Rivet elle-même.

C'est tout : pas de Service, pas de Resource, pas de FormRequest à écrire —
c'est le compromis que fait Rivet contre du Laravel "standard", à
condition de rester dans les clous de la convention de nommage.

### Généré — `php artisan rivet:make:crud`

Le même résultat, à partir de la table existante :

```bash
php artisan rivet:make:crud articles
```

Génère les 4 fichiers ci-dessus dans `app/...`, avec :
- `$fillable` déduit des colonnes (moins `id`/`created_at`/`updated_at`/`deleted_at`)
- `$casts` déduit des types de colonnes (boolean/integer/float/date/datetime/json)
- `SoftDeletes` ajouté automatiquement si une colonne `deleted_at` existe
- **Relations `belongsTo`** générées automatiquement à partir des clés
  étrangères de la table (exacte sur Laravel 11+ via `Schema::getForeignKeys()` ;
  devinée depuis le nom de colonne sur Laravel 10, auquel cas la commande
  affiche un avertissement à vérifier)
- **`$filters` de la Repository pré-rempli** avec les colonnes scalaires et
  les relations détectées
- **Règles de validation** déduites du type SQL, de la nullabilité, de la
  longueur du varchar (`max:`), d'un index unique (`unique:table,colonne`),
  et du nom de colonne (`email` si la colonne s'appelle `email` ou finit
  par `_email`)
- Un extrait de route affiché en fin de commande, à coller manuellement
  dans `routes/api.php` (la commande n'écrit jamais dans ce fichier —
  trop risqué de corrompre un fichier édité à la main)

Options :

```bash
# Nom de classe explicite si la convention de pluriel ne convient pas
php artisan rivet:make:crud people --model=Person

# Génère aussi une migration reflétant la table TELLE QU'ELLE EXISTE
# aujourd'hui (utile pour versionner un schéma déjà en prod)
php artisan rivet:make:crud articles --migration

# Écrase les fichiers déjà générés
php artisan rivet:make:crud articles --force
```

**Ce que la commande ne devine toujours pas** (à compléter à la main) :
- Les relations `hasMany`/`belongsToMany` (seule `belongsTo`, déduite des
  clés étrangères, est générée).
- Les règles de validation métier (`confirmed`, `regex:`, contraintes
  croisées entre champs) — le type SQL et le nom de colonne ne peuvent pas
  tout deviner ; une revue avant mise en prod reste nécessaire (rappelé en
  commentaire dans le fichier généré).
- **Limite connue sur SQLite** : `max:` (issu de la longueur du varchar)
  ne peut pas être déduit pour une table créée via le schema builder
  Laravel standard (`$table->string($nom, $longueur)`) — SQLite n'écrit
  cette longueur nulle part, ni dans son introspection ni dans le DDL. Ça
  fonctionne normalement sur MySQL/Postgres, ou sur une table SQLite créée
  via SQL brut.

---

## Le DSL de filtres et de tri

Toutes les actions `list` (et `read` implicitement, pour les relations à
charger) acceptent ces paramètres de requête :

| Paramètre | Rôle |
|---|---|
| `?filters=` | Conditions de filtrage |
| `?sort=` | Tri, un ou plusieurs champs séparés par des virgules |
| `?with=` | Relations à charger en eager loading |
| `?page=` / `?limit=` | Pagination (défauts dans `config/paginator.php`) |
| `?distinct=1` | Requête `SELECT DISTINCT` |

Ces paramètres peuvent arriver soit dans l'URL (`GET`), soit dans le corps
de la requête via la méthode HTTP `QUERY` ([RFC 10008](https://www.rfc-editor.org/rfc/rfc10008)) —
utile quand la syntaxe de filtre devient trop longue pour une URL. Les deux
sont fusionnés par `QueryStringToConfig` en un `QueryContext` propre à la
requête courante (voir l'architecture plus haut) ; aucun changement de
comportement pour du `GET` classique.

Seules les colonnes/relations listées dans `$filters` d'une Repository
sont exposées à `?filters=`/`?sort=` — un champ absent de `$filters` reste
utilisable en interne (par le code PHP) mais invisible depuis l'API.

### Opérateurs disponibles

Syntaxe : `champ:operateur(valeur)`.

| Opérateur | Signification | Exemple |
|---|---|---|
| `eq` | égal | `status:eq(published)` |
| `neq` | différent | `status:neq(draft)` |
| `gt` / `gte` | supérieur / supérieur ou égal | `price:gte(10)` |
| `lt` / `lte` | inférieur / inférieur ou égal | `created_at:lt(2026-01-01)` |
| `lk` / `nlk` | contient / ne contient pas (sous-chaîne, `LIKE %valeur%`) | `title:lk(guide)` |
| `ilk` / `nilk` | comme `lk`/`nlk`, insensible à la casse | `title:ilk(GUIDE)` |
| `in` | dans une liste (valeurs séparées par virgule) | `id:in(1,2,3)` |
| `btw` | entre deux bornes | `price:btw(10,50)` |
| `n` | est `NULL` | `deleted_at:n(1)` |
| `ist` / `isf` | est vrai / est faux (colonne booléenne) | `is_published:ist(1)` |

Pour `ist`/`isf`, la valeur entre parenthèses est ignorée — seul le nom de
l'opérateur détermine `true`/`false` (`is_published:ist(peu importe)`
fonctionne identiquement).

Préfixer un opérateur par `n` le négocie quand une variante existe :
`nbtw`, `nin`, `nn` (`NOT BETWEEN`, `NOT IN`, `IS NOT NULL`).

### Combiner plusieurs conditions

Il n'existe **pas** de "ET implicite" en séparant deux conditions par un
espace — seule la syntaxe bitwise explicite fonctionne :

```
# ET
?filters=title:lk(guide)|n|is_published:ist(1)

# OU
?filters=title:lk(guide)|u|title:lk(tutorial)
```

### Filtrer/trier sur une relation

Un filtre ou un tri peut cibler un champ d'un modèle lié, en préfixant par
la clé déclarée dans `$filters` (ici `author`, qui pointe vers
`relation.author`) :

```
?filters=author.name:eq(Jane)
?sort=author.name
```

`CRUD` détecte la relation par réflexion (type de retour de la méthode
`belongsTo`/`hasMany`/`belongsToMany` sur le Model) et construit la
jointure SQL nécessaire à la volée — aucune déclaration supplémentaire
n'est nécessaire au-delà de l'entrée dans `$filters`.

### Entrée invalide

Une clé de filtre inconnue, un opérateur inconnu, ou une valeur `btw`/`in`
malformée lève une `InvalidQueryFilterException` plutôt que de construire
silencieusement une requête incorrecte ou vide.

### Portée par utilisateur

Si le modèle a une colonne `user_id` (configurable via `config/crud.php`,
clés `user_relation`/`user_fk`) et qu'un utilisateur est authentifié,
`CRUD` restreint automatiquement `list`/`show` aux lignes de cet
utilisateur (plus les lignes où `user_id` est `NULL`). Pour exempter une
action de cette restriction (ex. une ressource publique) :

```php
class ArticleController extends BaseController
{
    static $AUTH_UNLIMITED = [ 'list', 'show' ];
}
```

---

## Authentification et RBAC

### Authentification (Laravel Sanctum)

Le middleware `lpfauth:sanctum` (alias de `Authenticate`) protège une
route par token Sanctum. Endpoints fournis par `AuthController` :

| Route | Rôle |
|---|---|
| `POST /auth/login` | Connexion, renvoie un token |
| `GET /auth/refresh` | Renouvelle le token courant |
| `GET /auth/logout` | Révoque le token courant |

`AuthController::login` compare la casse du login selon
`config('auth.login_case_sensitive')` (clé de `config/auth.php`).

### Authentification à deux facteurs (2FA)

Native à Rivet — TOTP (compatible Google Authenticator/Authy) et code à
usage unique par email, sans dépendance externe autre que
`pragmarx/google2fa` pour l'algorithme TOTP lui-même.

#### Le point d'extension : `LoginChallenger`

`AuthController::login()` ne connaît pas le 2FA directement. Juste avant
d'émettre le token Sanctum, une fois les identifiants validés, il vérifie
si quelque chose est lié à l'interface `Rivet\Contracts\Auth\LoginChallenger`
dans le conteneur, et lui délègue la décision :

```php
interface LoginChallenger
{
    public function challenge(User $user, Request $request): ?JsonResponse;
}
```

Retourner une réponse court-circuite `login()` entièrement (le client
reçoit cette réponse telle quelle, aucun token n'est émis) ; retourner
`null` laisse `login()` continuer normalement. `TwoFactorLoginChallenger`
est l'implémentation native, liée par défaut dans
`LaravelServiceProvider::register()` — une application hôte peut
remplacer cette liaison par la sienne si elle a besoin d'un comportement
de connexion entièrement différent.

#### Configuration (`config/two_factor.php`)

| Clé | Rôle |
|---|---|
| `enabled` | Interrupteur général (défaut : `true`) |
| `issuer` | Nom affiché dans l'appli d'authentification |
| `force_enrollment` | Si `true`, un utilisateur sans méthode confirmée est bloqué à la connexion derrière un enrôlement obligatoire plutôt que laissé passer sans protection |
| `bypass_permission` | Uid de la permission d'exemption (défaut : `RIVET_BYPASS_2FA`) — contourne le 2FA entièrement, y compris l'enrôlement forcé |
| `pending_token_ttl` | Durée de vie (minutes) du jeton intermédiaire |
| `email_code_ttl` | Durée de vie (minutes) d'un code envoyé par email |

#### Exempter un rôle précis du 2FA

`isExempt()` vérifie les permissions **du rôle** de l'utilisateur, pas
l'utilisateur directement — exempter un rôle entier revient donc à lui
attacher la permission `bypass_permission` (`RIVET_BYPASS_2FA` par
défaut). Un utilisateur avec plusieurs rôles est exempté dès qu'un seul
d'entre eux porte cette permission.

```php
use Rivet\Data\Models\Auth\Role;
use Rivet\Data\Models\Auth\Permission;
use Rivet\Data\Models\Dictionaries\Types\PermissionType;

$type = PermissionType::firstOrCreate(
    [ 'uid' => 'ENDPOINT' ], [ 'name' => 'Endpoint' ]
);
$permission = Permission::firstOrCreate(
    [ 'uid' => 'RIVET_BYPASS_2FA' ],
    [ 'name' => 'RIVET_BYPASS_2FA', 'permission_type_id' => $type->id ]
);

// Le rôle "SUPPORT" n'est jamais soumis au 2FA
Role::where('uid', 'SUPPORT')->first()->permissions()->attach($permission->id);
```

Pas d'équivalent en ligne de commande pour un rôle déjà existant : la
commande `rightsmanagement --action=create-role --withdefaultpermissions=true`
ne s'applique qu'à la création. Pour un rôle existant, passer par
Eloquent (ci-dessus) ou une migration/seeder.

#### Ce qui se passe à la connexion

Après validation des identifiants (login/mot de passe corrects, compte
actif) :

1. **Utilisateur exempté** (`bypass_permission`) → connexion normale,
   token Sanctum émis directement, aucune vérification.
2. **Méthode déjà confirmée** (TOTP ou email) → un **jeton intermédiaire**
   `intent: "verify"` est renvoyé à la place du token, avec la liste des
   méthodes disponibles. Pas de token tant que le code n'est pas validé.
3. **Rien de confirmé, `force_enrollment` actif** → un jeton intermédiaire
   `intent: "enroll"` est renvoyé — l'utilisateur doit enrôler une méthode
   avant d'obtenir un token.
4. **Rien de confirmé, `force_enrollment` inactif** (défaut) → connexion
   normale, comportement identique à un projet sans 2FA.

```json
{
    "data": {
        "pending_token": "a1b2c3...",
        "intent": "verify",
        "methods": [ "totp" ]
    }
}
```

#### Enrôlement et vérification

Les routes `/auth/2fa/*` sont accessibles de deux façons selon le
contexte : un utilisateur déjà connecté qui active le 2FA depuis ses
réglages (authentification Sanctum normale), ou un utilisateur en cours
de connexion qui porte un `pending_token` d'enrôlement forcé (aucune
session encore établie). `TwoFactorController::_targetUser()` résout
l'un ou l'autre automatiquement — aucune route à dupliquer.

| Route | Rôle |
|---|---|
| `POST /auth/2fa/totp/setup` | Génère un secret + l'URI `otpauth://` (à afficher en QR côté client — Rivet ne génère jamais l'image lui-même) |
| `POST /auth/2fa/totp/confirm` | Confirme avec un code |
| `POST /auth/2fa/email/enable` | Active la méthode email, envoie un premier code |
| `POST /auth/2fa/email/confirm` | Confirme avec le code reçu |
| `POST /auth/2fa/email/request-code` | (Re)envoie un code — utilisé pour vérifier une méthode email déjà confirmée à la connexion |
| `POST /auth/2fa/verify` | Vérifie un code (`pending_token` + `method` + `code`) et émet le vrai token Sanctum |
| `DELETE /auth/2fa/{method}` | Désactive une méthode — **exige une authentification Sanctum complète**, jamais accessible via un simple jeton intermédiaire |

Confirmer une méthode alors qu'on porte un jeton d'enrôlement forcé
(`intent: "enroll"`) **complète directement la connexion** : le token
Sanctum est émis dans la même réponse, sans étape de vérification
séparée — confirmer la méthode EST la preuve attendue.

Un jeton intermédiaire est à usage unique : consommé après une
vérification réussie, il ne peut pas être rejoué.

### Mot de passe oublié / réinitialisation

`PasswordController` fournit le flux complet : `forgot` (envoie un email
avec un token), `mailRenew` (lien cliqué dans l'email, vérifie le token),
`renew` (soumet le nouveau mot de passe). Un token de réinitialisation est
supprimé après un renouvellement réussi — il ne peut pas être rejoué.

### Permissions par route

`Authenticate::handle()` vérifie, pour chaque route protégée, si une
`Permission` existe pour l'uid de cette route (calculé par `ra_to_uid()`
à partir du nom du contrôleur/de l'action). Si aucune permission n'est
enregistrée pour cette route, l'accès est ouvert à tout utilisateur
authentifié ; si une permission existe, l'utilisateur doit avoir un rôle
qui la porte.

### Gestion en ligne de commande

```bash
# Créer un rôle, avec les permissions par défaut pour gérer les rôles
php artisan rightsmanagement --action=create-role --roleuid=ADMIN --rolename=Administrateur --withdefaultpermissions=true

# Créer un utilisateur et le rattacher à un rôle
php artisan rightsmanagement --action=create-user --roleuid=ADMIN --email=a@b.c --password=secret --name=Jane --surname=Doe

# Créer plusieurs utilisateurs depuis un CSV
php artisan rightsmanagement --action=create-users --file=/chemin/vers/users.csv

# Enregistrer une permission par route (scanne les routes existantes)
php artisan rightsmanagement --action=create-permissions
```

---

## Stockage de fichiers (Storage)

`Data\Models\Storage\File`/`Media` gèrent l'upload par blocs (chunks) et
le stockage physique. `FileController::stream()` sert un fichier
(affichage ou téléchargement) avec vérification des dimensions/mimetype
déclarées sur le `Media` associé. La suppression d'un `File` nettoie
automatiquement le fichier physique correspondant (via `FileTrait`, un
événement `deleting`).

---

## Mailing

`Mail\BaseMail` est la classe de base pour tous les emails du package —
lit `from`/`app.name` via `config()`, jamais `env()` directement (pour
rester correct sous `config:cache`).

Chaque email envoyé via `Mail::send()` est journalisé dans `Sendmail`
(`from`/`to`/`subject`/`content`/`sent_at`) par deux listeners
(`MessageSendingListener`/`MessageSentListener`) — c'est le mécanisme
utilisé pour les emails transactionnels (validation de compte,
réinitialisation de mot de passe).

Pour un envoi différé plutôt qu'immédiat, une ligne `Sendmail` peut être
créée directement avec `sent_at` laissé à `NULL` et un `content` au format
`['template' => '...', 'attributes' => [...]]`. Une classe hôte étendant
`SendmailService` (abstraite, à cadencer via le `schedule()` de
l'application) va chercher les lignes en attente et les met en file via
`ProcessSendmail` (un job par email, exécuté de façon asynchrone par un
worker de queue).

### Templates d'email

Un layout unique, en marque blanche (`resources/views/emails/layout.blade.php`,
généré avec MJML), habille les 7 emails transactionnels du package. Une
seule couleur d'accent à personnaliser :

```
MAIL_BRAND_COLOR=#4f46e5
```

(`config('mail.brand_color')`, valeur par défaut si non définie.)

Les emails avec un lien cliquable (reset de mot de passe, création de
mot de passe, validation d'email) pointent vers une page **frontend**,
pas directement vers une route de l'API — construite à partir de
`config('app.frontend_url')` (`FRONTEND_URL` en env, retombe sur
`APP_URL` si absent). Trois routes à implémenter côté frontend :

| Email | Route frontend attendue |
|---|---|
| Mot de passe oublié | `/reset-password/{token}` |
| Création du premier mot de passe | `/create-password/{token}` |
| Validation d'email | `/verify-email/{token}` |

Chaque template n'écrit que `@section('title')`/`@section('body')` — le
layout gère l'en-tête, le pied de page, et la compatibilité Outlook/mobile.
Pour personnaliser le contenu d'un email, surcharger la vue correspondante
dans `resources/views/vendor/rivet/emails/...` de l'application (convention
standard Laravel de surcharge des vues d'un package).

---

## Autres modules

| Module | Rôle |
|---|---|
| `Data\Models\Log\Log` | Journal des événements sur un modèle (create/update/delete), stocké sur une connexion MongoDB dédiée |
| `Data\Models\Dictionaries\Taxonomy`/`TaxonomyValue` | Listes de valeurs contrôlées (dictionnaires), avec CRUD standard |
| `Data\Models\CRONTask` | Table de déclaration de tâches planifiées (`command`/`minute`/`hour`/`day`/`month`/`year`) — **aucun ordonnanceur ne lit cette table dans le package actuel** ; à câbler soi-même (ex. une commande qui interroge `CRONTask` et appelle `Schedule::command()`) si ce module doit réellement déclencher des tâches |
| `Data\Models\DBVersion` | Exécute et journalise un script SQL à la création de la ligne (voir le mécanisme `creating` dans `DBVersionTrait`) — pensé pour des migrations de données pilotées par une table plutôt que par des fichiers de migration |

Chacun suit la même convention Controller → Repository → Model que le
reste du package ; se référer au code source pour le détail de leurs
Repositories/Validators respectifs.

---

## Validators

`Data\Validators\Validator` (implémenté concrètement par
`ValidatorService`) enveloppe `Illuminate\Support\Facades\Validator` :
déclarer `$rules` suffit, le reste (validation, récupération des erreurs)
est fourni par la classe de base.

```php
class ArticleValidator extends ValidatorService
{
    protected $rules = [
        'title' => 'required|string|max:255',
    ];
}
```

Branché sur une route via le middleware `dataValidation:X` (voir
"Créer un CRUD complet" ci-dessus) — appelé automatiquement pour toute
méthode HTTP mutante (`POST`/`PUT`/`PATCH`), jamais pour `GET`/`DELETE`.

---

## Référence des middlewares

| Alias | Classe | Rôle |
|---|---|---|
| `lpfauth:{guard}` | `Authenticate` | Authentifie via le guard donné (typiquement `sanctum`), vérifie les permissions par route |
| `dataValidation:{Validator},{namespace}` | `DataValidate` | Valide le corps de la requête via `{namespace}\Data\Validators\{Validator}Validator` (`namespace` par défaut : `app`) |
| (global, sans alias) | `QueryStringToConfig` | Parse `?filters=`/`?sort=`/`?with=`/`?page=`/`?limit=`/`?distinct=` en `QueryContext` |

---

## Variables d'environnement

Toutes les variables lues par Rivet passent par un fichier `config/*.php`
(jamais par un appel `env()` direct dans le code métier — voir
`docs/CHANGELOG.md` pour l'historique des bugs que ça causait sous
`php artisan config:cache`). Liste complète, par domaine :

### Application

| Variable | Défaut | Clé config |
|---|---|---|
| `APP_TIMEZONE` | `Europe/Paris` | `app.timezone` |
| `APP_LOCALE` | `fr` | `app.locale` |
| `APP_LOCALE_FALLBACK` | `en` | `app.fallback_locale` |
| `APP_LOCALE_FAKER` | `fr_FR` | `app.faker_locale` |
| `FRONTEND_URL` | `APP_URL` | `app.frontend_url` — base des liens cliquables dans les emails (reset mot de passe, validation d'email) |

CORS n'est **pas** géré par Rivet — c'est le middleware natif `HandleCors`
de Laravel qui s'en charge (`config/cors.php`, chemins couverts par
défaut : `api/*`). Ça se configure au niveau de l'application, pas de
Rivet :

```php
// config/cors.php de l'application
'paths' => [ 'api/*', 'sanctum/csrf-cookie' ],
'allowed_origins' => [ 'https://mon-frontend.exemple.com' ],
```

Si le fichier n'existe pas encore dans le projet :
`php artisan config:publish cors` (Laravel 11+) — sur une version plus
ancienne, le fichier est déjà présent par défaut dans le squelette
Laravel. Voir [`docs/INTEGRATION.md`](INTEGRATION.md) pour l'exemple complet.

### Authentification

| Variable | Défaut | Clé config |
|---|---|---|
| `IS_LOGIN_KS` | `false` | `auth.login_case_sensitive` |
| `AUTH_MAIL_LOCK` | `false` | `auth.is_mail_locked` — email vérifié obligatoire pour se connecter |
| `AUTH_MAIL_RELOCK` | `false` | `auth.is_mail_relocked` — revérification obligatoire après changement d'email |
| `TOKENS_PREFIX` | *(vide)* | `auth.token_prefix` — préfixe des tokens générés (reset mot de passe, etc.) |
| `PWD_TOKEN_VALIDITY` | `60` | `auth.pwd_token_validity` — durée de vie (minutes) d'un token de réinitialisation |
| `USER_MODEL` | `Rivet\Data\Models\Auth\User` | `crud.user_model` |
| `USER_RELATION` | `user` | `crud.user_relation` — nom de la relation vers l'utilisateur propriétaire |
| `USER_FK` | `user_id` | `crud.user_fk` — colonne de clé étrangère pour la portée par utilisateur |

### Sanctum

| Variable | Défaut | Clé config |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | domaines locaux standards | `sanctum.stateful` |
| `SANCTUM_TOKEN_EXPIRATION` | *(jamais)* | `sanctum.expiration` — minutes avant expiration d'un token |
| `SANCTUM_TOKEN_EXPIRATION_OVERRIDE` | *(jamais)* | `sanctum.expiration_override` |

### Authentification à deux facteurs

Voir la table dédiée dans la section "Authentification à deux facteurs"
plus haut (`TWO_FACTOR_ENABLED`, `TWO_FACTOR_ISSUER`,
`TWO_FACTOR_FORCE_ENROLLMENT`, `TWO_FACTOR_BYPASS_PERMISSION`,
`TWO_FACTOR_PENDING_TOKEN_TTL`, `TWO_FACTOR_EMAIL_CODE_TTL`).

### Mailing

| Variable | Défaut | Clé config |
|---|---|---|
| `MAIL_FROM_ADDRESS` | `hello@example.com` | `mail.from.address` |
| `MAIL_FROM_NAME` | `Example` | `mail.from.name` |
| `MAIL_EMAIL_CHECKED` | `true` | `mail.is_mail_checked` — envoie un email de confirmation quand l'adresse est vérifiée |
| `MAIL_FORCE_PASSWORD_CREATION` | `true` | `mail.is_forcing_password_creation` — envoie un lien de création de mot de passe si absent |
| `MAIL_CONFIRM_PASSWORD` | `true` | `mail.is_confirming_password` |
| `SENDMAIL_MAX_SENT` | `100` | `sendmail.max_sent` — nombre d'emails traités par appel de `SendmailService::send()` |
| `MAIL_BRAND_COLOR` | `#4f46e5` | `mail.brand_color` — couleur d'accent du layout d'email en marque blanche |

### Logs

| Variable | Défaut | Clé config |
|---|---|---|
| `IS_LOGGED` | `false` | `logs.is_logged` — active le journal `Log` (MongoDB) sur les événements de modèle |
| `LOGS_SQL_DURATION` | `false` | `logs.sql_duration` — active `DB::enableQueryLog()`, coûteux en mémoire sur une requête avec beaucoup de requêtes SQL, à n'activer qu'au besoin |

### Storage

| Variable | Défaut | Clé config |
|---|---|---|
| `STORAGE_CHUNK_SIZE` | `1024` | `storage.chunk_size` — taille max d'un chunk uploadé (Ko) |
| `STORAGE_MIMETYPES_SRC` | URL Apache par défaut | `storage.mimetypes_src` |
| `STORAGE_DISK` | `public` | `storage.disk` |
| `STORAGE_DIR` | `rivet_storage` | `storage.dir` |
| `STORAGE_MAX_VARIATIONS` | `10` | `storage.max_variations` — nombre max de variations d'image conservées |

### Pagination

| Variable | Défaut | Clé config |
|---|---|---|
| `PAGINATOR_LIMIT` | `12` | `paginator.limit` |
| `PAGINATOR_PAGE` | `1` | `paginator.page` |

---

## Convention de nommage (rappel)

Tout repose sur `ns_search()` (`src/helpers.php`) : un `XController` dans
`Http\Controllers` résout automatiquement `XRepository` dans
`Data\Repositories`, qui résout `X` dans `Data\Models`. Respecter cette
correspondance de noms est ce qui rend tout le reste automatique — s'en
écarter (ex. `ArticlesController` avec un `s`) casse la résolution et lève
une `ClassResolutionException` explicite plutôt qu'un crash silencieux
plus loin.
