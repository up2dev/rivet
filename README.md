# Rivet

Un socle Laravel pour construire une API REST par **convention plutôt que
configuration** : un CRUD complet (Modèle, Repository, Contrôleur,
Validator) suit toujours la même correspondance de noms, et le framework
résout et branche tout automatiquement à partir de là.

```bash
composer require up2dev/rivet
php artisan migrate
```

Pas de Service à écrire, pas de Resource, pas de FormRequest — dans le
cas courant, un contrôleur vide et une Repository avec `$filters`
suffisent pour obtenir `list`/`show`/`add`/`edit`/`remove` (et leurs
variantes en masse), un DSL de filtres/tri complet, et l'authentification.

## Pourquoi

- **Zéro boilerplate pour le cas courant** — `php artisan rivet:make:crud`
  génère un CRUD entier à partir d'une table existante : `$fillable`,
  `$casts`, relations `belongsTo` (déduites des clés étrangères),
  `$filters`, et règles de validation, tous inférés du schéma.
- **Un DSL de filtres/tri réellement expressif** — opérateurs de
  comparaison, ensembles, booléens, combinaison `ET`/`OU` explicite, et
  filtrage/tri sur un champ d'un modèle lié, sans écrire une seule ligne
  de query builder.
- **Authentification complète dès l'installation** — Sanctum, RBAC par
  permission de route, et une authentification à deux facteurs (TOTP +
  email) déjà câblée, avec un point d'extension propre si un besoin de
  connexion différent se présente.
- **Rien de cassé sous `config:cache`** — chaque variable
  d'environnement passe par un fichier de config, jamais par un appel
  `env()` direct dans le code métier.

## Modules

| Module | Ce qu'il fait |
|---|---|
| **CRUD engine** (`Data\Repositories\CRUD`) | Résolution par convention (Controller → Repository → Model), 8 actions génériques, DSL de filtres/tri avec support des relations |
| **Auth** (`Http\Controllers\Auth`) | Connexion/déconnexion Sanctum, mot de passe oublié, permissions par route |
| **Two-Factor** (`Services\TwoFactorLoginChallenger`) | TOTP + email OTP natifs, enrôlement forcé optionnel, permission d'exemption, point d'extension `LoginChallenger` |
| **RBAC** (`Data\Models\Auth\Role`/`Permission`) | Rôles et permissions, commande `rightsmanagement` pour la gestion CLI |
| **Storage** (`Data\Models\Storage`) | Upload par blocs, `File`/`Media`, nettoyage automatique à la suppression |
| **Mailing** (`Mail\BaseMail`) | Base d'emails lisant sa config via `config()`, file d'attente asynchrone (`SendmailService`/`ProcessSendmail`) |
| **Log** (`Data\Models\Log`) | Journal des événements de modèle, sur une connexion MongoDB dédiée |
| **Dictionaries** (`Data\Models\Dictionaries`) | Listes de valeurs contrôlées (`Taxonomy`/`TaxonomyValue`) |
| **DBVersion** | Exécute et journalise un script SQL à la création de la ligne — migrations de données pilotées par une table |
| **CRONTask** | Table de déclaration de tâches planifiées (pas d'ordonnanceur intégré — à câbler soi-même) |
| **`rivet:make:crud`** (`Console\Commands`) | Génère Model/Repository/Controller/Validator (+ migration optionnelle) à partir d'une table existante |

Détail complet de chaque module, exemples, et référence des variables
d'environnement : [`docs/USAGE.md`](docs/USAGE.md).

## Documentation

| Fichier | Contenu |
|---|---|
| [`docs/USAGE.md`](docs/USAGE.md) | Architecture, guide d'utilisation complet, référence des variables d'environnement |
| [`docs/INTEGRATION.md`](docs/INTEGRATION.md) | Exemple d'intégration complet dans un projet Laravel, de zéro |
| [`docs/TESTING.md`](docs/TESTING.md) | Lancer et comprendre la suite de tests |
| [`docs/DOCKER.md`](docs/DOCKER.md) | Lancer les tests via Docker, sans PHP local |
| [`docs/CHANGELOG.md`](docs/CHANGELOG.md) | Historique des versions |

## Développement

```bash
docker compose build && docker compose run --rm tests
```

Voir [`docs/DOCKER.md`](docs/DOCKER.md) et [`docs/TESTING.md`](docs/TESTING.md)
pour le détail.

## Licence

MIT.
