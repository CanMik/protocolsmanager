# Protocoles d’immobilisation

Protocols Manager est un plugin GLPI qui permet de créer et de gérer des documents PDF à partir des données d’inventaire. La version **2.0.0** propose trois parcours documentaires :

- **Fiches d’inventaire** pour les équipements affectés à un utilisateur.
- **Procès-verbaux de mise en service**.
- **Procès-verbaux de mise au rebut**.

Les PDF générés sont enregistrés comme documents GLPI et liés aux fiches GLPI concernées. Les fiches d’inventaire peuvent utiliser autant de modèles que nécessaire, gérés indépendamment. Les PV de mise en service et de mise au rebut disposent chacun de leur propre formulaire et modèle PDF paramétrables. Un logo et une couleur d’accentuation communs sont utilisés par les trois types de documents.

## Fonctionnalités

### Fiches d’inventaire

- Générer un PDF pour tout ou partie des équipements affectés à un utilisateur. L’onglet d’inventaire est disponible sur les fiches utilisateur et les fiches des équipements pris en charge.
- Consulter dans le tableau de sélection le type, le fabricant, le modèle, le nom, le numéro de série, le numéro d’inventaire, le statut GLPI, la personne affectée et le prix d’achat. Le statut, la personne affectée et le prix d’achat facilitent la sélection; ces colonnes ne sont pas ajoutées au PDF.
- Choisir d’afficher le numéro de série et le numéro d’inventaire dans deux colonnes distinctes ou dans une seule colonne, qui reprend le numéro d’inventaire si le numéro de série est absent.
- Ajouter un commentaire à chaque élément sélectionné, des lignes personnalisées et une note à la fiche.
- Créer autant de modèles d’inventaire que nécessaire. Chaque modèle peut définir le titre du document, la police, sa taille, l’orientation de la page, la coupure des mots, la ville, les textes supérieur, principal et de pied de page, le mode d’affichage des numéros, l’auteur du PDF et l’envoi automatique par courriel.
- Consulter les fiches générées dans la liste du plugin et dans Documents GLPI, les ouvrir ou les supprimer, et les envoyer par courriel si le profil dispose des droits nécessaires.

### Procès-verbaux de mise en service

L’onglet **Procès-verbaux** est disponible sur les ordinateurs, écrans, imprimantes, périphériques, téléphones, équipements réseau et licences logicielles.

- Sélectionner les immobilisations portant le même numéro d’immobilisation. Le tableau présente le nom, le type, le modèle, le numéro d’inventaire, le statut, la personne affectée, le prix d’achat et le numéro de série. Une commande permet de tout sélectionner ou tout désélectionner.
- Générer un PV sans numéro d’immobilisation si la fiche matériel ou licence ne contient pas d’information de gestion GLPI. Le numéro reste vide dans le PDF et le document demeure lié à la fiche courante.
- Paramétrer les libellés et l’affichage des champs du formulaire, les cases à cocher relatives à la mise en service, les rôles des validateurs et les lieux disponibles. Les libellés, l’affichage et l’état coché par défaut des cases sont modifiables. Les rôles de validation et les lieux peuvent être ajoutés, renommés, activés ou supprimés.
- Paramétrer le titre, l’affichage et le texte d’introduction de chaque chapitre standard du PDF. Ajouter ou supprimer des annexes libres. Les chapitres standards gardent un ordre fixe; le plugin fournit leurs données matériel et tableaux.
- La date effective de mise en service est préremplie avec la date d’achat ou d’acquisition GLPI lorsqu’une date unique est disponible pour le groupe sélectionné. Le formulaire précise ce report près du champ. Si aucune date certaine n’est disponible, le champ reste vide afin de pouvoir être rempli.
- Selon les chapitres et champs activés, le PDF comprend l’identification du matériel, les déclarations et cases cochées relatives à la mise en service, l’usage prévu, les observations, le tableau de signatures des validateurs et le lieu choisi. Si aucune observation n’est saisie, le PDF indique « RAS ».
- Le marqueur `{observations}` placé dans l’introduction du chapitre Observations est remplacé par l’observation saisie et n’est pas imprimé une seconde fois.
- Le document est lié à toutes les immobilisations sélectionnées. Un PV commencé depuis une licence logicielle reste lié à cette licence et est traité séparément des groupes de matériels.

### Procès-verbaux de mise au rebut

- Sélectionner les immobilisations portant le même numéro d’immobilisation. Le tableau de sélection affiche les mêmes informations d’identification et de gestion que celui du PV de mise en service, avec une commande pour tout sélectionner ou tout désélectionner.
- Générer un PV sans numéro d’immobilisation pour la fiche matériel ou licence courante.
- Paramétrer le titre, les champs et libellés du formulaire, les modes de mise au rebut, les cases à cocher, les titres et affichages des chapitres standards, leurs textes d’introduction et les annexes libres du PDF.
- Ajouter, renommer, activer, désactiver ou supprimer les modes de mise au rebut et les cases à cocher. L’état coché par défaut de chaque case est configurable. Si le choix **Autre** est disponible et sélectionné, l’utilisateur doit en préciser le détail.
- Selon le modèle, le PDF contient la date de mise au rebut, le demandeur et sa fonction (repris de l’utilisateur GLPI courant), l’identification du matériel, le motif, le mode choisi, l’état des cases et la section des signatures.
- Les marqueurs `{reason}` et `{method}` dans les introductions de chapitres correspondantes sont remplacés par les valeurs saisies ou choisies. Ces valeurs ne sont pas répétées sous l’introduction lorsque son marqueur est utilisé.
- Les informations détaillées du matériel restent dans le PDF. Le tableau redondant **Détails de l’immobilisation** a été retiré du formulaire; le tableau de sélection des matériels est conservé.

### Identité visuelle et pied de page communs

La section **Identité visuelle** permet de définir un logo PNG/JPEG et une couleur d’accentuation uniques pour les fiches d’inventaire et les deux types de PV. Le logo ne doit pas dépasser 2 Mo ni 5 000 × 5 000 pixels. Le remplacement ou la suppression du logo est répercuté sur tous les PDF générés.

Chaque page comporte un pied de page commun : le logo à gauche, le type de document centré, le numéro d’immobilisation sur la ligne suivante lorsqu’il est disponible, et la pagination à droite, alignée sur cette deuxième ligne. Le texte de pied de page défini dans le modèle d’inventaire est conservé dans le pied de page des fiches d’inventaire.

### Modèles de courriel

- Créer, modifier et supprimer des modèles de courriel réutilisables avec destinataires, objet, message et option d’ajout du propriétaire de l’équipement parmi les destinataires.
- Activer l’envoi automatique après génération pour les modèles d’inventaire. Les fiches peuvent aussi être envoyées manuellement depuis la liste des documents.
- L’envoi utilise le transport de courriel configuré dans GLPI. Les objets et messages des modèles acceptent les variables d’inventaire listées ci-dessous.

## Configuration

Ouvrir **Plugins → Protocols Manager**. La page de configuration comprend des sections distinctes pour les **modèles d’inventaire**, le **PV de mise en service**, le **PV de mise au rebut**, l’**identité visuelle globale** et les **courriels**.

Les textes des modèles d’inventaire ainsi que les champs objet et message des courriels acceptent les variables suivantes :

| Variable | Valeur |
| --- | --- |
| `{owner}` | Nom du propriétaire de l’équipement |
| `{admin}` | Nom de la personne qui génère la fiche |
| `{cur_date}` | Date du jour au format `d.m.Y` |

Chaque type de PV dispose d’un modèle unique; les utilisateurs ne choisissent pas un autre modèle au moment de générer le PV. Pour les chapitres standards, la page de configuration permet de définir le titre, l’affichage et le texte d’introduction. Le plugin renseigne les tableaux dynamiques et les champs matériel à partir de GLPI et des données du formulaire. Les chapitres libres ajoutés sont numérotés comme annexes après les chapitres standards. L’ordre des chapitres standards et leurs champs structurés sont fixes.

L’identité visuelle globale s’applique à tous les PDF. La typographie, l’orientation, le contenu et le pied de page restent définis dans chaque modèle d’inventaire.

## Droits

Configurer les droits du plugin dans **Administration → Profils → [profil] → Protocols Manager** :

- **Configuration du plugin** donne accès à la page de configuration. L’enregistrement des réglages nécessite aussi le droit de mise à jour de la configuration GLPI.
- **Accès aux onglets** donne accès aux onglets Protocols Manager sur les fiches GLPI prises en charge.
- **Création de documents** correspond au droit GLPI Documents nécessaire pour générer des PDF.

Les utilisateurs doivent également disposer des droits de lecture GLPI habituels sur les personnes et les matériels concernés. La génération groupée de PV vérifie les droits d’accès sur chaque matériel sélectionné. Les actions d’envoi et de suppression dépendent des droits de l’utilisateur et de la configuration du courriel dans GLPI.

## Utilisation

### Générer une fiche d’inventaire

1. Ouvrir la fiche d’un utilisateur ou d’un équipement pris en charge et associé à un utilisateur, puis cliquer sur l’onglet **Protocols Manager**.
2. Choisir un modèle d’inventaire et les éléments à inclure. Le tableau affiche le statut, la personne affectée et le prix d’achat à titre indicatif.
3. Ajouter au besoin des commentaires par élément, des lignes personnalisées ou une note.
4. Générer le PDF. Il apparaît dans la liste des documents du plugin et dans **Gestion → Documents**; il est lié à l’utilisateur et aux équipements inclus.

### Générer un PV de mise en service

1. Ouvrir une fiche de matériel ou de licence logicielle prise en charge, puis cliquer sur **Procès-verbaux**.
2. Déplier **PV de mise en service**. Si plusieurs immobilisations portent le même numéro, choisir celles à inclure, puis remplir les champs et cases activés.
3. Vérifier la date effective. Lorsqu’une date d’achat GLPI unique est disponible, elle est préremplie; sinon, saisir une date si nécessaire au PV.
4. Générer le PDF. Il apparaît dans la liste des PV et dans Documents GLPI, et est lié aux matériels sélectionnés ou à la fiche courante.

### Générer un PV de mise au rebut

1. Ouvrir une fiche de matériel ou de licence logicielle prise en charge, puis cliquer sur **Procès-verbaux**.
2. Déplier **PV de mise au rebut**. Si plusieurs immobilisations portent le même numéro, choisir celles à inclure.
3. Remplir les champs activés, saisir le motif, choisir un mode de mise au rebut configuré, préciser **Autre** le cas échéant et définir l’état des cases.
4. Générer le PDF. Il est enregistré dans Documents GLPI et lié aux matériels sélectionnés ou à la fiche courante.

## Prérequis et compatibilité

- GLPI **11.0 ou ultérieur, mais inférieur à 13.0** (le plugin déclare sa compatibilité avec GLPI 11.x et 12.x).
- PHP **8.0 ou ultérieur** pour le plugin. GLPI 12 nécessite PHP 8.3 ou ultérieur.
- Documents GLPI doit être disponible et le profil de l’utilisateur doit avoir les droits Documents nécessaires à la génération des PDF.

## Installation et mise à jour

1. Sauvegarder la base de données GLPI et les fichiers du plugin avant toute installation ou mise à jour.
2. Placer le répertoire `protocolsmanager` dans le répertoire `plugins` de GLPI.
3. Ouvrir **Configuration → Plugins**, installer Protocols Manager, puis l’activer.
4. Attribuer aux profils concernés les droits du plugin et les droits Documents.

Pour une installation existante, déployer les nouveaux fichiers du plugin et utiliser le mécanisme de mise à jour GLPI si celui-ci est proposé. Conserver la base de données existante et ne pas désinstaller le plugin lors d’une mise à jour : la procédure de désinstallation supprime les tables de configuration et d’indexation des documents du plugin. La procédure d’installation crée toute table de réglages manquante et initialise les valeurs par défaut des PV, tout en conservant les modèles d’inventaire et les réglages de courriel existants.

## Projet

- Code source, versions et suivi des problèmes : [github.com/Cyril6789/protocolsmanager](https://github.com/Cyril6789/protocolsmanager)
- Le projet reprend le plugin Protocols Manager d’origine de Mateusz Nitka. Merci à [Nomino](https://nomino.pl/) pour son soutien au projet initial.

![Nomino](docs/img/logo-nomino.png)
