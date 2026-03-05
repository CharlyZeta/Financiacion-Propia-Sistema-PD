🚀 Calculadora de Costos y Planes de Pago por Día para WooCommerce
==================================================================
Sistema para WordPress que simplifica la venta en cuotas diarias. 
Genera planes automáticos por producto y cuenta con un entorno para la gestión rápida de Notas de Venta profesionales en formato PDF, pensado para vendedores en salón.

🎯 Objetivo
-----------
El objetivo principal es brindar un ecosistema autogestionable y fácil para comercios que necesiten ofrecer opciones de pago o financiación con modalidades de "Pagos Diarios". Este plugin automatiza todos los procesos engorrosos de desglose de cuotas en base al precio de contado, ofreciendo un valor directo al equipo de ventas que opera en contacto con el cliente.

🧠 Contexto
-----------
Solución productiva e implementada originalmente para tiendas WooCommerce de la vida real enfocadas a equipamientos comerciales y para el hogar. El plugin estandariza los cierres de ventas dándole al vendedor una herramienta formal instalada en tablets o móviles para emitir notas al instante.

🛠 Tech Stack
------------
![WordPress](https://img.shields.io/badge/WordPress-%23117B85.svg?style=for-the-badge&logo=wordpress&logoColor=white) 
![WooCommerce](https://img.shields.io/badge/WooCommerce-%2396588a.svg?style=for-the-badge&logo=woocommerce&logoColor=white) 
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white) 
![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E) 
![TailwindCSS](https://img.shields.io/badge/tailwindcss-%2338B2AC.svg?style=for-the-badge&logo=tailwind-css&logoColor=white)

🏗 Arquitectura
---------------
El plugin está estructurado en tres módulos lógicos de alto nivel:

- **Core de Cálculo Backend:** 
Se enlaza a los hooks de guardado y edición (como `woocommerce_process_product_meta`). Cuenta además con una herramienta de procesamiento batch (AJAX) para procesar miles de productos mediante lotes de IDs, esquivando timeouts y guardando todo el resultado en post metas.
- **Frontend / Visor Público:** 
Catálogo grid renderizado mediante el shortcode `[visor_planes_pxd]`. Incluye galerías con integración de *Swiper.js* y botones pre-formateados para compartir cotizaciones por *WhatsApp* con un solo click.
- **Motor de Notas de Venta:** 
Aplicación privada de uso mixto renderizada desde `[cxd_nota_venta]`. Utiliza *TailwindCSS*, selectores de productos multi-línea asociados a sus variantes de pago y captura de firmas en vivo mediante *Signature Pad* (HTML5 Canvas a Base64). El resultado final se procesa hacia un documento con *Dompdf* que es despachado automáticamente vía e-mail a la caja central.

🔐 Seguridad
------------
*   **Acceso Restringido:** El bloque del creador de notas de venta requiere permisos (capabilities de Manager/Admin, estar incluido en una Whitelist de e-mail o poseer el rol dinámico `Vendedor de Pago Diario`).
*   **Validación de Forms:** Uso extensivo de Nonces para proteger la inyección y procesamiento de datos vía llamadas AJAX.

🐳 Ejecución Local / Instalación
---------------------------------
1.  Descarga el directorio raíz y empaquétalo en formato `.zip`.
2.  Desde el panel de WordPress dirígete a **Plugins > Añadir nuevo** y sube el `.zip`.
3.  Activa el plugin y navega al nuevo menú de **Calculadora PxD > Configuración** para asignar los parámetros operacionales (como el `descuento_transferencia`).

📡 Implementación
------------------
Simplemente añade estos shortcodes a páginas nuevas:
*   Página de catálogo (puede ser pública o interna): `[visor_planes_pxd]`
*   Generador / Intranet: `[cxd_nota_venta]`

📌 Estado del Proyecto
-----------------------
En producción (Estable - Versión 2.9.7).

🛣 Roadmap
----------
*   Ampliar configuraciones a nivel de Categoría además de general.
*   Implementar generación de recibos individuales de cobranza (post-venta).

👨‍💻 Autor
---------
**Gerardo Maidana**
**Requiere WordPress:** 5.6+
**Requiere PHP:** 7.4+
**Compatible con WooCommerce:** 5.0 - 8.0+

Backend Developer | Java & Spring Boot / WordPress Development
LinkedIn [gerardomaidana](https://linkedin.com/in/gerardomaidana)
GitHub [CharlyZeta](https://github.com/CharlyZeta/)
