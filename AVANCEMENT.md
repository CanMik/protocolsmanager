# Avancement — compatibilité GLPI 12

## Objectif

Mettre à jour le plugin Protocols Manager pour le rendre compatible avec GLPI 12, en préservant son comportement et les données existantes.

## Étapes

- 2026-10-16 — Démarrage : inspection de l’état du dépôt. Le dépôt est sur la branche `v11`. Des modifications locales préexistantes sont présentes ; les fichiers de plugin signalés par Git n’ont pas de diff de contenu (changements de permissions uniquement). Elles sont conservées et ne seront pas incluses dans les commits de migration.
- 2026-10-16 — Compatibilité déclarée : correction de la plage de versions GLPI dans `setup.php` (GLPI 11.x et 12.x, borne haute exclusive `13.0.0`) et du contrôle de prérequis qui rejetait auparavant GLPI 12. Mise à jour des prérequis documentés dans `README.md`.
- 2026-10-16 — Adaptations GLPI 12 : les formulaires dynamiques n’appellent `Session::getNewCSRFToken()` que si cette méthode existe, car GLPI 12 s’appuie sur les en-têtes Fetch Metadata et n’attend plus de jeton caché. Ajout des types de retour attendus par les méthodes d’onglet du plugin.
- 2026-10-16 — Version du plugin portée à `1.6.0.3` pour que GLPI détecte la mise à jour.
- 2026-10-16 — Métadonnées catalogue : ajout de la version `1.6.0.3` et compatibilités GLPI 11.0 et 12.0 dans `plugin.xml`.
- 2026-10-16 — Revue statique des changements GLPI 12 : confirmation de la transition CSRF vers les en-têtes Fetch Metadata et de l’ajout de types natifs aux API cœur. Pas de validation d’exécution effectuée : aucun environnement GLPI 12 de test n’est fourni dans le dépôt.
- 2026-10-16 — Métadonnées auteur : attribution du plugin à « Cheilmann » dans `setup.php` et `plugin.xml`, à la demande de l’auteur.
- 2026-10-16 — Rafraîchissement des métadonnées GLPI : nouvelle version `1.6.0.4` pour que GLPI recopie l’auteur dans ses informations enregistrées sur le plugin. L’interface devra appliquer la mise à jour détectée.
- 2026-10-16 — Bug PDF : qualification de `QueryExpression` dans `Glpi\DBAL` lors de la requête qui calcule le numéro de protocole. GLPI 12 a déplacé cette classe hors de l’espace de noms global.
- 2026-10-16 — Bug d’icône : ajout de l’icône Tabler `ti ti-file-text` à l’onglet utilisateur avec `createTabEntry()` sur GLPI 12, en conservant l’appel historique sur GLPI 11.
- 2026-10-16 — Version du correctif portée à `1.6.0.5` pour exposer les deux corrections à GLPI et dans les métadonnées du catalogue.
- 2026-10-16 — Logo PDF : réduction de la taille maximale par défaut de 200 px à 160 px dans chaque dimension; les dimensions explicitement configurées dans les modèles restent inchangées. Version portée à `1.6.0.6` pour faire détecter la mise à jour par GLPI.
- 2026-10-16 — Modèle PDF : ajout d’une couleur d’accent configurable par modèle, avec migration de la colonne `theme_color` et valeur brun doré par défaut. Version portée à `1.6.0.7` pour faire détecter la mise à jour par GLPI.
- 2026-10-16 — Modèle PDF : ajout d’une couleur de texte configurable pour les en-têtes colorés, blanche par défaut. Version portée à `1.6.0.8`.

## À faire

- Valider l’installation/mise à jour et les parcours de génération PDF, gestion des droits et envoi d’e-mails sur une instance de test GLPI 12 avant déploiement en production.

## Nouvelle fonctionnalité — procès-verbal de mise en service d’une immobilisation

### Objectif et règles de conservation

Ajouter au plugin un parcours distinct de génération d’un procès-verbal de mise en service pour du matériel GLPI. Le PDF reprend les données de gestion des matériels et permet d’ajouter les informations propres à la mise en service et aux validations.

- [x] Garder le parcours, le contenu et le comportement des documents d’inventaire utilisateur indépendants de cette nouvelle fonctionnalité.
- [x] Réutiliser le logo et les couleurs configurées pour les PDF existants, sans modifier leur rendu ni leurs réglages.
- [x] Tolérer les champs GLPI absents ou vides : omettre leur valeur dans le PDF sans avertissement PHP ni erreur de génération.
- [x] N’enregistrer aucune information spécifique au PV dans les champs des objets matériels de GLPI ; les saisies du formulaire servent à produire le document.
- [ ] Maintenir la compatibilité avec les versions GLPI prises en charge par le plugin et vérifier les droits de lecture des matériels sélectionnés.

### 1. Repérage des données GLPI et conception

- [ ] Identifier les types de matériels accessibles dans le parcours proposé et leurs tables/champs communs dans GLPI 11 et GLPI 12.
- [ ] Repérer les champs de l’onglet Gestion correspondant au numéro d’immobilisation, au fournisseur, à la date d’acquisition, au numéro de facture et à la valeur d’acquisition HT.
- [ ] Repérer les champs communs d’un matériel : nom, type, numéro d’inventaire, numéro de série et lieu d’affectation.
- [ ] Déterminer comment obtenir le premier niveau du lieu sélectionné (site d’affectation) et traiter un lieu non renseigné.
- [ ] Vérifier le format et les cas possibles du numéro d’immobilisation, notamment les valeurs absentes et les objets de types différents.
- [x] Définir le regroupement : un PV peut couvrir un ou plusieurs objets ayant exactement le même numéro d’immobilisation ; les objets d’autres numéros ne sont pas ajoutés au document.
- [ ] Définir le calcul du montant total d’acquisition HT pour les objets du groupe ; ne pas présenter un total partiel comme un total complet si une valeur d’acquisition manque.
- [x] Déterminer l’entrée utilisateur : un onglet distinct sur les fiches matériel, séparé de l’onglet d’inventaire utilisateur.
- [x] Identifier la source du prénom et du nom de l’utilisateur connecté qui signe la déclaration, ainsi que le format de la date courante.

### 2. Parcours de génération et sélection des objets

- [x] Ajouter un point d’entrée distinct pour le PV matériel, sans détourner la génération des documents d’inventaire utilisateur.
- [x] Permettre de produire un PV pour un seul objet.
- [x] Permettre de produire un PV regroupant plusieurs objets partageant le même numéro immo, avec un choix clair des objets concernés.
- [x] Filtrer ou valider côté serveur les objets sélectionnés afin qu’ils aient tous le même numéro immo ; refuser proprement une sélection incohérente.
- [x] Vérifier côté serveur les droits de lecture sur chaque objet, sans faire confiance aux seuls identifiants soumis par le formulaire.
- [x] Préserver les contrôles d’accès du plugin et échapper les valeurs affichées ou intégrées au HTML/PDF.
- [x] Préremplir la date effective de mise en service avec la date d’acquisition du matériel ; pour un groupe, ne préremplir que si toutes les dates sont identiques.
- [x] Précocher les quatre états « Installée », « Testée », « Réceptionnée conforme » et « Mise en exploitation », tout en permettant leur modification avant génération.
- [x] Proposer la saisie de la description d’utilisation prévue et des observations éventuelles.
- [x] Proposer le choix du lieu d’établissement parmi deux boutons radio : Eichhoffen et Barr.
- [x] Préremplir la date « Document établi le » avec la date du jour.
- [x] Afficher une erreur compréhensible si aucun matériel exploitable n’est sélectionné, sans produire de PDF incomplet par accident.

### 3. Contenu du PDF

- [x] Reprendre le numéro immo en tête du document.
- [x] Ajouter le chapitre 1 « Identification de l’immobilisation » avec, pour chaque objet, le numéro immo, la désignation (nom de l’objet et type), le numéro d’inventaire, le numéro de série, le fournisseur, la date d’acquisition, le numéro de facture, la valeur d’acquisition HT et le site d’affectation (premier niveau du lieu).
- [x] Pour un PV groupé, lister clairement les données de chaque objet et ajouter le montant total d’acquisition HT si les valeurs de tous les objets sont connues.
- [x] Ajouter le chapitre 2 « Mise en service » avec la déclaration « Je soussigné(e) <prénom nom de l’utilisateur en cours>, certifie que l’immobilisation décrite ci-dessous a été : » et les quatre états cochés/modifiables.
- [x] Ajouter la date effective de mise en service et la description libre de l’utilisation prévue.
- [x] Ajouter le chapitre 3 « Observations éventuelles » avec le texte libre multi-ligne.
- [x] Ajouter le chapitre 4 « Validations » et le tableau « Fonction | Nom | Signature | Date » avec les quatre lignes demandées.
- [x] Sous le tableau, afficher « Document établi le : <date actuelle>, lieu <Eichhoffen ou Barr> ».
- [x] Réutiliser le logo configuré et appliquer la couleur choisie aux titres et mots importants du nouveau PDF.
- [ ] Vérifier les sauts de page et les groupes longs afin que tableaux, observations et zones de validation restent lisibles et ne soient pas coupés de façon gênante.
- [x] Ne pas afficher de valeur « null » ou fictive pour les données GLPI vides ; conserver le tableau d’identification complet avec des cellules vides.

### 4. Implémentation et intégration

- [x] Isoler le nouveau formulaire, la préparation des données et le rendu du PV dans une classe et un point d’entrée dédiés.
- [x] Réutiliser Dompdf, le logo et les réglages de couleur sans changer le traitement de l’inventaire utilisateur.
- [x] Formater les dates et montants, et prendre en charge les caractères accentués et textes multi-lignes.
- [x] Protéger le rendu contre le HTML injecté par les noms de matériels, fournisseurs et champs libres.
- [ ] Vérifier en exécution qu’un champ facultatif vide ne déclenche ni notice, warning, erreur SQL ni échec Dompdf.
- [x] Mettre à jour les libellés et la documentation utilisateur du plugin.
- [x] Incrémenter la version du plugin et les métadonnées catalogue conformément à la convention du dépôt.

### 5. Vérifications et livraison

- [x] Relire le diff ciblé : seuls les fichiers du PV, sa documentation, les métadonnées et le journal d’avancement sont concernés ; aucun fichier de `dompdf/`, des captures ou des traductions générées n’est inclus.
- [x] Vérifier statiquement les chemins de génération, les droits, l’échappement et le comportement des champs absents ou vides ; `php -l` passe pour les trois fichiers PHP modifiés.
- [ ] Vérifier un PV d’un matériel avec tous les champs remplis.
- [ ] Vérifier un PV d’un matériel avec numéro de série, fournisseur, date, facture, valeur ou lieu manquant.
- [ ] Vérifier un PV groupé avec plusieurs objets au même numéro immo, le détail de chaque objet et le montant total.
- [ ] Vérifier qu’une sélection avec des numéros immo différents est refusée proprement.
- [ ] Vérifier le nom du signataire connecté, les états précochés, les champs libres, les radios Eichhoffen/Barr et la date du document.
- [ ] Vérifier logo et couleur configurés, ainsi que les sauts de page pour un document long.
- [ ] Vérifier une génération de document d’inventaire utilisateur avant et après les changements, afin de confirmer l’absence de régression.
- [ ] Valider les scénarios sur une instance de test compatible avec la version GLPI cible avant toute mise en production.
- [ ] Committer chaque étape cohérente de la fonctionnalité en ne sélectionnant que les fichiers concernés ; ne jamais inclure les modifications locales préexistantes sans rapport.

### Journal de progression

- 2026-10-19 — Demande enregistrée et checklist initiale commitée avant l’implémentation.
- 2026-10-19 — Premier parcours ajouté dans un onglet dédié aux matériels : génération pour un matériel ou une sélection au même numéro immo, saisies de mise en service, modèle PDF sélectionnable pour logo/couleurs, document GLPI lié aux matériels. Vérifications PHP de syntaxe réussies ; essais fonctionnels GLPI 11/12 et rendu PDF restent à faire.
- 2026-10-19 — Liens de la page plugin GLPI et du catalogue mis à jour vers `https://github.com/Cyril6789/protocolsmanager`, à la demande de l’utilisateur.
- 2026-10-19 — Incident après chargement du nouvel onglet : les journaux de `glpi.it.web` ont révélé une erreur fatale PHP due au conflit entre la méthode statique `showForm()` et `CommonDBTM::showForm()`. La méthode a été renommée `renderCommissionForm()` et le fichier passe `php -l` dans le conteneur. Le parcours ordinateur doit être rechargé pour confirmer le rétablissement.
- 2026-10-19 — Ajustement visuel du PV : logo réduit et centré, numéro immo placé à gauche avec libellé noir et numéro/soulignement dans la couleur du modèle, suppression des doublons du chapitre 1, regroupement inventaire/série sur une ligne et largeurs de colonnes de validation rééquilibrées.
- [x] Renommer l’onglet matériel « PV de mise en service » et réorganiser le formulaire en sections lisibles avec sélection des matériels, modèle PDF, date, états, utilisation prévue, lieu et observations.
- [x] Ajouter au formulaire un tableau des PV GLPI associés au matériel, avec document, date, fichier, créateur et commentaire.
- [x] Forcer le chapitre 4 « Validations » à commencer sur une nouvelle page PDF (page 2 lorsque le contenu précédent tient sur la première page).
- [ ] Vérifier visuellement le formulaire dans GLPI 12 et confirmer que le tableau liste les PV associés à un matériel simple et à un PV groupé.
- [ ] Générer un PDF représentatif sur une instance de test et confirmer que le chapitre 4 commence bien en haut de la page 2.
- 2026-10-19 — Mise en page du formulaire et tableau des documents générés ajoutés ; chapitre 4 configuré avec un saut de page explicite. `php -l` passe localement. Vérification visuelle en GLPI et rendu PDF à confirmer sur instance de test.
- [x] Autoriser la génération d’un PV autonome lorsque le numéro immo est vide ; enregistrer le document sans association à un matériel et laisser le numéro immo vide dans le PDF.
- [ ] Vérifier la génération autonome sur une instance de test et confirmer que le document reste sans lien matériel dans GLPI.
- 2026-10-19 — La validation du formulaire accepte maintenant une sélection vide uniquement lorsque le numéro immo est vide. Le PDF est créé sans lignes matériel ni liens GLPI ; avec un numéro immo renseigné, une sélection valide reste requise.
- [x] Garder le numéro immo et la date effective facultatifs ; afficher leurs valeurs vides dans le PDF sans bloquer la génération.
- [x] Intercepter les exceptions du rendu PDF et de l’enregistrement GLPI, journaliser leur détail et afficher un message compréhensible au lieu d’une erreur 500.
- [ ] Reproduire les cas signalés (numéro immo vide, date effective vide, puis les deux vides) sur une instance de test.
- 2026-10-19 — Confirmation statique : immo et date vide sont acceptés ; ajout d’une gestion d’erreur autour de Dompdf et de la création du document GLPI afin que les erreurs réelles soient consignées et remontées à l’utilisateur. Scénarios à reproduire en test.
- [x] Afficher le formulaire autonome si les données de gestion d’un matériel ne sont pas disponibles, afin qu’une fiche dont l’onglet Gestion est vide puisse quand même générer un PV sans lien matériel.
- 2026-10-19 — Cas signalé sur une imprimante sans données de gestion : `loadAsset()` pouvait retourner `null` et empêcher l’affichage du formulaire. Le parcours affiche maintenant le formulaire autonome dans ce cas.
- [x] Rattacher au matériel courant tout PV généré depuis son onglet, même lorsque le numéro immo est vide ; conserver un PDF sans numéro immo et sans regroupement d’autres matériels.
- [ ] Confirmer dans GLPI que le PV de l’imprimante 64 apparaît dans ses documents associés et que le numéro immo reste vide dans le PDF.
- 2026-10-19 — Correction après constat d’un document orphelin : le formulaire transmet l’identifiant du matériel courant, le serveur le revalide et l’ajoute aux associations GLPI et aux données d’identification du PV.
- [x] Conserver toutes les lignes du tableau d’identification dans le PDF lorsque certaines ou toutes les données matérielles sont absentes ; laisser les valeurs manquantes en blanc.
- [x] Afficher « RAS » dans les observations du PDF lorsque le champ correspondant est laissé vide à la génération.
- [x] Afficher l’icône maison de la carte du plugin dans la gestion des plugins GLPI et la faire pointer vers le dépôt GitHub du plugin.
- 2026-10-19 — La carte Marketplace GLPI 12 lit le lien maison depuis les métadonnées du catalogue distant et ignore le champ `homepage` local. Ajout d’un script frontend ciblé sur la carte `protocolsmanager`, avec mise à jour de la version du plugin pour invalider les caches d’assets.
- [x] Détecter aussi la carte si GLPI l’injecte après le chargement initial via AJAX.
- 2026-10-19 — Après retour utilisateur indiquant que l’icône n’apparaissait toujours pas, vérification du script servi (HTTP 200), de l’URL homepage en base et du chargement asynchrone potentiel. Le script observe maintenant le DOM entier et ajoute le lien dès que la carte apparaît.

## Extension de l’onglet Procès-Verbaux : mise au rebut

- [x] Ajouter un choix entre le PV de mise en service existant et le nouveau PV de mise au rebut.
- [x] Conserver le formulaire et le PDF de mise en service existants après sélection de ce type.
- [x] Ajouter le formulaire de mise au rebut avec date du jour par défaut, service Informatique, nom et fonction de l’utilisateur connecté.
- [x] Rendre obligatoires le motif et le mode de mise au rebut, côté formulaire et côté serveur.
- [x] Générer le chapitre 1 d’informations générales et le chapitre 2 avec désignation, inventaire, série, date disponible, montant, marque, modèle et type.
- [x] Ajouter les chapitres motif, mode de mise au rebut et signature, avec nom prérempli et date/signature laissées vides.
- [x] Réutiliser logo et couleurs du modèle PDF, afficher le numéro immo en tête et conserver un PDF générable lorsque ce numéro est vide.
- [x] Rattacher le document GLPI à la licence ou au matériel courant, ou à chaque matériel sélectionné pour un immo partagé ; afficher les deux types de PV dans la liste.
- [x] Mettre à jour le README et incrémenter la version du plugin.
- [x] Vérifier `php -l` pour les fichiers PHP modifiés et `git diff --check`.
- [ ] Vérifier sur GLPI la sélection des deux types et confirmer que le PV de mise en service garde son rendu et son parcours.
- [ ] Générer un PV de mise au rebut avec tous les champs, puis avec un numéro immo vide et des données de gestion absentes.
- [ ] Vérifier les refus lorsque le motif ou le mode de mise au rebut est vide, et le rattachement du document sur une licence, un objet simple et un groupe au même numéro immo.
- [x] Afficher les choix de type de PV directement dans la fiche GLPI, sans navigation vers une réponse AJAX isolée.
- 2026-10-19 — Correction du sélecteur : le contenu des onglets est chargé par GLPI via AJAX ; les liens construits à partir de l’URL courante ouvraient la réponse seule hors de la fiche. Les deux formulaires se déplient maintenant dans le contexte de l’objet.
- [x] Compacter le PDF de mise au rebut : numéro immo et date aux deux extrémités du logo, demandeur et fonction sur une ligne, inventaire et série sur une ligne, marque/modèle/type sur une ligne, espacements et tableau réduits.
- [x] Remplacer la saisie libre du mode de mise au rebut par les choix radio Destruction, Don, Vente symbolique, Recyclage DEEE après dépose du disque dur et Autre ; exiger une précision pour Autre et valider aussi côté serveur.
- [x] Afficher dans le PDF le choix de mode retenu et, le cas échéant, la précision fournie pour Autre.
- [x] Documenter les choix de mise au rebut dans le README et incrémenter la version du plugin.
- [x] Vérifier `php -l` sur les fichiers PHP modifiés et `git diff --check`.
- [ ] Vérifier dans GLPI l’affichage et la sélection des cinq modes et le champ de précision pour Autre.
- [ ] Générer un PV pour chacun des quatre choix prédéfinis, puis un PV Autre avec précision ; vérifier le mode reporté dans le PDF.
- [ ] Vérifier que le serveur refuse une raison vide, un mode absent/invalide et Autre sans précision.
- [x] Réaérer la mise en page du PV de mise au rebut en gardant les champs regroupés sur une ligne uniquement là où demandé ; réserver une zone manuscrite au numéro immo vide au-dessus du trait.
- [x] Rétablir la taille du logo du PV de mise au rebut à sa taille précédente (120 × 120 px maximum), tout en le gardant centré.
- [x] Élargir légèrement la colonne « Service concerné » du tableau d’informations générales.
- [x] Fixer la première colonne de libellés du chapitre 2 à 30 % directement sur ses cellules (Désignation, inventaire, date de mise en service, marque/modèle), en réduisant la première colonne de valeur afin que les libellés ne soient pas tronqués ou tassés.
- [x] Incrémenter la version du plugin après l’ajustement de présentation.
- [x] Vérifier `php -l` sur les fichiers PHP modifiés et `git diff --check`.
- [ ] Vérifier dans le PDF imprimé que l’espacement est confortable et que le numéro immo vide dispose d’une zone manuscrite avant son trait.
- [ ] Vérifier dans le PDF généré qu’un PV courant tient sur une page et que les textes longs ou les groupes de plusieurs objets restent lisibles.

# Refonte des réglages et des modèles PDF — branche `feature/pv-templates-1.60.20`

## Cadre validé

- Branche de travail créée depuis l’état courant : `feature/pv-templates-1.60.20`.
- Version de départ : `1.6.0.20`; portée à `1.6.0.21` une seule fois pour déclencher la migration du stockage dédié. Après achèvement du chantier, la version de livraison a d’abord été portée à `2.0.0.0`, puis normalisée à `2.0.0` à la demande de l’utilisateur; aucune hausse pour les modifications simples suivantes.
- Aucun push ni merge vers `master` sans autorisation explicite de l’utilisateur. L’utilisateur a autorisé la livraison finale, le merge vers `master` et le push; il a ensuite demandé le format de version `2.0.0`.
- Conserver autant de modèles de fiches d’inventaire Protocols Manager que nécessaire.
- Créer une zone de réglages dédiée aux procès-verbaux de mise en service et de mise au rebut.
- Logo et couleur d’accentuation sont des réglages globaux partagés par tous les modèles PDF.
- Il existe un modèle de PV de mise en service et un modèle de PV de mise au rebut, chacun entièrement réglable.
- Les champs proposés dans chaque formulaire de création de PV et les chapitres/contenus de chaque PDF doivent être pilotés par leur modèle.
- Les cases à cocher proposées dans les formulaires de PV doivent pouvoir être éditées dans les réglages et leur état saisi doit être repris dans le PDF.
- Dans les deux modèles de PV, l’administrateur doit pouvoir ajouter et supprimer librement des éléments cochables; chaque option a son libellé, son activation et son état coché par défaut.
- Ce journal est la source de suivi exhaustive : une case ne passe à `[x]` qu’une fois son implémentation achevée. Les validations dépendant d’une instance GLPI restent ouvertes tant qu’elles n’y ont pas été exécutées.
- Les commits ne doivent contenir que les fichiers du chantier explicitement sélectionnés. Le répertoire de travail comportait déjà de nombreux changements locaux (notamment `dompdf/`, `docs/img/`, `locales/` et `screenshots/`) avant le début de ce chantier ; ils sont préservés et exclus.

## Suivi détaillé du chantier

### A. Cadrage, architecture et compatibilité

- [x] Relever la branche, la version déclarée et les principaux composants existants de réglages, formulaire et génération des PV.
- [x] Créer une branche de travail dédiée depuis l’état courant, sans toucher aux changements locaux préexistants.
- [x] Cartographier le schéma actuel : les modèles d’inventaire sont des lignes multiples dans `glpi_plugin_protocolsmanager_config`; les réglages visuels (logo, dimensions, couleurs) sont actuellement par modèle; les e-mails vivent dans `glpi_plugin_protocolsmanager_emailconfig`; les PV n’ont pas encore de table de configuration dédiée.
- [x] Cartographier les points d’entrée : `front/config.form.php` et `PluginProtocolsmanagerConfig` pour les réglages; `PluginProtocolsmanagerGenerate::makeProtocol()` pour les fiches d’inventaire; `PluginProtocolsmanagerCommission::generateFromRequest()` / `generateDisposalFromRequest()` et `createPdf()` / `createDisposalPdf()` pour les PV.
- [ ] Cartographier les chemins complets de génération des fiches d’inventaire, notamment substitution des champs, numérotation, création des documents GLPI et e-mails.
- [ ] Définir et documenter les invariants de compatibilité avec les données et modèles déjà enregistrés.
- [x] Ne pas incrémenter la version à chaque retouche; faire un incrément unique `1.6.0.21` pour que GLPI exécute l’ajout de la table de réglages sur les installations existantes.
- [x] Porter la version publiée à `2.0.0.0` pour la livraison finale sur `master`, après les modifications du chantier (format initial, normalisé ensuite à trois composants).
- [x] Remettre le README en cohérence avec les trois flux PDF, les réglages, les droits, la compatibilité et les parcours actuels.
- [x] Retirer les captures d’écran obsolètes des références du README et des métadonnées du plugin.
- [ ] Vérifier que l’installation neuve et la mise à jour d’une installation existante suivent les mêmes migrations idempotentes.
- [x] Créer un stockage singleton séparé pour identité visuelle et modèles PV, sans toucher aux lignes des modèles d’inventaire ni aux réglages e-mail.
- [x] Initialiser les deux modèles PV avec les libellés, choix et chapitres actuels; initialiser logo/couleur globaux en reprenant les valeurs historiques à titre de point de départ.

### B. Navigation et gestion des réglages

- [x] Réorganiser l’écran de configuration en espaces distincts : modèles d’inventaire, PV de mise en service, PV de mise au rebut, identité visuelle globale et e-mails.
- [ ] Utiliser les composants et conventions d’interface GLPI réellement prévus pour les pages de configuration (en-tête, navigation/onglets, formulaires, boutons, messages et retours après sauvegarde), en vérifiant leur disponibilité dans les versions GLPI supportées.
- [x] Présenter les réglages d’inventaire, de PV et d’e-mail sous forme de cartes et sections hiérarchisées, avec libellés, aides contextuelles, états vides et actions explicites; garder les tableaux seulement pour les listes répétées (modèles et cases configurables).
- [ ] Vérifier l’ergonomie clavier, les labels associés, les erreurs de validation visibles et la cohérence visuelle avec GLPI sur grand écran et écran étroit.
- [x] Éliminer les formulaires imbriqués/incomplets des éditeurs d’inventaire et d’e-mail et des listes d’actions; corriger le sélecteur de taille de police et remplacer les styles de présentation par classes GLPI/Bootstrap.
- [ ] Faire relire le parcours de configuration complet et vérifier son rendu visuel dans une instance GLPI avant validation de cette exigence.
- [x] Conserver la création, modification, suppression et sélection d’un nombre illimité de modèles d’inventaire; les modèles restent des lignes indépendantes de l’ancienne table.
- [x] Conserver les réglages d’e-mail existants et leur comportement en gardant leurs champs, tables et routes de création/modification/suppression.
- [x] Ajouter un réglage global de logo utilisable par toutes les sorties PDF.
- [x] Ajouter une couleur d’accentuation globale utilisée par toutes les sorties PDF.
- [x] Initialiser les valeurs globales en reprenant le premier accent valide et le premier logo PNG/JPEG local, lisible et conforme aux limites depuis les anciens modèles; ne modifier aucune préférence historique par modèle.
- [x] Préserver à l’identique les couleurs de modèle et dimensions de logo historiques lors de la modification d’un modèle d’inventaire, même si ces réglages ne sont plus éditables dans l’interface globale.
- [x] Valider le logo global par son contenu PNG/JPEG réel, sa taille (2 Mo), ses dimensions (5 000 × 5 000 pixels maximum) et nettoyer les anciens fichiers gérés par le nouvel espace global lors du remplacement/suppression ou en cas d’échec de sauvegarde.
- [x] Limiter également l’aperçu de logo enregistré aux fichiers PNG/JPEG lisibles de 2 Mo maximum, sans intégrer d’autres types MIME dans la page de configuration.
- [ ] Vérifier les droits GLPI pour la consultation, création, modification et suppression des réglages.
- [ ] Ajouter les libellés/traductions et l’aide nécessaires à la nouvelle organisation.

### C. Modèle de PV de mise en service

- [x] Stocker un modèle unique de mise en service, distinct des modèles d’inventaire.
- [x] Permettre de régler le titre du PV, les champs actuellement prévus, leurs libellés et leur affichage.
- [x] Permettre d’activer, désactiver, renommer et définir l’état par défaut des cases à cocher existantes du formulaire.
- [x] Permettre d’ajouter et supprimer sans plafond applicatif des cases de mise en service; enregistrer libellé, visibilité et valeur par défaut.
- [x] Préserver une liste volontairement vide après suppression de toutes ses entrées, sans réinjecter les valeurs initiales lors du prochain affichage des réglages.
- [x] Permettre d’ajouter et supprimer les lieux proposés comme choix radio, puis afficher dans le PDF le libellé du lieu sélectionné.
- [x] Décomposer les quatre fonctions de validation du tableau de mise en service en rôles configurables : modifier leur libellé, ajouter/supprimer des rôles, activer/désactiver chaque rôle et générer une ligne PDF par rôle actif; conserver une liste vide si tous les rôles sont supprimés.
- [x] À la génération PDF, reprendre les quatre rôles initiaux si la clé de configuration est absente sur une installation antérieure; respecter une liste explicitement enregistrée vide sans imposer de migration.
- [x] Dupliquer la date effective de mise en service dans l’en-tête à droite du logo, symétriquement au numéro immo; conserver la date d’acquisition renseignée comme valeur de repli et laisser une ligne vide à compléter à la main si aucune date n’est disponible.
- [x] À côté de la date effective dans le formulaire, afficher la date de facture/acquisition préremplie au format `jj/mm/aaaa` lorsqu’elle est disponible comme indication de la valeur de repli.
- [x] Afficher les options configurées dans le formulaire de création et reprendre les options actives, cochées ou non, dans le chapitre de mise en service du PDF.
- [x] Permettre d’éditer la présence, le titre et le texte introductif des chapitres actuellement définis dans le PDF.
- [x] Ajouter et supprimer des chapitres libres dans chaque modèle, avec titre, contenu multi-ligne et activation; les rendre après les chapitres standard dans le PDF sous forme d’annexes.
- [x] Numéroter les annexes actives à la suite, sans saut lorsqu’une annexe est désactivée.
- [x] Reprendre dans le PDF de mise en service les libellés configurés pour les champs de date et d’utilisation, ainsi que le libellé du lieu sélectionné.
- [x] Remplacer le marqueur `{observations}` du texte d’introduction du chapitre Observations par la valeur saisie dans le formulaire, sans répéter cette valeur une seconde fois sous le texte lorsque le marqueur est utilisé.
- [ ] Permettre de créer/supprimer des champs et chapitres, de configurer les champs structurés de chaque chapitre et de changer l’ordre de rendu.
- [ ] Préserver les valeurs saisies et leur état lorsque le PV est généré ou réédité.
- [x] Construire le formulaire matériel à partir des options du modèle de mise en service, en conservant le regroupement d’objets et les contrôles de droits existants.
- [x] Construire les titres, activations et textes introductifs du PDF de mise en service depuis le modèle, en conservant les données GLPI et leur échappement.
- [x] Employer le logo et l’accentuation globaux.
- [x] Initialiser le modèle de mise en service avec les choix, libellés et chapitres déjà proposés par le formulaire/PDF.

### D. Modèle de PV de mise au rebut

- [x] Stocker un modèle unique de mise au rebut, distinct du modèle de mise en service et des modèles d’inventaire.
- [x] Permettre de régler le titre du PV, les champs actuellement prévus et les libellés affichés.
- [x] Permettre d’activer, désactiver et renommer les modes de mise au rebut proposés.
- [x] Ajouter une liste de contrôle dédiée au PV de mise au rebut, avec ajout/suppression d’options, libellé, visibilité et état coché par défaut.
- [x] Afficher la liste paramétrée dans le formulaire de mise au rebut et reprendre toutes les cases actives avec leur état sélectionné dans un chapitre dédié du PDF.
- [x] Permettre d’ajouter/supprimer sans plafond applicatif les modes de mise au rebut, avec libellé et activation; refuser l’enregistrement si le champ reste obligatoire sans aucun mode actif.
- [x] Préserver la validation serveur du motif et du mode actif sélectionné; exiger une précision si le choix actif porte la clé `autre` ou le libellé « Autre ».
- [x] Permettre d’éditer la présence, le titre et le texte introductif des chapitres actuellement définis dans le PDF.
- [x] Ajouter et supprimer des chapitres libres dans chaque modèle, avec titre, contenu multi-ligne et activation; les rendre après les chapitres standard dans le PDF sous forme d’annexes.
- [x] Numéroter les annexes actives à la suite, sans saut lorsqu’une annexe est désactivée.
- [x] Reprendre dans le PDF de mise au rebut le libellé configuré du champ date.
- [x] Remplacer les marqueurs `{reason}` et `{method}` présents dans les textes introductifs par la valeur saisie/choisie; ne pas imprimer une seconde fois la même valeur lorsqu’elle est insérée par son marqueur.
- [ ] Permettre de créer/supprimer des chapitres, configurer les champs structurés et changer l’ordre de rendu.
- [x] Construire le formulaire et les titres/activations/textes introductifs du PDF depuis le modèle de mise au rebut sans modifier les réglages de mise en service.
- [x] Afficher le titre paramétré de mise au rebut dans le formulaire de création et dans le titre principal du PDF.
- [x] Employer le logo et l’accentuation globaux.
- [x] Initialiser le modèle de mise au rebut avec les choix, libellés et chapitres déjà proposés par le formulaire/PDF.

### E. Formulaires sur les fiches matériel et licences

- [ ] Garder les deux parcours de PV distincts et identifiables depuis l’onglet GLPI.
- [x] Afficher les champs activés des deux modèles de PV et valider côté serveur les cases et modes transmis.
- [x] Permettre à l’utilisateur de modifier les cases du formulaire de mise en service et les choix de mise au rebut configurés.
- [x] Permettre à l’utilisateur de cocher/décocher les listes administrables des deux formulaires PV avant génération; valider côté serveur les valeurs transmises selon les options actives.
- [x] Ajouter dans les deux formulaires PV une case maître « Tout sélectionner / tout décocher », synchronisée avec les cases des matériels/licences sélectionnables.
- [x] Charger la synchronisation des cases maître et individuelles depuis un fichier JavaScript dédié enregistré par le hook GLPI `add_javascript`.
- [x] Afficher le statut GLPI et la personne affectée dans les tableaux de choix des matériels, pour les inventaires et les deux formulaires PV; ces informations restent visuelles et ne sont pas ajoutées aux PDFs.
- [x] Afficher le type, le modèle et le prix d’achat renseigné pour chaque matériel dans les tableaux où l’utilisateur coche les objets à inclure; ne pas ajouter ces colonnes d’aide à la sélection au contenu des PDFs.
- [x] Supprimer du formulaire de mise au rebut le tableau redondant « Détails de l’immobilisation » qui répétait les informations de chaque matériel; garder le tableau à cases pour choisir les matériels et conserver le contenu détaillé prévu dans le PDF.
- [ ] Conserver les valeurs par défaut et préremplissages actuels lorsque le modèle correspondant le prévoit.
- [ ] Préserver le regroupement par numéro immo, la sélection d’objets, les PV sans numéro immo et les associations GLPI actuelles.
- [ ] Préserver les contrôles d’accès et la validation serveur des identifiants d’objets soumis.
- [ ] Vérifier l’usage depuis les objets matériel, les licences et les PV sans données de gestion.

### F. Génération et structure des PDF

- [ ] Mettre en place un rendu commun qui reçoit le type de document, le modèle, les données GLPI et les valeurs du formulaire.
- [x] Afficher sur chaque page des trois familles de PDF un pied de page commun avec logo global réduit à gauche, type et numéro immo disponible au centre (ligne immo vide s’il n’y en a pas), pagination « Page x sur y » alignée à droite sur cette seconde ligne; conserver le texte de pied configuré sur les modèles d’inventaire.
- [ ] Conserver le moteur Dompdf et éviter toute modification non nécessaire de `dompdf/`.
- [ ] Rendre les chapitres configurés dans le bon ordre et respecter leur activation/désactivation.
- [x] Appliquer le logo et la couleur globale aux PDFs d’inventaire, de mise en service et de mise au rebut.
- [ ] Conserver les réglages propres aux modèles d’inventaire (police, taille, orientation, contenu, pied de page, numéros).
- [ ] Préserver les retours à la ligne, caractères accentués, tableaux, sauts de page et contenu long.
- [ ] Échapper les données d’objets et de formulaire pour éviter l’injection HTML dans les PDF.
- [ ] Préserver la création et l’association des documents GLPI, les notes et les erreurs compréhensibles.

### G. Migration, sécurité, documentation et livraison

- [x] Ajouter la table de réglages singleton à l’installation/mise à jour avec création conditionnelle et l’ajouter à la désinstallation du plugin.
- [ ] Vérifier l’installation/mise à jour et la répétabilité des migrations en base sur une instance GLPI.
- [x] Fournir des valeurs par défaut pour les deux modèles PV et l’identité visuelle; créer les réglages manquants de manière idempotente.
- [ ] Vérifier les limites de taille, la validation des couleurs et la validation de la structure des champs/chapitres.
- [x] Mettre à jour le README pour décrire les trois familles de documents, la configuration globale, les listes de cases/choix et les annexes configurables.
- [ ] Mettre à jour les traductions sources nécessaires sans inclure de changements de compilation préexistants.
- [x] Vérifier le diff commité depuis la branche de base et l’absence d’erreurs d’espacement avec `git diff --check master..HEAD`; les modifications locales préexistantes restent hors commits.
- [ ] Documenter ici chaque étape terminée, ses fichiers touchés, sa migration et ses validations.
- [ ] Faire un commit ciblé à chaque étape importante, après revue de son contenu.
- [ ] Tester les migrations, formulaires et trois catégories de PDF sur une instance GLPI de test avant de considérer le chantier terminé.
- [ ] Confirmer explicitement avec l’utilisateur avant tout merge vers `master` ou tout push.

## Journal des commits du chantier

- `9a245e7` — documentation initiale exhaustive et cases à cocher.
- `044c0ad` — critères de finition GLPI et cartographie des réglages/PV.
- `a68c9be` — stockage séparé des modèles de PV et réglages visuels, avec valeurs initiales de compatibilité.
- `138cc81` — version `1.6.0.21` pour déclencher la migration des réglages sur les installations existantes.
- `8786680` — ajout/suppression d’options cochables pour les deux PV, validation et sauvegarde des listes personnalisées, et rendu de l’état complet des cases dans les PDFs.
- `cf16ef6` — refonte des éditeurs et des listes de modèles d’inventaire et d’e-mail en composants carte/formulaire GLPI; correction des formulaires imbriqués et du sélecteur de taille de police.
- `e49f055` — ajout de chapitres personnalisés et prise en compte des libellés paramétrés dans les PDFs; validation renforcée du logo global.
- `6dd820c` — modes de mise au rebut ajoutables/supprimables avec contrôle d’au moins un mode actif lorsque le champ est affiché.
- `891e902` — mise à jour du journal après le commit des modes dynamiques.
- `7c0af6c` — lieux de mise en service ajoutables/supprimables, avec libellé affiché dans le PV.
- `3fdda1c` — suppression des plafonds applicatifs des listes et omission du lieu dans le PDF lorsqu’aucun choix n’est configuré.
- `8b4361d` — aperçu de logo limité aux fichiers globaux PNG/JPEG lisibles de 2 Mo maximum.
- `d69f49b` — reprise du logo d’un ancien modèle limitée aux images PNG/JPEG locales valides et conformes aux limites; les réglages historiques restent inchangés.
- `e36620a` — rendre la reprise des préférences historiques compatible avec l’absence de colonnes avant les migrations de schéma.
- `5f38fbb` — documenter les réglages globaux, les cases et choix configurables, les annexes PDF et les parcours PV sans sélection de modèle à la génération.
- `c47b3d2` — afficher les erreurs de sauvegarde de l’identité visuelle sans masquer les messages détaillés de validation.
- `6177423` — maintenir la validation « Autre » si son option est recréée et reçoit une clé technique différente.
- `c306b69` — afficher le titre paramétré du PV de mise au rebut dans son formulaire.
- `6059f0a` — supprimer les messages génériques redondants après les erreurs détaillées de sauvegarde PV.
- `ab28963` — conserver dans les réglages la suppression de toutes les lignes d’une liste sans restaurer les options par défaut.
- `0eb6a2b` — consigner le comportement de suppression totale des listes PV.
- `a452da7` — préserver les préférences historiques de couleurs et dimensions par modèle lors de leur édition.
- `fb31f52` — numéroter à la suite uniquement les annexes activées dans les PDFs.
- `125822c` — retirer l’espace final superflu du journal et vérifier le diff commité depuis `master`.
- `4d4d35c` — rendre les rôles du tableau de validation de mise en service modifiables, activables, ajoutables et supprimables dans les réglages; générer une ligne PDF par rôle actif.
- `99a7537` — rétablir les quatre rôles initiaux à la génération PDF quand une configuration préexistante ne possède pas encore la clé `validators`, sans migration et sans réinitialiser une liste explicitement vide.
- `209a8d5` — afficher également la date effective dans l’en-tête du PV, à droite du logo, tout en la conservant dans le chapitre 2 et en laissant une ligne manuscrite si aucune date n’est disponible.
- `ff4295d` — ajouter un footer Dompdf partagé sur chaque page des inventaires et des deux PV, avec logo global, type/nom et pagination; conserver le texte de pied des modèles d’inventaire.
- `6225b91` — consigner le footer commun dans le journal d’avancement.
- `ae6c264` — ajouter une case de sélection générale synchronisée pour chaque groupe de matériels PV et interpréter `{reason}` / `{method}` dans les textes introductifs sans doublonner les valeurs.
- `7444d70` — consigner les corrections des PV et la sélection des matériels.
- `55839e2` — afficher statut et personne affectée dans les tableaux de sélection, indiquer la date de facture/acquisition près de la date effective, corriger la synchronisation de la sélection générale et aligner la pagination du footer avec le numéro immo.
- `231eaab` — afficher près du champ date effective la mention complète « Si non remplie, la date de facture sera utilisée, à savoir : jj/mm/aaaa » quand une date est disponible; conserver ce repli même si certains matériels groupés n’ont pas de date, dès lors qu’une date unique est trouvée.
- `fd8d626` — compléter les tableaux de sélection des matériels avec des colonnes Type, Modèle et Prix d’achat; reprendre le prix de gestion GLPI lorsqu’il est renseigné, sans modifier les PDFs.
- `ac995a6` — retirer du formulaire de mise au rebut le tableau « Détails de l’immobilisation » redondant, tout en gardant les détails du document généré.
- `a2e02e6` — remplacer le marqueur `{observations}` de l’introduction du chapitre Observations par la valeur saisie, sans doublon avec le texte affiché sous l’introduction.
- `177684c` — réécrire le README sans captures obsolètes, actualiser les métadonnées du plugin, et porter sa version déclarée à `2.0.0.0`.
- Normaliser à trois composants la version active (`2.0.0`) dans `setup.php`, `plugin.xml`, le README et ce journal, à la demande de l’utilisateur.
- En cours — revue de cohérence des rendus PDF; revue visuelle et vérifications en instance GLPI restent ouvertes.

### Livraison 2.0.0

- [x] Mettre à jour le README et les métadonnées sans publier de captures d’écran obsolètes.
- [x] Déclarer initialement la version `2.0.0.0` dans `setup.php` et `plugin.xml`.
- [x] Faire avancer `master` de `eb6a17f` à `b04c19b` par fast-forward depuis `feature/pv-templates-1.60.20`.
- [x] Pousser `master` vers `origin` le 2026-10-20; le push a confirmé `eb6a17f..b04c19b`.
- [x] Normaliser la version déclarée à `2.0.0` dans `setup.php`, `plugin.xml` et le README.
- [x] Pousser le correctif de version sur `master` après la livraison initiale.
- [ ] Effectuer une vérification visuelle et fonctionnelle de la release dans une instance GLPI avant annonce aux utilisateurs.
