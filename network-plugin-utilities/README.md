# Network Plugin Utilities (MU)

**Version 1.7.0** | Must-Use Plugin pour WordPress Multisite

Outil complet d'audit et de gestion pour réseaux WordPress multisite. Affiche un tableau de bord récapitulatif de tous les sites avec leurs méta-données, statistiques et alertes.

## 🚀 Fonctionnalités principales

### 📊 Vue d'ensemble du réseau
Pour chaque site, visualisez :
- **Utilisateurs** : Liste complète avec rôles
- **Contenus** : Articles, pages et custom post types (natifs et personnalisés)
- **Taxonomies** : Catégories, tags et taxonomies personnalisées avec nombre de termes
- **Plugins locaux** : Plugins activés uniquement sur le site (hors plugins réseau)
- **Infos techniques** : Thème actif, version, langue, médias, dernière mise à jour

### ⚡ Performance optimisée
- **Cache intelligent** : Données mises en cache automatiquement (1h par défaut)
- **Pagination optimisée** : Ne charge que les sites de la page courante
- **Bouton de rafraîchissement** : Mise à jour manuelle avec rate limiting (1 min)
- **Réduction de 80-90%** du temps de chargement sur les gros réseaux

### 📥 Export de données
- **Export CSV** : Format Excel/Google Sheets compatible (UTF-8 BOM)
- **Export JSON** : Format structuré pour intégration externe
- Boutons d'export directement dans l'interface

### ⚠️ Système d'alertes
Détection automatique des sites nécessitant de l'attention :
- 🔴 **Sites sans utilisateurs** (orphelins)
- 🟠 **Sites inactifs** depuis X mois (configurable)
- 🔵 **Sites avec quota médias élevé** (configurable)

Affichage :
- Panneau de résumé en haut de page
- Badges colorés sur chaque site
- Détails au survol (tooltip)

### 🎯 Détection d'origine
- Identification automatique de l'origine des CPT/taxonomies
- Affiche si c'est du core, un plugin, un mu-plugin ou un thème
- Aide à comprendre la structure du site

### 🎨 Interface personnalisable
- **Options de l'écran** : Choisissez les colonnes à afficher/masquer
- **Pagination configurable** : Définissez le nombre de sites par page
- **Préférences sauvegardées** : Vos choix sont conservés par utilisateur
- Colonnes masquées par défaut : CPT personnalisés et taxonomies personnalisées

## 📦 Installation

En tant que must-use plugin, placez simplement le dossier dans :
```
wp-content/mu-plugins/
```

Le plugin s'activera automatiquement.

## ⚙️ Configuration

Paramètres modifiables dans `network-plugin-utilities/config.php` :

```php
return [
    'cache_duration' => HOUR_IN_SECONDS,      // Durée du cache
    'default_per_page' => 20,                  // Sites par page
    'alert_thresholds' => [
        'inactive_months' => 6,                // Alerte après 6 mois
        'high_media_count' => 1000,            // Seuil de médias
        'no_users' => true,                    // Alerter si pas d'users
    ],
];
```

## 📍 Accès

Menu : **Réseau Admin** > **NPU Core** > **Analyse du réseau**

Permissions requises : `manage_network_plugins`

## 🔄 Hooks disponibles

Le cache est automatiquement invalidé lors de :
- Création d'un nouveau site (`wpmu_new_blog`)
- Archivage/désarchivage (`archive_blog`, `unarchive_blog`)
- Suppression d'un site (`delete_blog`)

## 📈 Cas d'usage

- **Audit de sécurité** : Identifier les sites orphelins ou mal configurés
- **Reporting client** : Exporter les données pour des rapports
- **Maintenance** : Détecter les sites inactifs à nettoyer
- **Optimisation** : Trouver les sites avec trop de médias
- **Documentation** : Cartographier tous les sites du réseau

## 🛠️ Développement

### Structure
```
network-plugin-utilities/
├── src/
│   ├── NPU_Core.php          # Menu et paramètres
│   ├── NPU_Cache.php         # Gestion du cache
│   ├── NPU_Network_Overview.php  # Tableau principal
│   ├── NPU_Export.php        # Export CSV/JSON
│   ├── NPU_Alerts.php        # Système d'alertes
│   └── NPU_Network_Sites_Menu.php  # Menu frontend
├── assets/
│   ├── css/                  # Styles admin
│   └── scss/                 # Sources SCSS
├── config.php                # Configuration
└── CHANGELOG.md              # Historique
```

## 📝 Changelog

Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique complet des modifications.

## 👤 Auteur

**Cyrille de Gourcy** <cyrille@gourcy.net>

## 📄 Licence

GPLv3 - Voir LICENSE pour plus de détails