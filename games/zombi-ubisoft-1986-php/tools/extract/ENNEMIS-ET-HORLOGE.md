# Circulation des ennemis — version 0.7

## Observation de l'original

`probe-enemy-clock.py` attend quatre périodes dans l'original CPC, en conservant sa
population initiale et son horloge. Seule la position du personnage observé est préparée
sur le toit. Les écritures sur les fiches des pièces révèlent les routines `#7DE4–#7E9A`.
Le nombre total d'ennemis passe de 56 à 59, 62, 65 puis 67. Le quota à entreposer passe
de 34 à 45 : une entrée atteint sa capacité lors de la dernière période.

Le compteur `#6720` est indépendant de l'heure affichée. La boucle principale appelle
la mise à jour lorsque son octet haut atteint `#3B`, soit 15 104 ticks de 50 Hz
(environ 302 secondes réelles). Ce délai est proche d'une heure de l'horloge du jeu,
mais ne se déclenche pas au changement du chiffre des heures.

La routine parcourt les pièces 1 à 62 dans l'ordre. Pour chaque ennemi présent au
début du traitement d'une pièce :

- Le tirage utilise `R & 7`. Les résultats 6 ou 7 laissent cet ennemi sur place.
- Les six autres choix correspondent à nord, est, sud, ouest, descendre, monter.
- Un passage doit avoir le code 0 ou 20. Une destination doit être une pièce 1 à 62
  avec moins de cinq ennemis. L'ascenseur 63 et les extérieurs sont exclus.
- Un échec de passage ou de destination permet de retenter, au plus dix fois.
- Le déplacement modifie immédiatement les deux pièces. Un ennemi peut donc être
  traité à nouveau s'il atteint une pièce visitée plus tard dans le parcours.

Après les déplacements, les accès nord de 19, est de 31 et ouest de 32 peuvent chacun
ajouter un ennemi. Le code de passage 10 bloque cet ajout ; le code 30 d'une porte
fermée ne le bloque pas. Une pièce contenant cinq ennemis n'en reçoit pas davantage.
Chaque ajout incrémente aussi le compteur `#6621`, utilisé avant l'arrivée des pillards.

`probe-enemy-gates.py` compare trois préconditions contrôlées : accès barricadés,
portes simplement fermées et pièces d'entrée saturées. Les états des portes et, dans
le dernier cas, les populations de ces trois pièces sont préparés. Le programme
original effectue ensuite seul ses déplacements, ses ajouts et ses changements de quota.
`verify-enemy-clock.py` vérifie 17 propriétés, y compris les couples d'écritures
source/destination et la conservation du nombre d'ennemis hors nouvelles entrées.

## Transcription et limites

Le prototype utilise la même cadence, le même ordre de parcours, les mêmes passages,
capacités et portes d'entrée. Un générateur de 16 bits remplace le registre matériel R.
Sa séquence ne reproduit pas celle d'un Z80 ; sa graine est sauvegardée pour conserver
la suite d'une partie. Les multiplications restent dans les limites des entiers signés
32 bits de PHP/WASM.

La sauvegarde 0.7 conserve `enemyTicks`, `enemySeed` et le quota devenu variable.
Les formats 0.2 à 0.6 restent chargés ; leur délai de circulation repart à zéro.
Après l'arrivée des pillards, le prototype conserve le quota terminé pour le suivi,
alors que le compteur natif est réutilisé : il ne conditionne plus l'arrivée à ce stade.

`EnemyClockTest` vérifie cadence, pause, migration des sauvegardes, reprise du même
tirage, capacité, exclusion de l'ascenseur et quota variable. `QuestRouteTest` attend
une période depuis une nouvelle partie puis termine la quête sans fournir d'objet,
de position, de santé ou d'ennemi. Le parcours de nettoyage suit le quota et permet
plusieurs passages si les ennemis ont changé de pièce.

`browser-enemy-clock.cjs` prépare uniquement la graine et un délai proche de son seuil,
puis laisse les images du navigateur déclencher la mise à jour. Il vérifie les comptes,
le quota, la pause et la sauvegarde. C'est un contrôle isolé, distinct du parcours neuf
au clavier dans `browser-escape.cjs`.

Les règles de tir, de dégâts et d'armes blanches restent provisoires. Cette version
n'ajoute pas un système de munitions supposé : l'analyse de leurs routines doit continuer.
