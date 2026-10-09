# Contexte Projet — WooCommerce AI SEO & GEO Optimization (WASGO)

## 📌 1. Vue d'Ensemble & Identité

- **Nom du Plugin** : WooCommerce AI SEO & GEO Optimization (WASGO)
- **Slug GitHub** : [`SOYOO974/woo-ai-seo-geo`](https://github.com/SOYOO974/woo-ai-seo-geo)
- **Version actuelle** : `4.3`
- **Auteur** : Soyoo.re (`https://www.soyoo.re/`)
- **Text Domain** : `wasgo`
- **Dépendance Requise** : WooCommerce (`woocommerce/woocommerce.php`) et Action Scheduler (inclus nativement dans WooCommerce).
- **Sites en Production** : [`comptoirdecambaie.re`](https://comptoirdecambaie.re/) (et autres boutiques WooCommerce gérées par SOYOO).

### Mission Principale
Fournir une suite d'automatisation IA native pour WooCommerce permettant de :
1. **Régénérer et sublimer les images produits & galeries** via Google Gemini Image API (avec conversion WebP, redimensionnement et sauvegarde de l'original).
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
│   ├── class-wasgo-logs.php                # CPT `wasgo_log` pour consigner succès & erreurs + purge cron quotidienne
│   ├── class-wasgo-image-generator.php     # Appel Gemini Image API, gestion WebP, swap d'attachements et backups
│   ├── class-wasgo-batch-processor.php     # Traitement par lots Action Scheduler pour images principales & galeries
│   ├── class-wasgo-ajax.php                # Endpoints AJAX d'images (start, stop, progress, regen unitaire, purge backup)
│   ├── class-wasgo-content-utility.php     # Extraction métadonnées produits (attributs, specs, catégories, taxonomies)
│   ├── class-wasgo-content-generator.php   # Générateur OpenAI GPT-4o avec JSON Schema / Structured Outputs
│   ├── class-wasgo-content-validator.php   # Validateur anti-hallucination GPT-4o (fact-checking + vision contextuelle)
│   ├── class-wasgo-content-orchestrator.php# Orchestration cycle de vie (génération, retry, commit DB, review queue)
│   ├── class-wasgo-content-batch-processor.php # Batch Action Scheduler pour le contenu (produits + catégories, mode 'smart')
│   ├── class-wasgo-content-ajax.php        # Endpoints AJAX de contenu (bulk, unitaire, preview live, approbation review)
│   ├── class-wasgo-meta-boxes.php          # Métaboxes d'édition Produit et champs de taxonomie Catégorie Produit
│   └── class-wasgo-admin-menu.php          # Pages du panneau d'administration (Dashboard, Images, Contenu, Réglages)
├── AGENTS.md                               # Directives de gouvernance et protocole pour agents Antigravity
├── PROJECT_CONTEXT.md                      # Ce fichier (spécification complète et architecture)
└── .gitignore                              # Exclusion builds .zip et artefacts temporaires
```

---

## ⚙️ 3. Fonctionnement des Modules Métier

### Module A : Traitement & Amélioration d'Images (Gemini Vision)
- **Moteur IA** : Endpoint `https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image-preview:generateContent` via cURL.
- **Workflow d'une image** :
  1. Récupération de l'image source (originale ou backup précédent via meta `original_wasgo_image_id` / `original_ebp_image_url`).
  2. Envoi de l'image en base64 avec le prompt configuré (`[PRODUCT_TITLE]` substitué).
  3. Réception du flux binaire en base64 (`inline_data`).
  4. Si option `wasgo_auto_compress` active : redimensionnement (respect de `wasgo_max_height`), compression qualité (`wasgo_image_quality`) et conversion native en **WebP** via `wp_get_image_editor()`.
  5. Insertion du nouvel attachement WordPress et remplacement de la vignette produit (`set_post_thumbnail`).
  6. Marquage `prevent_ebp_image_sync = 1` pour éviter tout écrasement par les synchronisations ERP (ex: EBP).
  7. Gestion de la sauvegarde : suppression de l'ancien attachement si option `wasgo_delete_original` cochée, ou conservation avec lien de restauration.
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
| `wasgo_gemini_api_key` | Clé API Google Gemini pour les images |
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

### 1. 🚨 Fuite de Sécurité : Clé GitHub PAT codée en dur
- **Fichier** : `woocommerce-ai-seo-geo-optimization.php` (Ligne 30)
- **Code incriminé** : `$myUpdateChecker->setAuthentication('WASGO_GITHUB_TOKEN_REDACTED');`
- **Risque** : Un Personal Access Token GitHub (`ghp_...`) est versionné en clair dans le dépôt public/privé. N'importe qui consultant le fichier a accès au compte ou à l'organisation GitHub.
- **Action requise** : Révoquer immédiatement ce jeton sur GitHub, et pour les dépôts publics, supprimer cette ligne ou basculer sur une constante optionnelle définie dans `wp-config.php`.

### 2. ⚡ Dénomination du modèle Gemini Image
- **Fichier** : `class-wasgo-image-generator.php` (Lignes 94 et 379)
- **Code** : `https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image-preview:generateContent`
- **Risque** : `gemini-3.1-flash-image-preview` n'est pas un endpoint public standard de l'API Google Gemini (qui expose `imagen-3.0-generate-002`, `gemini-2.0-flash-exp` ou `gemini-1.5-flash`). Il convient de clarifier si cet endpoint est fonctionnel ou s'il s'agit d'un placeholder à aligner sur les modèles de production stables.

### 3. 🐘 Risque d'engorgement de la base de données (`wasgo_log`)
- **Fichier** : `class-wasgo-logs.php`
- **Mécanisme** : Chaque succès et chaque échec crée une entrée complète dans `wp_posts` (`post_type = 'wasgo_log'`) et 5 entrées dans `wp_postmeta`.
- **Risque CRO / Performance** : Sur une boutique WooCommerce avec 5 000 produits et 20 000 images, un traitement en masse va injecter 25 000 posts et plus de 100 000 métadonnées dans les tables centrales de WordPress. Sur des hébergements haute performance (ex: Rocket.net), cela dégrade les index MySQL et alourdit inutilement les sauvegardes.
- **Alternative supérieure** : Exploiter le système natif `WC_Logger` (`wc_get_logger()`) qui écrit dans des fichiers de logs tournants dans `wp-content/uploads/wc-logs/`, ou une table personnalisée légère et indexée sans toucher à `wp_posts`.

### 4. 🌍 L'angle mort du "GEO" dans `woo-ai-seo-geo`
- **Observation** : Le plugin s'appelle `WooCommerce AI SEO & GEO Optimization`, mais l'analyse intégrale du code montre qu'il n'existe **absolument aucun module GEO** (zéro balisage Schema.org LocalBusiness / GeoCoordinates, zéro personnalisation de contenu par commune ou zone de chalandise de La Réunion, zéro ciblage géographique dans les prompts).
- **Opportunité** : Développer un vrai moteur de contextualisation locale (ex: inclusion dynamique des zones de livraison à La Réunion, villes desservies, arguments insulaires, microdonnées locales JSON-LD).

### 5. 💰 Dualité des Fournisseurs d'IA (Gemini pour Image + OpenAI pour Texte)
- **Observation** : Le plugin oblige le commerçant à détenir et payer deux comptes séparés : Google AI Studio (Gemini) ET OpenAI (GPT-4o).
- **Recommandation** :
  - Gemini 2.0 Flash / 1.5 Pro sait désormais exceller en rédaction textuelle structurée (JSON Schema) et en vision pour un coût 10 à 20 fois inférieur à GPT-4o.
  - Offrir la possibilité d'utiliser un fournisseur unique (100% Gemini ou 100% OpenAI / Anthropic) réduirait la friction opérationnelle et le coût pour les clients de l'agence SOYOO.
