# Ascenseur et corde — observations de l’original

Les scripts `probe-lifts.py`, `probe-rope.py` et `verify-lifts-rope.py` ajoutent huit contrôles.
Les positions initiales et la présence de la corde sont fournies pour isoler les règles.
Les ennemis de la scène initiale sont retirés avant son affichage, sans changer les destinations ni les transitions.
Ces essais ne constituent pas une solution naturelle de la quête.

## Ascenseur

- La cabine est la pièce 63 et change de case dans la carte (`#6726`). Ses positions possibles sont
  les indices 2, 58, 114, 170, 226 : sous-sol, puis niveaux 1 à 4 du sélecteur original.
- Les paliers se trouvent huit cases plus loin : pièces 8, 33, 36, 49 et 59.
- Actionner l’objet 73 appelle la cabine au palier actuel : la case précédente devient vide, la nouvelle
  prend l’ID 63, l’ancienne sortie sud ferme (flag 10), celle du palier d’appel ouvre (0).
- Les survivants déjà dans la cabine sont transportés ensemble ; celui qui appelle reste au palier.
  L’essai place Alexandre dans la cabine et Patrick à la pièce 36 : Alexandre passe de l’indice 58 à 114,
  Patrick reste à 122. Les autres personnages restent sur le toit.
- L’objet 74 ouvre le choix de cinq étages. Le déplacement préserve les portes particulières (flags >=15).
  La pièce 59 conserve donc son flag 30 tant que l’appel extérieur ne l’a pas ouvert.
- Les essais avec `#6712=0` et `#6712=1` déplacent tous deux la cabine : aucun verrou électrique
  supplémentaire n’est ajouté au prototype.
- Routines : appel `#9A2B`, sélecteur `#9A95`, déplacement `#9B11`, transport des personnages `#9B6D`,
  adaptation des sorties `#9B8C`, contrôle d’entrée au palier supérieur `#9C1A`.
- Le prototype garde la porte ouverte si l’utilisateur revalide l’étage actuel, pour éviter de coincer
  le groupe par une sélection sans déplacement.

## Fenêtres et corde

Après ouverture, les sorties 0xFA des pièces 35 et 38, et la sortie déjà ouverte de la pièce 46,
ne sont pas des passages ordinaires :

| Départ | Direction | Arrivée avec corde active |
| --- | --- | --- |
| 35 | Ouest | 64 |
| 38 | Est | 66 |
| 46 | Nord | 65 |

Le contrôle lit l’objet **actif**, pas seulement l’objet tenu : `character+39 == 41`.
Sans corde active, la tentative dans la pièce 38 laisse le personnage au départ mais le rend indisponible (état 255).
Avec corde active, la corde est déposée sur la dernière case libre du décor de départ (indices 39 à 1),
retirée de l’inventaire et désactivée, puis le personnage rejoint la scène extérieure correspondante.
Si aucune case admissible n’est libre, la traversée échoue sans consommer la corde.
Routines : `#9C58–#9CE9`, recherche de case libre `#9CA4`, retrait d’objet `#A3E9`.

## Sauvegardes du prototype

Le format 0.3 ajoute la carte mobile ; validation avant toute mutation, une seule cabine et uniquement
les cinq positions autorisées. Les sauvegardes 0.2 restent chargeables avec le plan initial.
Le nom de fichier local est conservé afin de retrouver les sauvegardes existantes.
