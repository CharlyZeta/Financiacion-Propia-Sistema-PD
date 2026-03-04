# Calculadora de Costos y Planes de Pago por Día para WooCommerce

![WordPress](https://img.shields.io/badge/WordPress-%23117B85.svg?style=for-the-badge&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-%2396588a.svg?style=for-the-badge&logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)
![TailwindCSS](https://img.shields.io/badge/tailwindcss-%2338B2AC.svg?style=for-the-badge&logo=tailwind-css&logoColor=white)

**Versión:** 2.9.7
**Autor:** Gerardo Maidana
**Compatible con WooCommerce:** 5.0 - 8.0+

Este plugin para WordPress y WooCommerce ofrece un completo ecosistema de herramientas para la financiación y venta de productos. Permite generar automáticamente planes de pago en cuotas diarias y, además, crear notas de venta profesionales en PDF.

## ✨ Características Principales

### Módulo de Cálculo de Planes

-   **Página de Configuración Centralizada:** Administra todas las reglas de cálculo desde un único lugar en `Calculadora PxD > Configuración`.
-   **Cálculo Flexible de Planes:**
    -   Define un **descuento** sobre el precio de venta para obtener un costo base.
    -   Establece un **monto mínimo** para que una cuota sea considerada válida.
    -   Configura un **redondeo** hacia arriba para las cuotas (ej: a la centena más cercana).
    -   Asigna **coeficientes** de cálculo específicos para cada plan (30, 52, 78, 104, 156, 208 días).
-   **Integración Total con WooCommerce:**
    -   Los planes se calculan y guardan **automáticamente** al crear o actualizar un producto.
    -   **Meta Box con Vista Previa en Vivo:** En la página de edición de un producto, visualiza los planes calculados y obtén una vista previa instantánea si modificas el precio, sin necesidad de guardar.
-   **Procesamiento en Lote (Batch Processing):**
    -   Una herramienta para (re)calcular los planes de **todos los productos** de la tienda con un solo clic. El proceso se ejecuta de forma segura vía AJAX para evitar sobrecargas del servidor.
-   **Visor de Planes en el Administrador:**
    -   Una página en el menú de administración (`Calculadora PxD > Visor de Planes`) que muestra todos los productos en una tabla con sus planes, permitiendo búsquedas y filtros avanzados.

### Módulo de Visor Frontend

-   **Shortcode `[visor_planes_pxd]`:** Muestra un catálogo de productos interactivo en cualquier página.
-   **Diseño Moderno y Responsivo:**
    -   Visualización en formato de tarjetas que se adapta a cualquier dispositivo.
    -   Los filtros se agrupan en una sola línea en tablets y monitores para una mejor experiencia.
-   **Filtros y Búsqueda Avanzados:**
    -   Búsqueda por nombre.
    -   Filtro por categoría de producto.
    -   Filtro por estado de **stock**.
    -   Selector para **ordenar** los resultados (por fecha, precio, nombre).
-   **Interacción Mejorada:**
    -   **Botón "Detalles":** Abre una ventana modal con la descripción completa del producto sin salir de la página.
    -   **Botón "Compartir por WhatsApp":** Genera un mensaje pre-formateado con los detalles, **precio de contado/transferencia** y planes del producto, listo para enviar.
    -   **Botón "Añadir a Nota":** Redirige a la página de la Nota de Venta, precargando el producto seleccionado para agilizar el trabajo del vendedor.
-   **Visualización de Precios Adicionales:**
    -   Muestra opcionalmente el **Precio por Transferencia** calculado automáticamente basado en un porcentaje de descuento configurable.

### Módulo de Nota de Venta

-   **Shortcode `[cxd_nota_venta]`:** Incrusta un formulario profesional para generar notas de venta.
-   **Acceso Restringido:** Solo accesible para Administradores, Gerentes de Tienda, usuarios con el rol **Vendedor de Pago Diario** y correos electrónicos específicos autorizados.
-   **Formulario Completo e Inteligente:**
    -   Campos para datos obligatorios (nombre, DNI, domicilio, localidad, código postal, provincia, etc.).
    -   **Buscador Inteligente de Clientes:** Autocompleta los datos del cliente si ya existe en la base de datos de WooCommerce (búsqueda por DNI o Nombre).
    -   Selector dinámico para añadir múltiples productos a la nota con validación de **stock en tiempo real** y selector individual de Clase por producto.
    -   **Múltiples Formas de Pago:** Selección de medio de pago por artículo (Contado, Débito, Transferencia o Pago Diario). Soporte dinámico para recargos/descuentos en el pago diario.
    -   **Gestión de Entrega:** Casilla para "Entrega Inmediata" o selector de Fecha de Entrega pactada.
    -   **Panel de Firma Digital:** El cliente puede registrar su conformidad firmando directamente en el formulario.
    -   **Precarga de Productos:** El formulario puede iniciarse con un producto ya añadido desde el visor de planes.
-   **Generación de PDF:**
    -   Crea un documento PDF con un diseño profesional que incluye todos los datos del formulario, los productos desglosados con su plan de pago y la firma.
-   **Integración y Comunicación:**
    -   Envía automáticamente la nota de venta en PDF al correo de la empresa y opcionalmente copia al cliente.
    -   Ofrece un botón de confirmación automática por **WhatsApp** tras la generación de la nota, enviando un resumen completo con la fecha estimada de cancelación.
-   **Página de Configuración:** Gestiona los ajustes del módulo desde `Calculadora PxD > Ajustes Nota de Venta` (emails, url, recargos, usuarios autorizados).

## 🚀 Instalación

1.  Descarga el archivo `.zip` del plugin.
2.  En tu panel de WordPress, ve a `Plugins > Añadir nuevo`.
3.  Haz clic en `Subir plugin` y selecciona el archivo `.zip`.
4.  Instala y activa el plugin.

## ⚙️ Configuración y Uso

### 1. Ajustes de la Calculadora

Ve a **Calculadora PxD > Configuración** para configurar las reglas de cálculo de los planes de pago (descuento, redondeo, coeficientes, etc.).

### 2. Ajustes de la Nota de Venta

Ve a **Calculadora PxD > Ajustes Nota de Venta** para configurar el email de la empresa que recibirá las notas de venta y, muy importante, la **URL de la página** donde has colocado el shortcode `[cxd_nota_venta]` para activar la integración con el visor de planes.

### 3. Shortcodes

-   Para el visor de productos, crea una página y añade el shortcode: `[visor_planes_pxd]`
-   Para el formulario de nota de venta, crea otra página y añade el shortcode: `[cxd_nota_venta]`

---
_Este plugin ha sido desarrollado para facilitar la gestión de planes de financiación y mejorar la experiencia de compra en tiendas WooCommerce._
