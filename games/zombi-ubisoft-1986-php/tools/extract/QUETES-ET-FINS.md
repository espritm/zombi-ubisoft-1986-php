# Objets de quête, véhicules et fins — 30 octobre 2026

Neuf contrôles avancés passent (verify-quests.py), en complément des douze contrôles précédents. Les préconditions contrôlées sont conservées dans chaque results.json et dans extracted/quest-rules.json. Les essais ne constituent pas une partie terminée naturellement de bout en bout.

## Quatre intérieurs supplémentaires

Les 86 identifiants du plan ne comprennent pas les intérieurs de véhicules. La table de fiches se prolonge jusqu’à l’ID 90 : trois cabines de camion (87–89) et le cockpit d’hélicoptère (90).

Ouvrir la porte du véhicule transforme l’objet 1..4 en 14..17. L’action 16 fait entrer le personnage, avec +32 = objet ouvert +73 ; le lieu extérieur du personnage est conservé. Dans l’essai, Alexandre utilise sa clé initiale 47, ouvre la porte 4 →17 et entre dans le cockpit 90 par les commandes normales. La capture a été vérifiée.

Les quatre intérieurs ont été ajoutés à l’extraction : **90 scènes et 90 fonds**. Les 90 recompositions fond+objets correspondent pixel par pixel aux rendus contrôlés originaux. Le plan exporté distingue rooms (86) et vehicle_interiors (4).

## Placard et lampe

Sans fournir d’objet à l’inventaire, Patrick ouvre le placard 13 de la pièce 38, qui devient 26, puis prend son premier contenu : l’objet 43 (lampe). L’objet entre dans son sac. Cette séquence complète le précédent essai d’activation de lampe, qui avait fourni celle-ci comme précondition.

La position de départ est contrôlée ; la marche complète depuis l’hélicoptère jusqu’au magasin n’a pas été rejouée dans cet essai.

## Fusible et électricité

L’essai fournit le fusible 51, le boîtier fermé 9 et l’interrupteur 62 dans une pièce sûre, pour isoler leur fonctionnement. Aucun flag d’électricité ou de fusible posé n’est écrit par le script.

| Étape | Sans gants actifs | Avec l’objet 45 activé |
|---|---|---|
| Interrupteur sans fusible | aucun effet | aucun effet |
| Boîtier ouvert (9 →22), tentative de pose | F :70→50, H :80→70 ; fusible conservé dans le sac | pas de dommage ; fusible retiré du sac et stocké en #66EB |
| Action sur l’interrupteur | électricité reste coupée | objet 62→67, #6712=1 |
| Action suivante | toujours coupée | objet 67→62, #6712=0 |

La décharge à mains nues interrompt donc la pose. Les captures et les états ont été vérifiés. L’obtention naturelle des gants et du fusible, ainsi que les effets complets sur tous les équipements, restent à rejouer.

## Tir et visée

L’objet 34 est fourni comme arme tenue. Pour séparer la préparation de l’attaque qui bloque les commandes, le script retire d’abord l’ennemi puis le remet au contrôle d’ennemi de la routine d’arme (#949E), avec les valeurs observées PV=31 et force=27. Le calcul de visée et de dégâts n’est pas modifié.

L’action 3 ouvre le viseur ; COPY déclenche le tir. Un tir réussi amène PV 31→0 et nombre d’ennemis 1→0 ; le personnage reste disponible et tient encore son arme. L’essai a été reproduit avec +38 à 0 puis 1. Ces deux succès ne quantifient pas le bénéfice de ce flag et n’établissent pas une règle générale de munitions. La routine du fusil 36 et l’approvisionnement naturel des armes restent à tester.

Un premier essai sans séparer la préparation du combat n’activait pas l’arme ; il n’est pas compté comme preuve de tir. Le script actuel décrit explicitement l’introduction contrôlée de l’ennemi.

## Carburant et départ

Dans la séquence contrôlée fuel-chain-controlled, les éléments de départ fournis sont l’outil 42 tenu par Patrick, une source fermée 5 et le bidon vide 52. Ils sont placés pour isoler les dépendances de la quête : cette disposition n’est pas une scène normale du jeu.

Le script **n’écrit aucun des deux flags carburant**. Les actions du jeu produisent :

1. Ouvrir la source (5→18), puis utiliser l’outil : #6710 (bidon) 0→1 ; message « BIDON REMPLI. ».
2. Refermer la source (18→5), puis ouvrir le réservoir de l’hélicoptère (6→19).
3. Utiliser l’outil avec le bidon présent : #6710 1→0, #6711 (réservoir) 0→1 ; message « RESERVOIR REMPLI. ».
4. Sélectionner Alexandre, qui possède la clé 47 dès le démarrage ; ouvrir la porte, entrer, interagir avec le contact (64→69).
5. Interagir avec la commande de départ 72 : écran final d’hélicoptère et « A SUIVRE ... », PC terminal #8553.

Un autre couple d’essais vérifie le refus de départ avec réservoir vide et le départ avec réservoir plein. Dans le membre positif de ce couple, le réservoir est fourni comme précondition ; la séquence complète ci-dessus confirme séparément que le remplissage par les actions originales mène aussi au départ.

L’écran final a été ouvert et conservé dans extracted/ending-victory.png. La localisation et le transport naturels de tous les composants, les camions et leurs déplacements restent à couvrir par une vraie route de jeu.

## Défaite globale

La routine #84C8 examine les quatre états : s’ils sont tous au moins 0x20, elle affiche la défaite globale (#8518, boucle terminale #851F). Un personnage au repos (0x10) ne satisfait pas ce test.

Dans l’essai, trois survivants sont préparés indisponibles à 0xFF ; Patrick reste vivant avec ses jauges intactes et perd le combat original. L’écran des quatre zombies apparaît et le PC atteint #851F. Il est conservé dans extracted/ending-defeat.png. Ce test valide le contrôle global, pas la mort naturelle des quatre personnages dans une même partie.

## Reproduire

```powershell
./analysis/run-quests.ps1
./analysis/run-quests.ps1 -ReuseObservations
```

Les DSK et ROM restent locaux ; aucun changement du dépôt du jeu ni publication. Les ressources de fin et des intérieurs sont également locales, hors du dépôt public.

## Prêt pour la suite

Le socle graphique et de nombreuses règles sont suffisamment documentés pour commencer un portage local testable : interface, 90 scènes, personnages indépendants, inventaire/équipement, déplacements, portes, consommables, repos, combat, éclairage et fins.

À conserver comme lacunes explicites : déroulement naturel de la quête, ascenseurs, verrous et clés particuliers, variantes d’armes, échanges entre personnages, sauvegarde/chargement et cas limites des longues durées. Un prototype partiel ne doit pas être présenté comme le remake complet et fidèle.
