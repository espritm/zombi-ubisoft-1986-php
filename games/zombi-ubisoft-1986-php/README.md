# Zombi — prototype CPC 0.6

Portage PHP en cours du Zombi français Amstrad CPC publié par Ubi Soft en 1986.
Cette version remplace la démonstration de plateformes initiale par les scènes et objets extraits des deux disquettes.

## Tester

Depuis la racine du dépôt, avec Docker :

```bash
bin/jumphp install zombi-ubisoft-1986-php
bin/jumphp test zombi-ubisoft-1986-php
bin/jumphp run zombi-ubisoft-1986-php
bin/jumphp runtime:download
bin/jumphp build:remote zombi-ubisoft-1986-php
JUMPHP_WEB=remote bin/jumphp webshot zombi-ubisoft-1986-php 7 '[[1,"Enter"]]'
```

Les ressources d’origine doivent être reconstruites depuis vos propres disquettes et ROMs :
voir [tools/extract/README.md](tools/extract/README.md). Elles ne sont pas dans ce dépôt.
Sur JumPHP, elles sont chargées séparément via les fichiers privés du projet.

Au titre : Entrée pour jouer, C pour continuer, G pour la galerie des 90 décors, F1 pour l’aide.
En partie : flèches pour parcourir les actions/listes ou sélectionner une case, Entrée/Espace pour valider,
Tab pour annuler, Échap pour la pause, M pour les directions, I pour équiper, C ou 1–4 pour les personnages.
La souris permet de choisir les portraits, icônes, objets et listes. F5 sauvegarde, F9 recharge.
Pour une porte sans case sélectionnable : choisir OUVRIR ou FERMER, puis F et la direction.
Les sauvegardes restent sur le navigateur utilisé et ne sont pas compatibles avec le CPC.

Parcours de contrôle : départ sur le toit (Patrick), M → Est → Entrée pour la terrasse,
puis M → Descendre → Entrée pour l’escalier, puis Descendre pour le niveau 3.
Choisir Alexandre avec 1 : il est toujours au toit et possède la clé de l’hélicoptère.
Sauver avec F5, se déplacer, puis F9 doit restaurer les positions et inventaires.
Pour recommencer : Échap, R, Entrée. En pause, le temps du jeu est arrêté.

## Périmètre

- 90 fonds et leur composition d’objets comparés pixel par pixel à l’original ; portraits, carrousel, fonte CPC et musique d’introduction.
- Quatre survivants indépendants, passages ordinaires, portes, contenants, inventaire et objet actif, lumière,
  boissons/nourriture, repos, réanimation et défaite.
- Électricité (fusibles et gants), transfert de carburant, entrée dans les véhicules, contact et condition de décollage.
- Combat alterné et visée : première implémentation, équilibrage provisoire.
- Sauvegarde locale versionnée et validation des données chargées.
- Ascenseur mobile sur cinq niveaux, appels depuis les paliers et transport de tous les passagers.
- Trois passages avec corde : activer la corde avant de franchir l’ouverture. Sans corde active, le personnage chute.
- Sauvegarde du plan mobile et chargement conservé pour les anciennes parties 0.2.
- Trois trajets de camion : clé en main, contact puis démarrage. Les passagers suivent le camion et certains accès se ferment.
- Ordinateur : commandes au clavier (13 lettres/espaces maximum) ou menu cliquable ; Entrée valide, Échap quitte.
- Nouveaux décors après déplacement des camions et sauvegarde de ces changements ; parties 0.2 et 0.3 conservées.
- Lecteur vidéo : allumer le lecteur, poser la cassette dessus, allumer le téléviseur puis actionner le lecteur.
  La séance avance l’horloge de 80 minutes et entraîne les survivants présents dans la pièce.
- Sauvegarde de la cassette insérée ; chargement conservé pour les parties 0.2, 0.3 et 0.4.

- Corps transportables, entreposage dans la chambre froide et arrivée automatique du camion de carburant.
- Accès aux clés par l'outil 49, éclairage partagé et sauvegarde des compteurs de progression ; migration des parties 0.2 à 0.5.

**Ce n’est pas encore une adaptation complète.** Certains passages spéciaux,
usages secondaires d’objets, munitions et déplacements/réapparitions des ennemis restent à compléter.
Un parcours d'évasion du prototype depuis une nouvelle partie est testé, jusqu'au décollage des quatre survivants,
sans injection d'objet, de position, de santé ou d'ennemi. Cela ne prouve pas une équivalence complète au CPC.
Les chronomètres de repos/réanimation proviennent des observations ; l’horloge et le combat doivent encore être affinés.
Les observations ajoutées pour cette version sont détaillées dans [ASCENSEUR-ET-CORDE.md](tools/extract/ASCENSEUR-ET-CORDE.md).
Les trajets, les réponses de l’ordinateur et la correction des corps sont documentés dans [CAMIONS-ET-TERMINAL.md](tools/extract/CAMIONS-ET-TERMINAL.md).
Le parcours toit → cassette → lecture → sauvegarde/rechargement → retour au toit passe dans le navigateur,
sans injection d’objet, position, santé ou ennemi.
Voir [VIDEO-ET-PARCOURS.md](tools/extract/VIDEO-ET-PARCOURS.md) et `tools/browser-route.cjs`.
Le parcours d'évasion est décrit dans [EVASION.md](tools/extract/EVASION.md), avec ses préconditions de vérification CPC et ses limites.
Commencer une nouvelle partie est conseillé pour tester toute la chaîne de la version 0.6.

## Page et publication

`tools/site/shots.sh` régénère les captures avec le moteur. `tools/site/images.py` (Pillow) fabrique les images
du site ; les fichiers restent ignorés. La miniature publique est dessinée sans ressource originale par `tools/site/thumbnail.py`. Il copie aussi les images vers `assets/site/`
pour l’envoi privé et reproduit localement leur URL `play/private/assets/site/`.
Après `bin/jumphp build:site`, relancer `images.py` avant `bin/jumphp siteshot` pour créer ce miroir dans `dist/site/`.
La page utilise les URL privées de play.jumphp.net pour ses métadonnées sociales.
Envoyer `assets/original/` et `assets/site/` avec `upload_private_files`, puis pousser le code et `sync_project`.
Ne jamais ajouter au Git les disquettes, ROMs, dumps, images ou enregistrements d’origine.

Crédits originaux : Ubi Soft, Cadin, Bonan, Daher et Marchiset (1986). Prototype indépendant.
