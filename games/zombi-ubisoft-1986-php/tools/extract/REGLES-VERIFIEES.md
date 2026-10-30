# Règles mesurées dans Zombi original — 30 octobre 2026

Suite : QUETES-ET-FINS.md ajoute neuf contrôles (électricité, placard, tir, véhicules, carburant et fins), ainsi que quatre intérieurs supplémentaires aux 86 lieux du plan.

Les douze contrôles de verify-rules.py passent. Le détail exploitable est dans extracted/behavior-rules.json ; chaque entrée cite sa routine et son fichier de mesures. Il s’agit d’un sous-ensemble vérifié, pas encore de toutes les règles du jeu.

## Inventaire, équipement et activation

L’inventaire comporte quatre emplacements à +24..+27 et un objet tenu à +28 dans la fiche personnage. L’action 18 échange l’objet choisi et l’objet tenu ; choisir l’emplacement vide permet de ranger l’objet tenu.

L’action 3 active l’objet tenu, mémorisé à +39. L’objet 38 a été pris, équipé, activé puis rangé via les commandes originales. Ranger seul laisse +39 actif ; utiliser ensuite la main vide le remet à zéro. La prise de l’objet a été effectuée normalement, sans injection d’inventaire, dans la pièce 38 sélectionnée pour l’expérience.

## Portes

Dans la pièce 38, la porte ID 8 (case ligne 0, colonne 8) devient ID 21 avec l’action 8 puis redevient ID 8 avec l’action 9. Le flag de passage associé passe de 0xF0 à 0xFA puis revient à 0xF0. Les captures opened.png / closed.png de runs/door-38 ont été ouvertes et vérifiées. Cela ne suffit pas encore à spécifier les portes verrouillées ou tous les passages.

## Consommables

Les jauges sont nommées ici selon les lettres affichées par l’original, sans développer leur signification.

| Objet | Action | Mesure | Limite vérifiée |
|---|---|---|---|
| 54 | 5 | F : 60 → 62 | 69 → 70, maximum de Patrick |
| 55 | 5 | S : 70 → 74 | 79 → 80, maximum de Patrick |

Dans les quatre essais, l’objet a été pris dans la pièce 20 puis consommé ; l’inventaire est à nouveau vide. Les jauges de départ ont été volontairement réduites pour isoler l’effet et son plafond. Ce sont des préconditions contrôlées, pas une séquence d’approvisionnement complète.

## Sommeil et temps

Dans la pièce 34, l’action 6 sur le lit 56 transforme sa représentation et place Patrick en état 0x10 (repos). L’original demande alors de choisir un autre survivant ; Alexandre a été sélectionné pendant que Patrick restait sur place.

Le compteur de sommeil est remis à zéro au départ (+35..+37). La routine #7CC4 réveille le personnage au seuil 0x01D400 (119808 ticks), soit environ huit heures affichées dans cette séquence. À la trame 131900 Patrick est encore en repos avec S=56 ; à la trame 132400 il est disponible avec S=64 : gain de 8, plafonné au maximum.

La valeur initiale était S=64, réduite pour l’essai. Elle ne monte pas à 72 à la fin : la routine périodique #7D09 lui a fait perdre 4 points deux fois avant le réveil. Les écritures observées sont S=60 à la trame 114024, S=56 à 129135, puis S=64 au réveil vers 132161. Le compteur à +29 intervient dans cette baisse ; le repos remet aussi +30 à zéro. Les pénalités de très longue privation restent à couvrir systématiquement.

Dans un essai séparé de 10:27 à 16:27, en pièce sans ennemi, les trois jauges sont restées à [70,80,80]. Ce constat ne doit pas être généralisé à une absence de faim ou fatigue.

## Combat

Sans riposte dans la pièce 41, Patrick perd des points à F et H ; S reste initialement stable. Les écritures de dégâts sont observées dans #8803. Quand F atteint zéro, l’état devient 0x40 et une croix remplace le portrait.

Les routines #8707 et #871B lisent alternativement droite/gauche (ou leurs codes équivalents). Le compteur de poussée est traité toutes les dix trames par #7EB0. Un essai trop lent (une pression toutes les 20 trames) n’a pas fait baisser les points de vie de l’ennemi. Un essai avec pressions alternées toutes les quatre trames, maintenues deux trames, remplit la jauge et bat l’ennemi : PV 31 → 0 et nombre d’ennemis 1 → 0, Patrick toujours disponible. Le corps apparaît dans la scène ; la capture after_inputs_3.png a été vérifiée.

Ces expériences n’injectent ni santé, ni arme, ni ennemi : seule la position de départ de Patrick est contrôlée. Les règles propres aux armes à feu et aux munitions restent à tester.

## Après la croix : zombification

Après la défaite sans riposte, Alexandre est choisi pendant que Patrick reste dans la pièce 41. Le compteur de Patrick continue ; son état passe de 0x40 à 0x80. Le portrait zombie est visible alors qu’Alexandre reste contrôlable.

La routine #7F1B réalise ce passage après le seuil 0x7500 du compteur : elle rétablit F à son maximum et H au maximum moins 20. Dans l’essai, Patrick revient ainsi à F=70 et H=60. Son état zombie est conservé dans les mesures. Les déplacements autonomes et l’élimination d’un survivant devenu zombie restent à caractériser.

## Éclairage

Un essai contrôlé dans la pièce 2 fournit explicitement l’objet 43 à Patrick : son obtention naturelle n’est pas revendiquée. Le tenir ne révèle pas la pièce ; l’action 3 l’active et fait apparaître le décor. Après rangement et activation de la main vide, la pièce redevient noire. Les quatre captures et les états +28 / +39 valident cette distinction entre tenir et utiliser.

## Reproduire

```powershell
./analysis/run-rules.ps1
```

Pour contrôler seulement les observations déjà enregistrées :

```powershell
./analysis/run-rules.ps1 -ReuseObservations
```

Le démarrage utilise les deux DSK et les ROM locales. Les expériences de sommeil et zombification changent uniquement la position de départ de Patrick ; les trois autres restent dans l’hélicoptère. Les expériences de récupération indiquent explicitement leurs jauges réduites et celle d’éclairage son objet fourni. Aucun changement du code du jeu original, du dépôt du remake ni de sa publication.

## Prochain périmètre

Armes et munitions, objets de quête, portes verrouillées, électricité, ascenseurs, sortie et victoire/défaite globale. La transcription PHP pourra s’appuyer sur les règles déjà mesurées en conservant une liste explicite des comportements manquants.
