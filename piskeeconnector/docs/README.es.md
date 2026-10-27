# Piskee para PrestaShop, documentación

Piskee conecta tu tienda PrestaShop con Claude: el asistente de IA de tu back office. Dices lo que quieres hacer («reescribe las meta descriptions de la categoría Vestidos», «crea las tallas S a XL para este producto», «¿qué productos están agotados?») y Claude actúa directamente en tu tienda, con tus permisos y bajo tu control.

Diseñado y desarrollado en Chantilly (Francia) por Piskee, agencia SEO y GEO especializada en e-commerce.

## Antes de empezar

- PrestaShop 1.7.6 o superior (1.7, 8 y 9), PHP 7.2 o superior.
- La tienda en HTTPS, en la raíz de su dominio, con las URL amigables activadas.
- Nada más: el módulo incluye su propio servidor MCP y su servidor de autorización OAuth 2.1.
- Una cuenta de Claude (claude.ai, la aplicación de Claude o Claude Code).

## Instalación

1. Módulos, Gestor de módulos, Subir un módulo: arrastra el archivo zip del módulo.
2. Abre el módulo Piskee: la pestaña Conexión lo comprueba todo. Haz clic en **Probar la conexión** para hacer una prueba real de las direcciones públicas.

## Conectar Claude

1. Copia la dirección que aparece en la pestaña Conexión (termina en `/module/piskeeconnector/mcp`).
2. En Claude: Configuración, Conectores, Añadir, Añadir conector personalizado.
3. Llámalo «Piskee», pega la dirección, mantén los ajustes detectados (iniciar sesión ahora, registro automático), haz clic en Añadir y, después, en Conectar.
4. Tu back office se abre en una pantalla de autorización: revisa los permisos solicitados y haz clic en **Autorizar**.

Con Claude Code, un solo comando:

```
claude mcp add --transport http piskee https://tu-tienda.com/module/piskeeconnector/mcp
```

Después escribe `/mcp` en Claude Code para iniciar sesión.

## Lo que Claude puede hacer

**Productos y categorías**: buscar, ver y modificar un producto (precios, referencias, EAN, peso, estado, textos y SEO en todos los idiomas), crear un producto (desactivado por defecto), gestionar el stock con un valor exacto o mediante entradas y salidas, asignar categorías, modificar una categoría.

**Combinaciones**: listar y crear atributos (talla, color...) y sus valores, crear hasta 100 combinaciones de una vez con impacto en el precio, referencia, EAN y stock, modificar una combinación.

**Imágenes**: añadir una imagen desde una dirección web, redactar los textos alternativos, elegir la imagen de portada, reducir los originales demasiado pesados, centrarlos sobre un fondo cuadrado y regenerar todas las miniaturas.

**Enriquecimiento**: características, marcas, productos relacionados, promociones (porcentaje o importe, con fechas) y una auditoría completa del catálogo que enumera lo que frena tus ventas y tu visibilidad en buscadores.

**Venta cruzada y carrito**: productos relacionados (añadir, quitar, sustituir, hasta 50 productos de una vez), sugerencias «comprados juntos con frecuencia» calculadas a partir de tus pedidos reales, y el bloque nativo «los clientes también compraron» en la ficha de producto y en el carrito como compras adicionales. Los demás módulos de upsell se detectan y se enumeran, pero nunca se modifican.

**Pedidos y seguimiento**: encontrar un pedido (referencia, cliente, número de seguimiento), verlo con su historial y el enlace de seguimiento del transportista, cambiar su estado, indicar un número de seguimiento.

**Páginas CMS y traducciones**: ver y modificar las páginas CMS, modificar hasta 50 productos de una vez (precios, estados, textos o traducciones).

**Gestión diaria**: resumen de ventas de un periodo, stock bajo, búsqueda y ficha de clientes, lectura de los mensajes de atención al cliente y respuesta por correo electrónico (tras tu aprobación), creación de cupones de descuento.

**Tema hijo**: crear un tema hijo en borrador, leer y buscar en los archivos del tema, auditar los encabezados y las migas de pan de una página publicada, modificar las plantillas, el CSS y el JavaScript del borrador, restaurar una copia de seguridad.

En el menú de Claude también hay tres guías listas para usar: Resumen del día, Optimizar el catálogo, Limpiar las Hn y añadir migas de pan.

## Lo que Claude no puede hacer

- Reembolsar a un cliente o tocar un pago.
- Modificar el tema publicado o un tema padre.
- Publicar un tema: eres tú quien hace clic, en la pestaña Tema hijo.
- Eliminar un producto, un cliente o un pedido.
- Obtener más permisos que el empleado que lo conectó.

## Salvaguardas

- Cada herramienta que modifica la tienda está marcada como sensible: Claude te pide confirmación antes de ejecutarla.
- Los permisos son los del perfil PrestaShop del empleado que autorizó la conexión, y se comprueban en cada llamada.
- Cada modificación del tema se guarda en una copia de seguridad. Los archivos PHP y la configuración del tema no pueden modificarse. Las plantillas se revisan antes de guardarse: se rechazan las etiquetas Smarty desequilibradas, las funciones PHP fuera de una lista corta de permitidas, las llamadas a clases, las constantes y las lecturas de archivos.
- Puedes desactivar el servidor y desconectar cada asistente en cualquier momento, en la pestaña Conexión.
- No se envía ningún dato a Piskee.

## Publicar un tema

1. Pide a Claude que trabaje en un tema hijo en borrador.
2. Abre Piskee, pestaña Tema hijo, y haz clic en **Publicar** junto al borrador.
3. Comprueba la página de inicio, una categoría y un producto. Si algo no se ve bien, haz clic en **Volver al tema anterior**.

Solo cambia el tema: tus módulos, sus ajustes, las posiciones de los hooks, los tamaños de imagen y los diseños de página se mantienen tal cual. Un borrador solo puede publicarse sobre un tema de la misma familia (mismo tema padre).

## Gratis o Pro

La versión gratuita permite 100 modificaciones al mes y por tienda; la lectura, las búsquedas y las auditorías son ilimitadas. El contador se reinicia el día 1 de cada mes.

Piskee Pro (29 € + IVA al mes, precio de lanzamiento garantizado de por vida para los 100 primeros clientes, sin permanencia) elimina el límite y añade las redirecciones 301, las facturas por abono y las devoluciones, la multitienda con registro de auditoría y el soporte prioritario. Se ofrece una sesión de puesta en marcha con la agencia por 300 € + IVA.

Pega la clave de licencia que recibas por correo electrónico en la pestaña Gratis / Pro.

## Resolución de problemas

- **«Esta solicitud de conexión no es válida»** al autorizar: elimina el conector en Claude y vuelve a añadirlo.
- **Error 401 permanente**: algunos alojamientos eliminan la cabecera Authorization. Añade `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` al principio del archivo `.htaccess`.
- **Claude no ve las nuevas herramientas**: abre una nueva conversación.
- **Error al abrirse la pantalla de autorización**: comprueba que la tienda no esté en modo mantenimiento y que ningún cortafuegos bloquee las direcciones `/.well-known/`.
- **Un cambio en el tema no se ve**: es normal mientras el borrador no esté publicado.

## ¿Necesitas ayuda?

El equipo de Piskee responde a las preguntas sobre el módulo y acompaña a las tiendas que quieren ir más allá: auditorías SEO y GEO, contenidos, Google Ads, desarrollos a medida.

- Contacto: [piskee.com/contact](https://www.piskee.com/contact/)
- Correo electrónico: louisalexandre@piskee.com
- WhatsApp: +33 7 69 15 39 92
- Auditoría SEO y GEO gratuita en 48 h: [piskee.com](https://www.piskee.com/)

## Aviso legal

Piskee para PrestaShop es un módulo independiente editado por Piskee (Chantilly, Francia). No es un producto oficial de PrestaShop SA ni de Anthropic, PBC, que no lo han desarrollado, revisado ni aprobado, y que no asumen ninguna responsabilidad sobre él. PrestaShop es una marca de PrestaShop SA; Claude es una marca de Anthropic, PBC; estos nombres se citan únicamente para indicar la compatibilidad. El módulo se proporciona «tal cual», sin garantía, dentro de los límites previstos por la ley y por la licencia AFL-3.0. Las acciones decididas y aprobadas por el comerciante son responsabilidad suya: haz copias de seguridad periódicas y revisa los cambios antes de aceptarlos.
