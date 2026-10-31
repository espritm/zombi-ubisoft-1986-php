# Réveil manuel — version 0.15

## Observations CPC

`probe-wake.py` place Patrick et Alexandre dans la pièce 34, avec éclairage forcé,
S=50 et les compteurs nourriture/repos de Patrick à 5/20. Les touches ordinaires
font dormir Patrick dans l'un des deux lits, sélectionnent Alexandre, puis ouvrent
Réveiller et valident le lit occupé. Les variantes changent ensuite une position,
un état ou une association de lit pour isoler la sélection. Elles ne constituent
pas des parcours naturels de mort ou de déplacement des dormeurs.

La routine `#A16C–#A19F` accepte les objets 57 et 59. Elle parcourt les fiches
dans l'ordre du groupe et compare uniquement leur champ +32 au type de lit.
Elle ne vérifie ni l'état du personnage ni sa pièce. Les mesures donnent :

| Cas préparé | Résultat |
| --- | --- |
| Lit 57 ou 59, Patrick endormi | État 0, association au lit effacée |
| Patrick dans une autre pièce | Même réveil, sans déplacer Patrick |
| Patrick avec état 0, 64, 128 ou 255 et association conservée | État 0 également |
| Alexandre et Patrick associés au même type de lit | Seule la fiche d'Alexandre, la première, est changée |
| Clic sur un lit libre 58 | Aucune modification du dormeur ni du lit occupé |

Le lit choisi dans la pièce active redevient 56 ou 58. Le compteur de sommeil
est arrêté à sa valeur courante, sans remise à zéro. Le breakpoint `#A19C`, après
l'écriture de l'état, donne exactement la même valeur que la capture finale.
S=50, nourriture=5 et repos=20 restent inchangés ; les jauges F/H aussi.
Le réveil manuel ne donne donc pas le bonus de 8 ni la remise à zéro de fatigue
du réveil automatique. Un nouveau coucher remet, lui, le compteur de sommeil à zéro.

`verify-wake.py` vérifie 81 propriétés de neuf scénarios. Les entrées inhabituelles
ne prouvent pas que tous ces états conservent naturellement une association de lit :
le nettoyage des fiches et des corps reste un autre périmètre.

## Portage et sauvegarde

Choisir **Réveiller** puis valider le lit occupé, avec les flèches et Entrée ou la
souris. Le portage suit la première association de lit, même à distance, sans
modifier S, la fatigue, la nourriture ou le temps déjà dormi. Il efface ensuite
cette association et libère le lit choisi. Le nom du personnage est affiché.

La recherche du portage est bornée aux quatre personnages. Un lit occupé sans
association valide est rejeté en conservant le lit et les fiches. Le CPC suppose
l'association valide et sa boucle peut lire au-delà des quatre fiches ; cette
condition invalide n'est pas reproduite par un blocage du navigateur.

Les sauvegardes 0.15 valident l'association optionnelle `bed` : 57 ou 59. Les
formats 0.2–0.14 restent acceptés. L'ancien portage gardait par erreur une
association après un réveil manuel : au chargement ancien, les associations des
personnages déjà éveillés sont donc retirées, ainsi que l'ancienne valeur vide 0.
Les associations des dormeurs sont conservées. Un temps de sommeil déjà perdu
par l'ancienne remise à zéro n'est pas reconstruit.

Les interactions complètes des corps, la disparition des objets liés à un décès
et les effets exacts de tous les transferts de personnages restent à compléter.
Le coût des routines firmware et les contrôles modernes ne sont pas identiques
au CPC. La priorité par type de lit est une règle observée, pas un tri par distance.

## Validation

- 81 contrôles CPC ; 92 tests PHP, analyse statique et démarrage de 600 images réussis.
- Tests PHP des deux lits, du premier lien, des états, des compteurs conservés,
  du réveil à distance, du lit orphelin, du recoucher et de la migration.
- Navigateur local et JumPHP : coucher réel après préparation des positions,
  changement de survivant, réveil au clavier, compteurs et jauges conservés,
  sauvegarde/rechargement, sélection à distance, première association et rejet borné.
- Le scénario navigateur de faim/fatigue reste réussi localement.
- Captures officielles et lint réussis ; page publiée contrôlée et inspectée
  sur ordinateur/téléphone sans image cassée, débordement ni erreur JavaScript.
- Construction navigateur/site et contrôle visuel de la page locale également réussis.
