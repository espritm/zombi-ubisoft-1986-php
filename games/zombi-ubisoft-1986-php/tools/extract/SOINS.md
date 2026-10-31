# Soins — version 0.12

## Mesures CPC

`probe-healing.py` place Patrick sur le toit, lui fournit l'objet 33 et prépare
Alexandre avec F/H=40/30, ou 68/67 pour mesurer les plafonds. Les sélections passent
ensuite par les touches ordinaires. Les états, positions et deux entrées RNG sont
des préconditions contrôlées ; ce n'est pas un parcours naturel d'approvisionnement.

`#93EF–#943C` ouvre le sélecteur des quatre personnages et rend 10 points à F puis
H, plafonnés séparément aux maxima. Alexandre passe de 40/30 à 50/40, ou de 68/67
à 70/70. Patrick peut se soigner lui-même. La trousse reste dans la main.
La cible doit avoir l'état 0 et la même position sur le plan ; le champ véhicule
est ignoré. Les états 16, 64, 128, 255 et une autre pièce sont rejetés.

L'entrée dans Utiliser pose immédiatement `#6714=1` et remet `#6715` à zéro,
avant le choix et la validation de la cible. Toutes les trousses et tous les
survivants partagent ce flag. Même un choix invalide bloque la prochaine tentative.
L'interruption efface le flag au compteur `#3A98` (15000 ticks à 50 Hz), soit
300 secondes de simulation, environ une heure sur l'horloge affichée.
L'essai `cooldown` prépare uniquement la fin du compteur à 14998 ; l'interruption
réelle le fait expirer, puis un second soin donne 60/50. Aucun délai ne dépend
de l'horloge du système ou de la date réelle.

## La maladie n'est pas guérie par ce calcul

Le code `#943F–#9451` semble vouloir réduire le compteur de maladie à +34 selon
un tirage inférieur à S/2. Toutefois `#8AF0` écrase B, qui contient ce seuil :
après l'appel, B vaut `(R&63)+(R&31)` et A vaut B plus `(R&7)`.
Le `CP B / RET NC` retourne donc toujours, quelle que soit l'entrée R.
Les essais avec entrée 0 et 63 confirment un compteur maintenu à 2, malgré les
jauges soignées. Le portage ne promet pas de guérir une maladie avec cette trousse.
La progression périodique de la maladie et les autres conséquences différées
des blessures restent hors de cette version ; elles demandent des essais séparés.

`verify-healing.py` vérifie 52 propriétés des douze scénarios : soins, plafonds,
cibles rejetées, véhicule ignoré, utilisation répétée bloquée, objet conservé,
maladie inchangée et reprise après expiration.

## Portage, sauvegarde et interface

Tenir la trousse, appuyer sur U, choisir avec les flèches ou 1–4 et valider avec
Entrée. Le menu indique SOINS pour le distinguer du tir ; cliquer une ligne valide
aussi. Tab annule dans l'interface moderne sans rembourser le délai déjà commencé.
Cette annulation est une adaptation, pas une séquence validée sur le CPC.

Le délai restant est sauvegardé dans `healingTicks` (0 à 15000). Il continue pendant
les sélecteurs et l'exploration, et s'arrête dans la pause. Changer de survivant ou
recharger ne permet pas de contourner le délai. Les sauvegardes 0.2–0.11 migrent
avec un délai initial nul ; les jauges existantes restent conservées.

## Validation

- 52 contrôles sur le CPC original ; 70 tests PHP et analyse statique réussis.
- Démarrage de 600 images réussi.
- Navigateur local : sélecteur de soins, +10 F/H, objet conservé, délai partagé,
  sauvegarde/rechargement, expiration, annulation et cible endormie rejetée.
- Le test navigateur du sélecteur de tirs passe également.
