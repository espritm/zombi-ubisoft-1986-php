# Corps des survivants et inventaires — version 0.16

## Observations reproductibles sur les disquettes de l'utilisateur

`sources/probe-party-bodies.py` exécute 16 scénarios dans le jeu original :
`living`, `sparse`, `full`, `split`, `rowwrap`, `stale`, `stale-sparse`, `self`,
`sleep`, `vehicle`, `loot`, `loot-item`, `transport`, `zombie`, `combat`, `reanimate`.
`sources/verify-party-bodies.py` vérifie **103 assertions** dans leurs dumps.
Les captures privées sont dans `analysis/runs/body-*` ; chaque `results.json`
conserve les touches et les préconditions injectées. Ce sont des essais contrôlés,
pas un parcours naturel : inventaires, états, pièces, cases libres, alerte et
curseur de tir sont préparés pour isoler la règle. Les routines de mort,
transfert et fouille restent intactes. La réanimation est observée avec son
chronomètre préparé ; le combat conserve ses dégâts aléatoires natifs.

## Règles confirmées

- `#8368` distribue les causes de mort. Les tirs sur une personne vivante,
  endormie ou morte la passent à 255 sans retirer de points de jauge ; tuer une
  personne zombifiée met F à zéro. Une mort en combat passe à 64, avant réanimation.
- Les marqueurs **29–32** correspondent aux quatre identités, dans l'ordre
  Alexandre, Sylvie, Yannick, Patrick. Ce ne sont pas les cadavres ordinaires 53 :
  ils ne changent ni le compteur de corps de la pièce ni le quota du congélateur.
- `#8494–#84C7` cherche deux zéros consécutifs en parcourant les 40 cases à
  rebours. Le balayage est linéaire : les cases 29 et 30 peuvent être retenues,
  même si elles sont sur deux lignes différentes. Sans place, aucun marqueur
  n'est écrit, mais le transfert d'inventaire a tout de même lieu.
- `#84DD–#8507` parcourt les quatre cases du sac, puis l'objet tenu, compacte les
  objets non nuls dans le dossier du corps et vide les cases sources. La partie
  inutilisée du dossier n'est pas effacée : un ancien suffixe peut subsister.
- Les dossiers démarrent à `#66EC + 5 * index` : un en-tête de capacité, puis quatre
  cases. Le cinquième objet déborde sur l'en-tête du dossier suivant. Cette écriture
  est directement observée, y compris avec les cinq cases d'inventaire occupées.
- `#A1FE–#A207` ouvre le sélecteur du corps. Son choix initial permet de prendre
  **le corps lui-même** ; le choix suivant prend son premier objet. Dans le premier
  cas, l'identifiant 29–32 va dans le sac et tous ses marqueurs sont retirés de la
  pièce, sans vider ses objets. Dans le second cas, seule la case d'objet choisie
  est vidée et le corps reste visible.
- Le dépôt restaure les marqueurs et conserve le dossier d'objets. Un corps
  transporté reste distinct d'un cadavre 53 et n'entre pas automatiquement dans
  le quota des zombies congelés.
- `#8411–#8439` libère un lit 57/59 dans la pièce courante lors de l'élimination
  définitive, mais garde l'association de lit dans le dossier de la personne.
  Le tir manuel ignore son état de véhicule et place le corps dans la pièce du
  tireur. Les autres personnes gardent leurs positions et inventaires.
- `#7F34–#7F53` retire les marqueurs de cette identité lors de sa réanimation,
  dans sa pièce sur la carte, sans filtrer l'état de véhicule. Le dossier d'objets
  n'est pas effacé. Transporter le marqueur ne déplace pas la fiche du mort.

## Portage et limites explicites

La version 0.16 relie ces règles aux éliminations par tir, aux combats contre les
survivants zombifiés et aux morts du personnage actif en combat. Les objets du
corps et son identité peuvent être pris séparément par PRENDRE, transportés et
reposés dans une autre pièce. Les marqueurs alimentent le malus de proximité déjà
porté en 0.13 ; leur présence n'ajoute pas de quota congelé.

**Écart volontaire :** les quatre dossiers du portage ont cinq cases indépendantes.
Le cinquième objet reste récupérable sans écraser le dossier voisin ni lire de
mémoire hors limites. Le débordement mémoire natif n'est pas reproduit. Le suffixe
inutilisé d'un dossier est conservé. Les menus et le remplissage d'inventaire
gardent les règles bornées du prototype ; le garde natif exigeant deux places
libres avant PRENDRE n'est pas encore généralisé.

Les sauvegardes 0.2–0.15 conservent leurs quatre anciennes cases de corps et reçoivent
une cinquième case vide. Les nouveaux dossiers sont strictement validés comme cinq
entiers et les chargements invalides restent atomiques. Une association de lit
valide de la version 0.15 est désormais conservée telle quelle ; la correction des
anciennes associations éveillées concerne seulement 0.2–0.14. Les anciens inventaires
non transférés ne sont pas déplacés rétroactivement au chargement.

Les branches particulières de chute et d'électrocution, certains échanges de corps
avec des contenants et le traitement exact de la dernière mort du groupe restent
à vérifier et compléter. Le portage ne prétend pas à une équivalence CPC complète.

## Contrôles du portage

`PartyBodyTest` vérifie les cinq objets, la séparation des dossiers, les suffixes,
le manque de place, le balayage entre lignes, la fouille, le transport entre pièces,
le rechargement, les lits, la réanimation et la validation des anciennes sauvegardes.
`tools/browser-party-bodies.cjs` utilise de vrais contrôles clavier et souris avec
des inventaires/états initiaux préparés : tir, sélection des objets, corps transporté
et reposé, sauvegarde/rechargement, récupération du cinquième objet, suicide,
changement de survivant et élimination d'un survivant zombifié.

Validation locale : 103 assertions CPC, 101 tests PHP, PHPStan, lint et smoke
600 images réussis. Le scénario navigateur corps/fouille/transport/rechargement
passe sur la construction distante locale ; ses captures ainsi que le titre et
le jeu ont été inspectés. La page conserve les illustrations privées déjà publiées
de la version 0.15 ; aucun nouvel envoi de ressources originales n'est nécessaire
pour le fonctionnement de cette version.

Après publication de `432d014`, le même scénario navigateur passe sur JumPHP :
corps et cinq objets, fouille à la souris, transport dans une autre pièce,
dépôt, sauvegarde/rechargement, récupération du cinquième objet, élimination de
l'acteur puis sélection d'un survivant, élimination d'une personne zombifiée.
La page publiée passe aussi les contrôles ordinateur/téléphone : aucune image
manquante, erreur JavaScript ou largeur débordante. Ses illustrations 0.15 sont
conservées et identifiées comme telles. Le scénario est contrôlé et ne constitue
pas un nouveau parcours d'évasion complet dans le navigateur.
