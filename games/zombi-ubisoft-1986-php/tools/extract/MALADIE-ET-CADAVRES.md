# Blessures, maladie et corps proches — version 0.13

## Préconditions et mesures CPC

`probe-illness.py` prépare Alexandre sur le toit avec un état, une jauge S et les
octets +23/+34 connus. Il place le compteur périodique `#6720` à `#3AFF` ;
l'interruption et le programme réalisent ensuite le contrôle réel. Les cas avec
corps préparent le compteur +8 de la pièce ou un marqueur 29–32. Les expériences
ne constituent pas un parcours naturel de contamination ou de collecte des corps.

Le contrôle `#7D67–#7DCD` est atteint quand l'octet haut du compteur vaut au moins
`#3B`, soit 15104 ticks à 50 Hz (environ 302,08 secondes). Il attend la boucle
principale : un menu d'équipement ouvert conserve S=80 après le seuil ; valider
ce menu permet ensuite la baisse à S=70. Le compteur continue dans le menu puis
est remis à zéro lors du traitement ; ce n'est pas un cumul de pertes à rattraper.

| Précondition | Résultat observé |
| --- | --- |
| Maladie +34=2, état 0 ou 16, S=80 | S=70 ; état conservé |
| Même maladie, état 64/128/255 | S=80 ; état conservé |
| Maladie, S=10 | S=0 et état 0 pour ce contrôle |
| Maladie, S=9 | S=0 et état 64 ; F/H conservées |
| Corps dans la pièce, S=80 | S=76 |
| Corps, S=3 | S=255 et état 255 |
| Maladie S=9 puis présence d'un corps | S=252 et état 255 |
| Marqueur 29, 30, 31 ou 32 | S=76, comme le compteur de corps |
| Corps dans une autre pièce | Aucun effet sur Alexandre |

La présence d'un corps retire une seule fois 4 points, indépendamment de leur
nombre. La soustraction est un octet : le passage sous zéro conserve le résultat
replié et déclare la mort définitive, contrairement à la maladie qui met S à zéro
et donne l'état 64. Cette deuxième soustraction peut remplacer l'état 64 par 255
dans le même contrôle. Le contrôle des corps utilise la case du plan, sans filtrer
le champ véhicule. La représentation par les marqueurs de corps des personnages
reste partiellement portée ; ces marqueurs existants sont désormais reconnus.

## Compteur de blessures et nouvelle maladie

`probe-exposure.py` fixe seulement les entrées aléatoires d'une attaque (score 0,
dégât 3) et observe la première fin de tour. `#8841–#8853` ajoute le double du dégât
à l'octet +23 après les deux soustractions non fatales :

| Cas | F/H après | +23 après |
| --- | --- | --- |
| Zombie, +23 initial=0 | 67/77 | 6 |
| Compagnon zombifié, +23 initial=0 | 67/77 | 6 |
| Pillard, +23 initial=250 | 67/77 | 250 |
| Zombie, +23 initial=250 | 67/77 | 0, repli 8 bits |
| Coup fatal à F ou H, +23 initial=250 | 0/80 ou 0/2 | 250 |

Le champ du portage `exposure` désigne cet octet +23 ; `illness` désigne +34.
La tentative d'acquisition d'une nouvelle maladie en `#7D88` ne peut pas réussir :
`#8AF0` remplace le seuil B par `(R&63)+(R&31)` et renvoie cette valeur plus `(R&7)`.
Le test A<B est donc toujours faux. Les entrées contrôlées 0 et 63 conservent +34=0,
même avec +23=255. Le portage ne remplace pas ce test par une probabilité inventée.
Cela n'établit pas l'absence de toute autre source de maladie dans le programme.
La même particularité empêche les soins de réduire +34, comme documenté dans SOINS.md.

`verify-illness.py` vérifie 93 propriétés des 19 scénarios périodiques et des six
attaques contrôlées. Les captures et octets d'origine restent privés.

## Portage et sauvegarde

Les effets concernent les quatre survivants, même inactifs. `healthTicks`,
`party[].exposure` et `party[].illness` sont sauvegardés et validés. Les anciens
formats 0.2–0.12 reçoivent les deux octets à zéro et reprennent la phase du compteur
des ennemis comme phase initiale de santé. Les blessures antérieures ne sont pas
reconstruites depuis les jauges.

Le contrôle est reporté dans les sélecteurs et les combats, traité une fois au
retour normal, et arrêté par la pause. Le portage garde une horloge de santé
distincte pour conserver le comportement déjà porté de circulation des ennemis,
qui continue dans les sélecteurs ; le CPC partage son contrôle de santé et de
circulation dans la boucle principale. Le tirage portable n'émule pas le registre R.
Les calculs de faim, de fatigue prolongée et le nettoyage des objets des personnages
restent partiels. Le coût des routines d'affichage n'est pas reproduit, notamment
quand une jauge atteint zéro. Il ne s'agit pas d'une parité complète du cycle vital.

## Validation

- 93 contrôles CPC ; 77 tests PHP, analyse statique et démarrage de 600 images réussis.
- Navigateur local : période reportée, sauvegarde puis traitement unique à la
  reprise, passage S=10→0 puis état 64 au contrôle suivant, corps et mort définitive,
  compteur de blessures après un coup de zombie et absence de maladie inventée.
- Le test navigateur de la trousse de soins reste réussi.
- Le même scénario d'effets différés passe sur JumPHP, avec reprise unique après
  rechargement et vérification des deux formes de mort.
- Lint, captures officielles et construction navigateur/site réussis. Les pages
  locale et publiée passent les contrôles et l'inspection sur ordinateur/téléphone,
  sans image cassée, débordement ni erreur JavaScript.
- Évasion complète sur JumPHP réussie depuis une nouvelle partie : 34 corps
  congelés, trois camions garés, arrivée des pillards, sauvegarde/rechargement et
  victoire des quatre survivants, avec une période de circulation ennemie franchie.
  Le parcours n'injecte pas de positions, objets, jauges ou ennemis.
- Un premier lancement du parcours a expiré pendant le chargement initial du
  moteur (180 secondes), avant toute action. Le nouvel essai a chargé le jeu puis
  terminé l'évasion. Le script distingue désormais l'état du chargeur et les
  erreurs d'exécution dans son diagnostic de démarrage.
