<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Añade la página de ajustes para la Nota de Venta al menú de administración.
 * Se enganchará como un submenú de 'Planes PxD'.
 */
add_action( 'admin_menu', 'cxd_agregar_pagina_configuracion_nota_venta' );
function cxd_agregar_pagina_configuracion_nota_venta() {
    add_submenu_page(
        'cxd-visor-planes', // Slug del menú padre 'Planes PxD'
        'Ajustes de Nota de Venta', // Título de la página
        'Ajustes Nota de Venta', // Título en el menú
        'manage_options', // Capacidad requerida
        'cxd-nota-venta-settings', // Slug de esta página
        'cxd_render_pagina_configuracion_nota_venta' // Función que renderiza el HTML
    );
}

/**
 * Renderiza el HTML de la página de ajustes.
 */
function cxd_render_pagina_configuracion_nota_venta() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'cxd_nota_venta_options_group' );
            do_settings_sections( 'cxd-nota-venta-settings' );
            submit_button( 'Guardar Ajustes' );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registra los ajustes, secciones y campos usando la API de Ajustes de WordPress.
 */
add_action( 'admin_init', 'cxd_registrar_ajustes_nota_venta' );
function cxd_registrar_ajustes_nota_venta() {
    register_setting( 'cxd_nota_venta_options_group', 'cxd_nota_venta_options', [
        'sanitize_callback' => 'cxd_sanitizar_opciones_nota_venta'
    ]);

    // Sección de Email
    add_settings_section(
        'cxd_section_email',
        'Ajustes de Correo Electrónico',
        'cxd_descripcion_seccion_email',
        'cxd-nota-venta-settings'
    );

    add_settings_field(
        'email_empresa',
        'Email de la Empresa',
        'cxd_render_campo_email_empresa',
        'cxd-nota-venta-settings',
        'cxd_section_email'
    );

    // Nueva sección de Recargos
    add_settings_section(
        'cxd_section_recargos',
        'Ajustes de Recargos',
        'cxd_descripcion_seccion_recargos',
        'cxd-nota-venta-settings'
    );

    add_settings_field(
        'recargos_porcentuales',
        'Porcentajes de Recargo',
        'cxd_render_campo_recargos',
        'cxd-nota-venta-settings',
        'cxd_section_recargos'
    );

    // Nueva sección de Integración
    add_settings_section(
        'cxd_section_integracion',
        'Ajustes de Integración',
        'cxd_descripcion_seccion_integracion',
        'cxd-nota-venta-settings'
    );

    add_settings_field(
        'url_nota_venta',
        'URL de la página de Nota de Venta',
        'cxd_render_campo_url_nota_venta',
        'cxd-nota-venta-settings',
        'cxd_section_integracion'
    );

    // Nueva sección de Seguridad
    add_settings_section(
        'cxd_section_seguridad',
        'Ajustes de Seguridad',
        'cxd_descripcion_seccion_seguridad',
        'cxd-nota-venta-settings'
    );

    add_settings_field(
        'emails_autorizados',
        'Emails Autorizados',
        'cxd_render_campo_emails_autorizados',
        'cxd-nota-venta-settings',
        'cxd_section_seguridad'
    );
}

function cxd_descripcion_seccion_email() {
    echo '<p>Configura las direcciones de correo a las que se enviarán las notificaciones de nuevas notas de venta.</p>';
}

function cxd_render_campo_email_empresa() {
    $options = get_option( 'cxd_nota_venta_options' );
    $email = isset( $options['email_empresa'] ) ? $options['email_empresa'] : '';
    ?>
    <input type="email" name="cxd_nota_venta_options[email_empresa]" value="<?php echo esc_attr( $email ); ?>" class="regular-text" placeholder="ventas@empresa.com">
    <p class="description">El email principal que recibirá la nota de venta para su procesamiento.</p>
    <?php
}

// Nuevas funciones para la sección de recargos
function cxd_descripcion_seccion_recargos() {
    echo '<p>Define los porcentajes de recargo o descuento que aparecerán como botones en cada producto de la nota de venta.</p>';
}

function cxd_render_campo_recargos() {
    $options = get_option( 'cxd_nota_venta_options' );
    $recargos = isset( $options['recargos_porcentuales'] ) ? $options['recargos_porcentuales'] : '2, 5, 7, 10, 15';
    ?>
    <input type="text" name="cxd_nota_venta_options[recargos_porcentuales]" value="<?php echo esc_attr( $recargos ); ?>" class="regular-text">
    <p class="description">Introduce los valores separados por comas. Usa números negativos para descuentos (ej: -5, -2, 5, 10).</p>
    <?php
}

// Nuevas funciones para la sección de integración
function cxd_descripcion_seccion_integracion() {
    echo '<p>Configura la URL de la página donde se encuentra el shortcode de la nota de venta.</p>';
}

function cxd_render_campo_url_nota_venta() {
    $options = get_option( 'cxd_nota_venta_options' );
    $url = isset( $options['url_nota_venta'] ) ? $options['url_nota_venta'] : '';
    ?>
    <input type="url" name="cxd_nota_venta_options[url_nota_venta]" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://tusitio.com/nota-de-venta/">
    <p class="description">Esta URL se usará para el botón "Añadir a Nota" en el visor de planes.</p>
    <?php
}

// Nuevas funciones para la sección de seguridad
function cxd_descripcion_seccion_seguridad() {
    echo '<p>Configura los usuarios autorizados para acceder a la nota de venta. Solo administradores, gerentes de tienda y usuarios con rol "Vendedor de Pago Diario" o emails específicos pueden usar la funcionalidad.</p>';
}

function cxd_render_campo_emails_autorizados() {
    $options = get_option( 'cxd_nota_venta_options' );
    $emails = isset( $options['emails_autorizados'] ) ? $options['emails_autorizados'] : '';
    ?>
    <textarea name="cxd_nota_venta_options[emails_autorizados]" rows="4" class="large-text"><?php echo esc_textarea( $emails ); ?></textarea>
    <p class="description">Introduce los emails autorizados separados por comas. Si está vacío, solo roles específicos tienen acceso.</p>
    <?php
}

function cxd_sanitizar_opciones_nota_venta( $input ) {
    $output = [];
    if ( isset( $input['email_empresa'] ) ) {
        $output['email_empresa'] = sanitize_email( $input['email_empresa'] );
    }

    if ( isset( $input['recargos_porcentuales'] ) ) {
        // Limpiamos la cadena: eliminamos espacios y caracteres no deseados.
        $recargos_str = sanitize_text_field( $input['recargos_porcentuales'] );
        // Convertimos la cadena en un array.
        $recargos_arr = explode( ',', $recargos_str );
        // Limpiamos y validamos cada elemento del array.
        $recargos_limpios = [];
        foreach ( $recargos_arr as $recargo ) {
            // Convertimos a float, eliminando espacios extra.
            $valor = floatval( trim( $recargo ) );
            // Solo añadimos si es un número válido (floatval devuelve 0 si no lo es, lo cual es un recargo válido).
            $recargos_limpios[] = $valor;
        }
        // Volvemos a unir la cadena con comas para un formato consistente.
        $output['recargos_porcentuales'] = implode( ',', $recargos_limpios );
    }

    if ( isset( $input['url_nota_venta'] ) ) {
        $output['url_nota_venta'] = esc_url_raw( $input['url_nota_venta'] );
    }

    if ( isset( $input['emails_autorizados'] ) ) {
        $emails_str = sanitize_textarea_field( $input['emails_autorizados'] );
        $emails_arr = array_map( 'trim', explode( ',', $emails_str ) );
        $emails_validos = [];
        foreach ( $emails_arr as $email ) {
            if ( is_email( $email ) ) {
                $emails_validos[] = $email;
            }
        }
        $output['emails_autorizados'] = implode( ', ', $emails_validos );
    }

    return $output;
}