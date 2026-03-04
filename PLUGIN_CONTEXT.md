# Arquitectura y Funcionalidad del Plugin: Calculadora PxD y Notas de Venta

## 1. Resumen General

Este plugin de WordPress/WooCommerce ofrece dos funcionalidades principales y en gran medida independientes:

1.  **Calculadora y Visor de Planes de Pago:** Transforma el precio de un producto en múltiples planes de financiación con cuotas diarias y muestra alternativas de pago (ej. Transferencia). Esta función está orientada al **cliente final** y se muestra a través de un catálogo de productos personalizado.
2.  **Generador de Notas de Venta:** Proporciona un formulario para **uso interno (vendedores habilitados)** que permite crear, firmar y enviar por correo electrónico y WhatsApp un documento PDF formal de "Nota de Venta".

**Dependencias Notables:**
*   **WooCommerce:** Esencial para la gestión de productos, precios y metadatos de usuario (ej. DNI).
*   **Dompdf:** Librería PHP (incluida en `lib/`) para la generación de PDFs.
*   **Signature Pad, Tailwind CSS, Swiper.js:** Librerías JavaScript/CSS cargadas desde CDN para mejorar la interactividad y el estilo de los componentes de frontend.

---

## 2. Funcionalidad 1: Calculadora y Visor de Planes

### 2.1. Lógica de Cálculo

-   **Función Principal:** `cxd_calcular_y_guardar_planes($product_id, $args = [])` en `includes/cxd-core-functions.php`.
-   **Disparadores (Triggers):** El cálculo se activa automáticamente en los siguientes eventos:
    -   Al guardar un producto en el editor de WooCommerce (`woocommerce_process_product_meta`).
    -   Al usar la "Edición Rápida" de un producto (`woocommerce_product_quick_edit_save`).
    -   Al actualizar el precio desde el editor masivo BEAR (`woobe_after_update_page_field`).
    -   Manualmente a través del proceso por lotes en la página de configuración.
-   **Algoritmo:**
    1.  Obtiene el precio del producto.
    2.  Carga las opciones de cálculo desde la opción de WordPress `cxd_opciones`.
    3.  Calcula un **Costo Base** aplicando un descuento: `costo_base = precio * (1 - (descuento / 100))`.
    4.  Para cada plan definido (ej: 30, 52, 78 días), aplica la fórmula de cuota: `ceil((costo_base * coeficiente_plan / dias) / base_redondeo) * base_redondeo`.
    5.  Un plan se considera válido solo si la cuota diaria resultante es mayor o igual al `monto_minimo` configurado.
-   **Almacenamiento de Datos (Post Meta):**
    -   `_costoxd_base`: El costo base calculado.
    -   `_cxd_p{dias}`: La cuota diaria para un plan específico.
    -   `_costoxd_planes`: Un array serializado que contiene todos los planes válidos.
    -   `fecha_ultima_modificacion`: Timestamp de la última vez que se modificó el precio.

### 2.2. Interfaz de Administración

-   **Ubicación:** Menú "Calculadora PxD" > "Configuración".
-   **Archivo:** `includes/admin/cxd-admin-settings.php`.
-   **Opciones (`cxd_opciones`):** Permite configurar:
    -   `descuento`: Porcentaje de descuento para el costo base de los planes.
    -   `descuento_transferencia`: Porcentaje aplicado al precio de lista para mostrar opcionalmente un precio especial por transferencia en el Visor.
    -   `monto_minimo`: Cuota diaria mínima para que un plan sea válido.
    -   `redondeo`: Múltiplo para el redondeo hacia arriba de la cuota.
    -   `coef_plan_{dias}`: Coeficientes de ajuste para cada plan.
-   **Recálculo en Lote:**
    -   **Arquitectura Robusta:** Usa AJAX (`cxd_get_all_product_ids`) para obtener todos los IDs de productos y luego los procesa en pequeños lotes desde el frontend (`cxd-batch.js`) hacia el backend (`cxd_procesar_lote`). Evita timeouts de PHP.

### 2.3. Visualización Frontend

-   **Shortcode:** `[visor_planes_pxd]`
-   **Archivo:** `includes/cxd-shortcode-handler.php`.
-   **Funcionalidad:**
    -   Catálogo interactivo con filtros (búsqueda, categoría, stock) y ordenamiento dinámico.
    -   **Tarjeta de Producto:**
        -   Carrusel de imágenes de galería usando **Swiper.js**.
        -   Muestra Precio base y Precio por **Transferencia** (calculado en vivo si está configurado).
        -   Lista los planes de pago disponibles.
        -   **Botón "Compartir":** Genera enlace WhatsApp con resumen de precio de lista, transferencia y planes.
        -   **Botón "Detalles":** Abre modal descriptivo.
        -   **Botón "Añadir a Nota":** Redirige a la Nota de Venta precargando el producto mediante URL parameter `?agregar-producto=ID`.
    -   **Cache Busting:** Usa `filemtime` en assets para asegurar la propagación inmediata de actualizaciones visuales en combinación con los hooks globales de purga de caché.

---

## 3. Funcionalidad 2: Generador de Notas de Venta

### 3.1. Formulario Frontend y Lógica de Negocio

-   **Shortcode:** `[cxd_nota_venta]`
-   **Seguridad:** Accesible únicamente para Administradores, Gerentes de Tienda, el rol personalizado `vendedor_pago_diario` y direcciones de email explícitamente listadas en los ajustes.
-   **Estructura del Formulario (Tailwind CSS):**
    -   **Buscador Inteligente de Clientes:** Capacidad de buscar por nombre o DNI a usuarios existentes de WooCommerce. Autocompleta automáticamente campos (DNI, Nombre, Domicilio, Localidad, CP, Provincia, Teléfono, Email). Al no existir, el vendedor ingresa los datos a mano.
    -   **Gestión de Entrega:** Casilla para "Entrega inmediata" o selector de "Fecha de Entrega" pactada.
    -   **Listado de Productos Multilínea:**
        -   Se pueden añadir múltiples líneas. Cada línea incluye un **buscador integrado** para encontrar rápidamente productos en stock.
        -   **Opciones por línea:** Selector de Clase (A, B, C, etc.) y Forma de Pago (Contado, Débito, Transferencia, Pago Diario).
        -   **Lógica de Pago Diario:** Permite escoger el número de días y opcionalmente aplicar sobre ese plan un porcentaje de descuento o recargo, calculando la nueva cuota diaria al instante.
    -   **Panel de Firma:** Captura firma manuscrita digitalizada en canvas HTML5 a formato Base64 a través de `Signature Pad`.

### 3.2. Procesamiento Backend

-   **Manejador AJAX:** `procesar_nota_venta_ajax()` en `includes/nota-venta/class-cxd-nota-venta-shortcode.php`.
-   **Ciclo de Vida:**
    1.  **Validación y Recolección:** Validaciones exhaustivas (ej. cantidad pedida vs. stock en inventario actualizado). Prepara las líneas e info del cliente.
    2.  **Generación de PDF con Dompdf:** Ensambla un HTML desde `template-pdf-nota-venta.php` que incluye tabla de desglose, datos del cliente, opciones de entrega y la firma Base64 incrustada. Lo convierte a PDF A4.
    3.  **Distribución y Notificación:**
        -   Se envía un e-mail con el PDF adjunto a los receptores configurados (empresa y cliente).
        -   Si se tildó "Confirmar por WhatsApp", genera una URL `wa.me/...` con un texto procesado que resume los artículos comprados, formas de pago, y **fecha estimada de cancelación** (para pago diario, excluyendo domingos).
    4.  **Respuesta Frontend:** Avisa éxito, muestra los botones de "Generar Nueva Nota" y "Compartir por WhatsApp". Se limpia el PDF del servidor.

### 3.3. Configuración de la Nota de Venta

-   **Ubicación:** Menú "Calculadora PxD" > "Ajustes Nota de Venta".
-   **Archivo:** `includes/admin/cxd-admin-nota-venta-settings.php`.
-   **Opciones (`cxd_nota_venta_options`):**
    -   `email_empresa`: Destinatario fijo de todas las notas.
    -   `url_nota_venta`: URL pública/privada donde reside el shortcode `[cxd_nota_venta]`. Ayuda a la interconexión con el visor.
    -   `recargos_porcentuales`: Define los valores permitidos para alterar la cuota (botones dinámicos), ej. `10, 5, -5`.
    -   `emails_autorizados`: Lista CSV de correos autorizados a ver el shortcode, útil para asesores externos sin cuenta de WP.

---

## 4. Estrategia de Versionado y Caché

- **Cache Busting Infalible:** Emplea `filemtime` para encolar CSS/JS (`cxd-frontend-visor.css`, JS de backend y frontend).
- **Auto-purga Global:** En ganchos de actualización de plugins (`upgrader_process_complete`), invoca `cxd_purge_all_caches()` que integra soporte para WP Rocket, Litespeed, W3TC, SG Optimizer y Object Caches.
- **Workflow:** Todo cambio requiere incrementar el número de versión (actual v2.9.7) documentado en la cabecera del archivo raíz del plugin y detallado en `changelog.txt`.