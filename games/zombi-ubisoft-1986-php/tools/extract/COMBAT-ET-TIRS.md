# Corps à corps et tirs — version 0.8

La version 0.9 remplace la paire ennemie de référence et ajoute les ripostes ; voir
[PROFILS-ET-RIPOSTES.md](PROFILS-ET-RIPOSTES.md).

## Préconditions et observations CPC

`verify-combat-damage.py` vérifie 92 propriétés dans les captures de l'original.
`probe-melee-damage.py` prépare l'arme tenue/active et un pic de poussée de 20 à
l'entrée du premier calcul d'attaque. La position de départ est contrôlée. Il ne
modifie ni les jauges du personnage, ni les PV ou la force de l'ennemi, ni le calcul
de dégâts. Les statistiques initiales de l'ennemi varient entre ces essais.

La routine `#7EB0` examine les touches alternées toutes les dix trames (0,2 seconde) :
zéro ou une transition fait reculer la poussée, deux la maintiennent, au moins trois
la font avancer d'un cran. La poussée est limitée à 28. Le seuil de la confrontation
est `27 - ((F + H) / 8)`, avec l'arithmétique 8 bits du programme. Une fois le seuil
atteint, le maximum de poussée est conservé jusqu'au prochain calcul d'attaque.

Tous les 200 ticks (4 secondes), `#8803` effectue d'abord un tirage d'attaque ennemi.
Le score tiré est `(R & 63) + (R & 31) + (R & 7)` ; il doit être inférieur aux PV
actuels de l'ennemi pour toucher. La perte sur F et H vaut `((R & 7) + 6) / 2`, soit
3 à 6. Une attaque n'est donc pas une perte automatique à chaque période.

Si le personnage survit et a atteint le seuil, ses dégâts dépendent de la poussée,
de F et de l'objet actif (`#87C4`, `#8864–#8899`). Pour F=70, H=80, seuil=9 et pic=20 :

| Objet actif | Bonus | Dégâts observés |
| --- | ---: | ---: |
| Aucun | 0 | 4 |
| Pistolets 34/35 | 6 | 7 |
| Fusil 36 | 8 | 8 |
| Couteau 37 | 6 | 7 |
| Marteau 38 | 10 | 9 |
| Hache 39 | 8 | 8 |

Les dégâts réduisent à la fois les PV et la force. L'épuisement de l'une de ces deux
valeurs termine l'affrontement. Le pic est ensuite remis à zéro ; les pressions seules
ne provoquent pas des dégâts immédiats comme dans l'ancien prototype.

## Tirs : résultat surprenant conservé

`probe-aim-damage.py` prépare l'arme, l'ennemi à PV=31/force=27 et une position du
curseur au calcul du tir. La routine de dégâts reste intacte. Les essais naturels
`probe-natural-shots.py` utilisent uniquement des COPY temporisés pour tirer : le
curseur n'est pas modifié. Un breakpoint relève sa position au moment du calcul.
Les deux méthodes donnent les mêmes résultats.

La largeur vaut l'arrondi supérieur de `(H + bonus) / 20`, au minimum 1. Le fusil
ajoute 20 et l'entraînement ajoute 40, avec la somme 8 bits du CPC. Pour H=80 :
pistolet 4, pistolet entraîné 6, fusil 5, fusil entraîné 7.

La position est repliée autour des cellules 28/29. Dans la zone centrale, les essais
retirent 12 PV et 12 points de force ; plusieurs tirs peuvent être nécessaires.
La branche `#8A58` met au contraire les PV à zéro hors de cette zone. Ce comportement
contre-intuitif est conservé, plutôt que remplacé par une règle de précision supposée.
Exemple sans modification de curseur : décalage de COPY de deux trames → cellule 28,
PV 31→19 ; une trame → cellule 25, PV 31→0 ; trois trames → cellule 31, PV 31→0.

Après un tir partiel, l'original attend 96 ticks (1,92 seconde). Le viseur a une limite
d'environ 512 ticks (10,24 secondes). Sa vitesse du prototype est une approximation
des positions mesurées, environ 137,5 cellules par seconde ; elle n'émule pas le coût
exact des appels firmware du CPC. Aucun compteur de munitions n'est inventé.

## Sauvegarde, contrôles et limites

Les PV et la force de l'adversaire sont sauvegardés. Les anciens formats 0.2–0.7
reçoivent une force de référence de 27. Au chargement, la sélection et les compteurs
transitoires du combat repartent au repos ; la blessure infligée reste conservée.
Le tirage portable sauvegardé est aussi utilisé pour les attaques, sans prétendre
reproduire la séquence du registre R.

`CombatTest` contrôle rythme, dégâts, probabilités, bonus, tir partiel, branche extérieure,
largeur, délai et sauvegarde. `browser-combat.cjs` prépare seulement les lieux/armes
et le curseur de certains tirs ; les commandes passent par le clavier. L'évasion
`browser-escape.cjs` reste une partie neuve sans injection de position, équipement,
santé ou ennemi. Son alternance de touches est accélérée pour dépasser le seuil de rythme.

Les statistiques de départ de chaque rencontre sont encore une paire de référence
31/27, alors que le CPC les tire au sort. La gestion de toutes les ripostes à distance,
des cibles amies et des conséquences différées des blessures reste à compléter.
Ces essais ne constituent pas une preuve de parité complète des combats.

## Validation du 31 octobre 2026

- 92 contrôles CPC et 47 tests PHP passent, avec analyse statique, syntaxe et démarrage de 600 images.
- Les contrôles de combat passent dans Chromium local et sur JumPHP : rythme lent/rapide,
  tirs partiels, branche extérieure, largeur fusil/entraînement et sauvegarde de l'ennemi blessé.
- Une évasion locale au clavier réussit avec les quatre survivants et un quota porté à 36.
  Le parcours de contrôle suivant équipe les survivants avant le nettoyage pour éviter les longs combats à mains nues.
- Ce parcours armé réussit aussi sur JumPHP avec les quatre survivants, 34 corps entreposés,
  un cycle de circulation des ennemis et sauvegarde/rechargement après l'arrivée du camion.
- Les pages locale et publiée passent les contrôles ordinateur/téléphone.
