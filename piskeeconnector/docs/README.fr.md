# Piskee pour PrestaShop, documentation

Piskee relie votre boutique PrestaShop à Claude : c'est l'assistant IA de votre back-office. Vous demandez en français ce que vous voulez faire (« réécrivez les meta descriptions de la catégorie Robes », « créez les tailles S à XL pour ce produit », « quels produits sont en rupture ? ») et Claude agit directement dans votre boutique, avec vos droits et sous votre contrôle.

Conçu et développé à Chantilly (Oise) par Piskee, agence SEO & GEO e-commerce.

## Ce qu'il faut avant de commencer

- PrestaShop 1.7.6 ou plus récent (1.7, 8 et 9), PHP 7.2 ou plus récent.
- La boutique en HTTPS, à la racine de son domaine, avec les URL simplifiées activées.
- Rien d'autre : le module intègre son propre serveur MCP et son serveur d'autorisation OAuth 2.1.
- Un compte Claude (claude.ai, l'application Claude ou Claude Code).

## Installation

1. Modules, Gestionnaire de modules, Installer un module : déposez le fichier zip du module.
2. Ouvrez le module Piskee : l'onglet Connexion vérifie tout. Cliquez sur **Tester la connexion** pour un test réel des adresses publiques.

## Connecter Claude

1. Copiez l'adresse affichée dans l'onglet Connexion (elle se termine par `/module/piskeeconnector/mcp`).
2. Dans Claude : Paramètres, Connecteurs, Ajouter, Ajouter un connecteur personnalisé.
3. Nommez-le « Piskee », collez l'adresse, gardez les réglages détectés (connexion maintenant, enregistrement automatique), cliquez sur Ajouter puis Connecter.
4. Votre back-office s'ouvre sur un écran d'autorisation : vérifiez les droits demandés et cliquez sur **Autoriser**.

Avec Claude Code, une seule commande :

```
claude mcp add --transport http piskee https://votre-boutique.fr/module/piskeeconnector/mcp
```

Puis tapez `/mcp` dans Claude Code pour vous connecter.

## Ce que Claude peut faire

**Produits et catégories** : rechercher, lire et modifier un produit (prix, références, EAN, poids, activation, textes et SEO dans toutes les langues), créer un produit (inactif par défaut), gérer le stock à la quantité près ou par entrée et sortie, classer un produit, modifier une catégorie.

**Déclinaisons** : lister et créer des attributs (taille, couleur...) et leurs valeurs, créer jusqu'à 100 déclinaisons d'un coup avec impact prix, référence, EAN et stock, modifier une déclinaison.

**Images** : ajouter une image depuis une adresse web, écrire les textes alternatifs, choisir l'image de couverture, réduire les originaux trop lourds, les centrer sur un fond carré et régénérer toutes les miniatures.

**Enrichissement** : caractéristiques, marques, produits associés, promotions (pourcentage ou montant, avec dates), et un audit complet du catalogue qui liste ce qui freine vos ventes et votre référencement.

**Vente croisée et panier** : produits associés (ajout, retrait, remplacement, jusqu'à 50 produits d'un coup), suggestions « souvent achetés ensemble » calculées à partir de vos vraies commandes, et bloc natif « les clients ont aussi acheté » sur la fiche produit et dans le panier, en achats additionnels. Les autres modules de vente additionnelle sont détectés et listés, jamais modifiés.

**Commandes et suivi** : rechercher une commande (référence, client, numéro de suivi), la lire avec son historique et son lien de suivi transporteur, changer son statut, renseigner un numéro de suivi.

**Pages CMS et traductions** : lire et modifier les pages CMS, modifier jusqu'à 50 produits d'un coup (prix, statuts, textes ou traductions).

**Gestion quotidienne** : synthèse des ventes sur une période, stocks bas, recherche et fiche client, lecture des messages du service client et réponse par e-mail (après votre validation), création de bons de réduction.

**Thème enfant** : créer un thème enfant brouillon, lire et chercher dans les fichiers du thème, auditer les balises Hn et le fil d'Ariane d'une page en ligne, modifier les templates, le CSS et le JavaScript du brouillon, restaurer une sauvegarde.

Trois guides prêts à l'emploi sont aussi proposés dans le menu de Claude : Point du jour, Optimiser le catalogue, Nettoyer les Hn et poser un fil d'Ariane.

## Ce que Claude ne peut pas faire

- Rembourser un client ou toucher à un paiement.
- Modifier le thème en ligne ou un thème parent.
- Mettre un thème en ligne : c'est vous qui cliquez, dans l'onglet Thème enfant du module Piskee.
- Supprimer un produit, un client ou une commande.
- Avoir plus de droits que l'employé qui l'a connecté.

## Les garde-fous

- Chaque outil qui modifie la boutique est signalé comme sensible : Claude vous demande confirmation avant de l'exécuter.
- Les droits sont ceux du profil PrestaShop de l'employé qui a autorisé la connexion, vérifiés à chaque appel.
- Chaque modification de thème est sauvegardée. Les fichiers PHP et la configuration du thème ne peuvent pas être modifiés. Les templates sont vérifiés avant enregistrement : balises Smarty mal fermées, fonctions PHP hors liste blanche, appels de classes, constantes et lectures de fichiers sont refusés.
- Vous pouvez couper le serveur et déconnecter chaque assistant à tout moment, dans l'onglet Connexion.
- Aucune donnée n'est envoyée à Piskee.

## Mettre un thème en ligne

1. Demandez à Claude de travailler sur un thème enfant brouillon.
2. Ouvrez Piskee, onglet Thème enfant du module Piskee, et cliquez sur **Mettre en ligne** à côté du brouillon.
3. Vérifiez la page d'accueil, une catégorie et un produit. En cas de souci, cliquez sur **Revenir au thème précédent**.

Seul le thème change : vos modules, leurs réglages, la position des modules, les tailles d'images et les mises en page restent identiques. Un brouillon ne peut être mis en ligne qu'à la place d'un thème de la même famille (même thème parent).

## Gratuit ou Pro

La version gratuite permet 100 modifications par mois et par boutique ; la lecture, les recherches et les audits sont illimités. Le compteur repart à zéro le 1er du mois.

Piskee Pro (29 € HT par mois, ou 290 € HT par an avec 2 mois offerts, prix de lancement garanti à vie pour les 100 premiers clients, sans engagement) lève la limite et ajoute les redirections 301 et le support prioritaire. Une licence Pro couvre une boutique : en multiboutique, chaque boutique supplémentaire coûte 10 € HT par mois. Une prise en main avec l'agence est proposée à 150 € HT.

S'abonner : [au mois](https://buy.stripe.com/28EbJ1bO4121gcQ0kZ9EI00) · [à l'année](https://buy.stripe.com/28EcN59FW5ihd0Ed7L9EI02) · [boutique supplémentaire](https://buy.stripe.com/aFa6oH19qaCBgcQ2t79EI04) · [prise en main](https://buy.stripe.com/3cI14nbO47qp7GkffT9EI03).

La clé de licence reçue par e-mail se colle dans l'onglet Gratuit / Pro.

## Dépannage

- **« Cette demande de connexion n'est pas valide »** à l'autorisation : supprimez le connecteur dans Claude, puis ajoutez-le à nouveau.
- **Erreur 401 permanente** : certains hébergeurs retirent l'en-tête Authorization. Ajoutez `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` en haut du fichier `.htaccess`.
- **Claude ne voit pas les nouveaux outils** : ouvrez une nouvelle conversation.
- **Erreur à l'ouverture de l'écran d'autorisation** : vérifiez que le site n'est pas en maintenance et qu'aucun pare-feu ne bloque les adresses `/.well-known/`.
- **Une modification de thème ne se voit pas** : c'est normal tant que le brouillon n'est pas en ligne.

## Besoin d'aide ?

L'équipe Piskee répond aux questions sur le module, et accompagne les boutiques qui veulent aller plus loin : audit SEO et GEO, contenus, Google Ads, développement sur mesure.

- Contact : [piskee.com/contact](https://www.piskee.com/contact/)
- E-mail : louisalexandre@piskee.com
- WhatsApp : +33 7 69 15 39 92
- Audit SEO & GEO gratuit en 48 h : [piskee.com](https://www.piskee.com/)

## Mentions et responsabilités

Piskee pour PrestaShop est un module indépendant édité par Piskee (Chantilly, Oise, France). Ce n'est pas un produit officiel de PrestaShop SA ni d'Anthropic, PBC, qui ne l'ont ni développé, ni validé, ni approuvé, et qui n'assument aucune responsabilité à son sujet. PrestaShop est une marque de PrestaShop SA ; Claude est une marque d'Anthropic, PBC ; ces noms sont cités uniquement pour indiquer la compatibilité. Le module est fourni « en l'état », sans garantie, dans les limites prévues par la loi et par la licence AFL-3.0. Les actions décidées et validées par le marchand restent sous sa responsabilité : faites des sauvegardes régulières et vérifiez les modifications avant de les accepter.
