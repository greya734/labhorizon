# LabHorizon — Documentation API Backend

> **Stack backend :** Laravel 12 · MySQL · PHP 8.2
> **URL locale :** `http://127.0.0.1:8000`
> **Branche :** `fullstack` (dossier `/backend`)
> **Dernière mise à jour :** 2026-08-03

---

## Démarrage rapide

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Configurer la BDD dans .env (MySQL)
php artisan migrate

# Lancer le serveur
php artisan serve        # → http://127.0.0.1:8000

# Lancer le worker de jobs (génération LLM asynchrone)
php artisan queue:work   # dans un second terminal
```

---

## Authentification

L'auth utilise la **session cookie** Laravel Breeze (pas de token JWT/Sanctum).
Frontend et backend étant sur le même domaine, les requêtes protégées doivent inclure le cookie de session.

### Configuration CORS requise

Le CORS est déjà configuré dans `config/cors.php` pour `localhost:5173`.
Pour un autre port, modifier `allowed_origins`.

```php
// config/cors.php
'allowed_origins' => ['http://localhost:5173'],
'supports_credentials' => true,
```

### Headers requis pour toutes les requêtes POST/PUT/DELETE

```http
X-XSRF-TOKEN: {valeur du cookie XSRF-TOKEN}
Content-Type: application/json
Accept: application/json
```

> ⚠️ Le cookie `XSRF-TOKEN` est fourni automatiquement par Laravel à la première requête GET.
> Il faut le lire et le renvoyer dans le header `X-XSRF-TOKEN`.

### Workflow de connexion recommandé (SPA)

```
1. GET  /sanctum/csrf-cookie   → initialise la session + cookie XSRF-TOKEN
2. POST /api/login             → authentifie, retourne l'utilisateur
3. GET  /api/me                → vérifie la session active
4. POST /api/logout            → déconnecte
```

---

## Endpoints Auth (`/api`)

### `POST /api/login`
Connecte un utilisateur existant.

**Body (JSON) :**
```json
{
  "email": "user@example.com",
  "password": "motdepasse"
}
```

**Réponse 200 :**
```json
{
  "user": {
    "id": 1,
    "name": "Jean Dupont",
    "email": "user@example.com",
    "orcid": "0000-0002-1825-0097",
    "orcid_verified": true,
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

**Réponse 401 :**
```json
{ "message": "Identifiants invalides." }
```

---

### `POST /api/logout` 🔒
Déconnecte l'utilisateur courant.

**Réponse 200 :**
```json
{ "message": "Déconnecté." }
```

---

### `GET /api/me` 🔒
Retourne l'utilisateur connecté.

**Réponse 200 :**
```json
{
  "id": 1,
  "name": "Jean Dupont",
  "email": "user@example.com",
  "orcid": "0000-0002-1825-0097",
  "orcid_verified": true
}
```

**Réponse 401 :** si non connecté.

---

## Endpoints Recherches (`/api/recherches`)

### `GET /api/recherches`
Liste publique de toutes les recherches. **Pas d'auth requise.**

**Paramètres query (optionnels) :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| `page` | int | Numéro de page (défaut : 1) |

**Réponse 200 :**
```json
{
  "data": [
    {
      "id": 1,
      "titre": "Titre de la recherche",
      "abstract": "Résumé...",
      "date_production": "2026-01-15",
      "source": "hal",
      "hal_id": "hal-12345678",
      "hal_url": "https://hal.science/hal-12345678v1",
      "pdf_path": "recherches/fichier.pdf",
      "pdf_url": "http://127.0.0.1:8000/files/recherches/fichier.pdf",
      "vulgarisations_count": 2,
      "domaines": [
        { "id": 1, "code": "0.info", "label": "Informatique" }
      ],
      "auteurs": [
        { "id": 1, "nom": "Jean Dupont" }
      ]
    }
  ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

---

### `GET /api/recherches/{id}`
Détail d'une recherche avec ses vulgarisations. **Pas d'auth requise.**

**Réponse 200 :**
```json
{
  "id": 1,
  "titre": "Titre de la recherche",
  "abstract": "Résumé complet...",
  "date_production": "2026-01-15",
  "source": "hal",
  "hal_id": "hal-12345678",
  "hal_url": "https://hal.science/hal-12345678v1",
  "pdf_path": "recherches/fichier.pdf",
  "pdf_url": "http://127.0.0.1:8000/files/recherches/fichier.pdf",
  "domaines": [
    { "id": 1, "code": "0.info", "label": "Informatique" }
  ],
  "auteurs": [
    { "id": 1, "nom": "Jean Dupont" }
  ],
  "structures": [
    { "id": 1, "nom": "CNRS" }
  ],
  "vulgarisations": [
    {
      "id": 1,
      "titre": "Vulgarisation grand public",
      "resume": "Explication simple...",
      "niveau_public": "grand_public",
      "langue": "fr",
      "pdf_path": null,
      "pdf_url": null,
      "created_at": "2026-08-01T10:00:00Z"
    }
  ]
}
```

**Réponse 404 :** si la recherche n'existe pas.

---

### `GET /api/recherches/{id}/vulgarisations`
Liste des vulgarisations d'une recherche. **Pas d'auth requise.**

**Réponse 200 :**
```json
[
  {
    "id": 1,
    "titre": "Vulgarisation grand public",
    "resume": "Explication simple...",
    "niveau_public": "grand_public",
    "langue": "fr",
    "pdf_path": null,
    "pdf_url": null,
    "created_at": "2026-08-01T10:00:00Z"
  }
]
```

---

### `POST /api/recherches` 🔒
Créer une recherche manuellement. **Auth requise.**

**Body (multipart/form-data) :**
| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `titre` | string | ✅ | Titre de la recherche |
| `description` | string | ❌ | Description libre |
| `date_production` | date | ❌ | Format `YYYY-MM-DD` |
| `pdf` | file | ❌ | PDF max 20MB |

**Réponse 201 :**
```json
{
  "id": 5,
  "titre": "Ma recherche",
  "source": "manuel",
  "user_id": 1,
  "created_at": "2026-08-03T10:00:00Z"
}
```

**Réponse 422 :** si validation échoue.

---

### `PUT /api/recherches/{id}` 🔒
Modifier une recherche. **Auth requise + propriétaire uniquement.**

**Body (JSON) :**
```json
{
  "titre": "Nouveau titre",
  "description": "Nouvelle description"
}
```

**Réponse 200 :** objet recherche mis à jour.
**Réponse 403 :** si l'utilisateur n'est pas propriétaire.

---

### `DELETE /api/recherches/{id}` 🔒
Supprimer une recherche. **Auth requise + propriétaire uniquement.**

**Réponse 200 :**
```json
{ "message": "Recherche supprimée." }
```

**Réponse 403 :** si l'utilisateur n'est pas propriétaire.

---

## Fichiers PDF

Les PDFs sont servis publiquement via une route Laravel dédiée :

```
GET http://127.0.0.1:8000/files/recherches/{nom-du-fichier}.pdf
GET http://127.0.0.1:8000/files/vulgarisations/{nom-du-fichier}.pdf
```

> Le champ `pdf_url` est directement disponible dans les réponses JSON — pas besoin de reconstruire l'URL manuellement.

```javascript
// Exemple
const pdfUrl = recherche.pdf_url; // null si pas de PDF
if (pdfUrl) window.open(pdfUrl);
```

---

## Domaines disponibles

Les domaines sont stockés en BDD (`GET /api/recherches` les retourne directement dans chaque recherche).

| Code | Label |
|------|-------|
| `0.info` | Informatique |
| `0.math` | Mathématiques |
| `0.phys` | Physique |
| `0.sdv` | Sciences du Vivant |
| `0.shs` | Sciences Humaines et Sociales |
| `0.spi` | Sciences de l'Ingénieur |
| `0.chim` | Chimie |
| `0.scco` | Sciences Cognitives |
| `0.sde` | Sciences de la Terre |
| `0.stat` | Statistiques |
| `...` | *(et sous-domaines HAL)* |

---

## Niveaux de vulgarisation

| Valeur | Description |
|--------|-------------|
| `grand_public` | Grand public |
| `lyceen` | Lycéen |
| `collegien` | Collégien |

## Langues de vulgarisation

| Valeur | Description |
|--------|-------------|
| `fr` | Français |
| `en` | Anglais |

---

## Rôles utilisateur

| Rôle | Description | Accès |
|------|-------------|-------|
| Visiteur | Non connecté | `GET /api/recherches`, `GET /api/recherches/{id}`, `GET /api/recherches/{id}/vulgarisations` |
| Chercheur | Connecté | Tout ce qui précède + création/modification/suppression de ses recherches |

---

## Import HAL (back-office uniquement)

L'import HAL est géré côté back-office Blade (`/moncompte/hal/import`), pas via l'API.
Les recherches importées sont automatiquement disponibles via `GET /api/recherches`.

---

## Génération de vulgarisation IA (back-office uniquement)

La génération via LLM (LM Studio) est disponible depuis `/moncompte/recherches/{id}/vulgarisations/vulgariser`.
Elle fonctionne de manière **asynchrone** via un job Laravel — lancer `php artisan queue:work` pour traiter les jobs.

---

## Variables d'environnement

```env
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Pacific/Noumea

DB_CONNECTION=mysql
DB_DATABASE=labhorizon
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log          # emails dans storage/logs/laravel.log en dev

LLM_ENABLED=false        # false = mode test sans LM Studio
LLM_URL=http://localhost:1234/v1
LLM_MODEL=local-model

QUEUE_CONNECTION=database
```

---

## Codes d'erreur

| Code | Signification |
|------|--------------|
| `401` | Non authentifié — session absente ou expirée |
| `403` | Interdit — ressource appartenant à un autre utilisateur |
| `404` | Ressource introuvable |
| `422` | Erreur de validation — voir champ `errors` dans la réponse |
| `500` | Erreur serveur interne |

**Format d'erreur 422 :**
```json
{
  "message": "The titre field is required.",
  "errors": {
    "titre": ["The titre field is required."]
  }
}
```
