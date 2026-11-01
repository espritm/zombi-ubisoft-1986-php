# Chutes et électrocution — version 0.17

## Mesures sur l'original

`sources/probe-hazards.py` produit 25 scénarios contrôlés et
`sources/verify-hazards.py` vérifie **187 assertions**. Les préconditions et les
touches sont enregistrées dans `analysis/runs/hazard-*/results.json` ; les dumps,
images et rapports `hazards.json` restent privés. Les pièces initiales, les objets,
les jauges et, pour deux essais, les états des autres survivants sont préparés.
Les routines de déplacement, décharge, mort et transfert ne sont pas modifiées.

Les essais couvrent les trois fenêtres, une corde tenue et une corde activée,
l'absence de place au sol, un inventaire partiellement rempli, les seuils de F et H,
les gants tenus/activés et la dernière mort du groupe. Les valeurs de jauge
supérieures aux maxima habituels sont des fixtures de vérification des octets,
pas des états établis par un parcours naturel.

## Chute sans corde active

`#9CA4` teste l'objet actif 41. Tenir une corde sans l'activer ne protège pas.
La branche `#8368` cause 10 met l'état à 255 et désactive l'objet utilisé, sans
changer les jauges ni la position de la fiche du personnage. Le corps est placé
dans les deux dernières cases libres consécutives de la pièce située en bas :

| Pièce de départ | Pièce du corps | Direction du prototype |
| --- | --- | --- |
| 35 | 64 | Ouest |
| 38 | 66 | Est |
| 46 | 65 | Nord |

Les routines `#848A/#84AF` balayent cette pièce, et `#84DD` transfère le sac puis
l'objet tenu vers le dossier du corps. Aucun marqueur n'est placé à l'étage de
départ. Le manque de place empêche seulement le marqueur, pas le transfert.
Le personnage reste enregistré en haut : ce n'est pas un déplacement réussi.

Le transfert se produit **avant** la recherche d'un autre survivant par `#84C8`.
Il a donc aussi lieu lors de la dernière mort, avant l'écran de défaite. La corde
active conserve son trajet existant et est laissée en haut ; cet essai ne mesure
pas des dégâts propres à la descente, car des ennemis peuvent agir en bas.

## Décharge du boîtier de fusibles

`#A33C–#A367` demande l'objet actif 45. Des gants seulement tenus ne protègent pas.
Sans protection, le fusible n'est pas installé. Les calculs sont successifs :

1. Calculer `(F - 20) & 255`. Si le résultat est >=128, mourir avant de l'écrire.
2. Sinon écrire ce nouveau F, puis calculer `(H - 10) & 255`.
3. Si ce résultat est >=128, mourir avant de l'écrire ; sinon écrire H.

Ainsi F=19/H=80 meurt en gardant 19/80 ; F=70/H=9 meurt avec 50/9.
F=20/H=80 reste vivant à 0/70, H=10 peut atteindre zéro sans mort immédiate, et
20/10 devient 0/0 tout en gardant l'état vivant. Le test porte sur l'octet obtenu,
pas simplement sur la présence d'un zéro : les fixtures 147→127 et 148→128 pour F,
137→127 et 138→128 pour H confirment la borne supérieure.

La cause 20 dans `#83B1/#83FD` donne une mort définitive 255, désactive l'objet et
laisse le corps **dans la pièce courante**, avec son inventaire. Elle ne force pas
F à zéro, ne transforme pas la victime en état 64 et ne lance pas de réanimation.
S et la position ne changent pas. Sans mort, le fusible et l'objet actif restent
portés et le joueur revient au jeu normal.

Contrairement à la chute, `#8479` appelle la recherche d'un survivant **avant** le
placement et le transfert. Si la décharge tue le dernier survivant disponible,
le jeu affiche la défaite avec le sac et l'objet tenu encore dans sa fiche, sans
créer de corps ni remplir son dossier. Sinon le transfert a lieu, même sans place
pour afficher le corps. Avec les gants actifs, les jauges restent intactes et le
fusible est installé normalement.

## Portage et vérifications

La version 0.17 conserve ces ordres et ces seuils. Elle réutilise les corps 29–32
et les cinq cases indépendantes de la version 0.16, sans reproduire les débordements
mémoire décrits dans [CORPS-ET-INVENTAIRES.md](CORPS-ET-INVENTAIRES.md). Aucun nouveau
champ de sauvegarde n'est ajouté ; les formats 0.2–0.16 continuent à se charger.
Les anciennes parties ne reçoivent pas de corps rétroactivement.

`HazardTest` contrôle les trois destinations, les jauges, les inventaires,
les cas sans place, les protections, l'ordre de la dernière mort et le chargement
du format 0.16. `tools/browser-hazards.cjs` utilise les commandes réelles sur des
fixtures initiales : ouvertures de fenêtres, déplacement, fusibles, sélection d'un
autre survivant, récupération d'un corps en bas et sauvegarde/rechargement.
Ce scénario n'est pas un parcours naturel complet. Les autres échanges de corps
avec des contenants, certaines autres causes de décès et le timing du firmware
restent à vérifier ; l'adaptation complète n'est pas déclarée terminée.

Validation locale : 187 assertions CPC, 109 tests PHP, PHPStan, lint et smoke
600 images réussis. Le scénario navigateur passe sur la construction distante
locale, y compris récupération du corps en bas, sauvegarde/rechargement,
protections actives et ordre de la dernière mort. Le titre, le jeu et la capture
de récupération du corps ont été inspectés. Les illustrations privées déjà
publiées de la version 0.15 sont conservées ; aucun nouvel envoi n'est requis.

Après publication de `eb15a9a`, le même scénario passe sur JumPHP : les trois
chutes, les soustractions successives et les zéros non mortels, les gants, la corde,
la récupération du corps en bas, la sauvegarde/rechargement et les deux ordres
de dernière mort. La page publiée passe les contrôles ordinateur/téléphone sans
image manquante, erreur JavaScript ou débordement horizontal ; sa capture a été
inspectée. Aucun nouveau parcours d'évasion complet n'a été exécuté dans le
navigateur pour cette étape ; le parcours PHP reste dans la suite de 109 tests.
