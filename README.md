# WooCommerce AI SEO & GEO Optimization

[![Version](https://img.shields.io/badge/version-4.5-blue.svg)](https://github.com/SOYOO974/woo-ai-seo-geo)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-Compatible-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-777bb4.svg)](https://www.php.net/)

Extension in-house mutualisée développée par **[SOYOO](https://www.soyoo.re/)** pour optimiser automatiquement les images, les métadonnées SEO et le contenu des boutiques WooCommerce à l'aide de l'intelligence artificielle multi-fournisseurs (**Magnific AI Nano Banana Pro**, **Higgsfield AI**, **Google Gemini Vision** et **OpenAI GPT-4o**).

---

## 🚀 Fonctionnalités Clés

- **Amélioration Visuelle Multi-Providers (Magnific, Higgsfield, Gemini)** :
  - **Magnific AI** : Génération studio packshot via **Nano Banana Pro** (`imagen-nano-banana-2`) sans blocage de copyright ni dézoom, avec upload binaire presigné S3 et polling `creations_wait`.
  - **Higgsfield AI** : Intégration de modèles studio packshots haute fidélité (`higgsfield-ai/soul`) avec authentification `KEY_ID:KEY_SECRET` ou Bearer.
  - **Google Gemini Vision** : Traitement direct via l'API officielle v1beta (`gemini-3.1-flash-image-preview` configurable).
  - **Repli Automatique (Auto-Fallback Gemini)** : Bascule transparente et journalisée vers Google Gemini en cas de timeout, quota dépassé ou indisponibilité du fournisseur principal.
  - Conversion et compression automatique en WebP (`wp_get_image_editor`).
  - Système de sauvegarde avec restauration en 1 clic de l'original.
  - Verrou de synchronisation ERP (`prevent_ebp_image_sync`).
- **Moteur de Logs Haute Performance (`WC_Logger`)** :
  - Journalisation native par fichiers tournants dans `wp-content/uploads/wc-logs/`.
  - Protection absolue des tables centrales (`wp_posts` et `wp_postmeta`) contre la saturation.
  - Intégration transparente avec WooCommerce > État > Journaux et lecteur optimisé dans l'administration WASGO.
  - Outil de purge instantané des anciens logs résiduels en base.
- **Génération de Contenu SEO Produit & Catégorie (GPT-4o)** :
  - Descriptions courtes, longues, Meta Titles et Meta Descriptions sur-mesure.
  - Intégration des attributs WooCommerce et spécifications techniques dynamiques.
  - Prise en compte du contexte visuel (multimodal Vision).
- **Fact-Checking Anti-Hallucination à Double Passe** :
  - Validation automatique par IA (temperature 0) face aux données réelles du produit.
  - Retentative automatique avec feedback contextuel en cas d'anomalie mineure.
  - File d'attente de modération manuelle (*Review Required*) avec interface d'édition et régénération en un clic.
- **Interopérabilité SEO Native** :
  - Synchronisation transparente sans conflit avec **The SEO Framework (TSF)**, **Rank Math SEO** et **Yoast SEO**.
- **Traitement par Lots Résilient** :
  - Exécution en arrière-plan orchestrée par **Action Scheduler**.
  - Mode "Smart" évitant le retraitement inutile des éléments déjà optimisés.
- **Mises à Jour Automatiques** :
  - Intégration de *Plugin Update Checker (PUC)* relié au dépôt GitHub.

---

## 📋 Prérequis

- WordPress 6.0+
- WooCommerce 7.0+
- Action Scheduler (fourni nativement avec WooCommerce)
- PHP 8.0 ou supérieur
- Clés API :
  - Fournisseurs Images (au choix avec repli Gemini recommandé) :
    - Magnific API Key / Token (recommandé pour packshots Nano Banana Pro)
    - Higgsfield API Key (`KEY_ID:KEY_SECRET` ou token)
    - Google Gemini API Key (traitement direct ou repli automatique de secours)
  - Fournisseur Contenu SEO :
    - OpenAI API Key (pour la rédaction et validation de descriptions/métadonnées)

---

## 🔒 Directives Développeurs

Pour toute contribution ou intervention technique, consulter obligatoirement :
- [`AGENTS.md`](file:///c:/Antigravity/woo-plugins/woo-ai-seo-geo/AGENTS.md) : Règles de gouvernance, linting syntaxique obligatoire et protocole de déploiement GitHub.
- [`PROJECT_CONTEXT.md`](file:///c:/Antigravity/woo-plugins/woo-ai-seo-geo/PROJECT_CONTEXT.md) : Spécification architecturale détaillée, options WordPress, schéma de données et cartographie des métadonnées.

---

## 📄 Licence & Propriété

Propriété exclusive de **[SOYOO](https://www.soyoo.re/)** — Julien Vanwinsberghe.
Tous droits réservés.
