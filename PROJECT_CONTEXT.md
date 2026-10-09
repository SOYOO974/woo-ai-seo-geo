# Contexte Projet — WooCommerce AI SEO & GEO Optimization (WASGO)

## 📌 1. Vue d'Ensemble & Identité

- **Nom du Plugin** : WooCommerce AI SEO & GEO Optimization (WASGO)
- **Slug GitHub** : [`SOYOO974/woo-ai-seo-geo`](https://github.com/SOYOO974/woo-ai-seo-geo)
- **Version actuelle** : `4.5`
- **Auteur** : Soyoo.re (`https://www.soyoo.re/`)
- **Text Domain** : `wasgo`
- **Dépendance Requise** : WooCommerce (`woocommerce/woocommerce.php`) et Action Scheduler (inclus nativement dans WooCommerce).
- **Sites en Production** : [`comptoirdecambaie.re`](https://comptoirdecambaie.re/) (et autres boutiques WooCommerce gérées par SOYOO).

### Mission Principale
Fournir une suite d'automatisation IA native pour WooCommerce permettant de :
1. **Régénérer et sublimer les images produits & galeries** via architecture multi-providers (**Magnific AI Nano Banana Pro**, **Higgsfield AI**, ou **Google Gemini Vision**) avec repli automatique vers Gemini en cas d'erreur ou d'épuisement de quota.
2. **Générer le contenu textuel et métadonnées SEO** (Description courte, Description longue, Meta Title, Meta Description) pour les **Produits** et les **Catégories de Produits** via OpenAI GPT-4o.
3. **Valider automatiquement le contenu produit contre les hallucinations** via un fact-checker IA strict à double passe (Génération -> Validation -> Retentative ou File de révision manuelle).
4. **Synchroniser nativement les balises SEO** avec les 3 extensions majeures du marché : **The SEO Framework (TSF)**, **Rank Math SEO**, et **Yoast SEO**.
5. **Traiter de volumineux catalogues en arrière-plan** de façon résiliente via **Action Scheduler** (mode 'smart' ou forcé).

---

## 🏗️ 2. Architecture Technique & Cartographie des Fichiers

```text
woo-ai-seo-geo/
├── woocommerce-ai-seo-geo-optimization.php # Point d'entrée, vérification WooCommerce, chargement PUC & classes
├── plugin-update-checker/                  # YahnisElsts/plugin-update-checker v5.6 (mises à jour GitHub automatiques)
├── assets/
│   ├── css/
│   │   └── admin.css                       # Styles modernes d'administration (UI tabs, badges, cards, progress bars)
│   └── js/
│       └── admin.js                        # Contrôleurs AJAX, polling Action Scheduler, prévisualisation, review queue
├── includes/
│   ├── class-wasgo-settings.php            # Déclaration et sanitization de toutes les options WordPress
│   ├── class-wasgo-logs.php                # Logs résilients WC_Logger + purge batch des anciens posts CPT résiduels
│   ├── class-wasgo-image-generator.php     # Délégation multi-providers, auto-fallback Gemini, WebP & swaps d'attachements
│   ├── class-wasgo-batch-processor.php     # Traitement par lots Action Scheduler pour images principales & galeries
│   ├── class-wasgo-ajax.php                # Endpoints AJAX d'images (start, stop, progress, regen unitaire, purge backup)
│   ├── class-wasgo-content-utility.php     # Extraction métadonnées produits (attributs, specs, catégories, taxonomies)
│   ├── class-wasgo-content-generator.php   # Générateur OpenAI GPT-4o avec JSON Schema / Structured Outputs
│   ├── class-wasgo-content-validator.php   # Validateur anti-hallucination GPT-4o (fact-checking + vision contextuelle)
│   ├── class-wasgo-content-orchestrator.php# Orchestration cycle de vie (génération, retry, commit DB, review queue)
│   ├── class-wasgo-content-batch-processor.php # Batch Action Scheduler pour le contenu (produits + catégories, mode 'smart')
│   ├── class-wasgo-content-ajax.php        # Endpoints AJAX de contenu (bulk, unitaire, preview live, approbation review)
│   ├── class-wasgo-meta-boxes.php          # Métaboxes d'édition Produit et champs de taxonomie Catégorie Produit
│   ├── class-wasgo-admin-menu.php          # Pages du panneau d'administration (Dashboard, Images, Contenu, Réglages)
│   └── providers/                          # [NOUVEAU v4.5] Couche d'abstraction Multi-Providers IA Image
│       ├── interface-wasgo-image-provider.php       # Interface commune WASGO_Image_Provider_Interface
│       ├── class-wasgo-provider-gemini.php          # Adaptateur Google Gemini Vision (direct & fallback)
│       ├── class-wasgo-provider-magnific.php        # Adaptateur Magnific API (JSON-RPC 2.0 / imagen-nano-banana-2)
│       ├── class-wasgo-provider-higgsfield.php      # Adaptateur Higgsfield AI (Studio packshot avec polling)
│       └── class-wasgo-image-provider-factory.php   # Factory et sélecteur de provider actif
├── AGENTS.md                               # Directives de gouvernance et protocole pour agents Antigravity
├── PROJECT_CONTEXT.md                      # Ce fichier (spécification complète et architecture)
└── .gitignore                              # Exclusion builds .zip et artefacts temporaires
```

---

## ⚙️ 3. Fonctionnement des Modules Métier

### Module A : Traitement & Amélioration d'Images (Multi-Providers & Auto-Fallback)
- **Architecture Multi-Providers** :
  - **Magnific AI** : Connexion JSON-RPC 2.0 (`https://mcp.magnific.com`) avec upload binaire direct S3 presigné (`creations_request_upload` -> PUT -> `creations_finalize_upload`), génération via le modèle studio packshot **Nano Banana Pro** (`imagen-nano-banana-2`) sans risque de blocage copyright, et polling de complétion synchrone/asynchrone (`creations_wait`).
  - **Higgsfield AI** : Soumission directe (`https://api.higgsfield.ai/v2/{model}`) avec prompt et image encodée data URI, support de modèles packshots studio (`higgsfield-ai/soul`) et polling du statut (`/requests/{id}/status`).
  - **Google Gemini Vision** : Intégration directe via l'API v1beta (`gemini-3.1-flash-image-preview` ou modèle configurable).
- **Mécanisme de Repli Automatique (Auto-Fallback Gemini)** :
  - Si le fournisseur principal (Magnific ou Higgsfield) échoue (timeout, quota insuffisant, clé expirée), WASGO consigne un avertissement dans `WC_Logger` et rebascule automatiquement sur Google Gemini Vision pour ne jamais bloquer la file de production.
- **Workflow d'une image** :
  1. Récupération de l'image source (originale ou backup précédent via meta `original_wasgo_image_id` / `original_ebp_image_url`).
  2. Envoi des données binaires et du prompt (`[PRODUCT_TITLE]` substitué) au provider actif avec auto-fallback.
  3. Réception du flux image (binaire ou base64 décodé).
  4. Si option `wasgo_auto_compress` active : redimensionnement (respect de `wasgo_max_height`), compression qualité (`wasgo_image_quality`) et conversion native en **WebP** via `wp_get_image_editor()`.
  5. Insertion du nouvel attachement WordPress et remplacement de la vignette produit (`set_post_thumbnail`).
  6. Marquage `prevent_ebp_image_sync = 1` pour éviter tout écrasement par les synchronisations ERP (ex: EBP).
  7. Traçabilité dans les logs : `Image successfully generated and optimized via <Nom du Provider> (Attachment #ID)`.
- **Galeries Produits** : Support complet des galeries via `_product_image_gallery` avec tâches unitaires indépendantes dans Action Scheduler (`wasgo_process_gallery_item`).

### Module B : Génération de Contenu & Métadonnées SEO (GPT-4o)
- **Moteur IA** : OpenAI `gpt-4o-2024-08-06` avec mode `response_format => json_schema` strict.
- **Typologies générées** :
  - **Produit** : `short` (Description courte / `post_excerpt`), `long` (Description longue / `post_content`), `title` (SEO Meta Title), `desc` (SEO Meta Description).
  - **Catégorie** : `cat_title` (Meta Title de la catégorie), `cat_desc` (Meta Description de la catégorie).
- **Contexte injecté dans le prompt** :
  - Identité produit (Titre, Catégories, Attributs WooCommerce mis en forme).
  - Spécifications vérifiées sélectionnées (Custom fields dynamiques scannés via `WASGO_Content_Utility::get_available_specs()`).
  - Contexte visuel : Envoi de l'image en multimodal (`image_url`) si `wasgo_content_image_required` est actif.
  - Langue cible (`wasgo_content_language`, ex: French, English).

### Module C : Double Passe de Validation Anti-Hallucination
1. **Passe 1 (Génération)** : GPT-4o produit le JSON structuré avec un indice de confiance initial (`confidence_score`), les ambiguïtés relevées (`ambiguity_flags`) et les hypothèses formulées (`assumptions_made`).
2. **Passe 2 (Fact-Checking)** : GPT-4o (temperature 0) analyse le texte généré face aux specs d'origine, à la catégorie, et à la photo du produit (audit visuel des couleurs et matières).
   - *Status `pass` (Score >= 0.85)* : Enregistrement direct en base de données.
   - *Status `retry` (Tentative < 2)* : Deuxième appel de génération avec injection du feedback d'erreur (`feedback_text`).
   - *Status `fail` ou échec retentative* : Placement dans la **File de Révision (« Review Required »)** sous la clé meta `_wasgo_content_review` + `_wasgo_needs_review = 1`.
3. **Interface de révision** : L'administrateur peut examiner, éditer manuellement, régénérer ou approuver en 1 clic les contenus litigieux.

### Module D : Synchronisation Multi-Plugins SEO
Lors de la sauvegarde d'un titre ou d'une méta description (produit ou catégorie), WASGO détecte automatiquement le plugin SEO actif et alimente ses métadonnées sans conflit :
- **The SEO Framework (TSF)** :
  - Produits : `_genesis_title` et `_genesis_description`.
  - Catégories (termmeta) : array sérialisé `autodescription-term-settings` avec clés `doctitle` et `description`.
- **Rank Math SEO** :
  - Produits : `rank_math_title` et `rank_math_description`.
  - Catégories : `rank_math_title` et `rank_math_description`.
- **Yoast SEO** :
  - Produits : `_yoast_wpseo_title` et `_yoast_wpseo_metadesc`.
  - Catégories : `wpseo_title` et `wpseo_desc`.
- **Fallback universel** : `_wasgo_ai_title` et `_wasgo_ai_desc`.

---

## 🗄️ 4. Répertoire des Données & Schéma WordPress

### Options Globales (`wp_options`)
| Option | Rôle |
|---|---|
| `wasgo_image_provider` | Fournisseur IA image actif (`gemini`, `magnific`, `higgsfield`) |
| `wasgo_image_fallback_gemini` | Booléen : repli automatique vers Gemini en cas d'échec du provider principal |
| `wasgo_gemini_api_key` | Clé API Google Gemini pour les images (direct ou fallback) |
| `wasgo_gemini_model` | Modèle Gemini (défaut `gemini-3.1-flash-image-preview`) |
| `wasgo_magnific_api_key` | Clé API / Bearer Token Magnific |
| `wasgo_magnific_model` | Modèle Magnific (défaut `imagen-nano-banana-2` / Nano Banana Pro) |
| `wasgo_higgsfield_api_key` | Clé API Higgsfield (`KEY_ID:KEY_SECRET` ou Bearer token) |
| `wasgo_higgsfield_model` | Modèle Higgsfield (défaut `higgsfield-ai/soul`) |
| `wasgo_openai_api_key` | Clé API OpenAI pour la génération et validation de texte |
| `wasgo_ai_prompt` | Prompt maître pour l'optimisation des images |
| `wasgo_delete_original` | Booléen : supprimer définitivement l'image originale après génération |
| `wasgo_auto_compress` | Booléen : convertir en WebP et compresser les images générées |
| `wasgo_auto_clear_logs` | Booléen : purge quotidienne automatique des logs de plus de 30 jours |
| `wasgo_exclude_outofstock` | Booléen : ignorer les produits en rupture de stock |
| `wasgo_auto_process_new` | Booléen : déclencher automatiquement l'optimisation image à la publication d'un produit |
| `wasgo_image_quality` | Entier (0-100, défaut 85) : qualité de compression WebP |
| `wasgo_max_height` | Entier (défaut 1000px) : hauteur maximale des images |
| `wasgo_enhance_gallery` | Booléen : traiter également les images des galeries produits |
| `wasgo_content_[type]_prompt` | Prompts spécifiques par type (`short`, `long`, `title`, `desc`, `cat_title`, `cat_desc`) |
| `wasgo_content_[type]_specs` | Tableau des métadonnées / attributs à injecter comme contexte pour chaque type |
| `wasgo_content_disable_[type]`| Toggles d'activation/désactivation sélective par type de contenu |
| `wasgo_content_out_of_stock` | Booléen : exclure les ruptures pour la génération de contenu |
| `wasgo_content_auto_process` | Booléen : générer automatiquement le contenu à la création d'un produit |
| `wasgo_content_image_required`| Booléen : exiger la présence d'une image pour générer le contenu |
| `wasgo_content_language` | Chaîne (défaut 'English') : langue cible de rédaction |
| `wasgo_content_include_categories`| Booléen : injecter l'arborescence des catégories dans le prompt |

### Métadonnées Produits (`wp_postmeta`)
- `prevent_ebp_image_sync` : Marqueur indiquant qu'une image a été optimisée par l'IA (bloque l'écrasement ERP).
- `original_wasgo_image_id` : ID de l'attachement d'origine conservé en sauvegarde.
- `original_ebp_image_url` : URL de secours d'origine (historique/legacy).
- `_wasgo_disable_ai_gen` : Désactivation manuelle du traitement image sur ce produit spécifique.
- `_wasgo_ai_fields` : Tableau des types de contenu déjà optimisés par l'IA (`['short', 'title', ...]`).
- `_wasgo_content_review` : Données de révision en attente (`content`, `issues`, `score`, `date`).
- `_wasgo_needs_review` : Flag `1` si le produit est en file d'attente de modération.
- `wasgo_api_failed` : Timestamp du dernier échec d'API pour éviter les boucles infinies.
- `wasgo_batch_attempt` : Timestamp de la dernière tentative de batch (cooloff de 15 min).
- `wasgo_gallery_enhanced` : Flag indiquant que toutes les images de la galerie sont traitées.

### Métadonnées Catégories (`wp_termmeta`)
- `_wasgo_disable_cat_ai_gen` : Exclusion de la catégorie des traitements auto/bulk.
- `_wasgo_ai_fields` : Tableau des champs optimisés (`['cat_title', 'cat_desc']`).
- `_wasgo_content_review` / `_wasgo_needs_review` : File de révision pour la catégorie.

### Custom Post Type `wasgo_log`
Enregistre les réussites (`_wasgo_log_nature = 'success'`) et les erreurs (`_wasgo_log_nature = 'error'`) avec `_wasgo_log_type` (`image` ou `content`), `failed_product_id` / `success_product_id` et `_wasgo_log_data`.

---

## 🥊 5. Analyse Critique CTO & Dettes Techniques Identifiées

En posture d'associé technique et sparring-partner exigeant, voici les **5 anomalies et axes critiques** relevés dans le code :

### 1. 🔒 Sécurité : Clé GitHub PAT codée en dur (RÉSOLU)
- **Fichier** : `woocommerce-ai-seo-geo-optimization.php` (Ligne 30)
- **Action réalisée** : Le token en clair a été supprimé du code source. L'authentification PUC s'effectue désormais de manière sécurisée via la constante optionnelle `WASGO_GITHUB_ACCESS_TOKEN` (à définir dans `wp-config.php` si le repo nécessite une authentification privée).
- **Rappel sécurité** : Le token GitHub précédemment exposé doit impérativement être révoqué sur l'interface GitHub de l'organisation SOYOO974.

### 2. ⚡ Dénomination du modèle Gemini Image
- **Fichier** : `class-wasgo-image-generator.php` (Lignes 94 et 379)
- **Code** : `https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image-preview:generateContent`
- **Risque** : `gemini-3.1-flash-image-preview` n'est pas un endpoint public standard de l'API Google Gemini. Il convient d'aligner l'appel direct sur les endpoints officiels ou de passer par la nouvelle passerelle multi-providers (Magnific / Higgsfield).

### 3. 🐘 Risque d'engorgement de la base de données (`wasgo_log`) (RÉSOLU v4.4)
- **Action réalisée** : Migration complète vers `WC_Logger` (`wc_get_logger()`). Les logs sont écrits dans des fichiers tournants dans `wp-content/uploads/wc-logs/` (sources `wasgo-image` et `wasgo-content`) sans aucun post ni meta dans `wp_posts` / `wp_postmeta`.
- **Interface d'administration** : Lecteur haute performance sans requête MySQL lourde (`read_last_lines` via `fseek` arrière à empreinte mémoire fixe < 1 Mo) avec aperçu contextuel JSON, lien natif vers WooCommerce > État > Journaux, et bouton de purge immédiate des anciens posts résiduels en base.

### 4. 🌍 L'angle mort du "GEO" dans `woo-ai-seo-geo` (ARBITRAGE VALIDÉ)
- **Décision validée** : Priorité absolue donnée à la fiabilisation et l'optimisation des modules Images et Contenu SEO existants. La brique GEO (Schema.org LocalBusiness, ancrage communes de La Réunion, etc.) est volontairement mise de côté pour cette phase de développement.

### 5. 💰 Sélecteur de Providers & Nouveaux Moteurs d'Images (ARBITRAGE VALIDÉ)
- **Décision validée** :
  - Mise en place d'un sélecteur de provider configurable dans les réglages.
  - Intégration des API **Magnific** et **Higgsfield** (pour lesquelles Julien dispose d'abonnements actifs) pour la génération d'images haute fidélité.
  - Utilisation privilégiée du modèle **Gemini Nano Banana** (`imagen-nano-banana-2`) via l'API Magnific pour standardiser les rendus packshots sans blocage de marque/copyright.
  - Modularité pour la génération de texte (OpenAI GPT-4o, Google Gemini, Anthropic Claude).

---

## 🎯 6. Feuille de Route & Prochaines Étapes de Développement

1. **Lot 1 — Moteur de Logs Propre (WC_Logger)** : **[TERMINÉ v4.4]**
   - Remplacement de l'insertion CPT `wasgo_log` par `wc_get_logger()` avec le contexte `wasgo-image` et `wasgo-content`.
   - Lecteur de logs optimisé dans les onglets admin (Images et Contenu) avec liaison vers WooCommerce > État > Journaux.
   - Outil de purge en 1 clic des anciens posts `wasgo_log` et `postmeta` résiduels dans `wp_posts`.

2. **Lot 2 — Sélecteur de Providers & Moteur d'Images (Magnific / Higgsfield / Gemini)** : **[TERMINÉ v4.5]**
   - Abstraction via `WASGO_Image_Provider_Interface` et factory `WASGO_Image_Provider_Factory`.
   - Implémentation de l'adaptateur **Magnific API** (`WASGO_Provider_Magnific`) via JSON-RPC 2.0 (`creations_request_upload` -> PUT binaire direct -> `creations_finalize_upload` -> `images_generate` avec modèle `imagen-nano-banana-2` / Nano Banana Pro -> `creations_wait`).
   - Implémentation de l'adaptateur **Higgsfield AI** (`WASGO_Provider_Higgsfield`) avec soumission asynchrone, polling de statut et support de clés `KEY_ID:KEY_SECRET` ou Bearer.
   - Adaptateur **Google Gemini Vision** direct (`WASGO_Provider_Gemini`) avec modèle configurable (`gemini-3.1-flash-image-preview`).
   - Mécanisme de repli automatique (**Auto-Fallback Gemini**) en cas d'erreur ou d'épuisement de quota du provider principal.
   - Refonte moderne de l'écran des réglages (`render_settings_page`) avec cartes dédiées par provider, sélecteur actif et toggle de repli.

3. **Lot 3 — Moteur de Texte & Fact-Checking Multi-Modèles** :
   - Abstraire la génération et validation de texte pour supporter à la fois OpenAI (GPT-4o) et Google Gemini (Gemini 2.0 Flash / Pro).
   - Sécuriser les timeouts et la résilience sur les volumineux catalogues.

4. **Lot 4 — Tests en Conditions Réelles sur `comptoirdecambaie.re`** :
   - Validation en préproduction / staging.
   - Déploiement sans régression.

---

## 🚀 7. Protocole de Release GitHub & Déploiement Auto-Update (PUC v5)

Pour garantir la distribution instantanée et sans friction des mises à jour sur les sites clients (ex: `comptoirdecambaie.re`), le protocole suivant est **obligatoire** à chaque nouvelle version :

1. **Incrémentation de Version** :
   - Mettre à jour l'en-tête de `woocommerce-ai-seo-geo-optimization.php` (`Version: X.Y`).
   - Mettre à jour la constante `WASGO_VERSION` (`define( 'WASGO_VERSION', 'X.Y' );`).
2. **Linting PHP Syntaxique** :
   - Exécuter `php -l` sur l'ensemble des fichiers PHP.
3. **Commit Conventionnel & Push `main`** :
   - Commiter avec message conventionnel explicite (`feat: ... (vX.Y)`).
   - Pusher sur `origin main`.
4. **Tag Git & Création Systématique de la Release GitHub** :
   - Poser le tag Git : `git tag X.Y` et `git push origin X.Y`.
   - Créer impérativement la **Release GitHub** correspondante via GitHub CLI ou interface web :
     ```bash
     gh release create X.Y --title "Version X.Y — Titre explicite" --notes "Description détaillée des nouveautés et correctifs..."
     ```
5. **Raison Technique & Mécanisme PUC** :
   - **Plugin Update Checker (PUC v5.6)** écoute la branche `main` et les releases GitHub (`$myUpdateChecker->getVcsApi()->enableReleaseAssets()`).
   - La Release GitHub sert de balise officielle pour WordPress : elle déclenche la détection de mise à jour, affiche le changelog complet dans l'admin WordPress et permet la mise à niveau en 1 clic sans intervention manuelle.
