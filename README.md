# Med Finances v2.5 — secteur 1, secteur 2 OPTAM et structure d’exercice

Application PHP 8.3 destinée à comparer deux décisions professionnelles irréversibles pour un neurologue hospitalier :

1. débuter immédiatement une activité libérale en secteur 1 ;
2. attendre une année puis débuter en secteur 2 avec adhésion à l’OPTAM.

## Évolutions de la v2.5

- séparation stricte du revenu hospitalier et du résultat libéral dans tous les tableaux ;
- affichage du chiffre d’affaires libéral annuel avant charges ;
- ventilation de la redevance hospitalière, des charges professionnelles, de l’Urssaf, de la CARMF, de l’IR, de l’IS et de la fiscalité des dividendes ;
- calcul distinct pour le secteur 1 et le secteur 2 OPTAM ;
- estimation paramétrable de la prime OPTAM ;
- comparaison des structures et régimes suivants :
  - entreprise individuelle en BNC, déclaration contrôlée à l’IR ;
  - micro-BNC lorsque le seuil le permet ;
  - entreprise individuelle avec option pour l’IS ;
  - SCP imposée à l’IR ;
  - SELARL ou SELARLU à l’IS ;
  - SELAS ou SELASU à l’IS ;
- distinction entre revenu personnel disponible et trésorerie conservée dans une société soumise à l’IS ;
- conservation du calcul hospitalier par ancienneté, échelon, astreintes et retenues salariales ;
- projection patrimoniale jusqu’à 40 ans.

## Terminologie importante

- **BNC** est une catégorie d’imposition des revenus professionnels, pas une forme de société.
- **IS** signifie impôt sur les sociétés. Le terme « SI » n’est pas utilisé en fiscalité française.
- **OPTAM** n’est pas une cotisation supplémentaire. Le dispositif peut donner droit à une rémunération conventionnelle annuelle, calculée notamment selon les honoraires à tarifs opposables, le taux de charges de la spécialité et le respect des engagements.
- Une **SELARL** ou une **SELAS** est une société d’exercice libéral réservée aux professions libérales réglementées. Depuis les revenus 2024, la rémunération technique des associés de SEL relève en principe des BNC, sauf lien de subordination.

## Paramètres réglementaires intégrés

Les valeurs par défaut sont datées de 2026 et restent modifiables dans l’interface :

- PASS : 48 060 € ;
- CARMF base : 8,73 % sur la tranche 1 et 1,87 % dans la limite de 5 PASS ;
- CARMF complémentaire : 11,80 % dans la limite de 3,5 PASS ;
- ASV : forfait et ajustement distincts entre secteur 1 et secteur 2 ;
- micro-BNC : seuil de 83 600 € pour 2026 à 2028 et abattement de 34 % ;
- IS : 15 % jusqu’à 42 500 € de bénéfice sous conditions, puis 25 % ;
- IR 2026 sur les revenus 2025 : tranches 11 600 €, 29 579 €, 84 577 € et 181 917 €.

L’Urssaf est volontairement modélisée par une enveloppe effective paramétrable, car l’assiette sociale réformée, les prises en charge conventionnelles et les situations de SEL nécessitent une validation individualisée. La CARMF est calculée par composantes.

## Déploiement Dockge / TrueNAS SCALE

```bash
docker compose down
docker compose up -d --build
```

Adresse par défaut : `http://IP_DU_NAS:8083`

Pour changer le port, modifier `8083:80` dans `compose.yaml`.

## Avertissement

Ce moteur est un outil d’aide à la décision. Il ne remplace pas le simulateur officiel de l’Urssaf, les appels CARMF, le calcul définitif de l’Assurance Maladie au titre de l’OPTAM, ni une étude par un expert-comptable, un avocat fiscaliste et l’Ordre des médecins.

Les modèles SELARL et SELAS sont des simulations économiques. La qualification exacte des rémunérations techniques, des fonctions de direction et des dividendes doit être vérifiée avant toute décision.
