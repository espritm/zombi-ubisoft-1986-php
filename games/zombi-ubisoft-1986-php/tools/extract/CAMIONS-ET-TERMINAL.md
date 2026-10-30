# Camions et ordinateur — observations de l’original

`probe-trucks.py`, `probe-terminal.py` et `verify-trucks-terminal.py` ajoutent 22 contrôles,
dont six recompositions graphiques exactes. Les positions, la clé et les passagers sont fournis pour isoler
les mécanismes ; les ennemis des scènes initiale et d’arrivée sont retirés avant leur premier affichage.
Ces essais ne constituent pas une solution naturelle de la quête.

## Camions

Le joueur ouvre la porte, entre, actionne le contact avec la clé 48 en main, puis le démarreur 71.
Le contact doit devenir 68. Sans clé, le contact reste à 63 et le démarrage est refusé.

| Cabine | Pièce de départ | Pièce d’arrivée | Accès bloqués après déplacement |
| --- | --- | --- | --- |
| 87 | 77 | 65 | Nord de 19, sud de 65 |
| 88 | 72 | 66 | Est de 31, ouest de 66 |
| 89 | 82 | 64 | Ouest de 32, est de 64 |

Routines `#98FA–#99D8` : les trente premières cases d’objets du départ remplacent celles de l’arrivée.
Les deux premières cases de la dernière ligne d’arrivée sont conservées ; les huit suivantes sont effacées.
Les trente cases de départ sont ensuite vidées, son préfixe +9 passe de 77 à 0 et celui d’arrivée devient 77.
Ce préfixe commande aussi le dessin du camion : changer seulement les objets laisserait la carrosserie au mauvais endroit.
Les six fonds sont capturés avant composition d’objets au point `#9D98`, puis packagés dans le fichier privé `trucks.png`.

Tous les personnages dont le champ intérieur vaut la même cabine sont déplacés avec elle.
Les occupants d’un autre camion restent en place. Le personnage sort ensuite au point d’arrivée.
Un nouvel essai de démarrage après le trajet ne déplace plus le camion : l’original affiche « PLUS D'ESSENCE. ».
Le prototype n’ajoute pas de consommation du bidon de l’hélicoptère, absente de ce contrôle original.

## Ordinateur, objet 75

La routine `#972E–#97E1` affiche LOGIN, RDY:, puis accepte jusqu’à treize lettres majuscules ou espaces
avec suppression et validation. Les mots connus sont comparés par préfixe.

| Commande | Réponse / effet observé |
| --- | --- |
| LIST | ELECTRICITE, puis NUIT |
| ELECTRICITE | FUSIBLES. ; ne rétablit pas le courant |
| NUIT | 22h-6h. ; ne change pas l’horloge |
| ZOMBI DEAD | SHOOT BRAIN. ; active character+38 pour le personnage courant |
| BYE | Sortie du terminal |
| Mot inconnu | SYNTAX ERROR |

Le prototype conserve la saisie clavier, la limite de treize caractères et les réponses, et ajoute des raccourcis
cliquables pour les commandes publiques. La commande cachée d’entraînement n’est pas révélée dans ce menu.
Les lettres H, M, C et I et l’espace sont du texte dans le terminal, sans activer les raccourcis du jeu.
Les messages et la saisie sont temporaires ; l’entraînement et les nouveaux décors des camions sont sauvegardés.
Les sauvegardes 0.2 et 0.3 restent chargeables.

Le bénéfice chiffré exact de l’entraînement dans la visée reste à mesurer : le combat du prototype reste provisoire.
Le lecteur vidéo 65/70 est un équipement distinct, encore à porter.

## Correction du corps après combat

Les observations précédentes `combat-alternating` et `weapon-34-training-0`, ainsi que la routine `#93E2`,
montrent que le corps est l’objet 53 aux deux cases inférieures gauches (indices 30 et 31), avec +1 au compteur
de corps du préfixe +8. Le prototype utilisait à tort l’objet 48, qui est la clé des camions ; cette confusion est corrigée.
