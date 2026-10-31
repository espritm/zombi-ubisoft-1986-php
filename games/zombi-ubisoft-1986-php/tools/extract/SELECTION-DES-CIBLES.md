# Sélection des cibles — version 0.11

## Observation sur CPC

`probe-target-selection.py` reprend le scénario de tir contre Alexandre sur le toit.
Patrick reçoit le pistolet 34 ; la cible reçoit l'état voulu et F/H=50/40.
Le compteur d'engagement est remis à zéro pendant la préparation afin de ne pas
entrer en corps à corps avant le choix de l'action. Les touches normales ouvrent
Utiliser, parcourent le sélecteur et valident. Le curseur est fixé à la cellule 28
à `#8A04` uniquement pour isoler les dégâts du tir. Les contrôles ne constituent
pas un parcours naturel d'approvisionnement ou de zombification.

Sans ennemi ordinaire (`#949E`), `#94D4` appelle le sélecteur de personnages
`#9DF6`. Il commence sur le personnage actif ; droite/gauche boucle sur les quatre
fiches et COPY valide. Les vérifications et effets observés sont :

| Cible choisie | Résultat |
| --- | --- |
| Vivante, état 0 | État 255, F/H inchangées, sans visée |
| Endormie, état 16 | État 255, F/H inchangées, sans visée |
| Morte, état 64 | État 255, F/H inchangées, sans visée |
| Zombie, état 128 | Visée ; tir central : 50/40 → 38/28 |
| Déjà éliminée, état 255 | Aucun changement |
| Autre pièce | Aucun changement |
| Même pièce, autre champ véhicule | Tir possible : 50/40 → 38/28 |
| Personnage actif lui-même | État 255, F/H inchangées |

Les branches `#94EC–#9537` confirment ces différences. Le contrôle de lieu du tir
manuel compare seulement l'identifiant de la case du plan, contrairement au
combat automatique `#81EF` qui contrôle aussi le champ véhicule. Aucun de ces cas
n'ajoute d'ennemi ordinaire, de corps 53 ou de point au quota du congélateur.
`verify-target-selection.py` vérifie 30 propriétés des huit captures.

## Portage et commandes

En l'absence d'ennemi ordinaire, Utiliser avec un pistolet ou un fusil ouvre le
sélecteur des quatre personnages. Leur nom et leur état sont affichés ; une cible
absente est marquée AILLEURS. Flèches ou 1–4 sélectionnent, Entrée valide, Tab annule.
Cliquer une ligne valide cette cible. Les chiffres dans ce menu ne changent pas
le survivant contrôlé. Avec un ennemi ordinaire, la visée reste directe.

La visée et sa transition vers le corps à corps gardent le zombie choisi, même si
un autre adversaire arrive entre-temps. L'élimination d'une cible non zombifiée
conserve ses jauges comme sur le CPC. En se visant soi-même, le prototype propose
de choisir un autre survivant disponible. La défaite globale reste liée à la
disponibilité du groupe.

Le choix de cible est transitoire : annuler, changer d'action ou de personnage,
sauvegarder ou recharger l'efface. Les blessures et états restent dans la sauvegarde.
Les formats 0.2–0.10 restent acceptés, avec le même nom de fichier. L'affichage des
états et la sélection par chiffres/souris sont des adaptations de l'interface.
Les sons, le coût exact des appels firmware et les détails de nettoyage des objets
des personnages éliminés ne sont pas établis par ces essais.

## Validation

- 30 contrôles CPC ; 66 tests PHP, analyse statique et démarrage de 600 images réussis.
- Navigateur local : flèches, chiffres, souris, annulation, cible alliée, second
  zombie, dégâts partiels, rechargement et cible soi-même puis changement de survivant.
- Le scénario de zombification de la version précédente passe avec le sélecteur.
