# Corps, arrivée du camion et évasion — version 0.6

Les essais `probe-quest-gates.py` démarrent le vrai programme CPC. Leurs préconditions
sont contrôlées : position, inventaire, compteur de corps et stationnement des camions.
La phase des ennemis et les indicateurs de carburant ne sont pas forcés.
Ce sont des essais de règles isolées, pas une traversée naturelle complète du CPC.

- Le compteur initial d'ennemis à entreposer vaut 34 (`#6621`).
- Poser le corps 53 dans la chambre froide 11 incrémente `#6620` et le consomme.
  Ailleurs, cela ajoute un corps à la pièce, représenté dans les cases 30 et 31.
  Prendre le dernier corps remplace ces deux marqueurs par 255 (`#A22C`).
- `#828D–#8367` déclenche automatiquement l'arrivée lorsque le quota est atteint et
  que les trois entrées 64, 65 et 66 sont barricadées. La radio n'est pas un préalable.
  Les essais négatifs gardent un corps manquant ou une entrée non barricadée.
- La phase `#6713` passe de 1 à 0. Le nouveau camion en 78 possède le bouchon 5,
  en case 17, et deux ennemis. L'entrée centrale se rouvre et son ancien camion retourne en 77.
- L'outil 49 en main permet d'ouvrir l'accès au local 59 depuis l'ascenseur au dernier
  étage : OUVRIR, F, NORD. Sans outil, l'accès reste verrouillé.
- La lumière de `#9D57–#9D8D` est partagée : un survivant avec la lampe active éclaire
  les autres présents à la même position. Cela permet de porter les gants et poser le fusible.
  `probe-shared-light.py` confirme l'affichage noir, puis éclairé avec un autre porteur
  dans la pièce, puis noir après séparation. Seuls la position et l'objet actif de ce
  porteur sont préparés ; le courant et l'équipement du personnage observé restent inchangés.

`verify-quest-gates.py` vérifie les états des cinq essais et la création du bouchon.
Le fond du camion et les visages des deux types d'ennemis sont reconstruits par `package.py`.
Ils restent privés, comme les autres ressources des disquettes.

## Parcours du prototype

`QuestRouteTest` réalise une évasion depuis un `new World`, avec les actions ordinaires :
lampe partagée, arme, outil, clés, gants/fusible, courant, trois camions, 34 corps dans
la chambre froide, arrivée, siphonnage, remplissage de l'hélicoptère et quatre survivants à bord.
Les aides du test sélectionnent des actions et des cases ; elles ne modifient ni position,
ni inventaire, ni santé, ni ennemis. `browser-escape.cjs` reproduit ce parcours par les touches
et vérifie aussi la sauvegarde après l'arrivée. Son pont PHP lit seulement l'état pour les assertions.

Cela valide la chaîne d'évasion du prototype. Les déplacements périodiques des ennemis,
leurs réapparitions, les munitions et l'équilibrage des combats ne reproduisent pas encore
tous les comportements CPC. Commencer une nouvelle partie est conseillé pour tester la chaîne entière.

## Validation du 31 octobre 2026

- 33 tests PHP, 2 205 assertions ; analyse statique, syntaxe et démarrage de 600 images réussis.
- Dix contrôles des portes/arrivées dans l'original, plus trois états d'éclairage partagé.
- Régressions navigateur : déplacements, sauvegarde, ascenseur, corde, terminal et camions.
- Évasion complète dans Chromium local et sur `play.jumphp.net`, avec les quatre survivants et sauvegarde/rechargement
  après l'arrivée. Les touches doivent rester assez longtemps enfoncées pour être observées
  par la boucle du jeu ; les pressions du script sont de 45 ms, avec 65 ms entre les touches.
- Page d'accueil locale et publiée vérifiée sur ordinateur et téléphone.
