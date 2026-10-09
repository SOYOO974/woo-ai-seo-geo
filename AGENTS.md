# Directives pour Agents Antigravity — WooCommerce AI SEO & GEO Optimization (WASGO)

## 🔒 SANCTUARISATION DU PLUGIN & GOUVERNANCE

> [!CRITICAL]
> **SOURCE DE VÉRITÉ ABSOLUE : `C:\Antigravity\woo-plugins\woo-ai-seo-geo`**
>
> 1. **Interdiction formelle de modification depuis un projet client** :
>    - Dès qu'un agent intervient sur un projet client (ex: Comptoir de Cambaie, Jardin Naturel, Conforama, etc.), il lui est **formellement interdit** d'éditer ou commiter directement le code de cette extension sur le serveur ou dans un sous-dossier client.
>    - Toute évolution, correction de bug ou refactorisation doit obligatoirement être réalisée et versionnée au sein de ce dépôt dédié.
> 2. **Découplage Métier & Respect des Hooks WordPress / WooCommerce** :
>    - Ne jamais coder en dur des identifiants ou clés spécifiques à un site client dans le cœur du code.
>    - Utiliser systématiquement les options WordPress enregistrées via `WASGO_Settings` ou des filtres extensibles (`apply_filters`).

---

## 🚫 PROTOCOLE DE DÉPLOIEMENT : ZÉRO DÉPLOIEMENT FTP PAR DÉFAUT

> [!CRITICAL]
> **INTERDICTION FORMELLE DE DÉPLOYER PAR FTP / SFTP OU DE PROPOSER DES TABLEAUX RÉCAPITULATIFS FTP SANS DEMANDE EXPLICITE DE JULIEN.**
> 
> Cette extension in-house mutualisée est hébergée sur GitHub (`SOYOO974/woo-ai-seo-geo`) et intègre **Plugin Update Checker (PUC v5)** configuré pour surveiller la branche `main` / releases GitHub.
> Les sites WordPress/WooCommerce clients (ex: `comptoirdecambaie.re`) se mettent à jour via le gestionnaire d'extensions WordPress.

---

## 🔄 WORKFLOW DE DÉVELOPPEMENT, VERSIONING & QUALITÉ

Dès qu'une modification ou amélioration est apportée à cette extension :

1. **Incrémentation de Version Sémantique** :
   - Mettre à jour l'en-tête de [`woocommerce-ai-seo-geo-optimization.php`](file:///c:/Antigravity/woo-plugins/woo-ai-seo-geo/woocommerce-ai-seo-geo-optimization.php) (`Version: X.Y`).
   - Mettre à jour la constante `WASGO_VERSION` (`define( 'WASGO_VERSION', 'X.Y' );`).

2. **Validation & Linting Syntaxique Obligatoire** :
   - Exécuter impérativement `php -l` sur tous les fichiers PHP du projet avant tout commit :
     ```powershell
     Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\\.git\\' } | ForEach-Object { php -l $_.FullName }
     ```

3. **Commit Conventionnel & Push Proactif sur GitHub** :
   - Exécuter automatiquement le cycle Git sans attendre de consigne explicite :
     ```bash
     git add .
     git commit -m "feat/fix: <description explicite conventionnelle> (vX.Y)"
     git push origin main
     ```

4. **Posture CTO & Sparring-Partner** :
   - Analyser froidement les propositions, alerter sur les risques de dérive (dette technique, surcharge de la base `wp_posts`, temps d'exécution des crons/APIs, sécurité des clés API).
   - Bannir toute flatterie ou complaisance.
