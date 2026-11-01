# Échanges et contenants — version 0.18

`sources/probe-storage.py` exécute 19 scénarios contrôlés dans le jeu CPC ;
`sources/verify-storage.py` vérifie **96 assertions**. Les inventaires, cases et
contenus initiaux sont préparés, puis les transferts utilisent les vraies commandes
POSER/PRENDRE, sans modification de leurs routines. Les touches et préconditions
sont enregistrées dans `analysis/runs/storage-*/results.json`. Les dumps,
captures et rapports `storage.json` restent privés.

## Règles confirmées

- `#A1A2–#A1C6` compte les emplacements vides dans les quatre cases du sac et
  la main. Il faut **deux places libres** avant d'ouvrir le choix d'un objet dans
  la pièce, même si une seule prise est prévue. Zéro ou une place libre refuse
  l'ouverture du sélecteur ; deux permettent la prise dans la première case vide
  du sac. Les essais distinguent une main vide et une case de sac vide.
- `#A38F–#A3A1` dépose dans le premier zéro d'un contenant, dans la limite de
  sa capacité. Une armoire de deux cases pleine refuse le dépôt. Un corps possède
  quatre cases de dépôt : les quatre occupées refusent aussi, sans retirer l'objet
  porté ni désactiver son usage.
- Un corps identifié 30 peut être placé dans une armoire 23 ou dans le dossier
  d'un autre corps 29, puis repris. Son propre contenu reste dans son dossier
  d'identité ; il n'est pas copié dans le contenant parent ni vidé. Les scénarios
  `roundtrip-cupboard` et `roundtrip-body` vérifient le retour en inventaire.
- Les contenants 27 et 28 sont aussi transportables. Le sélecteur propose leur
  identité avant leurs objets, comme pour les corps 29–32. Leur prise enlève les
  marqueurs de la pièce et garde leur contenu. Ils ont respectivement deux et
  quatre cases dans les tables originales. Les meubles 22–26 restent fixes.
- `#A29A–#A2A1` refuse de poser boisson 54 et nourriture 55. Leur inventaire et
  le contenant visé restent inchangés.
- `#A3E9–#A415` cherche la première occurrence de l'objet, du début du sac jusqu'à
  la main, et désactive son usage si son identifiant correspond. Avec une lampe
  dans le sac et une autre tenue, c'est la première case du sac qui est vidée,
  même si le joueur a choisi l'exemplaire tenu. La lampe en main reste portée,
  mais est désactivée. Les deux choix sont mesurés séparément.

## Portage

La version 0.18 applique le garde des deux places avant PRENDRE, le premier trou
du contenant, les refus de dépôt, l'ordre sac→main pour POSER et la prise des
contenants transportables. Les dossiers restent plats et indexés par identité :
un corps rangé conserve donc ses objets lors des transferts et des sauvegardes.

La cinquième case de secours ajoutée en 0.16 conserve toujours le cinquième objet
transféré après un décès et reste récupérable. Elle n'est **pas** une cinquième
place de dépôt ordinaire : POSER cherche seulement parmi les quatre premières.
Cela respecte la capacité native sans reproduire la corruption mémoire décrite
dans [CORPS-ET-INVENTAIRES.md](CORPS-ET-INVENTAIRES.md). Les anciennes sauvegardes
0.2–0.17 restent acceptées ; aucun nouvel état persistant n'est ajouté et les
anciens objets déjà conservés dans cette cinquième case sont préservés.

## Parcours et validation

Le parcours automatique d'évasion utilise désormais de vrais dépôts temporaires :
le bidon est posé dans la pièce 52 avant la prise des gants, puis repris après la
remise du courant ; les gants devenus inutiles y sont laissés. Le nettoyage revient
au congélateur dès qu'il reste moins de deux emplacements libres. Aucun objet,
emplacement, santé ou ennemi n'est injecté dans ce parcours. Les fixtures de stockage,
distinctes du parcours, servent à isoler les règles de transferts.

`StorageTest` vérifie garde, capacités, cinquième case de secours, doublons,
contenants transportables et corps imbriqués avec sauvegarde/rechargement.
`tools/browser-storage.cjs` reprend ces contrôles avec les commandes clavier et
souris. Les recettes CPC et le parcours restent reproductibles ; les timings
firmware, d'autres usages d'objets et les autres routines de retrait d'objets avec
doublons ne sont pas déclarés équivalents au CPC.

Validation locale : 96 assertions CPC, 116 tests PHP (dont l'évasion adaptée),
PHPStan, lint et smoke 600 images réussis. Le scénario navigateur de stockage
passe sur la construction distante locale, y compris transferts imbriqués,
sauvegarde/rechargement, contenants transportables, capacité de dépôt et garde
de prise. Le titre, le jeu et la capture de sélection du corps imbriqué sont
inspectés. Les refus de contenant plein conservent le sélecteur du prototype,
contrairement au retour au menu natif ; la fidélité des menus n'est pas complète.

Après publication de `fc0bb14`, les transferts et sauvegardes passent aussi sur
JumPHP. Le parcours complet depuis une nouvelle partie y réussit avec les quatre
survivants : courant, trois camions, 34 corps congelés, arrivée des pillards,
sauvegarde/rechargement, ravitaillement puis décollage. Une période de circulation
ennemie s'écoule pendant le parcours. Le pont PHP ne fait que lire l'état pour
les assertions : aucune injection d'objet, position, santé ou ennemi. La capture
de victoire est inspectée. Ce succès valide le parcours du prototype, pas une
équivalence générale au CPC. La page publiée passe aussi les contrôles ordinateur
et téléphone, sans image manquante, erreur JavaScript ni débordement horizontal.
