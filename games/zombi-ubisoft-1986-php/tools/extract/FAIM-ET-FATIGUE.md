# Faim, fatigue et récupération — version 0.14

## Mesures sur le CPC

`probe-deprivation.py` prépare les compteurs +29 et +30 d'Alexandre, sa jauge S
et certains états. Le compteur périodique est placé juste avant son seuil, puis
le programme réalise le contrôle réel. Les cas de réveil placent seulement le
compteur de sommeil à une trame de son seuil. Ce sont des préconditions contrôlées,
pas une partie ayant duré 27 heures affichées sans repos ni nourriture.

Le contrôle `#7D09–#7D64` incrémente d'abord les compteurs sur un octet. Les seuils
ci-dessous concernent leur valeur après incrément, et s'appliquent aux quatre fiches :

| Compteur | Valeur après incrément | Effet |
| --- | --- | --- |
| Sans nourriture, +29 | 0–6 | Aucun |
| Sans nourriture | 7–255 | S moins 4 |
| Sans repos, +30 | 0–18 | Aucun |
| Sans repos | 19–20 | S moins 4 |
| Sans repos | 21 | S moins 8 |
| Sans repos | 22–26 | S moins 12 |
| Sans repos | 27–255 | État 16, compteur de sommeil remis à zéro |

Le test de la valeur 22 en `#7D43` est inaccessible dans ce chemin : l'essai
21→22 retire bien 12, et non 8. Les deux compteurs passent de 255 à zéro ; leurs
pénalités sont alors inactives pour ce contrôle. Le délai est celui de santé
(15104 ticks), indépendant des changements de l'heure affichée.

Le sommeil forcé ne filtre pas l'état précédent. Les essais contrôlés d'un
personnage déjà endormi, mort ou marqué 255 donnent aussi l'état 16 si S reste
dans la plage vivante à la fin du contrôle. Cela ne rouvre pas une partie dont
la défaite globale a déjà arrêté la simulation. Tant que le compteur reste >=27,
le contrôle suivant recommence encore le compteur de sommeil : le prototype ne
remplace pas cette particularité par une récupération garantie après huit heures.
Aucun nouveau lit ou marqueur d'objet n'est créé par cette branche.

## Ordre des pertes et récupération

Les pertes de faim et fatigue précèdent la maladie et les corps. Chaque
soustraction conserve son résultat sur un octet, puis le contrôle final peut
donner l'état 255 si S>=128. Avec S=3 et le compteur de faim à 6 :

- Faim seule : S=255, état 255.
- Puis maladie déjà présente : S=0, état 64.
- Puis présence d'un corps : S=251, état 255.

L'ordre est donc conservé, plutôt que de plafonner toutes les pertes à zéro.

Le réveil automatique `#7CC4` rend 8 à S avec plafond et remet uniquement le
compteur de repos à zéro. Les essais donnent S 50→58 et 77→80, le compteur de
nourriture restant à 5. Quand réveil et contrôle périodique coïncident, le réveil
vient d'abord : S=58, état 0, nourriture 5→6 et repos 26→0→1. Le sommeil n'est
donc pas recommencé par la fatigue dans ce cas précis.

`probe-deprivation-consume.py` fournit un objet à Patrick puis utilise le menu
Consommer. La nourriture 55 donne S 50→54 et remet +29 de 6 à zéro. La boisson
54 donne F 50→52 et garde +29=6. Les deux conservent +30=20 et consomment l'objet.
L'approvisionnement naturel de ces objets n'est pas établi par ces essais.

`verify-deprivation.py` vérifie 126 propriétés de 26 scénarios périodiques/de réveil
et de deux consommations. Seuls les scripts et notes sont publics.

## Portage et sauvegarde

La version 0.14 remplace l'incrément approximatif au changement d'heure par le
contrôle périodique de santé. Les compteurs restent nommés `foodHours` et
`awakeHours` pour la compatibilité, mais représentent ces contrôles. Ils sont
conservés dans les sauvegardes ; la version courante exige 0–255. Les formats
0.2–0.13 restent lisibles et leurs éventuelles grandes valeurs sont ramenées
à l'octet bas, sans reconstruction des périodes passées.

Le sommeil forcé du personnage actif ouvre le choix d'un autre survivant.
Les menus et combats reportent le contrôle comme en 0.13, la pause l'arrête,
et une sauvegarde en attente ne double pas les pénalités au rechargement.
Le réveil et la réanimation sont désormais traités avant le contrôle périodique.

Le coût exact du firmware, les fenêtres précises des octets du compteur de sommeil
lors d'une très longue interruption et toutes les interactions de réveil manuel
restent à vérifier. La représentation et le nettoyage des corps des personnages
restent partiels. Le portage n'émule pas les blocages d'affichage de jauges nulles.

## Validation

- 126 contrôles CPC, 84 tests PHP, analyse statique et démarrage de 600 images réussis.
- Navigateur local : faim et fatigue reportées puis sauvegardées/rechargées,
  indépendance de l'heure affichée, sommeil forcé et remise à zéro répétée,
  réveil, consommation et compteurs séparés.
- Même scénario réussi sur JumPHP, incluant le réveil et le contrôle périodique
  sur la même mise à jour. Les effets de maladie restent vérifiés localement.
- Captures officielles et lint réussis ; page publiée contrôlée et inspectée
  sur ordinateur/téléphone, sans image cassée, débordement ni erreur JavaScript.
- Construction navigateur/site et inspection de la page locale également réussies.
- Évasion complète sur JumPHP réussie depuis une nouvelle partie : 34 corps
  congelés, trois camions garés, arrivée des pillards, sauvegarde/rechargement et
  décollage des quatre survivants. Une période de circulation ennemie est franchie.
  Le parcours n'injecte ni positions, ni objets, ni jauges, ni ennemis ; les
  privations longues restent vérifiées séparément avec les fixtures décrites ici.
