<?php
/**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Texts shown to the merchant, in French, English, Spanish, Italian and
 * Polish. Any text missing in a language falls back to English. Everything
 * read by the assistant (tool descriptions) stays in English in
 * PiskeeConnectorTools.
 */
class PiskeeConnectorI18n
{
    /** Languages of the merchant interface, in display order. */
    public const LANGS = ['fr', 'en', 'es', 'it', 'pl'];

    /** Language used when a text is missing in the requested one. */
    public const FALLBACK = 'en';

    /**
     * PrestaShop language iso codes mapped to an interface language.
     * mx, ag and cb are the iso codes PrestaShop gives to Spanish (Mexico),
     * Spanish (Argentina) and Spanish (Colombia). Note that "ar" is Arabic.
     */
    private const ISO_MAP = [
        'fr' => 'fr',
        'en' => 'en',
        'gb' => 'en',
        'es' => 'es',
        'mx' => 'es',
        'ag' => 'es',
        'cb' => 'es',
        'it' => 'it',
        'pl' => 'pl',
    ];

    private const LANGUAGE_NAMES = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'it' => 'Italiano',
        'pl' => 'Polski',
    ];

    private const TOOL_TITLES = [
        'shop_info' => ['fr' => 'Informations de la boutique', 'en' => 'Shop information', 'es' => 'Información de la tienda', 'it' => 'Informazioni sul negozio', 'pl' => 'Informacje o sklepie'],
        'search_products' => ['fr' => 'Rechercher des produits', 'en' => 'Search products', 'es' => 'Buscar productos', 'it' => 'Ricerca prodotti', 'pl' => 'Wyszukiwanie produktów'],
        'get_product' => ['fr' => 'Lire un produit', 'en' => 'Read a product', 'es' => 'Ver un producto', 'it' => 'Lettura di un prodotto', 'pl' => 'Podgląd produktu'],
        'update_product' => ['fr' => 'Modifier un produit', 'en' => 'Update a product', 'es' => 'Modificar un producto', 'it' => 'Modifica di un prodotto', 'pl' => 'Edycja produktu'],
        'create_product' => ['fr' => 'Créer un produit', 'en' => 'Create a product', 'es' => 'Crear un producto', 'it' => 'Creazione di un prodotto', 'pl' => 'Tworzenie produktu'],
        'set_stock' => ['fr' => 'Modifier le stock', 'en' => 'Set stock', 'es' => 'Modificar el stock', 'it' => 'Aggiornamento delle giacenze', 'pl' => 'Zmiana stanu magazynowego'],
        'list_categories' => ['fr' => 'Lister les catégories', 'en' => 'List categories', 'es' => 'Listar las categorías', 'it' => 'Elenco delle categorie', 'pl' => 'Lista kategorii'],
        'get_category' => ['fr' => 'Lire une catégorie', 'en' => 'Read a category', 'es' => 'Ver una categoría', 'it' => 'Lettura di una categoria', 'pl' => 'Podgląd kategorii'],
        'update_category' => ['fr' => 'Modifier une catégorie', 'en' => 'Update a category', 'es' => 'Modificar una categoría', 'it' => 'Modifica di una categoria', 'pl' => 'Edycja kategorii'],
        'set_product_categories' => ['fr' => 'Classer un produit', 'en' => 'Set product categories', 'es' => 'Asignar categorías a un producto', 'it' => 'Assegnazione delle categorie di un prodotto', 'pl' => 'Przypisanie kategorii produktu'],
        'list_combinations' => ['fr' => 'Lister les déclinaisons', 'en' => 'List combinations', 'es' => 'Listar las combinaciones', 'it' => 'Elenco delle combinazioni', 'pl' => 'Lista kombinacji'],
        'list_attribute_groups' => ['fr' => 'Lister les attributs', 'en' => 'List attributes', 'es' => 'Listar los atributos', 'it' => 'Elenco degli attributi', 'pl' => 'Lista atrybutów'],
        'create_attribute_group' => ['fr' => 'Créer un attribut', 'en' => 'Create an attribute group', 'es' => 'Crear un atributo', 'it' => 'Creazione di un attributo', 'pl' => 'Tworzenie atrybutu'],
        'create_attribute_value' => ['fr' => 'Créer une valeur d\'attribut', 'en' => 'Create an attribute value', 'es' => 'Crear un valor de atributo', 'it' => 'Creazione di un valore di attributo', 'pl' => 'Tworzenie wartości atrybutu'],
        'create_combinations' => ['fr' => 'Créer des déclinaisons', 'en' => 'Create combinations', 'es' => 'Crear combinaciones', 'it' => 'Creazione di combinazioni', 'pl' => 'Tworzenie kombinacji'],
        'update_combination' => ['fr' => 'Modifier une déclinaison', 'en' => 'Update a combination', 'es' => 'Modificar una combinación', 'it' => 'Modifica di una combinazione', 'pl' => 'Edycja kombinacji'],
        'list_product_images' => ['fr' => 'Lister les images', 'en' => 'List images', 'es' => 'Listar las imágenes', 'it' => 'Elenco delle immagini', 'pl' => 'Lista zdjęć'],
        'add_product_image_from_url' => ['fr' => 'Ajouter une image', 'en' => 'Add an image', 'es' => 'Añadir una imagen', 'it' => 'Aggiunta di un\'immagine', 'pl' => 'Dodawanie zdjęcia'],
        'update_product_image' => ['fr' => 'Modifier une image (alt, couverture)', 'en' => 'Update an image (alt, cover)', 'es' => 'Modificar una imagen (alt, portada)', 'it' => 'Modifica di un\'immagine (alt, copertina)', 'pl' => 'Edycja zdjęcia (alt, zdjęcie główne)'],
        'optimize_product_images' => ['fr' => 'Redimensionner les images', 'en' => 'Resize images', 'es' => 'Redimensionar las imágenes', 'it' => 'Ridimensionamento delle immagini', 'pl' => 'Zmiana rozmiaru zdjęć'],
        'list_features' => ['fr' => 'Lister les caractéristiques', 'en' => 'List features', 'es' => 'Listar las características', 'it' => 'Elenco delle caratteristiche', 'pl' => 'Lista cech'],
        'create_feature' => ['fr' => 'Créer une caractéristique', 'en' => 'Create a feature', 'es' => 'Crear una característica', 'it' => 'Creazione di una caratteristica', 'pl' => 'Tworzenie cechy'],
        'set_product_feature' => ['fr' => 'Renseigner une caractéristique', 'en' => 'Set a product feature', 'es' => 'Asignar una característica', 'it' => 'Impostazione di una caratteristica', 'pl' => 'Ustawienie cechy produktu'],
        'list_brands' => ['fr' => 'Lister les marques', 'en' => 'List brands', 'es' => 'Listar las marcas', 'it' => 'Elenco dei marchi', 'pl' => 'Lista marek'],
        'set_related_products' => ['fr' => 'Produits associés', 'en' => 'Related products', 'es' => 'Productos relacionados', 'it' => 'Prodotti correlati', 'pl' => 'Produkty powiązane'],
        'get_cross_selling' => ['fr' => 'Vente croisée d\'un produit', 'en' => 'Cross-selling of a product', 'es' => 'Venta cruzada de un producto', 'it' => 'Vendita incrociata di un prodotto', 'pl' => 'Sprzedaż krzyżowa produktu'],
        'suggest_related_products' => ['fr' => 'Suggestions « achetés ensemble »', 'en' => '"Bought together" suggestions', 'es' => 'Sugerencias «comprados juntos»', 'it' => 'Suggerimenti «acquistati insieme»', 'pl' => 'Sugestie „kupowane razem”'],
        'bulk_set_related_products' => ['fr' => 'Produits associés en masse', 'en' => 'Related products in bulk', 'es' => 'Productos relacionados en bloque', 'it' => 'Prodotti correlati in blocco', 'pl' => 'Masowe produkty powiązane'],
        'configure_cross_selling_block' => ['fr' => 'Bloc « ont aussi acheté » (fiche et panier)', 'en' => '"Also bought" block (product and cart)', 'es' => 'Bloque «también compraron» (ficha y carrito)', 'it' => 'Blocco «hanno acquistato anche» (scheda e carrello)', 'pl' => 'Blok „kupili również” (karta produktu i koszyk)'],
        'list_promotions' => ['fr' => 'Lister les promotions', 'en' => 'List promotions', 'es' => 'Listar las promociones', 'it' => 'Elenco delle promozioni', 'pl' => 'Lista promocji'],
        'create_promotion' => ['fr' => 'Créer une promotion', 'en' => 'Create a promotion', 'es' => 'Crear una promoción', 'it' => 'Creazione di una promozione', 'pl' => 'Tworzenie promocji'],
        'delete_promotion' => ['fr' => 'Supprimer une promotion', 'en' => 'Delete a promotion', 'es' => 'Eliminar una promoción', 'it' => 'Eliminazione di una promozione', 'pl' => 'Usuwanie promocji'],
        'catalog_audit' => ['fr' => 'Audit du catalogue', 'en' => 'Catalogue audit', 'es' => 'Auditoría del catálogo', 'it' => 'Audit del catalogo', 'pl' => 'Audyt katalogu'],
        'search_customers' => ['fr' => 'Rechercher des clients', 'en' => 'Search customers', 'es' => 'Buscar clientes', 'it' => 'Ricerca clienti', 'pl' => 'Wyszukiwanie klientów'],
        'get_customer' => ['fr' => 'Lire un client', 'en' => 'Read a customer', 'es' => 'Ver un cliente', 'it' => 'Lettura di un cliente', 'pl' => 'Podgląd klienta'],
        'sales_summary' => ['fr' => 'Synthèse des ventes', 'en' => 'Sales summary', 'es' => 'Resumen de ventas', 'it' => 'Riepilogo vendite', 'pl' => 'Podsumowanie sprzedaży'],
        'low_stock' => ['fr' => 'Stocks bas', 'en' => 'Low stock', 'es' => 'Stock bajo', 'it' => 'Scorte basse', 'pl' => 'Niskie stany magazynowe'],
        'list_customer_messages' => ['fr' => 'Messages clients', 'en' => 'Customer messages', 'es' => 'Mensajes de clientes', 'it' => 'Messaggi dei clienti', 'pl' => 'Wiadomości od klientów'],
        'get_customer_messages' => ['fr' => 'Lire une conversation', 'en' => 'Read a conversation', 'es' => 'Ver una conversación', 'it' => 'Lettura di una conversazione', 'pl' => 'Podgląd rozmowy'],
        'reply_to_customer' => ['fr' => 'Répondre à un client', 'en' => 'Reply to a customer', 'es' => 'Responder a un cliente', 'it' => 'Risposta a un cliente', 'pl' => 'Odpowiedź klientowi'],
        'create_voucher' => ['fr' => 'Créer un bon de réduction', 'en' => 'Create a voucher', 'es' => 'Crear un cupón de descuento', 'it' => 'Creazione di un buono sconto', 'pl' => 'Tworzenie kodu rabatowego'],
        'list_themes' => ['fr' => 'Lister les thèmes', 'en' => 'List themes', 'es' => 'Listar los temas', 'it' => 'Elenco dei temi', 'pl' => 'Lista motywów'],
        'create_child_theme' => ['fr' => 'Créer un thème enfant brouillon', 'en' => 'Create a draft child theme', 'es' => 'Crear un tema hijo en borrador', 'it' => 'Creazione di un tema figlio in bozza', 'pl' => 'Tworzenie szkicu motywu potomnego'],
        'list_theme_files' => ['fr' => 'Lister les fichiers du thème', 'en' => 'List theme files', 'es' => 'Listar los archivos del tema', 'it' => 'Elenco dei file del tema', 'pl' => 'Lista plików motywu'],
        'read_theme_file' => ['fr' => 'Lire un fichier du thème', 'en' => 'Read a theme file', 'es' => 'Ver un archivo del tema', 'it' => 'Lettura di un file del tema', 'pl' => 'Odczyt pliku motywu'],
        'search_theme_files' => ['fr' => 'Chercher dans le thème', 'en' => 'Search the theme', 'es' => 'Buscar en el tema', 'it' => 'Ricerca nel tema', 'pl' => 'Wyszukiwanie w motywie'],
        'write_theme_file' => ['fr' => 'Modifier un fichier (brouillon)', 'en' => 'Write a file (draft)', 'es' => 'Modificar un archivo (borrador)', 'it' => 'Modifica di un file (bozza)', 'pl' => 'Edycja pliku (szkic)'],
        'remove_theme_file' => ['fr' => 'Retirer une surcharge (brouillon)', 'en' => 'Remove an override (draft)', 'es' => 'Quitar una sobrecarga (borrador)', 'it' => 'Rimozione di un override (bozza)', 'pl' => 'Usunięcie nadpisania (szkic)'],
        'list_theme_backups' => ['fr' => 'Lister les sauvegardes', 'en' => 'List backups', 'es' => 'Listar las copias de seguridad', 'it' => 'Elenco dei backup', 'pl' => 'Lista kopii zapasowych'],
        'restore_theme_file' => ['fr' => 'Restaurer une sauvegarde', 'en' => 'Restore a backup', 'es' => 'Restaurar una copia de seguridad', 'it' => 'Ripristino di un backup', 'pl' => 'Przywracanie kopii zapasowej'],
        'audit_page' => ['fr' => 'Audit Hn et fil d\'Ariane', 'en' => 'Headings and breadcrumb audit', 'es' => 'Auditoría de Hn y migas de pan', 'it' => 'Audit di Hn e breadcrumb', 'pl' => 'Audyt nagłówków Hn i ścieżki nawigacyjnej'],
        'search_orders' => ['fr' => 'Rechercher des commandes', 'en' => 'Search orders', 'es' => 'Buscar pedidos', 'it' => 'Ricerca ordini', 'pl' => 'Wyszukiwanie zamówień'],
        'get_order' => ['fr' => 'Lire une commande et son suivi', 'en' => 'Read an order and its tracking', 'es' => 'Ver un pedido y su seguimiento', 'it' => 'Lettura di un ordine e del suo tracciamento', 'pl' => 'Podgląd zamówienia i śledzenia przesyłki'],
        'list_order_statuses' => ['fr' => 'Lister les statuts de commande', 'en' => 'List order statuses', 'es' => 'Listar los estados de pedido', 'it' => 'Elenco degli stati degli ordini', 'pl' => 'Lista statusów zamówień'],
        'set_order_status' => ['fr' => 'Changer le statut d\'une commande', 'en' => 'Change an order status', 'es' => 'Cambiar el estado de un pedido', 'it' => 'Cambio di stato di un ordine', 'pl' => 'Zmiana statusu zamówienia'],
        'set_tracking_number' => ['fr' => 'Renseigner un numéro de suivi', 'en' => 'Set a tracking number', 'es' => 'Indicar un número de seguimiento', 'it' => 'Inserimento di un numero di tracciamento', 'pl' => 'Ustawienie numeru przesyłki'],
        'list_cms_pages' => ['fr' => 'Lister les pages CMS', 'en' => 'List CMS pages', 'es' => 'Listar las páginas CMS', 'it' => 'Elenco delle pagine CMS', 'pl' => 'Lista stron CMS'],
        'get_cms_page' => ['fr' => 'Lire une page CMS', 'en' => 'Read a CMS page', 'es' => 'Ver una página CMS', 'it' => 'Lettura di una pagina CMS', 'pl' => 'Podgląd strony CMS'],
        'update_cms_page' => ['fr' => 'Modifier une page CMS', 'en' => 'Update a CMS page', 'es' => 'Modificar una página CMS', 'it' => 'Modifica di una pagina CMS', 'pl' => 'Edycja strony CMS'],
        'bulk_update_products' => ['fr' => 'Modifier des produits en masse', 'en' => 'Bulk update products', 'es' => 'Modificar productos en bloque', 'it' => 'Modifica di prodotti in blocco', 'pl' => 'Masowa edycja produktów'],
    ];

    private const SCOPES = [
        'catalog.read' => [
            'fr' => 'Lire le catalogue : produits, déclinaisons, catégories, images, stocks',
            'en' => 'Read the catalogue: products, combinations, categories, images, stock',
            'es' => 'Ver el catálogo: productos, combinaciones, categorías, imágenes, stock',
            'it' => 'Leggere il catalogo: prodotti, combinazioni, categorie, immagini, giacenze',
            'pl' => 'Odczyt katalogu: produkty, kombinacje, kategorie, zdjęcia, stany magazynowe',
        ],
        'orders.read' => [
            'fr' => 'Lire les commandes et leur suivi',
            'en' => 'Read orders and their tracking',
            'es' => 'Ver los pedidos y su seguimiento',
            'it' => 'Leggere gli ordini e il loro tracciamento',
            'pl' => 'Odczyt zamówień i śledzenia przesyłek',
        ],
        'orders.write' => [
            'fr' => 'Changer le statut et le suivi des commandes (peut envoyer des e-mails)',
            'en' => 'Change order status and tracking (may send emails)',
            'es' => 'Cambiar el estado y el seguimiento de los pedidos (puede enviar correos electrónicos)',
            'it' => 'Modificare lo stato e il tracciamento degli ordini (può inviare e-mail)',
            'pl' => 'Zmiana statusu zamówień i numerów przesyłek (może wysyłać e-maile)',
        ],
        'catalog.write' => [
            'fr' => 'Modifier le catalogue : produits, prix, stocks, déclinaisons, images, catégories, promotions',
            'en' => 'Change the catalogue: products, prices, stock, combinations, images, categories, promotions',
            'es' => 'Modificar el catálogo: productos, precios, stock, combinaciones, imágenes, categorías, promociones',
            'it' => 'Modificare il catalogo: prodotti, prezzi, giacenze, combinazioni, immagini, categorie, promozioni',
            'pl' => 'Zmiany w katalogu: produkty, ceny, stany magazynowe, kombinacje, zdjęcia, kategorie, promocje',
        ],
        'customers.read' => [
            'fr' => 'Lire les fiches clients',
            'en' => 'Read customer records',
            'es' => 'Ver las fichas de clientes',
            'it' => 'Leggere le schede dei clienti',
            'pl' => 'Odczyt danych klientów',
        ],
        'sales.read' => [
            'fr' => 'Lire les chiffres de vente',
            'en' => 'Read sales figures',
            'es' => 'Ver las cifras de ventas',
            'it' => 'Leggere i dati di vendita',
            'pl' => 'Odczyt danych sprzedażowych',
        ],
        'service.read' => [
            'fr' => 'Lire les messages du service client',
            'en' => 'Read customer service messages',
            'es' => 'Ver los mensajes de atención al cliente',
            'it' => 'Leggere i messaggi del servizio clienti',
            'pl' => 'Odczyt wiadomości obsługi klienta',
        ],
        'service.write' => [
            'fr' => 'Répondre aux clients (envoie des e-mails)',
            'en' => 'Reply to customers (sends emails)',
            'es' => 'Responder a los clientes (envía correos electrónicos)',
            'it' => 'Rispondere ai clienti (invia e-mail)',
            'pl' => 'Odpowiadanie klientom (wysyła e-maile)',
        ],
        'marketing.write' => [
            'fr' => 'Créer des bons de réduction',
            'en' => 'Create vouchers',
            'es' => 'Crear cupones de descuento',
            'it' => 'Creare buoni sconto',
            'pl' => 'Tworzenie kodów rabatowych',
        ],
        'theme.read' => [
            'fr' => 'Lire les thèmes et auditer les pages',
            'en' => 'Read themes and audit pages',
            'es' => 'Ver los temas y auditar las páginas',
            'it' => 'Leggere i temi e analizzare le pagine',
            'pl' => 'Odczyt motywów i audyt stron',
        ],
        'theme.write' => [
            'fr' => 'Modifier les thèmes enfants brouillons (jamais le thème en ligne)',
            'en' => 'Change draft child themes (never the live theme)',
            'es' => 'Modificar los temas hijo en borrador (nunca el tema publicado)',
            'it' => 'Modificare i temi figlio in bozza (mai il tema online)',
            'pl' => 'Zmiany w szkicach motywów potomnych (nigdy w opublikowanym motywie)',
        ],
    ];

    private const GROUPS = [
        'shop' => ['fr' => 'Boutique', 'en' => 'Shop', 'es' => 'Tienda', 'it' => 'Negozio', 'pl' => 'Sklep'],
        'catalog' => ['fr' => 'Produits et catégories', 'en' => 'Products and categories', 'es' => 'Productos y categorías', 'it' => 'Prodotti e categorie', 'pl' => 'Produkty i kategorie'],
        'variants' => ['fr' => 'Déclinaisons', 'en' => 'Combinations', 'es' => 'Combinaciones', 'it' => 'Combinazioni', 'pl' => 'Kombinacje'],
        'images' => ['fr' => 'Images', 'en' => 'Images', 'es' => 'Imágenes', 'it' => 'Immagini', 'pl' => 'Zdjęcia'],
        'enrichment' => ['fr' => 'Enrichissement et optimisation', 'en' => 'Enrichment and optimisation', 'es' => 'Enriquecimiento y optimización', 'it' => 'Arricchimento e ottimizzazione', 'pl' => 'Wzbogacanie i optymalizacja'],
        'crossselling' => ['fr' => 'Vente croisée et panier', 'en' => 'Cross-selling and cart', 'es' => 'Venta cruzada y carrito', 'it' => 'Vendita incrociata e carrello', 'pl' => 'Sprzedaż krzyżowa i koszyk'],
        'redirects' => ['fr' => 'Redirections 301 (Pro)', 'en' => '301 redirects (Pro)', 'es' => 'Redirecciones 301 (Pro)', 'it' => 'Redirect 301 (Pro)', 'pl' => 'Przekierowania 301 (Pro)'],
        'customers' => ['fr' => 'Clients', 'en' => 'Customers', 'es' => 'Clientes', 'it' => 'Clienti', 'pl' => 'Klienci'],
        'orders' => ['fr' => 'Commandes et suivi', 'en' => 'Orders and tracking', 'es' => 'Pedidos y seguimiento', 'it' => 'Ordini e tracciamento', 'pl' => 'Zamówienia i śledzenie'],
        'cms' => ['fr' => 'Pages CMS', 'en' => 'CMS pages', 'es' => 'Páginas CMS', 'it' => 'Pagine CMS', 'pl' => 'Strony CMS'],
        'daily' => ['fr' => 'Gestion quotidienne', 'en' => 'Daily management', 'es' => 'Gestión diaria', 'it' => 'Gestione quotidiana', 'pl' => 'Codzienne zarządzanie'],
        'theme' => ['fr' => 'Thème enfant', 'en' => 'Child theme', 'es' => 'Tema hijo', 'it' => 'Tema figlio', 'pl' => 'Motyw potomny'],
        // Default group of the tools brought by add-ons (see PiskeeConnectorTools::extensions()).
        'pro' => ['fr' => 'Piskee Pro', 'en' => 'Piskee Pro', 'es' => 'Piskee Pro', 'it' => 'Piskee Pro', 'pl' => 'Piskee Pro'],
    ];

    /** Module name and description shown in the PrestaShop module list. */
    private const MODULE = [
        'display_name' => [
            'fr' => 'Piskee, l\'assistant IA de votre boutique (compatible Claude)',
            'en' => 'Piskee, the AI assistant for your shop (works with Claude)',
            'es' => 'Piskee, el asistente de IA de su tienda (compatible con Claude)',
            'it' => 'Piskee, l\'assistente IA del Suo negozio (compatibile con Claude)',
            'pl' => 'Piskee, asystent AI Państwa sklepu (współpracuje z Claude)',
        ],
        'description' => [
            'fr' => 'Pilotez votre boutique avec Claude : catalogue, déclinaisons, images, stocks, vente croisée, service client et thème enfant. Redirections 301 avec Piskee Pro. Module indépendant, non officiel.',
            'en' => 'Run your shop with Claude: catalogue, combinations, images, stock, cross-selling, customer service and child theme. 301 redirects with Piskee Pro. Independent, unofficial module.',
            'es' => 'Gestione su tienda con Claude: catálogo, combinaciones, imágenes, stock, venta cruzada, atención al cliente y tema hijo. Redirecciones 301 con Piskee Pro. Módulo independiente, no oficial.',
            'it' => 'Gestisca il Suo negozio con Claude: catalogo, combinazioni, immagini, giacenze, vendita incrociata, assistenza clienti e tema figlio. Redirect 301 con Piskee Pro. Modulo indipendente, non ufficiale.',
            'pl' => 'Zarządzanie sklepem z Claude: katalog, kombinacje, zdjęcia, stany magazynowe, sprzedaż krzyżowa, obsługa klienta i motyw potomny. Przekierowania 301 w Piskee Pro. Niezależny, nieoficjalny moduł.',
        ],
        'confirm_uninstall' => [
            'fr' => 'Tous les assistants connectés seront déconnectés. Continuer ?',
            'en' => 'Every connected assistant will be disconnected. Continue?',
            'es' => 'Todos los asistentes conectados se desconectarán. ¿Continuar?',
            'it' => 'Tutti gli assistenti collegati verranno scollegati. Continuare?',
            'pl' => 'Wszyscy połączeni asystenci zostaną odłączeni. Kontynuować?',
        ],
    ];

    /** Prices of the Pro plan and of the onboarding, for fr and en see PiskeeConnectorLicense. */
    private const PRICES = [
        'pro' => [
            'es' => '29 € + IVA / mes',
            'it' => '29 € + IVA / mese',
            'pl' => '29 € netto / mies.',
        ],
        'onboarding' => [
            'es' => '150 € + IVA',
            'it' => '150 € + IVA',
            'pl' => '150 € netto',
        ],
    ];

    /** Titles and descriptions of the ready-made MCP prompts (PiskeeConnectorMcp::prompts()). */
    private const PROMPTS = [
        'daily-brief' => [
            'title' => [
                'fr' => 'Point du jour',
                'en' => 'Daily brief',
                'es' => 'Resumen del día',
                'it' => 'Riepilogo del giorno',
                'pl' => 'Podsumowanie dnia',
            ],
            'description' => [
                'fr' => 'Ventes, stocks bas et messages clients en attente, en un seul point.',
                'en' => 'Sales, low stock and pending customer messages in one brief.',
                'es' => 'Ventas, stock bajo y mensajes de clientes pendientes, en un solo resumen.',
                'it' => 'Vendite, scorte basse e messaggi dei clienti in attesa, in un unico riepilogo.',
                'pl' => 'Sprzedaż, niskie stany i oczekujące wiadomości od klientów w jednym podsumowaniu.',
            ],
        ],
        'catalog-optimisation' => [
            'title' => [
                'fr' => 'Optimiser le catalogue',
                'en' => 'Optimise the catalogue',
                'es' => 'Optimizar el catálogo',
                'it' => 'Ottimizzazione del catalogo',
                'pl' => 'Optymalizacja katalogu',
            ],
            'description' => [
                'fr' => 'Audit du catalogue puis corrections SEO et GEO priorisées, produit par produit.',
                'en' => 'Catalogue audit, then prioritised SEO and GEO fixes, product by product.',
                'es' => 'Auditoría del catálogo y, después, correcciones SEO y GEO priorizadas, producto por producto.',
                'it' => 'Audit del catalogo, poi correzioni SEO e GEO in ordine di priorità, prodotto per prodotto.',
                'pl' => 'Audyt katalogu, a następnie poprawki SEO i GEO według priorytetu, produkt po produkcie.',
            ],
            'arg.focus' => [
                'fr' => 'Ce qui compte le plus (ex. : une catégorie)',
                'en' => 'What matters most (e.g. one category)',
                'es' => 'Lo que más importa (p. ej.: una categoría)',
                'it' => 'Ciò che conta di più (ad es. una categoria)',
                'pl' => 'Co jest najważniejsze (np. jedna kategoria)',
            ],
        ],
        'theme-headings-breadcrumb' => [
            'title' => [
                'fr' => 'Nettoyer les Hn et poser un fil d\'Ariane',
                'en' => 'Clean headings and add a breadcrumb',
                'es' => 'Limpiar las Hn y añadir migas de pan',
                'it' => 'Sistemare gli Hn e aggiungere un breadcrumb',
                'pl' => 'Porządkowanie nagłówków Hn i ścieżki nawigacyjnej',
            ],
            'description' => [
                'fr' => 'Audit des balises Hn et du fil d\'Ariane, corrections dans un thème enfant brouillon.',
                'en' => 'Audit of headings and breadcrumb, fixes in a draft child theme.',
                'es' => 'Auditoría de las etiquetas Hn y de las migas de pan, con correcciones en un tema hijo en borrador.',
                'it' => 'Audit dei tag Hn e del breadcrumb, con correzioni in un tema figlio in bozza.',
                'pl' => 'Audyt nagłówków Hn i ścieżki nawigacyjnej, poprawki w szkicu motywu potomnego.',
            ],
        ],
    ];

    /**
     * Interface language for a PrestaShop iso code ("fr", "es", "mx"...) or a
     * locale ("es-AR", "it_IT"). Anything unknown gives English.
     */
    public static function lang(?string $iso): string
    {
        $iso = Tools::strtolower(trim((string) $iso));
        if (isset(self::ISO_MAP[$iso])) {
            return self::ISO_MAP[$iso];
        }
        $prefix = (string) preg_replace('/[-_].*$/', '', $iso);
        if ($prefix !== $iso && in_array($prefix, self::LANGS, true)) {
            return $prefix;
        }

        return 'en';
    }

    /**
     * Whether a code is one of the interface languages (for ?pklang= and the
     * language cookie).
     */
    public static function isSupported(string $code): bool
    {
        return in_array($code, self::LANGS, true);
    }

    /**
     * Interface languages with their own name, for a language menu.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return self::LANGUAGE_NAMES;
    }

    /**
     * The language the single switch link points to: English, or French
     * when the page is already in English.
     */
    public static function switchTarget(string $lang): string
    {
        return $lang === 'en' ? 'fr' : 'en';
    }

    /**
     * Documentation files, relative to the module folder, by language.
     *
     * @return array<string, string>
     */
    public static function docFiles(): array
    {
        $out = [];
        foreach (self::LANGS as $code) {
            $out[$code] = 'docs/README.' . $code . '.md';
        }

        return $out;
    }

    /**
     * Picks the text of a language from a {"fr": ..., "en": ...} array,
     * falling back to English, then French, then $fallback. Used for core
     * texts and for the titles given by add-on tools.
     *
     * @param array<string, mixed> $texts
     */
    public static function pick(array $texts, string $lang, string $fallback = ''): string
    {
        foreach ([$lang, self::FALLBACK, 'fr'] as $code) {
            if (isset($texts[$code]) && is_scalar($texts[$code]) && (string) $texts[$code] !== '') {
                return (string) $texts[$code];
            }
        }

        return $fallback;
    }

    public static function toolTitle(string $tool, string $lang): string
    {
        return isset(self::TOOL_TITLES[$tool]) ? self::pick(self::TOOL_TITLES[$tool], $lang, $tool) : $tool;
    }

    public static function scopeLabel(string $scope, string $lang): string
    {
        return isset(self::SCOPES[$scope]) ? self::pick(self::SCOPES[$scope], $lang, $scope) : $scope;
    }

    public static function groupLabel(string $group, string $lang): string
    {
        return isset(self::GROUPS[$group]) ? self::pick(self::GROUPS[$group], $lang, $group) : $group;
    }

    /**
     * Module name, description and uninstall confirmation.
     *
     * @return array{display_name:string, description:string, confirm_uninstall:string}
     */
    public static function module(string $lang): array
    {
        return [
            'display_name' => self::pick(self::MODULE['display_name'], $lang),
            'description' => self::pick(self::MODULE['description'], $lang),
            'confirm_uninstall' => self::pick(self::MODULE['confirm_uninstall'], $lang),
        ];
    }

    /** Monthly price of Piskee Pro, written the local way. */
    public static function price(string $lang): string
    {
        if ($lang === 'fr') {
            return PiskeeConnectorLicense::PRO_PRICE;
        }

        return self::PRICES['pro'][$lang] ?? PiskeeConnectorLicense::PRO_PRICE_EN;
    }

    /** Price of the onboarding session, written the local way. */
    public static function onboardingPrice(string $lang): string
    {
        if ($lang === 'fr') {
            return PiskeeConnectorLicense::ONBOARDING_PRICE;
        }

        return self::PRICES['onboarding'][$lang] ?? PiskeeConnectorLicense::ONBOARDING_PRICE_EN;
    }

    /**
     * A text of a ready-made prompt: "title", "description" or "arg.<name>".
     */
    public static function promptText(string $prompt, string $key, string $lang): string
    {
        return isset(self::PROMPTS[$prompt][$key]) ? self::pick(self::PROMPTS[$prompt][$key], $lang) : '';
    }

    /**
     * Texts of the module page. Keys missing in a language come from English.
     *
     * @return array<string, string>
     */
    public static function ui(string $lang): array
    {
        $en = self::uiEn();
        switch ($lang) {
            case 'fr':
                return array_merge($en, self::uiFr());
            case 'es':
                return array_merge($en, self::uiEs());
            case 'it':
                return array_merge($en, self::uiIt());
            case 'pl':
                return array_merge($en, self::uiPl());
            default:
                return $en;
        }
    }

    /**
     * Texts of the consent screen. Keys missing in a language come from English.
     *
     * @return array<string, string>
     */
    public static function consent(string $lang): array
    {
        $all = [
            'fr' => [
                'title' => 'Connecter un assistant',
                'lead' => 'demande à agir sur votre boutique en votre nom.',
                'application' => 'Application',
                'returns_to' => 'Réponse envoyée à',
                'as_employee' => 'Connecté en tant que',
                'shop' => 'Boutique',
                'rights' => 'Ce que l\'assistant pourra faire',
                'limits' => 'L\'assistant n\'aura jamais plus de droits que votre profil d\'employé, et vous demandera confirmation avant chaque modification. Vous pourrez le déconnecter à tout moment depuis le module Piskee.',
                'allow' => 'Autoriser',
                'deny' => 'Refuser',
                'expired_title' => 'Demande expirée',
                'expired_text' => 'Cette demande de connexion n\'est plus valide. Relancez la connexion depuis votre assistant.',
                'help' => 'Une question sur cette connexion ?',
            ],
            'en' => [
                'title' => 'Connect an assistant',
                'lead' => 'asks to act on your shop on your behalf.',
                'application' => 'Application',
                'returns_to' => 'Answer sent to',
                'as_employee' => 'Signed in as',
                'shop' => 'Shop',
                'rights' => 'What the assistant will be able to do',
                'limits' => 'The assistant will never get more rights than your employee profile, and will ask for your confirmation before each change. You can disconnect it at any time from the Piskee module.',
                'allow' => 'Authorise',
                'deny' => 'Refuse',
                'expired_title' => 'Request expired',
                'expired_text' => 'This connection request is no longer valid. Start the connection again from your assistant.',
                'help' => 'A question about this connection?',
            ],
            'es' => [
                'title' => 'Conectar un asistente',
                'lead' => 'solicita actuar en su tienda en su nombre.',
                'application' => 'Aplicación',
                'returns_to' => 'Respuesta enviada a',
                'as_employee' => 'Conectado como',
                'shop' => 'Tienda',
                'rights' => 'Lo que podrá hacer el asistente',
                'limits' => 'El asistente nunca tendrá más permisos que su perfil de empleado y le pedirá confirmación antes de cada modificación. Podrá desconectarlo en cualquier momento desde el módulo Piskee.',
                'allow' => 'Autorizar',
                'deny' => 'Rechazar',
                'expired_title' => 'Solicitud caducada',
                'expired_text' => 'Esta solicitud de conexión ya no es válida. Vuelva a iniciar la conexión desde su asistente.',
                'help' => '¿Tiene alguna pregunta sobre esta conexión?',
            ],
            'it' => [
                'title' => 'Collegare un assistente',
                'lead' => 'chiede di agire sul Suo negozio per Suo conto.',
                'application' => 'Applicazione',
                'returns_to' => 'Risposta inviata a',
                'as_employee' => 'Connesso come',
                'shop' => 'Negozio',
                'rights' => 'Cosa potrà fare l\'assistente',
                'limits' => 'L\'assistente non avrà mai più permessi del Suo profilo dipendente e Le chiederà conferma prima di ogni modifica. Potrà scollegarlo in qualsiasi momento dal modulo Piskee.',
                'allow' => 'Autorizza',
                'deny' => 'Rifiuta',
                'expired_title' => 'Richiesta scaduta',
                'expired_text' => 'Questa richiesta di connessione non è più valida. Riavvii la connessione dal Suo assistente.',
                'help' => 'Ha una domanda su questa connessione?',
            ],
            'pl' => [
                'title' => 'Połączenie asystenta',
                'lead' => 'prosi o możliwość działania w Państwa sklepie w Państwa imieniu.',
                'application' => 'Aplikacja',
                'returns_to' => 'Odpowiedź wysłana do',
                'as_employee' => 'Zalogowano jako',
                'shop' => 'Sklep',
                'rights' => 'Co asystent będzie mógł robić',
                'limits' => 'Asystent nigdy nie otrzyma większych uprawnień niż Państwa profil pracownika i poprosi o potwierdzenie przed każdą zmianą. Mogą go Państwo odłączyć w dowolnym momencie w module Piskee.',
                'allow' => 'Zezwól',
                'deny' => 'Odmów',
                'expired_title' => 'Prośba wygasła',
                'expired_text' => 'Ta prośba o połączenie jest już nieważna. Prosimy ponownie rozpocząć łączenie w asystencie.',
                'help' => 'Mają Państwo pytanie dotyczące tego połączenia?',
            ],
        ];

        return $lang === 'en' || !isset($all[$lang]) ? $all['en'] : array_merge($all['en'], $all[$lang]);
    }

    /**
     * @return array<string, string>
     */
    private static function uiFr(): array
    {
        return [
            'title' => 'Piskee',
            'by' => 'L\'assistant IA de votre boutique PrestaShop · compatible Claude',
            'tagline' => 'Pilotez votre boutique PrestaShop en conversation avec Claude : catalogue, déclinaisons, images, stocks, service client et thème enfant.',
            'tab_connect' => 'Connexion',
            'tab_tools' => 'Ce que fait le module',
            'tab_theme' => 'Thème enfant',
            'tab_docs' => 'Documentation',
            'tab_pro' => 'Gratuit / Pro',
            'tab_about' => 'À propos',
            'status' => 'État de la connexion',
            'status_ok' => 'Tout est prêt',
            'status_todo' => 'Action requise',
            'check_https' => 'HTTPS actif',
            'check_rewrite' => 'URL simplifiées activées',
            'address' => 'Adresse à donner à Claude',
            'copy' => 'Copier',
            'copied' => 'Copié',
            'steps' => 'Connecter Claude en 5 étapes',
            'step1' => 'Ouvrez claude.ai (ou l\'application Claude) et allez dans Paramètres, puis Connecteurs.',
            'step2' => 'Cliquez sur Ajouter, puis Ajouter un connecteur personnalisé.',
            'step3' => 'Nommez-le « Piskee » et collez l\'adresse ci-dessus.',
            'step4' => 'Laissez les réglages détectés par Claude (connexion maintenant, enregistrement automatique), cliquez sur Ajouter puis sur Connecter.',
            'step5' => 'Votre back-office s\'ouvre : vérifiez les droits demandés et cliquez sur Autoriser. C\'est prêt.',
            'claude_code' => 'Avec Claude Code',
            'try' => 'Pour tester, demandez à Claude',
            'try1' => '« Donnez-moi les infos de ma boutique »',
            'try2' => '« Faites un audit SEO de mon catalogue et proposez les 10 corrections prioritaires »',
            'try3' => '« Quels produits sont bientôt en rupture ? »',
            'does' => 'Ce que fait le module',
            'does_not' => 'Ce que le module ne fait pas',
            'not1' => 'Il ne rembourse jamais un client et ne touche à aucun paiement.',
            'not2' => 'Il ne modifie jamais le thème en ligne ni un thème parent : seulement des thèmes enfants brouillons.',
            'not3' => 'Il ne met jamais un thème en ligne : c\'est vous qui cliquez, ici, dans votre back-office.',
            'not4' => 'Il ne supprime ni produit, ni client, ni commande.',
            'not5' => 'Il ne donne jamais à Claude plus de droits que l\'employé qui l\'a connecté.',
            'not6' => 'Il n\'envoie aucune donnée à Piskee : tout passe entre votre boutique et Claude.',
            'safety' => 'Les garde-fous',
            'safety1' => 'Chaque action qui modifie la boutique est signalée à Claude comme sensible : Claude vous demande confirmation avant de l\'exécuter.',
            'safety2' => 'Chaque modification de thème est sauvegardée et peut être restaurée.',
            'safety3' => 'Vous pouvez couper le serveur à tout moment et déconnecter chaque assistant, ici, dans l\'onglet Connexion.',
            'reads' => 'lecture',
            'writes' => 'modification',
            'theme_title' => 'Thèmes enfants et mise en ligne',
            'theme_intro' => 'Claude ne travaille que sur des thèmes enfants brouillons. Quand le résultat vous convient, c\'est vous qui le mettez en ligne ici. Pensez à vérifier le rendu sur un autre navigateur juste après.',
            'theme_name' => 'Thème',
            'theme_parent' => 'Parent',
            'theme_state' => 'État',
            'theme_live' => 'En ligne',
            'theme_draft' => 'Brouillon',
            'theme_parent_theme' => 'Thème parent',
            'theme_activate' => 'Mettre en ligne',
            'theme_confirm' => 'Mettre ce thème en ligne sur la boutique ? Les visiteurs verront immédiatement le nouveau design.',
            'theme_rollback' => 'Revenir au thème précédent',
            'theme_none' => 'Aucun thème enfant pour l\'instant. Demandez à Claude : « Créez un thème enfant brouillon de mon thème actuel ».',
            'theme_done' => 'Le thème est en ligne.',
            'rollback_done' => 'Le thème précédent est de nouveau en ligne.',
            'fix_done' => 'Configuration recommandée appliquée.',
            'help_title' => 'Un souci, une question ?',
            'help_text' => 'Connexion qui ne passe pas, outil qui manque, besoin d\'un accompagnement SEO ou d\'un développement sur mesure : l\'équipe Piskee vous répond.',
            'help_cta' => 'Nous contacter',
            'help_whatsapp' => 'WhatsApp',
            'audit_cta' => 'Audit SEO & GEO gratuit en 48 h',
            'docs_intro' => 'Toute la documentation, en français, anglais, espagnol, italien et polonais.',
            'pro_title' => 'Gratuit ou Pro',
            'pro_intro' => 'La version gratuite est complète pour démarrer. Le Pro lève la limite et ajoute les fonctions avancées.',
            'plan_free' => 'Gratuit',
            'plan_pro' => 'Pro',
            'plan_current' => 'Votre formule',
            'plan_usage' => 'modifications utilisées ce mois-ci',
            'plan_reads' => 'La lecture (recherches, audits, rapports) reste illimitée. Le compteur repart à zéro le 1er de chaque mois.',
            'plan_valid' => 'Licence Pro valide jusqu\'au',
            'plan_for' => 'pour',
            'compare_feature' => 'Fonction',
            'row_tools' => 'Tous les outils catalogue, déclinaisons, images, SAV et thème enfant',
            'row_writes' => 'Modifications par mois',
            'row_writes_free' => '100',
            'row_writes_pro' => 'Illimitées',
            'row_bulk' => 'Rédaction SEO et GEO par lots, audit planifié avec rapport mensuel',
            'row_redirects' => 'Redirections 301 (produits et catégories supprimés ou renommés)',
            'row_refunds' => 'Avoirs, retours produits et remboursements',
            'row_multistore' => 'Multiboutique et journal d\'audit détaillé par employé',
            'row_support' => 'Support prioritaire',
            'within_quota' => 'dans les 100',
            'per_month' => '/ mois',
            'pro_cta' => 'Passer au Pro',
            'pro_note' => 'Prix de lancement garanti à vie pour les 100 premiers clients. Sans engagement. Paiement sécurisé par Stripe, la clé de licence vous est envoyée par e-mail.',
            'onboarding_title' => 'Prise en main avec l\'agence',
            'onboarding_text' => 'Une session avec un expert Piskee : connexion de Claude, réglage des droits, premiers audits de votre catalogue et de votre thème, et les bonnes demandes à faire à Claude pour votre boutique.',
            'onboarding_cta' => 'Réserver la prise en main',
            'ob_kicker' => 'Installation accompagnée',
            'ob_title' => 'On installe Piskee ensemble',
            'ob_text' => 'Une visio d\'une heure avec un expert Piskee. Nous installons le module avec vous, puis nous vous montrons en direct comment en tirer le meilleur sur votre boutique.',
            'ob_b1' => 'Installation et connexion de Claude, ensemble',
            'ob_b2' => 'Démonstration sur votre propre catalogue',
            'ob_b3' => 'Les demandes qui font gagner le plus de temps',
            'ob_amount' => '150 €',
            'ob_tax' => 'HT',
            'ob_unit' => 'session unique · 1 h en visio',
            'ob_cta' => 'Réserver ma session',
            'license_title' => 'Clé de licence Pro',
            'license_placeholder' => 'Collez ici la clé reçue par e-mail (PKC1...)',
            'license_save' => 'Enregistrer',
            'license_ok' => 'Licence Pro activée. Merci de votre confiance !',
            'license_removed' => 'Licence retirée : la boutique repasse en formule gratuite.',
            'license_error_malformed' => 'Cette clé n\'est pas valide. Copiez-la en entier depuis l\'e-mail.',
            'license_error_signature' => 'Cette clé n\'a pas été émise par Piskee.',
            'license_error_domain' => 'Cette clé a été émise pour un autre domaine. Contactez-nous pour la transférer.',
            'license_error_expired' => 'Cette clé a expiré. Renouvelez votre abonnement pour continuer en Pro.',
            'license_error_plan' => 'Cette clé ne correspond pas à la formule Pro.',
            'license_error_sodium' => 'L\'extension PHP sodium manque sur ce serveur : la clé ne peut pas être vérifiée.',
            'about_title' => 'À propos de Piskee',
            'about_text' => 'Piskee est une agence web, SEO et GEO basée à Chantilly (Oise), spécialisée en e-commerce : PrestaShop, Shopify, WordPress, Magento et Webflow. Nous travaillons votre visibilité sur Google et dans les assistants IA comme ChatGPT, Claude et Perplexity, avec un objectif : le chiffre d\'affaires, pas seulement les positions.',
            'about_services' => 'Audit SEO technique et sémantique · Contenus SEO et GEO · Netlinking · Google Ads et Merchant Center · Tracking · Emailing · Création et refonte de sites',
            'about_open' => 'Ce module est open source (licence AFL-3.0). Il intègre son propre serveur MCP et son serveur d\'autorisation OAuth 2.1 : un seul module, aucune dépendance.',
            'about_repo' => 'Code source sur GitHub',
            'website' => 'Site de l\'agence',
            'version' => 'Version',
            'footer' => 'Piskee est un module indépendant, non affilié à Anthropic. Claude est une marque d\'Anthropic.',
            'lang_switch' => 'English',
            'lang_label' => 'Langue',
            'check_server' => 'Serveur MCP du module activé',
            'check_admin' => 'Back-office repéré pour l\'écran d\'autorisation',
            'check_test' => 'Adresses publiques joignables (test réel)',
            'run_test' => 'Tester la connexion',
            'test_ok' => 'Test réussi : les adresses MCP et OAuth répondent correctement.',
            'test_ko' => 'Test en échec',
            'server_toggle_on' => 'Couper le serveur',
            'server_toggle_off' => 'Activer le serveur',
            'server_off_done' => 'Serveur coupé : plus aucun assistant ne peut agir sur la boutique.',
            'server_on_done' => 'Serveur activé.',
            'connections' => 'Assistants connectés',
            'connections_none' => 'Aucun assistant connecté pour l\'instant.',
            'conn_app' => 'Application',
            'conn_employee' => 'Employé',
            'conn_last' => 'Dernière utilisation',
            'conn_revoke' => 'Déconnecter',
            'revoked' => 'Assistant déconnecté.',
            'welcome_title' => 'Bienvenue ! Voici ce que vous pouvez demander à Claude',
            'w1' => 'Réécrire les titres, descriptions et meta de vos fiches produits et catégories, dans toutes vos langues.',
            'w2' => 'Créer vos fiches produits et rédiger leurs descriptions, avec les déclinaisons (tailles, couleurs), les prix, les références et les stocks.',
            'w3' => 'Ajouter des images, écrire les textes alternatifs, redimensionner et mettre au carré vos visuels.',
            'w4' => 'Faire un audit SEO et GEO du catalogue et corriger les points prioritaires.',
            'w5' => 'Suivre vos ventes, repérer les ruptures et répondre aux messages clients.',
            'w6' => 'Créer des promotions et des bons de réduction.',
            'w7' => 'Nettoyer les balises Hn, poser un fil d\'Ariane et personnaliser votre thème, dans un thème enfant brouillon.',
            'w8' => 'Gérer la vente croisée : produits associés, suggestions « souvent achetés ensemble » tirées de vos commandes, et bloc d\'achats additionnels dans le panier.',
            'w9' => 'Avec Piskee Pro : redirections 301, créées automatiquement quand l\'URL d\'un produit, d\'une catégorie ou d\'une page change ou qu\'elle est supprimée, et suivi des pages 404.',
            'w10' => 'Générer le fichier llms.txt de votre boutique, pour être mieux compris et cité par les IA (ChatGPT, Claude, Perplexity).',
            'soon' => 'Bientôt',
            'reco_title' => 'Quelle version de Claude choisir ?',
            'reco1' => 'Toutes les formules Claude (gratuite, Pro, Max, Team, Enterprise) acceptent ce connecteur. La formule gratuite est limitée à un seul connecteur personnalisé.',
            'reco2' => 'Nous recommandons Claude Pro pour un usage quotidien, et Claude Max pour les gros chantiers (catalogue complet, refonte SEO, thème), qui consomment beaucoup d\'échanges.',
            'reco3' => 'Côté modèle : Sonnet pour les tâches courantes, rapide et précis ; Opus pour les audits, la rédaction en volume et les modifications de thème.',
            'reco4' => 'Utilisez claude.ai ou l\'application Claude (ordinateur ou mobile). Les développeurs peuvent aussi passer par Claude Code.',
            'legal_title' => 'Mentions et responsabilités',
            'legal1' => 'Piskee est un module indépendant édité par Piskee. Ce n\'est pas un produit officiel de PrestaShop SA ni d\'Anthropic, qui ne l\'ont ni développé, ni validé, ni approuvé, et qui n\'assument aucune responsabilité à son sujet.',
            'legal2' => 'PrestaShop est une marque de PrestaShop SA. Claude est une marque d\'Anthropic, PBC. Ces noms sont cités uniquement pour indiquer la compatibilité.',
            'legal3' => 'Le module est fourni « en l\'état », sans garantie, dans les limites prévues par la loi et par la licence AFL-3.0. Les actions décidées et validées par le marchand restent sous sa responsabilité : faites des sauvegardes régulières et vérifiez les modifications proposées avant de les accepter.',
            'legal4' => 'Les échanges avec Claude sont soumis aux conditions d\'utilisation d\'Anthropic. Le module ne transmet aucune donnée à Piskee.',
            'made_in' => 'Conçu à Chantilly, Oise',
            'story_title' => 'Né à Chantilly, pensé pour les marchands',
            'story_text' => 'Piskee a été imaginé et développé en France, à Chantilly, dans l\'Oise, par l\'équipe de Piskee. Nous accompagnons chaque jour des boutiques PrestaShop sur leur référencement et leur croissance, et nous voyions les mêmes tâches revenir sans cesse : fiches produits à réécrire, déclinaisons à créer, images à reprendre, balises Hn à nettoyer. Nous avons construit l\'outil que nous voulions pour nos clients : un assistant qui travaille directement dans votre boutique, avec des garde-fous solides, et une équipe humaine joignable quand vous en avez besoin.',
            'email' => 'E-mail',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function uiEn(): array
    {
        return [
            'title' => 'Piskee',
            'by' => 'The AI assistant for your PrestaShop store · works with Claude',
            'tagline' => 'Run your PrestaShop store by talking to Claude: catalogue, combinations, images, stock, customer service and child theme.',
            'tab_connect' => 'Connection',
            'tab_tools' => 'What it does',
            'tab_theme' => 'Child theme',
            'tab_docs' => 'Documentation',
            'tab_pro' => 'Free / Pro',
            'tab_about' => 'About',
            'status' => 'Connection status',
            'status_ok' => 'All set',
            'status_todo' => 'Action needed',
            'check_https' => 'HTTPS enabled',
            'check_rewrite' => 'Friendly URLs enabled',
            'address' => 'Address to give Claude',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'steps' => 'Connect Claude in 5 steps',
            'step1' => 'Open claude.ai (or the Claude app) and go to Settings, then Connectors.',
            'step2' => 'Click Add, then Add custom connector.',
            'step3' => 'Name it "Piskee" and paste the address above.',
            'step4' => 'Keep the settings Claude detects (sign in now, automatic registration), click Add, then Connect.',
            'step5' => 'Your back office opens: check the requested rights and click Authorise. Done.',
            'claude_code' => 'With Claude Code',
            'try' => 'To try it, ask Claude',
            'try1' => '"Give me my shop information"',
            'try2' => '"Audit my catalogue for SEO and suggest the 10 most important fixes"',
            'try3' => '"Which products are about to run out?"',
            'does' => 'What the module does',
            'does_not' => 'What the module does not do',
            'not1' => 'It never refunds a customer and never touches a payment.',
            'not2' => 'It never changes the live theme or a parent theme: only draft child themes.',
            'not3' => 'It never puts a theme live: you click, here, in your back office.',
            'not4' => 'It never deletes products, customers or orders.',
            'not5' => 'It never gives Claude more rights than the employee who connected it.',
            'not6' => 'It sends no data to Piskee: everything goes between your shop and Claude.',
            'safety' => 'Safeguards',
            'safety1' => 'Every action that changes the shop is flagged to Claude as sensitive: Claude asks for your confirmation before running it.',
            'safety2' => 'Every theme change is backed up and can be restored.',
            'safety3' => 'You can switch the server off at any time and disconnect each assistant, here, in the Connection tab.',
            'reads' => 'read',
            'writes' => 'write',
            'theme_title' => 'Child themes and going live',
            'theme_intro' => 'Claude only works on draft child themes. When you are happy with the result, you put it live here yourself. Check the rendering in another browser right after.',
            'theme_name' => 'Theme',
            'theme_parent' => 'Parent',
            'theme_state' => 'State',
            'theme_live' => 'Live',
            'theme_draft' => 'Draft',
            'theme_parent_theme' => 'Parent theme',
            'theme_activate' => 'Put live',
            'theme_confirm' => 'Put this theme live on the shop? Visitors will see the new design immediately.',
            'theme_rollback' => 'Back to the previous theme',
            'theme_none' => 'No child theme yet. Ask Claude: "Create a draft child theme of my current theme".',
            'theme_done' => 'The theme is live.',
            'rollback_done' => 'The previous theme is live again.',
            'fix_done' => 'Recommended settings applied.',
            'help_title' => 'A problem, a question?',
            'help_text' => 'Connection not working, a tool missing, SEO support or custom development: the Piskee team will get back to you.',
            'help_cta' => 'Contact us',
            'help_whatsapp' => 'WhatsApp',
            'audit_cta' => 'Free SEO & GEO audit in 48 h',
            'docs_intro' => 'The full documentation, in French, English, Spanish, Italian and Polish.',
            'pro_title' => 'Free or Pro',
            'pro_intro' => 'The free version is complete to get started. Pro lifts the limit and adds advanced features.',
            'plan_free' => 'Free',
            'plan_pro' => 'Pro',
            'plan_current' => 'Your plan',
            'plan_usage' => 'changes used this month',
            'plan_reads' => 'Reading (searches, audits, reports) stays unlimited. The counter resets on the 1st of each month.',
            'plan_valid' => 'Pro licence valid until',
            'plan_for' => 'for',
            'compare_feature' => 'Feature',
            'row_tools' => 'Every catalogue, combination, image, customer service and child theme tool',
            'row_writes' => 'Changes per month',
            'row_writes_free' => '100',
            'row_writes_pro' => 'Unlimited',
            'row_bulk' => 'Batch SEO and GEO copywriting, scheduled audit with a monthly report',
            'row_redirects' => '301 redirects (deleted or renamed products and categories)',
            'row_refunds' => 'Credit slips, product returns and refunds',
            'row_multistore' => 'Multistore and detailed audit log per employee',
            'row_support' => 'Priority support',
            'within_quota' => 'within the 100',
            'per_month' => '/ month',
            'pro_cta' => 'Go Pro',
            'pro_note' => 'Launch price locked in for life for the first 100 customers. No commitment. Secure payment by Stripe, your licence key is emailed to you.',
            'onboarding_title' => 'Onboarding with the agency',
            'onboarding_text' => 'A session with a Piskee expert: connecting Claude, setting rights, first audits of your catalogue and theme, and the right requests to make to Claude for your shop.',
            'onboarding_cta' => 'Book the onboarding',
            'ob_kicker' => 'Guided setup',
            'ob_title' => 'We install Piskee with you',
            'ob_text' => 'A one-hour video call with a Piskee expert. We install the module with you, then show you live how to get the most out of it on your shop.',
            'ob_b1' => 'Installation and Claude connection, together',
            'ob_b2' => 'A demo on your own catalogue',
            'ob_b3' => 'The requests that save the most time',
            'ob_amount' => '€150',
            'ob_tax' => 'excl. VAT',
            'ob_unit' => 'one-off session · 1 h video call',
            'ob_cta' => 'Book my session',
            'license_title' => 'Pro licence key',
            'license_placeholder' => 'Paste the key you received by email (PKC1...)',
            'license_save' => 'Save',
            'license_ok' => 'Pro licence activated. Thank you!',
            'license_removed' => 'Licence removed: the shop is back on the free plan.',
            'license_error_malformed' => 'This key is not valid. Copy it whole from the email.',
            'license_error_signature' => 'This key was not issued by Piskee.',
            'license_error_domain' => 'This key was issued for another domain. Contact us to transfer it.',
            'license_error_expired' => 'This key has expired. Renew your subscription to stay on Pro.',
            'license_error_plan' => 'This key is not for the Pro plan.',
            'license_error_sodium' => 'The PHP sodium extension is missing on this server: the key cannot be checked.',
            'about_title' => 'About Piskee',
            'about_text' => 'Piskee is a web, SEO and GEO agency based in Chantilly, France, specialised in e-commerce: PrestaShop, Shopify, WordPress, Magento and Webflow. We grow your visibility on Google and in AI assistants such as ChatGPT, Claude and Perplexity, with one goal: revenue, not just rankings.',
            'about_services' => 'Technical and semantic SEO audits · SEO and GEO content · Link building · Google Ads and Merchant Center · Tracking · Emailing · Website creation and redesign',
            'about_open' => 'This module is open source (AFL-3.0 licence). It ships its own MCP server and OAuth 2.1 authorisation server: one module, no dependency.',
            'about_repo' => 'Source code on GitHub',
            'website' => 'Agency website',
            'version' => 'Version',
            'footer' => 'Piskee is an independent module, not affiliated with Anthropic. Claude is a trademark of Anthropic.',
            'lang_switch' => 'Français',
            'lang_label' => 'Language',
            'check_server' => 'Module MCP server switched on',
            'check_admin' => 'Back office located for the authorisation screen',
            'check_test' => 'Public addresses reachable (live test)',
            'run_test' => 'Test the connection',
            'test_ok' => 'Test passed: the MCP and OAuth addresses answer correctly.',
            'test_ko' => 'Test failed',
            'server_toggle_on' => 'Switch the server off',
            'server_toggle_off' => 'Switch the server on',
            'server_off_done' => 'Server switched off: no assistant can act on the shop any more.',
            'server_on_done' => 'Server switched on.',
            'connections' => 'Connected assistants',
            'connections_none' => 'No assistant connected yet.',
            'conn_app' => 'Application',
            'conn_employee' => 'Employee',
            'conn_last' => 'Last used',
            'conn_revoke' => 'Disconnect',
            'revoked' => 'Assistant disconnected.',
            'welcome_title' => 'Welcome! Here is what you can ask Claude',
            'w1' => 'Rewrite titles, descriptions and meta tags of your products and categories, in all your languages.',
            'w2' => 'Create your product pages and write their descriptions, with combinations (sizes, colours), prices, references and stock.',
            'w3' => 'Add images, write alt texts, resize and square your pictures.',
            'w4' => 'Audit the catalogue for SEO and GEO and fix what matters most.',
            'w5' => 'Follow your sales, spot stock-outs and reply to customer messages.',
            'w6' => 'Create promotions and vouchers.',
            'w7' => 'Clean up headings, add a breadcrumb and customise your theme, in a draft child theme.',
            'w8' => 'Manage cross-selling: related products, "frequently bought together" suggestions from your orders, and an additional purchases block in the cart.',
            'w9' => 'With Piskee Pro: 301 redirects, created automatically when the URL of a product, category or page changes or is deleted, and 404 follow-up.',
            'w10' => 'Generate your shop\'s llms.txt file, so AI assistants (ChatGPT, Claude, Perplexity) understand and cite it better.',
            'soon' => 'Soon',
            'reco_title' => 'Which Claude should I use?',
            'reco1' => 'Every Claude plan (Free, Pro, Max, Team, Enterprise) accepts this connector. The Free plan is limited to one custom connector.',
            'reco2' => 'We recommend Claude Pro for daily use, and Claude Max for big projects (whole catalogue, SEO overhaul, theme), which use many exchanges.',
            'reco3' => 'Model: Sonnet for everyday tasks, fast and precise; Opus for audits, copywriting in volume and theme changes.',
            'reco4' => 'Use claude.ai or the Claude app (desktop or mobile). Developers can also use Claude Code.',
            'legal_title' => 'Legal notice and responsibilities',
            'legal1' => 'Piskee is an independent module published by Piskee. It is not an official product of PrestaShop SA or Anthropic, which did not develop, review or endorse it, and which bear no responsibility for it.',
            'legal2' => 'PrestaShop is a trademark of PrestaShop SA. Claude is a trademark of Anthropic, PBC. These names are used only to state compatibility.',
            'legal3' => 'The module is provided "as is", without warranty, within the limits of the law and of the AFL-3.0 licence. Actions decided and approved by the merchant remain the merchant\'s responsibility: keep regular backups and review proposed changes before accepting them.',
            'legal4' => 'Conversations with Claude are subject to Anthropic\'s terms of use. The module sends no data to Piskee.',
            'made_in' => 'Made in Chantilly, France',
            'story_title' => 'Born in Chantilly, built for merchants',
            'story_text' => 'Piskee was imagined and built in France, in Chantilly (Oise), by the Piskee team. Every day we help PrestaShop stores with their search visibility and growth, and we kept seeing the same tasks come back: product pages to rewrite, combinations to create, images to rework, headings to clean. So we built the tool we wanted for our clients: an assistant that works right inside your shop, with solid safeguards, and a human team you can reach when you need it.',
            'email' => 'Email',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function uiEs(): array
    {
        return [
            'title' => 'Piskee',
            'by' => 'El asistente de IA de su tienda PrestaShop · compatible con Claude',
            'tagline' => 'Gestione su tienda PrestaShop conversando con Claude: catálogo, combinaciones, imágenes, stock, atención al cliente y tema hijo.',
            'tab_connect' => 'Conexión',
            'tab_tools' => 'Qué hace el módulo',
            'tab_theme' => 'Tema hijo',
            'tab_docs' => 'Documentación',
            'tab_pro' => 'Gratis / Pro',
            'tab_about' => 'Acerca de',
            'status' => 'Estado de la conexión',
            'status_ok' => 'Todo listo',
            'status_todo' => 'Acción necesaria',
            'check_https' => 'HTTPS activo',
            'check_rewrite' => 'URL amigables activadas',
            'address' => 'Dirección que debe indicar a Claude',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
            'steps' => 'Conecte Claude en 5 pasos',
            'step1' => 'Abra claude.ai (o la aplicación de Claude) y vaya a Configuración y, después, a Conectores.',
            'step2' => 'Haga clic en Añadir y, después, en Añadir conector personalizado.',
            'step3' => 'Llámelo «Piskee» y pegue la dirección indicada arriba.',
            'step4' => 'Mantenga los ajustes que detecta Claude (iniciar sesión ahora, registro automático), haga clic en Añadir y, después, en Conectar.',
            'step5' => 'Se abre su back office: revise los permisos solicitados y haga clic en Autorizar. Ya está.',
            'claude_code' => 'Con Claude Code',
            'try' => 'Para probarlo, pida a Claude',
            'try1' => '«Deme la información de mi tienda»',
            'try2' => '«Haga una auditoría SEO de mi catálogo y proponga las 10 correcciones prioritarias»',
            'try3' => '«¿Qué productos están a punto de agotarse?»',
            'does' => 'Qué hace el módulo',
            'does_not' => 'Qué no hace el módulo',
            'not1' => 'Nunca reembolsa a un cliente ni toca ningún pago.',
            'not2' => 'Nunca modifica el tema publicado ni un tema padre: solo temas hijo en borrador.',
            'not3' => 'Nunca publica un tema: es usted quien hace clic, aquí, en su back office.',
            'not4' => 'No elimina productos, clientes ni pedidos.',
            'not5' => 'Nunca da a Claude más permisos que los del empleado que lo conectó.',
            'not6' => 'No envía ningún dato a Piskee: todo circula entre su tienda y Claude.',
            'safety' => 'Las salvaguardas',
            'safety1' => 'Cada acción que modifica la tienda se marca ante Claude como sensible: Claude le pide confirmación antes de ejecutarla.',
            'safety2' => 'Cada modificación del tema se guarda en una copia de seguridad y puede restaurarse.',
            'safety3' => 'Puede desactivar el servidor en cualquier momento y desconectar cada asistente, aquí, en la pestaña Conexión.',
            'reads' => 'lectura',
            'writes' => 'modificación',
            'theme_title' => 'Temas hijo y publicación',
            'theme_intro' => 'Claude solo trabaja en temas hijo en borrador. Cuando el resultado le convenza, es usted quien lo publica aquí. Compruebe cómo se ve en otro navegador justo después.',
            'theme_name' => 'Tema',
            'theme_parent' => 'Padre',
            'theme_state' => 'Estado',
            'theme_live' => 'Publicado',
            'theme_draft' => 'Borrador',
            'theme_parent_theme' => 'Tema padre',
            'theme_activate' => 'Publicar',
            'theme_confirm' => '¿Publicar este tema en la tienda? Los visitantes verán el nuevo diseño de inmediato.',
            'theme_rollback' => 'Volver al tema anterior',
            'theme_none' => 'Todavía no hay ningún tema hijo. Pida a Claude: «Cree un tema hijo en borrador de mi tema actual».',
            'theme_done' => 'El tema está publicado.',
            'rollback_done' => 'El tema anterior vuelve a estar publicado.',
            'fix_done' => 'Configuración recomendada aplicada.',
            'help_title' => '¿Un problema o una pregunta?',
            'help_text' => 'Una conexión que no funciona, una herramienta que falta, acompañamiento SEO o un desarrollo a medida: el equipo de Piskee le responde.',
            'help_cta' => 'Contáctenos',
            'help_whatsapp' => 'WhatsApp',
            'audit_cta' => 'Auditoría SEO y GEO gratuita en 48 h',
            'docs_intro' => 'Toda la documentación, en francés, inglés, español, italiano y polaco.',
            'pro_title' => 'Gratis o Pro',
            'pro_intro' => 'La versión gratuita es completa para empezar. Pro elimina el límite y añade las funciones avanzadas.',
            'plan_free' => 'Gratis',
            'plan_pro' => 'Pro',
            'plan_current' => 'Su plan',
            'plan_usage' => 'modificaciones utilizadas este mes',
            'plan_reads' => 'La lectura (búsquedas, auditorías, informes) sigue siendo ilimitada. El contador se reinicia el día 1 de cada mes.',
            'plan_valid' => 'Licencia Pro válida hasta el',
            'plan_for' => 'para',
            'compare_feature' => 'Función',
            'row_tools' => 'Todas las herramientas de catálogo, combinaciones, imágenes, atención al cliente y tema hijo',
            'row_writes' => 'Modificaciones al mes',
            'row_writes_free' => '100',
            'row_writes_pro' => 'Ilimitadas',
            'row_bulk' => 'Redacción SEO y GEO por lotes, auditoría programada con informe mensual',
            'row_redirects' => 'Redirecciones 301 (productos y categorías eliminados o renombrados)',
            'row_refunds' => 'Facturas por abono, devoluciones de productos y reembolsos',
            'row_multistore' => 'Multitienda y registro de auditoría detallado por empleado',
            'row_support' => 'Soporte prioritario',
            'within_quota' => 'dentro de las 100',
            'per_month' => '/ mes',
            'pro_cta' => 'Pasar a Pro',
            'pro_note' => 'Precio de lanzamiento garantizado de por vida para los 100 primeros clientes. Sin permanencia. Pago seguro con Stripe; recibirá la clave de licencia por correo electrónico.',
            'onboarding_title' => 'Puesta en marcha con la agencia',
            'onboarding_text' => 'Una sesión con un experto de Piskee: conexión de Claude, ajuste de permisos, primeras auditorías de su catálogo y su tema, y las peticiones adecuadas que hacer a Claude para su tienda.',
            'onboarding_cta' => 'Reservar la puesta en marcha',
            'ob_kicker' => 'Instalación acompañada',
            'ob_title' => 'Instalamos Piskee con usted',
            'ob_text' => 'Una videollamada de una hora con un experto de Piskee. Instalamos el módulo con usted y le mostramos en directo cómo sacarle el máximo partido en su tienda.',
            'ob_b1' => 'Instalación y conexión de Claude, juntos',
            'ob_b2' => 'Demostración con su propio catálogo',
            'ob_b3' => 'Las peticiones que más tiempo le ahorran',
            'ob_amount' => '150 €',
            'ob_tax' => '+ IVA',
            'ob_unit' => 'sesión única · 1 h por videollamada',
            'ob_cta' => 'Reservar mi sesión',
            'license_title' => 'Clave de licencia Pro',
            'license_placeholder' => 'Pegue aquí la clave recibida por correo electrónico (PKC1...)',
            'license_save' => 'Guardar',
            'license_ok' => 'Licencia Pro activada. ¡Gracias por su confianza!',
            'license_removed' => 'Licencia retirada: la tienda vuelve al plan gratuito.',
            'license_error_malformed' => 'Esta clave no es válida. Cópiela completa desde el correo electrónico.',
            'license_error_signature' => 'Esta clave no ha sido emitida por Piskee.',
            'license_error_domain' => 'Esta clave se emitió para otro dominio. Contáctenos para transferirla.',
            'license_error_expired' => 'Esta clave ha caducado. Renueve su suscripción para seguir en Pro.',
            'license_error_plan' => 'Esta clave no corresponde al plan Pro.',
            'license_error_sodium' => 'Falta la extensión PHP sodium en este servidor: no se puede verificar la clave.',
            'about_title' => 'Acerca de Piskee',
            'about_text' => 'Piskee es una agencia web, SEO y GEO con sede en Chantilly (Francia), especializada en e-commerce: PrestaShop, Shopify, WordPress, Magento y Webflow. Trabajamos su visibilidad en Google y en asistentes de IA como ChatGPT, Claude y Perplexity, con un objetivo: la facturación, no solo las posiciones.',
            'about_services' => 'Auditoría SEO técnica y semántica · Contenidos SEO y GEO · Linkbuilding · Google Ads y Merchant Center · Tracking · Emailing · Creación y rediseño de sitios web',
            'about_open' => 'Este módulo es de código abierto (licencia AFL-3.0). Integra su propio servidor MCP y su servidor de autorización OAuth 2.1: un solo módulo, sin dependencias.',
            'about_repo' => 'Código fuente en GitHub',
            'website' => 'Web de la agencia',
            'version' => 'Versión',
            'footer' => 'Piskee es un módulo independiente, no afiliado a Anthropic. Claude es una marca de Anthropic.',
            'lang_switch' => 'English',
            'lang_label' => 'Idioma',
            'check_server' => 'Servidor MCP del módulo activado',
            'check_admin' => 'Back office localizado para la pantalla de autorización',
            'check_test' => 'Direcciones públicas accesibles (prueba real)',
            'run_test' => 'Probar la conexión',
            'test_ok' => 'Prueba superada: las direcciones MCP y OAuth responden correctamente.',
            'test_ko' => 'Prueba fallida',
            'server_toggle_on' => 'Desactivar el servidor',
            'server_toggle_off' => 'Activar el servidor',
            'server_off_done' => 'Servidor desactivado: ningún asistente puede actuar ya en la tienda.',
            'server_on_done' => 'Servidor activado.',
            'connections' => 'Asistentes conectados',
            'connections_none' => 'Todavía no hay ningún asistente conectado.',
            'conn_app' => 'Aplicación',
            'conn_employee' => 'Empleado',
            'conn_last' => 'Último uso',
            'conn_revoke' => 'Desconectar',
            'revoked' => 'Asistente desconectado.',
            'welcome_title' => '¡Bienvenido! Esto es lo que puede pedir a Claude',
            'w1' => 'Reescribir los títulos, descripciones y metaetiquetas de sus fichas de producto y categorías, en todos sus idiomas.',
            'w2' => 'Crear sus fichas de producto y redactar sus descripciones, con las combinaciones (tallas, colores), los precios, las referencias y el stock.',
            'w3' => 'Añadir imágenes, redactar los textos alternativos, redimensionar y cuadrar sus imágenes.',
            'w4' => 'Hacer una auditoría SEO y GEO del catálogo y corregir los puntos prioritarios.',
            'w5' => 'Seguir sus ventas, detectar las roturas de stock y responder a los mensajes de los clientes.',
            'w6' => 'Crear promociones y cupones de descuento.',
            'w7' => 'Limpiar las etiquetas Hn, añadir migas de pan y personalizar su tema, en un tema hijo en borrador.',
            'w8' => 'Gestionar la venta cruzada: productos relacionados, sugerencias «comprados juntos con frecuencia» basadas en sus pedidos y un bloque de compras adicionales en el carrito.',
            'w9' => 'Con Piskee Pro: redirecciones 301, creadas automáticamente cuando la URL de un producto, una categoría o una página cambia o se elimina, y seguimiento de las páginas 404.',
            'w10' => 'Generar el archivo llms.txt de su tienda, para que las IA (ChatGPT, Claude, Perplexity) la entiendan y la citen mejor.',
            'soon' => 'Próximamente',
            'reco_title' => '¿Qué versión de Claude elegir?',
            'reco1' => 'Todos los planes de Claude (Free, Pro, Max, Team, Enterprise) aceptan este conector. El plan gratuito está limitado a un solo conector personalizado.',
            'reco2' => 'Recomendamos Claude Pro para el uso diario y Claude Max para los grandes proyectos (catálogo completo, revisión SEO, tema), que consumen muchos intercambios.',
            'reco3' => 'En cuanto al modelo: Sonnet para las tareas habituales, rápido y preciso; Opus para las auditorías, la redacción en volumen y las modificaciones del tema.',
            'reco4' => 'Utilice claude.ai o la aplicación de Claude (ordenador o móvil). Los desarrolladores también pueden usar Claude Code.',
            'legal_title' => 'Aviso legal y responsabilidades',
            'legal1' => 'Piskee es un módulo independiente editado por Piskee. No es un producto oficial de PrestaShop SA ni de Anthropic, que no lo han desarrollado, revisado ni aprobado, y que no asumen ninguna responsabilidad sobre él.',
            'legal2' => 'PrestaShop es una marca de PrestaShop SA. Claude es una marca de Anthropic, PBC. Estos nombres se citan únicamente para indicar la compatibilidad.',
            'legal3' => 'El módulo se proporciona «tal cual», sin garantía, dentro de los límites previstos por la ley y por la licencia AFL-3.0. Las acciones decididas y validadas por el comerciante son responsabilidad suya: haga copias de seguridad periódicas y revise los cambios propuestos antes de aceptarlos.',
            'legal4' => 'Las conversaciones con Claude están sujetas a las condiciones de uso de Anthropic. El módulo no transmite ningún dato a Piskee.',
            'made_in' => 'Diseñado en Chantilly (Francia)',
            'story_title' => 'Nacido en Chantilly, pensado para los comerciantes',
            'story_text' => 'Piskee fue ideado y desarrollado en Francia, en Chantilly (Oise), por el equipo de Piskee. Cada día acompañamos a tiendas PrestaShop en su posicionamiento y su crecimiento, y veíamos repetirse una y otra vez las mismas tareas: fichas de producto que reescribir, combinaciones que crear, imágenes que retocar, etiquetas Hn que limpiar. Así que construimos la herramienta que queríamos para nuestros clientes: un asistente que trabaja directamente en su tienda, con salvaguardas sólidas, y un equipo humano disponible cuando lo necesite.',
            'email' => 'Correo electrónico',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function uiIt(): array
    {
        return [
            'title' => 'Piskee',
            'by' => 'L\'assistente IA del Suo negozio PrestaShop · compatibile con Claude',
            'tagline' => 'Gestisca il Suo negozio PrestaShop conversando con Claude: catalogo, combinazioni, immagini, giacenze, assistenza clienti e tema figlio.',
            'tab_connect' => 'Connessione',
            'tab_tools' => 'Cosa fa il modulo',
            'tab_theme' => 'Tema figlio',
            'tab_docs' => 'Documentazione',
            'tab_pro' => 'Gratis / Pro',
            'tab_about' => 'Chi siamo',
            'status' => 'Stato della connessione',
            'status_ok' => 'Tutto pronto',
            'status_todo' => 'Azione richiesta',
            'check_https' => 'HTTPS attivo',
            'check_rewrite' => 'URL semplificati attivati',
            'address' => 'Indirizzo da fornire a Claude',
            'copy' => 'Copia',
            'copied' => 'Copiato',
            'steps' => 'Collegare Claude in 5 passaggi',
            'step1' => 'Apra claude.ai (o l\'app Claude) e vada in Impostazioni, poi Connettori.',
            'step2' => 'Clicchi su Aggiungi, poi su Aggiungi connettore personalizzato.',
            'step3' => 'Lo chiami «Piskee» e incolli l\'indirizzo qui sopra.',
            'step4' => 'Mantenga le impostazioni rilevate da Claude (accesso immediato, registrazione automatica), clicchi su Aggiungi e poi su Connetti.',
            'step5' => 'Si apre il Suo back office: controlli i permessi richiesti e clicchi su Autorizza. Fatto.',
            'claude_code' => 'Con Claude Code',
            'try' => 'Per provarlo, chieda a Claude',
            'try1' => '«Mi dia le informazioni del mio negozio»',
            'try2' => '«Faccia un audit SEO del mio catalogo e proponga le 10 correzioni prioritarie»',
            'try3' => '«Quali prodotti stanno per esaurirsi?»',
            'does' => 'Cosa fa il modulo',
            'does_not' => 'Cosa non fa il modulo',
            'not1' => 'Non rimborsa mai un cliente e non tocca alcun pagamento.',
            'not2' => 'Non modifica mai il tema online né un tema padre: solo temi figlio in bozza.',
            'not3' => 'Non mette mai online un tema: è Lei a cliccare, qui, nel Suo back office.',
            'not4' => 'Non elimina prodotti, clienti né ordini.',
            'not5' => 'Non dà mai a Claude più permessi del dipendente che lo ha collegato.',
            'not6' => 'Non invia alcun dato a Piskee: tutto passa tra il Suo negozio e Claude.',
            'safety' => 'Le protezioni',
            'safety1' => 'Ogni azione che modifica il negozio viene segnalata a Claude come sensibile: Claude Le chiede conferma prima di eseguirla.',
            'safety2' => 'Ogni modifica al tema viene salvata e può essere ripristinata.',
            'safety3' => 'Può disattivare il server in qualsiasi momento e scollegare ogni assistente, qui, nella scheda Connessione.',
            'reads' => 'lettura',
            'writes' => 'modifica',
            'theme_title' => 'Temi figlio e messa online',
            'theme_intro' => 'Claude lavora solo su temi figlio in bozza. Quando il risultato La convince, è Lei a metterlo online da qui. Subito dopo, controlli la resa su un altro browser.',
            'theme_name' => 'Tema',
            'theme_parent' => 'Padre',
            'theme_state' => 'Stato',
            'theme_live' => 'Online',
            'theme_draft' => 'Bozza',
            'theme_parent_theme' => 'Tema padre',
            'theme_activate' => 'Metti online',
            'theme_confirm' => 'Mettere online questo tema sul negozio? I visitatori vedranno subito il nuovo design.',
            'theme_rollback' => 'Torna al tema precedente',
            'theme_none' => 'Ancora nessun tema figlio. Chieda a Claude: «Crei un tema figlio in bozza del mio tema attuale».',
            'theme_done' => 'Il tema è online.',
            'rollback_done' => 'Il tema precedente è di nuovo online.',
            'fix_done' => 'Configurazione consigliata applicata.',
            'help_title' => 'Un problema, una domanda?',
            'help_text' => 'Connessione che non funziona, uno strumento che manca, supporto SEO o uno sviluppo su misura: il team Piskee Le risponde.',
            'help_cta' => 'Ci contatti',
            'help_whatsapp' => 'WhatsApp',
            'audit_cta' => 'Audit SEO e GEO gratuito in 48 h',
            'docs_intro' => 'Tutta la documentazione, in francese, inglese, spagnolo, italiano e polacco.',
            'pro_title' => 'Gratis o Pro',
            'pro_intro' => 'La versione gratuita è completa per iniziare. Pro elimina il limite e aggiunge le funzioni avanzate.',
            'plan_free' => 'Gratis',
            'plan_pro' => 'Pro',
            'plan_current' => 'Il Suo piano',
            'plan_usage' => 'modifiche utilizzate questo mese',
            'plan_reads' => 'La lettura (ricerche, audit, report) resta illimitata. Il contatore si azzera il 1° di ogni mese.',
            'plan_valid' => 'Licenza Pro valida fino al',
            'plan_for' => 'per',
            'compare_feature' => 'Funzione',
            'row_tools' => 'Tutti gli strumenti per catalogo, combinazioni, immagini, assistenza clienti e tema figlio',
            'row_writes' => 'Modifiche al mese',
            'row_writes_free' => '100',
            'row_writes_pro' => 'Illimitate',
            'row_bulk' => 'Redazione SEO e GEO in blocco, audit programmato con report mensile',
            'row_redirects' => 'Redirect 301 (prodotti e categorie eliminati o rinominati)',
            'row_refunds' => 'Note di credito, resi e rimborsi',
            'row_multistore' => 'Multinegozio e registro di audit dettagliato per dipendente',
            'row_support' => 'Supporto prioritario',
            'within_quota' => 'entro le 100',
            'per_month' => '/ mese',
            'pro_cta' => 'Passa a Pro',
            'pro_note' => 'Prezzo di lancio garantito a vita per i primi 100 clienti. Senza vincoli. Pagamento sicuro con Stripe, la chiave di licenza Le viene inviata via e-mail.',
            'onboarding_title' => 'Avvio con l\'agenzia',
            'onboarding_text' => 'Una sessione con un esperto Piskee: collegamento di Claude, impostazione dei permessi, primi audit del catalogo e del tema, e le richieste giuste da fare a Claude per il Suo negozio.',
            'onboarding_cta' => 'Prenota l\'avvio',
            'ob_kicker' => 'Installazione assistita',
            'ob_title' => 'Installiamo Piskee insieme a Lei',
            'ob_text' => 'Una videochiamata di un\'ora con un esperto Piskee. Installiamo il modulo insieme a Lei e Le mostriamo dal vivo come sfruttarlo al meglio nel Suo negozio.',
            'ob_b1' => 'Installazione e collegamento di Claude, insieme',
            'ob_b2' => 'Dimostrazione sul Suo catalogo',
            'ob_b3' => 'Le richieste che fanno risparmiare più tempo',
            'ob_amount' => '150 €',
            'ob_tax' => '+ IVA',
            'ob_unit' => 'sessione unica · 1 h in videochiamata',
            'ob_cta' => 'Prenoti la Sua sessione',
            'license_title' => 'Chiave di licenza Pro',
            'license_placeholder' => 'Incolli qui la chiave ricevuta via e-mail (PKC1...)',
            'license_save' => 'Salva',
            'license_ok' => 'Licenza Pro attivata. Grazie per la fiducia!',
            'license_removed' => 'Licenza rimossa: il negozio torna al piano gratuito.',
            'license_error_malformed' => 'Questa chiave non è valida. La copi per intero dall\'e-mail.',
            'license_error_signature' => 'Questa chiave non è stata emessa da Piskee.',
            'license_error_domain' => 'Questa chiave è stata emessa per un altro dominio. Ci contatti per trasferirla.',
            'license_error_expired' => 'Questa chiave è scaduta. Rinnovi l\'abbonamento per restare in Pro.',
            'license_error_plan' => 'Questa chiave non corrisponde al piano Pro.',
            'license_error_sodium' => 'Su questo server manca l\'estensione PHP sodium: la chiave non può essere verificata.',
            'about_title' => 'Chi è Piskee',
            'about_text' => 'Piskee è un\'agenzia web, SEO e GEO con sede a Chantilly (Francia), specializzata in e-commerce: PrestaShop, Shopify, WordPress, Magento e Webflow. Lavoriamo sulla Sua visibilità su Google e negli assistenti IA come ChatGPT, Claude e Perplexity, con un obiettivo: il fatturato, non solo i posizionamenti.',
            'about_services' => 'Audit SEO tecnico e semantico · Contenuti SEO e GEO · Link building · Google Ads e Merchant Center · Tracking · Email marketing · Creazione e restyling di siti',
            'about_open' => 'Questo modulo è open source (licenza AFL-3.0). Integra il proprio server MCP e il proprio server di autorizzazione OAuth 2.1: un solo modulo, nessuna dipendenza.',
            'about_repo' => 'Codice sorgente su GitHub',
            'website' => 'Sito dell\'agenzia',
            'version' => 'Versione',
            'footer' => 'Piskee è un modulo indipendente, non affiliato ad Anthropic. Claude è un marchio di Anthropic.',
            'lang_switch' => 'English',
            'lang_label' => 'Lingua',
            'check_server' => 'Server MCP del modulo attivato',
            'check_admin' => 'Back office individuato per la schermata di autorizzazione',
            'check_test' => 'Indirizzi pubblici raggiungibili (test reale)',
            'run_test' => 'Testa la connessione',
            'test_ok' => 'Test riuscito: gli indirizzi MCP e OAuth rispondono correttamente.',
            'test_ko' => 'Test non riuscito',
            'server_toggle_on' => 'Disattiva il server',
            'server_toggle_off' => 'Attiva il server',
            'server_off_done' => 'Server disattivato: nessun assistente può più agire sul negozio.',
            'server_on_done' => 'Server attivato.',
            'connections' => 'Assistenti collegati',
            'connections_none' => 'Ancora nessun assistente collegato.',
            'conn_app' => 'Applicazione',
            'conn_employee' => 'Dipendente',
            'conn_last' => 'Ultimo utilizzo',
            'conn_revoke' => 'Scollega',
            'revoked' => 'Assistente scollegato.',
            'welcome_title' => 'Benvenuto! Ecco cosa può chiedere a Claude',
            'w1' => 'Riscrivere titoli, descrizioni e meta tag delle schede prodotto e delle categorie, in tutte le Sue lingue.',
            'w2' => 'Creare le Sue schede prodotto e scriverne le descrizioni, con le combinazioni (taglie, colori), i prezzi, i riferimenti e le giacenze.',
            'w3' => 'Aggiungere immagini, scrivere i testi alternativi, ridimensionare e rendere quadrate le Sue foto.',
            'w4' => 'Fare un audit SEO e GEO del catalogo e correggere i punti prioritari.',
            'w5' => 'Seguire le vendite, individuare i prodotti esauriti e rispondere ai messaggi dei clienti.',
            'w6' => 'Creare promozioni e buoni sconto.',
            'w7' => 'Sistemare i tag Hn, aggiungere un breadcrumb e personalizzare il tema, in un tema figlio in bozza.',
            'w8' => 'Gestire la vendita incrociata: prodotti correlati, suggerimenti «spesso acquistati insieme» ricavati dai Suoi ordini e un blocco di acquisti aggiuntivi nel carrello.',
            'w9' => 'Con Piskee Pro: redirect 301, creati automaticamente quando l\'URL di un prodotto, di una categoria o di una pagina cambia o viene eliminato, e monitoraggio delle pagine 404.',
            'w10' => 'Generare il file llms.txt del Suo negozio, perché le IA (ChatGPT, Claude, Perplexity) lo comprendano e lo citino meglio.',
            'soon' => 'Presto',
            'reco_title' => 'Quale versione di Claude scegliere?',
            'reco1' => 'Tutti i piani Claude (Free, Pro, Max, Team, Enterprise) accettano questo connettore. Il piano gratuito è limitato a un solo connettore personalizzato.',
            'reco2' => 'Consigliamo Claude Pro per l\'uso quotidiano e Claude Max per i grandi progetti (catalogo completo, revisione SEO, tema), che richiedono molti scambi.',
            'reco3' => 'Quanto al modello: Sonnet per le attività di tutti i giorni, veloce e preciso; Opus per gli audit, la redazione in volume e le modifiche al tema.',
            'reco4' => 'Utilizzi claude.ai o l\'app Claude (desktop o mobile). Gli sviluppatori possono usare anche Claude Code.',
            'legal_title' => 'Note legali e responsabilità',
            'legal1' => 'Piskee è un modulo indipendente pubblicato da Piskee. Non è un prodotto ufficiale di PrestaShop SA né di Anthropic, che non lo hanno sviluppato, verificato né approvato, e che non se ne assumono alcuna responsabilità.',
            'legal2' => 'PrestaShop è un marchio di PrestaShop SA. Claude è un marchio di Anthropic, PBC. Questi nomi sono citati solo per indicare la compatibilità.',
            'legal3' => 'Il modulo è fornito «così com\'è», senza garanzia, nei limiti previsti dalla legge e dalla licenza AFL-3.0. Le azioni decise e approvate dall\'esercente restano sotto la sua responsabilità: esegua backup regolari e controlli le modifiche proposte prima di accettarle.',
            'legal4' => 'Le conversazioni con Claude sono soggette alle condizioni d\'uso di Anthropic. Il modulo non trasmette alcun dato a Piskee.',
            'made_in' => 'Progettato a Chantilly (Francia)',
            'story_title' => 'Nato a Chantilly, pensato per gli esercenti',
            'story_text' => 'Piskee è stato ideato e sviluppato in Francia, a Chantilly (Oise), dal team di Piskee. Ogni giorno affianchiamo negozi PrestaShop nella loro visibilità sui motori di ricerca e nella loro crescita, e vedevamo tornare sempre le stesse attività: schede prodotto da riscrivere, combinazioni da creare, immagini da sistemare, tag Hn da ripulire. Abbiamo quindi costruito lo strumento che volevamo per i nostri clienti: un assistente che lavora direttamente nel Suo negozio, con protezioni solide, e un team di persone in carne e ossa raggiungibile quando ne ha bisogno.',
            'email' => 'E-mail',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function uiPl(): array
    {
        return [
            'title' => 'Piskee',
            'by' => 'Asystent AI Państwa sklepu PrestaShop · współpracuje z Claude',
            'tagline' => 'Zarządzanie sklepem PrestaShop w rozmowie z Claude: katalog, kombinacje, zdjęcia, stany magazynowe, obsługa klienta i motyw potomny.',
            'tab_connect' => 'Połączenie',
            'tab_tools' => 'Co robi moduł',
            'tab_theme' => 'Motyw potomny',
            'tab_docs' => 'Dokumentacja',
            'tab_pro' => 'Darmowy / Pro',
            'tab_about' => 'O nas',
            'status' => 'Stan połączenia',
            'status_ok' => 'Wszystko gotowe',
            'status_todo' => 'Wymagane działanie',
            'check_https' => 'HTTPS włączony',
            'check_rewrite' => 'Przyjazne adresy URL włączone',
            'address' => 'Adres do podania w Claude',
            'copy' => 'Kopiuj',
            'copied' => 'Skopiowano',
            'steps' => 'Połączenie z Claude w 5 krokach',
            'step1' => 'Prosimy otworzyć claude.ai (lub aplikację Claude) i przejść do Ustawień, a następnie do Łączników.',
            'step2' => 'Następnie należy kliknąć Dodaj, a potem Dodaj własny łącznik.',
            'step3' => 'Łącznik należy nazwać „Piskee” i wkleić powyższy adres.',
            'step4' => 'Prosimy zostawić ustawienia wykryte przez Claude (logowanie teraz, automatyczna rejestracja), kliknąć Dodaj, a potem Połącz.',
            'step5' => 'Otworzy się panel administracyjny sklepu: prosimy sprawdzić wymagane uprawnienia i kliknąć Zezwól. Gotowe.',
            'claude_code' => 'Z Claude Code',
            'try' => 'Na początek warto poprosić Claude',
            'try1' => '„Proszę podać informacje o moim sklepie”',
            'try2' => '„Proszę zrobić audyt SEO mojego katalogu i zaproponować 10 najważniejszych poprawek”',
            'try3' => '„Których produktów zaraz zabraknie?”',
            'does' => 'Co robi moduł',
            'does_not' => 'Czego moduł nie robi',
            'not1' => 'Nigdy nie zwraca pieniędzy klientom i nie dotyka żadnych płatności.',
            'not2' => 'Nigdy nie zmienia opublikowanego motywu ani motywu nadrzędnego: tylko szkice motywów potomnych.',
            'not3' => 'Nigdy nie publikuje motywu: to Państwo klikają, tutaj, w panelu administracyjnym.',
            'not4' => 'Nie usuwa produktów, klientów ani zamówień.',
            'not5' => 'Nigdy nie daje Claude większych uprawnień niż pracownik, który go połączył.',
            'not6' => 'Nie wysyła żadnych danych do Piskee: wszystko odbywa się między Państwa sklepem a Claude.',
            'safety' => 'Zabezpieczenia',
            'safety1' => 'Każda czynność zmieniająca sklep jest oznaczana dla Claude jako wrażliwa: Claude prosi Państwa o potwierdzenie przed jej wykonaniem.',
            'safety2' => 'Każda zmiana motywu jest zapisywana w kopii zapasowej i można ją przywrócić.',
            'safety3' => 'W każdej chwili mogą Państwo wyłączyć serwer i odłączyć każdego asystenta, tutaj, w zakładce Połączenie.',
            'reads' => 'odczyt',
            'writes' => 'zmiana',
            'theme_title' => 'Motywy potomne i publikacja',
            'theme_intro' => 'Claude pracuje wyłącznie na szkicach motywów potomnych. Gdy wynik Państwu odpowiada, to Państwo publikują go tutaj. Zaraz potem warto sprawdzić wygląd sklepu w innej przeglądarce.',
            'theme_name' => 'Motyw',
            'theme_parent' => 'Nadrzędny',
            'theme_state' => 'Stan',
            'theme_live' => 'Opublikowany',
            'theme_draft' => 'Szkic',
            'theme_parent_theme' => 'Motyw nadrzędny',
            'theme_activate' => 'Opublikuj',
            'theme_confirm' => 'Opublikować ten motyw w sklepie? Odwiedzający od razu zobaczą nowy wygląd.',
            'theme_rollback' => 'Powrót do poprzedniego motywu',
            'theme_none' => 'Nie ma jeszcze żadnego motywu potomnego. Wystarczy poprosić Claude: „Proszę utworzyć szkic motywu potomnego mojego obecnego motywu”.',
            'theme_done' => 'Motyw jest opublikowany.',
            'rollback_done' => 'Poprzedni motyw jest ponownie opublikowany.',
            'fix_done' => 'Zastosowano zalecaną konfigurację.',
            'help_title' => 'Problem lub pytanie?',
            'help_text' => 'Połączenie nie działa, brakuje narzędzia, potrzebne jest wsparcie SEO albo rozwiązanie na zamówienie: zespół Piskee chętnie odpowie.',
            'help_cta' => 'Kontakt',
            'help_whatsapp' => 'WhatsApp',
            'audit_cta' => 'Bezpłatny audyt SEO i GEO w 48 h',
            'docs_intro' => 'Pełna dokumentacja po francusku, angielsku, hiszpańsku, włosku i polsku.',
            'pro_title' => 'Darmowy czy Pro',
            'pro_intro' => 'Wersja darmowa w pełni wystarcza na start. Pro znosi limit i dodaje funkcje zaawansowane.',
            'plan_free' => 'Darmowy',
            'plan_pro' => 'Pro',
            'plan_current' => 'Państwa plan',
            'plan_usage' => 'zmian wykorzystanych w tym miesiącu',
            'plan_reads' => 'Odczyt (wyszukiwanie, audyty, raporty) pozostaje bez limitu. Licznik zeruje się 1. dnia każdego miesiąca.',
            'plan_valid' => 'Licencja Pro ważna do',
            'plan_for' => 'dla',
            'compare_feature' => 'Funkcja',
            'row_tools' => 'Wszystkie narzędzia do katalogu, kombinacji, zdjęć, obsługi klienta i motywu potomnego',
            'row_writes' => 'Zmiany w miesiącu',
            'row_writes_free' => '100',
            'row_writes_pro' => 'Bez limitu',
            'row_bulk' => 'Partiowe tworzenie treści SEO i GEO, zaplanowany audyt z miesięcznym raportem',
            'row_redirects' => 'Przekierowania 301 (usunięte lub przemianowane produkty i kategorie)',
            'row_refunds' => 'Faktury korygujące, zwroty produktów i zwroty pieniędzy',
            'row_multistore' => 'Multistore i szczegółowy dziennik audytu dla każdego pracownika',
            'row_support' => 'Wsparcie priorytetowe',
            'within_quota' => 'w ramach 100',
            'per_month' => '/ mies.',
            'pro_cta' => 'Przejście na Pro',
            'pro_note' => 'Cena startowa gwarantowana dożywotnio dla pierwszych 100 klientów. Bez zobowiązań. Bezpieczna płatność przez Stripe, klucz licencyjny zostanie przesłany e-mailem.',
            'onboarding_title' => 'Wdrożenie z agencją',
            'onboarding_text' => 'Sesja z ekspertem Piskee: połączenie Claude, ustawienie uprawnień, pierwsze audyty katalogu i motywu oraz właściwe polecenia dla Claude dopasowane do Państwa sklepu.',
            'onboarding_cta' => 'Rezerwacja wdrożenia',
            'ob_kicker' => 'Instalacja z ekspertem',
            'ob_title' => 'Instalujemy Piskee razem z Państwem',
            'ob_text' => 'Godzinna wideorozmowa z ekspertem Piskee. Instalujemy moduł razem z Państwem, a następnie pokazujemy na żywo, jak najlepiej wykorzystać go w Państwa sklepie.',
            'ob_b1' => 'Wspólna instalacja i połączenie z Claude',
            'ob_b2' => 'Demonstracja na Państwa katalogu',
            'ob_b3' => 'Polecenia, które oszczędzają najwięcej czasu',
            'ob_amount' => '150 €',
            'ob_tax' => 'netto',
            'ob_unit' => 'jednorazowa sesja · 1 h online',
            'ob_cta' => 'Rezerwacja sesji',
            'license_title' => 'Klucz licencji Pro',
            'license_placeholder' => 'Prosimy wkleić tutaj klucz otrzymany e-mailem (PKC1...)',
            'license_save' => 'Zapisz',
            'license_ok' => 'Licencja Pro aktywowana. Dziękujemy za zaufanie!',
            'license_removed' => 'Licencja usunięta: sklep wraca do planu darmowego.',
            'license_error_malformed' => 'Ten klucz jest nieprawidłowy. Prosimy skopiować go w całości z e-maila.',
            'license_error_signature' => 'Ten klucz nie został wydany przez Piskee.',
            'license_error_domain' => 'Ten klucz został wydany dla innej domeny. Prosimy o kontakt w celu jego przeniesienia.',
            'license_error_expired' => 'Ten klucz wygasł. Aby dalej korzystać z Pro, prosimy odnowić subskrypcję.',
            'license_error_plan' => 'Ten klucz nie dotyczy planu Pro.',
            'license_error_sodium' => 'Na tym serwerze brakuje rozszerzenia PHP sodium: nie można zweryfikować klucza.',
            'about_title' => 'O Piskee',
            'about_text' => 'Piskee to agencja web, SEO i GEO z siedzibą w Chantilly (Francja), specjalizująca się w e-commerce: PrestaShop, Shopify, WordPress, Magento i Webflow. Budujemy Państwa widoczność w Google i w asystentach AI, takich jak ChatGPT, Claude i Perplexity, z jednym celem: sprzedaż, nie tylko pozycje.',
            'about_services' => 'Techniczny i semantyczny audyt SEO · Treści SEO i GEO · Link building · Google Ads i Merchant Center · Tracking · E-mail marketing · Tworzenie i przebudowa stron',
            'about_open' => 'Ten moduł jest open source (licencja AFL-3.0). Zawiera własny serwer MCP i własny serwer autoryzacji OAuth 2.1: jeden moduł, żadnych zależności.',
            'about_repo' => 'Kod źródłowy na GitHubie',
            'website' => 'Strona agencji',
            'version' => 'Wersja',
            'footer' => 'Piskee to niezależny moduł, niepowiązany z Anthropic. Claude jest znakiem towarowym Anthropic.',
            'lang_switch' => 'English',
            'lang_label' => 'Język',
            'check_server' => 'Serwer MCP modułu włączony',
            'check_admin' => 'Panel administracyjny zlokalizowany dla ekranu autoryzacji',
            'check_test' => 'Publiczne adresy osiągalne (test na żywo)',
            'run_test' => 'Test połączenia',
            'test_ok' => 'Test zakończony powodzeniem: adresy MCP i OAuth odpowiadają prawidłowo.',
            'test_ko' => 'Test nieudany',
            'server_toggle_on' => 'Wyłącz serwer',
            'server_toggle_off' => 'Włącz serwer',
            'server_off_done' => 'Serwer wyłączony: żaden asystent nie może już działać w sklepie.',
            'server_on_done' => 'Serwer włączony.',
            'connections' => 'Połączeni asystenci',
            'connections_none' => 'Nie połączono jeszcze żadnego asystenta.',
            'conn_app' => 'Aplikacja',
            'conn_employee' => 'Pracownik',
            'conn_last' => 'Ostatnie użycie',
            'conn_revoke' => 'Odłącz',
            'revoked' => 'Asystent odłączony.',
            'welcome_title' => 'Witamy! Oto o co mogą Państwo poprosić Claude',
            'w1' => 'Przepisanie tytułów, opisów i meta tagów kart produktów i kategorii, we wszystkich językach sklepu.',
            'w2' => 'Tworzenie kart produktów i pisanie ich opisów, wraz z kombinacjami (rozmiary, kolory), cenami, indeksami i stanami magazynowymi.',
            'w3' => 'Dodawanie zdjęć, pisanie tekstów alternatywnych, zmiana rozmiaru i kadrowanie zdjęć do kwadratu.',
            'w4' => 'Audyt SEO i GEO katalogu oraz poprawa najważniejszych elementów.',
            'w5' => 'Śledzenie sprzedaży, wykrywanie braków w magazynie i odpowiadanie na wiadomości klientów.',
            'w6' => 'Tworzenie promocji i kodów rabatowych.',
            'w7' => 'Porządkowanie nagłówków Hn, dodanie ścieżki nawigacyjnej i personalizacja motywu, w szkicu motywu potomnego.',
            'w8' => 'Zarządzanie sprzedażą krzyżową: produkty powiązane, sugestie „często kupowane razem” oparte na Państwa zamówieniach i blok dodatkowych zakupów w koszyku.',
            'w9' => 'Z Piskee Pro: przekierowania 301 tworzone automatycznie, gdy adres URL produktu, kategorii lub strony zmienia się albo zostaje usunięty, oraz monitorowanie stron 404.',
            'w10' => 'Generowanie pliku llms.txt Państwa sklepu, aby asystenci AI (ChatGPT, Claude, Perplexity) lepiej go rozumieli i cytowali.',
            'soon' => 'Wkrótce',
            'reco_title' => 'Którą wersję Claude wybrać?',
            'reco1' => 'Wszystkie plany Claude (Free, Pro, Max, Team, Enterprise) obsługują ten łącznik. Plan darmowy jest ograniczony do jednego własnego łącznika.',
            'reco2' => 'Polecamy Claude Pro do codziennej pracy, a Claude Max do dużych projektów (cały katalog, przebudowa SEO, motyw), które wymagają wielu wymian.',
            'reco3' => 'Model: Sonnet do codziennych zadań, szybki i precyzyjny; Opus do audytów, masowego tworzenia treści i zmian w motywie.',
            'reco4' => 'Można korzystać z claude.ai lub aplikacji Claude (komputer lub telefon). Programiści mogą też używać Claude Code.',
            'legal_title' => 'Informacje prawne i odpowiedzialność',
            'legal1' => 'Piskee to niezależny moduł wydawany przez Piskee. Nie jest oficjalnym produktem PrestaShop SA ani Anthropic, które go nie opracowały, nie zweryfikowały ani nie zatwierdziły i nie ponoszą za niego żadnej odpowiedzialności.',
            'legal2' => 'PrestaShop jest znakiem towarowym PrestaShop SA. Claude jest znakiem towarowym Anthropic, PBC. Nazwy te są używane wyłącznie w celu wskazania kompatybilności.',
            'legal3' => 'Moduł jest dostarczany „w stanie, w jakim jest”, bez gwarancji, w granicach przewidzianych przez prawo i licencję AFL-3.0. Za działania podjęte i zatwierdzone przez sprzedawcę odpowiada sprzedawca: zalecamy regularne tworzenie kopii zapasowych i sprawdzanie proponowanych zmian przed ich zaakceptowaniem.',
            'legal4' => 'Rozmowy z Claude podlegają warunkom korzystania z usług Anthropic. Moduł nie przekazuje żadnych danych do Piskee.',
            'made_in' => 'Zaprojektowano w Chantilly (Francja)',
            'story_title' => 'Narodzony w Chantilly, stworzony dla sprzedawców',
            'story_text' => 'Piskee został wymyślony i zbudowany we Francji, w Chantilly (Oise), przez zespół Piskee. Na co dzień wspieramy sklepy PrestaShop w pozycjonowaniu i rozwoju i ciągle widzieliśmy te same powtarzalne zadania: karty produktów do przepisania, kombinacje do utworzenia, zdjęcia do poprawienia, nagłówki Hn do uporządkowania. Zbudowaliśmy więc narzędzie, jakiego chcieliśmy dla naszych klientów: asystenta, który pracuje bezpośrednio w Państwa sklepie, z solidnymi zabezpieczeniami, oraz zespół ludzi, z którym mogą się Państwo skontaktować, gdy tylko zajdzie potrzeba.',
            'email' => 'E-mail',
        ];
    }
}
