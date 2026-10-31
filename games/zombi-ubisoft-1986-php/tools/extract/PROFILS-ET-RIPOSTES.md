# Profils ennemis et ripostes — version 0.9

## Valeurs observées dans le programme CPC

`probe-encounter-stats.py` prépare le lieu de départ et la phase (`#6713`). Deux
essais sans remplacement du tirage observent naturellement les seize paires de chaque
phase. Les autres remplacent uniquement `LD A,R` par `LD A,n` en `#8AFF` pour fournir
une entrée aléatoire connue ; le calcul suivant reste intact.

| Phase | Première jauge | Deuxième jauge | Somme |
| --- | --- | --- | --- |
| Zombies | 26 + (R & 15), donc 26–41 | 58 moins la première, donc 32–17 | 58 |
| Pillards | 30 + (R & 15), donc 30–45 | 65 moins la première, donc 35–20 | 65 |

Les paramètres viennent de `#8129/#8141`, le calcul de `#8AFF–#8B09`.
Les écritures de `#8B04/#8B09` confirment les valeurs. La paire fixe 31/27 du
prototype précédent n'était qu'un des profils possibles des zombies.

Pour les pillards, `#814A` tire aussi le score `(R & 63) + (R & 31) + (R & 7)`.
Un score inférieur à 75 arme le tir de riposte (`#670E`). Il ne faut pas assimiler
cette condition à une probabilité uniforme de 75 %. Les essais de frontière donnent
score 73 → armé, score 76 → non armé.

## Cadence, report et dégâts

Le compteur global `#6717` progresse à 50 Hz. Tous les 256 ticks, l'interruption
`#8020–#802C` copie le flag d'armement vers le flag en attente `#670B`, uniquement
pendant la phase pillards. Il s'agit d'un flag, pas d'une accumulation de balles.
Deux déclenchements observés sont séparés de 256 trames, soit 5,12 secondes.

La boucle principale appelle `#8A65` pour traiter le flag. Les sélecteurs, la visée
et le corps à corps bloquent ce traitement, tandis que l'interruption peut continuer
à préparer le tir. Une fois revenu dans la boucle principale :

- Sans ennemi dans la pièce, la routine revient sans infliger de dégâts.
- Le score tiré doit dépasser la moitié de la deuxième jauge de l'adversaire.
- Un échec laisse le flag en attente : la boucle peut réessayer sans attendre une
  nouvelle période de 5,12 secondes.
- Un succès retire 6 à F puis 6 à H et efface le flag. Les captures passent de
  70/80 à 64/74, puis 58/68.
- Les écritures sont séquentielles : avec F=6, le résultat est F=0/H=80 ; avec
  H=6, le résultat est F=0/H=6. H n'est pas réécrite dans ces issues fatales.

Le compteur est aussi contrôlé à 512 ticks (`#818A/#8192`) pour engager le corps
à corps si le joueur reste face à un ennemi. Le délai est global et n'est pas
recommencé à chaque changement de pièce. La fin d'une visée sans victoire rejoint
directement le corps à corps (`#94AF`), sans traiter d'abord le tir différé.

`probe-ranged-reply.py` fournit des entrées RNG constantes dans deux instructions,
et certains essais fournissent un flag en attente ou une jauge proche de zéro.
La cadence de l'interruption, la décision de toucher et les soustractions restent
celles du programme. Les cas incluent adversaire non armé, pièce vide, phase zombie,
score trop faible, frontière d'armement et issues fatales. `verify-ranged-stats.py`
vérifie 24 propriétés dans les captures et les journaux.

## Portage, sauvegarde et limites

Les nouveaux adversaires reçoivent désormais une paire du bon profil et un attribut
de tir pour les pillards. Un nouvel adversaire après une victoire, un nouvel arrivant
dans une pièce vide et le changement de phase produisent un nouveau profil.
La graine portable déjà sauvegardée fournit les tirages ; ce n'est pas la séquence R du Z80.

Les profils et attributs restent attachés à l'adversaire courant d'une pièce, afin
de conserver les blessures introduites dans la sauvegarde 0.8. Le CPC relance ces
tirages plus fréquemment dans sa boucle de menu : cette différence reste explicite.
Les réponses différées sont traitées à la cadence des images du navigateur, pas au
coût exact des appels firmware. Les contrôles modernes autorisent aussi l'annulation
et les raccourcis pendant les combats, contrairement au blocage strict de certaines
boucles originales.

La sauvegarde 0.9 ajoute `alertTicks`, `rangedPending`, `rangedArmed` et l'attribut
`shooter` des pièces. Un tir sauvegardé en attente peut donc partir à la reprise ;
un tir déjà consommé n'est pas rejoué. Les anciens formats 0.2–0.8 démarrent cette
horloge à zéro et donnent aux adversaires déjà présents l'attribut non armé inconnu,
sans supprimer leurs blessures. Les prochains adversaires suivent les nouvelles règles.

Le raccourci **U** utilise l'objet tenu et permet notamment de déclencher la visée
après l'engagement du corps à corps. Les autres contrôles restent disponibles.
`RangedStatsTest` couvre profils, cadence, report, sauvegarde, frontières et validation.
`browser-ranged.cjs` emploie des préconditions contrôlées puis les mises à jour réelles
du navigateur et les touches ordinaires. L'évasion complète reste un test séparé
depuis une nouvelle partie, sans injection d'objets, de positions, de santé ou d'ennemis.
