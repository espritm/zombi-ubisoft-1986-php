# Survivants zombifiés — version 0.10

Ces observations décrivent la version 0.10. La sélection manuelle introduite en
0.11 est documentée dans [SELECTION-DES-CIBLES.md](SELECTION-DES-CIBLES.md).

## Observations CPC et préconditions

Les expériences placent Patrick sur le toit (62) et donnent à Alexandre l'état
`#80`. Ses jauges F/H sont contrôlées : 50/40, ou 5/40 pour isoler une issue fatale.
Ce ne sont pas des parties naturelles menées jusqu'à la zombification.
`probe-zombie-party-combat.py` utilise ensuite les touches normales. Le cas `low`
alterne gauche/droite toutes les quatre trames ; `idle` ne riposte pas. Les cas
`other` et `vehicle` changent respectivement la pièce et le champ véhicule du zombie.

Après 512 ticks du compteur global, `#81EF–#8228` parcourt les quatre personnages
dans l'ordre de leurs fiches. Le combat exige la même pièce, le même champ véhicule
et l'état exactement `#80`. Les ennemis ordinaires ont priorité (`#8198–#81A6`).
La routine ajoute 18 à l'adresse de la fiche pour utiliser F/H comme les deux
jauges adverses. Un zombie inactif ne se déplace pas dans les essais observés.

Dans `idle`, Alexandre reste à 50/40 et Patrick perd des points : le zombie attaque
sans que le joueur ait à le choisir. Dans `other` et `vehicle`, Patrick conserve
70/80. Dans `low`, les écritures observées sont F 5→2, H 40→37, puis F→0 ;
H reste à 37 lors du coup fatal. `#841B` passe ensuite l'état à `#FF` et efface
l'objet actif. Le nombre d'ennemis et le compteur de corps de la pièce restent nuls.

`probe-zombie-party-shot.py` fournit le pistolet 34 et retarde explicitement le
compteur d'engagement pendant le choix de l'action. Le joueur choisit Alexandre
avec le sélecteur normal. Le curseur seul est contrôlé à `#8A04` pour isoler le tir :

| Cellule | F/H avant | F/H après | État après |
| --- | --- | --- | --- |
| 28 | 50/40 | 38/28 | `#80` |
| 1 | 50/40 | 0/40 | `#FF` |

Le chemin `#94FF–#951B` transmet les jauges de la fiche à la visée existante.
Le résultat confirme les dégâts 12/12 et la branche extérieure contre-intuitive
déjà mesurée pour les ennemis ordinaires. Aucun corps 53 n'est créé.
`verify-zombie-party.py` vérifie 17 propriétés de ces captures.

## Portage et limites

Le prototype combat désormais les survivants à l'état 128 présents dans la même
pièce et le même véhicule, après les ennemis ordinaires. Le premier zombie dans
l'ordre du groupe est la cible des touches de combat et de U. Son portrait zombifié
apparaît dans l'emplacement de l'adversaire. Les survivants vivants, endormis,
morts en attente de transformation ou définitivement éliminés ne sont pas ciblés.

F/H servent directement de résistance et restent dans la sauvegarde du personnage.
Un coup fatal met F à zéro, conserve la dernière H et donne l'état 255 ; ce personnage
ne se réanime plus. Son portrait affiche la croix. Il ne devient pas un corps 53
transportable et n'augmente pas le quota du congélateur. Les anciennes sauvegardes
0.2–0.9 restent acceptées avec le même nom de fichier.

La sélection manuelle de plusieurs cibles et les attaques volontaires contre des
survivants vivants ne sont pas encore portées. La cible du prototype est recalculée
selon la présence dans la pièce ; les menus et les raccourcis restent des adaptations
modernes. Ces tests ne prouvent ni une parité complète des combats ni un parcours
naturel jusqu'à la zombification.

## Validation

- 17 contrôles du programme CPC original.
- 61 tests PHP, analyse statique et démarrage de 600 images réussis.
- Navigateur local : transformation, engagement, portrait, tir, sauvegarde des
  blessures, élimination définitive et rechargement, sans ajout de corps ordinaire.
- Même scénario ciblé réussi sur JumPHP ; combats ordinaires et ripostes différées
  également contrôlés dans le navigateur local sans régression détectée.
- Lint et construction navigateur/site réussis. Pages locale et publiée contrôlées
  sur ordinateur et téléphone, sans image cassée, débordement ou erreur JavaScript.
  Les captures privées sont versionnées dans leurs URLs pour renouveler le cache.
- Évasion complète sur JumPHP : 34 corps congelés, trois camions garés, arrivée
  des pillards, sauvegarde/rechargement puis victoire des quatre survivants.
  Ce parcours utilise une nouvelle partie et les commandes ordinaires, sans
  injection de positions, objets, jauges ou ennemis ; une période de circulation
  ennemie est franchie. La zombification reste vérifiée séparément par fixtures.
