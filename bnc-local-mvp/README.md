# BNC Local — MVP 0.1

Application locale Windows pour piloter une comptabilité BNC avec justificatifs et sauvegarde NAS.

## Architecture de cette version

- **Moteur + WebUI sur le PC professionnel**.
- **SQLite local** : `data/bnc.sqlite3`.
- **Documents séparés** : `data/documents/`, stockage adressé par hash SHA-256.
- **NAS = cible de sauvegarde uniquement**. La base SQLite active n'est jamais ouverte depuis le NAS.
- **Scan Windows** via WIA (compatible avec un scanner exposant WIA, dont le Brother DS-740D avec ses pilotes Windows).
- Interface écoutant par défaut uniquement sur `127.0.0.1:8787`.

## Fonctions présentes

- tableau de bord recettes / dépenses / trésorerie ;
- saisie manuelle recettes et dépenses ;
- catégories BNC médicales (URSSAF, CARMF, redevance hôpital, Ordre, documentation, repas, déplacements, etc.) ;
- champs payeur CPAM / AMC / patient et token/UUID de passage ;
- import bancaire CSV ;
- pièces jointes avec SHA-256 et détection de doublons ;
- lien direct journal → justificatifs ;
- acquisition scanner locale ;
- exports XLSX, PDF et DOCX ;
- sauvegarde SQLite transactionnellement cohérente via l'Online Backup API ;
- copie du coffre documentaire vers NAS ;
- manifeste SHA-256 ;
- restauration avec contrôle du manifeste et `PRAGMA integrity_check` ;
- journal d'événements applicatifs.

## Installation Windows

### Prérequis

1. Windows 11 recommandé.
2. Python 3.11 ou ultérieur installé sur le PC.
3. Pour le DS-740D : pilotes Brother WIA/TWAIN installés.
4. Le partage NAS doit être accessible par Windows, par exemple `Z:\\BNC-Backups` ou `\\NAS\\BNC-Backups`.

### Installer

Ouvrir PowerShell dans ce dossier et exécuter :

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\install.ps1
```

Le script :

- crée un environnement Python isolé `.venv` ;
- installe les dépendances ;
- initialise la base ;
- crée un raccourci **BNC Local** sur le Bureau.

### Démarrer

Double-cliquer sur **BNC Local** sur le Bureau ou lancer :

```bat
start_bnc_local.bat
```

Puis ouvrir :

`http://127.0.0.1:8787`

## Configurer la sauvegarde NAS

Dans **Paramètres** :

- renseigner le chemin du partage NAS ;
- choisir le nombre de snapshots à conserver ;
- utiliser **Sauvegarder maintenant vers le NAS**.

Une sauvegarde ressemble à :

```text
backup-20260812-023000/
├── bnc.sqlite3
├── config.json
├── documents/
└── manifest.json
```

`bnc.sqlite3` est créé avec l'API de backup SQLite et non par copie sauvage du fichier actif.

## Restauration

1. Fermer BNC Local.
2. Lancer `restore_from_backup.bat`.
3. Indiquer le dossier `backup-YYYYMMDD-HHMMSS` à restaurer.
4. Le programme vérifie :
   - SHA-256 de la base ;
   - SHA-256 de chaque document ;
   - intégrité interne SQLite.
5. Une copie locale `pre-restore-*` est créée avant remplacement.

## Scanner Brother DS-740D

Le bouton **Scanner via DS-740D (Windows)** appelle le composant Windows WIA via PowerShell. La boîte d'acquisition Windows permet de sélectionner le scanner disponible.

Le scan est ensuite :

1. placé dans `incoming/` ;
2. hashé SHA-256 ;
3. déplacé dans le coffre documentaire ;
4. enregistré dans la base comme document non encore rattaché.

Cette V0.1 utilise WIA pour maximiser la simplicité d'installation. Une couche TWAIN native plus avancée pourra être ajoutée ensuite pour profils DS-740D, duplex, résolution, cadrage automatique et traitement spécifique des tickets CB.

## Import bancaire

Format CSV actuellement pris en charge par détection d'entêtes :

- `Date`
- `Libellé`
- soit `Montant`
- soit `Débit` et `Crédit`

Les lignes importées sont créées **non validées** pour éviter qu'un import brut devienne automatiquement une écriture fiscale définitive.

## Sécurité de cette version

- WebUI locale uniquement par défaut (`127.0.0.1`).
- Aucun cloud requis.
- Pas de télémétrie.
- Aucun accès CPAM / mutuelle.
- Pas de facturation électronique.
- Le NAS n'est pas le volume de travail actif.
- Les pièces sont indexées par SHA-256.

### À configurer au niveau Windows/NAS

Cette application ne remplace pas les protections du poste :

- activer BitLocker sur le PC ;
- chiffrer/protéger le NAS ;
- comptes NAS nominatifs ;
- partage de backup non accessible en écriture aux utilisateurs non nécessaires ;
- sauvegarde externe débranchée en plus du NAS ;
- mot de passe Windows fort + verrouillage automatique.

## Limites du MVP 0.1

Cette version est un socle fonctionnel, pas encore la version fiscale finale. Restent notamment à implémenter :

- rapprochement automatique actes ↔ CPAM ↔ AMC ↔ patient ↔ banque ;
- import configurable du logiciel métier ;
- édition détaillée d'une écriture importée ;
- ventilation URSSAF/CSG et règles fiscales avancées ;
- journal détaillé des déplacements et barème annuel ;
- moteur 2035 versionné ;
- registre d'immobilisations/amortissements ;
- OCR et normalisation/cadrage des tickets ;
- profils TWAIN avancés DS-740D ;
- chiffrement applicatif du coffre documentaire ;
- sauvegardes planifiées automatiquement ;
- second nœud/réplica et continuité en cas de panne du PC ;
- authentification multi-utilisateur si l'interface est un jour exposée sur un LAN.

## Structure

```text
bnc_local_mvp/
├── app/
│   ├── main.py
│   ├── templates/
│   └── static/
├── data/
│   ├── documents/
│   ├── incoming/
│   └── exports/
├── scripts/
│   ├── scan_wia.ps1
│   ├── backup_now.py
│   └── restore_backup.py
├── install.ps1
├── start_bnc_local.bat
├── backup_to_nas.bat
├── restore_from_backup.bat
├── requirements.txt
└── run.py
```

## Positionnement

Ce logiciel est volontairement conçu comme un **outil de comptabilité et de rapprochement**, sans communication directe avec CPAM/AMC et sans ambition de devenir un logiciel de télétransmission ou de facturation électronique certifié.
