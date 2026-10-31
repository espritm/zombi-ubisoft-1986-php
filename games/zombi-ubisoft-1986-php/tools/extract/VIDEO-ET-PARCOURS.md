# Lecteur vidéo et premier parcours sans préparation

## Observations de l’original

`probe-video.py` et `verify-video.py` ajoutent sept contrôles. Les essais isolés placent Patrick dans la
pièce 29, lui fournissent la cassette 50 dans le sac et placent Alexandre dans cette même pièce.
Ils ne modifient ni le logement du lecteur, ni les flags d’entraînement, ni l’horloge.

1. Poser la cassette sur le lecteur éteint 60 échoue : elle reste dans le sac.
2. Actionner le lecteur 60 le transforme en 65.
3. Poser la cassette 50 sur 65 la retire du sac et l’enregistre en `#66E9` (`#A31F–#A32E`).
4. Actionner le téléviseur 61 le transforme en 66 ; une nouvelle action sur 66 l’éteint.
5. Actionner 65 sans cassette ou sans téléviseur 66 ne lance aucune séance.
6. Avec les deux préconditions, la routine `#988F–#98EA` affiche brièvement l’image 70, donne
   l’indice « SHOOT BRAIN. », ajoute 80 minutes et active character+38 pour chaque personnage situé en pièce 29.
   Dans l’essai, seuls Alexandre et Patrick sont entraînés ; Sylvie et Yannick restent inchangés.
7. Le lecteur revient à 65 et conserve la cassette. Le prototype reproduit cet état final et les effets de la séance ;
   il n’affiche pas l’image transitoire 70.

La sauvegarde 0.5 ajoute `videoCassette` (0 ou 50). Les versions 0.2, 0.3 et 0.4 se chargent avec un logement vide.
La validation complète précède toute modification du monde lors du chargement.

## Parcours du prototype sans injection

`tests/QuestRouteTest.php` part de `new World()` et n’écrit ni positions, ni inventaires, ni santé, ni ennemis.
Il cherche des passages ordinaires dans le plan, puis utilise les actions normales pour obtenir la cassette,
allumer les appareils, insérer et lire la cassette, et rentrer au toit.

`tools/browser-route.cjs` rejoue la même route dans une session neuve avec le clavier et vérifie la
sauvegarde après rechargement. Le pont PHP lit seulement l’état courant vers un JSON temporaire ;
il ne modifie pas le monde. Aucun flag d’éclairage ou d’entraînement n’est préparé.

| Tronçon | Nombre de déplacements ordinaires |
| --- | --- |
| Toit 62 → cassette en pièce 48 | 10 |
| Pièce 48 → lecteur en pièce 29 | 15 |
| Pièce 29 → toit 62 | 11 |

Le test confirme la cassette installée et l’entraînement de Patrick après sauvegarde/rechargement,
son retour vivant au toit et la clé d’hélicoptère d’Alexandre conservée.
Le trajet est calculé avec connaissance du plan ; ce n’est pas une solution humaine exhaustive.
Il valide le parcours de la vidéo dans le prototype, pas sa reproduction complète dans l’émulateur CPC,
ni une partie d’évasion terminée naturellement. Le carburant, les autres usages d’objets et l’équilibrage
des combats doivent encore être couverts par un parcours complet.
