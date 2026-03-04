<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', 'cxd_agregar_pagina_configuracion');
function cxd_agregar_pagina_configuracion() {
    add_submenu_page(
        'cxd-visor-planes', // parent slug
        'Configuración de Calculadora PxD', // page title
        'Configuración', // menu title
        'manage_options', // capability
        'costoxd-config', // menu slug
        'cxd_renderizar_pagina_configuracion' // function
    );
}

function cxd_renderizar_pagina_configuracion() {
    ?>
    <div class="wrap">
        <h1>Configuración de la Calculadora CostoXD</h1>
        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 10px 20px; margin-top: 20px;">
            <h2>Uso del Shortcode</h2>
            <p>Para mostrar el visor de productos en cualquier página o entrada, copia y pega el siguiente shortcode:</p>
            <p><input type="text" value="[visor_planes_pxd]" readonly onfocus="this.select();" style="width: 100%; max-width: 300px; text-align: center;"></p>
        </div>
        <form action="options.php" method="post" style="margin-top: 20px;">
            <?php
            settings_fields('cxd_opciones_grupo');
            do_settings_sections('costoxd-config');
            submit_button('Guardar Cambios');
            ?>
        </form>
        <hr>
        <h2>Procesamiento en Lote</h2>
        <p>Usa este botón para calcular o recalcular los planes para todos los productos existentes. El proceso puede tardar varios minutos.</p>
        <button id="cxd-recalcular-todos" class="button button-primary">Iniciar Recálculo de Todos los Productos</button>
        <div id="cxd-recalcular-status" style="margin-top: 15px; padding: 10px; border: 1px solid #ccc; background: #f7f7f7; display: none;"></div>
    </div>
    <?php
    // Añadir el pie de página con la versión del plugin
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin_data = get_plugin_data( CXD_PLUGIN_PATH . 'calculadora-costoxd.php' );
    $plugin_version = $plugin_data['Version'];
    echo '<div class="cxd-admin-footer" style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #777;">';
    echo 'Calculadora PxD v' . esc_html( $plugin_version );
    echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'cxd_encolar_script_admin' );
function cxd_encolar_script_admin( $hook ) {
    if ( 'settings_page_costoxd-config' === $hook ) {
        $nonce = wp_create_nonce( 'cxd_recalcular_nonce' );
        $js_path = plugin_dir_path( __FILE__ ) . '../../cxd-batch.js';
        wp_enqueue_script( 'cxd-batch-script', plugin_dir_url( __FILE__ ) . '../../cxd-batch.js', [ 'jquery' ], file_exists($js_path) ? filemtime($js_path) : CXD_VERSION, true );
        wp_localize_script( 'cxd-batch-script', 'cxd_ajax_obj', ['ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => $nonce] );
    }
}

add_action( 'wp_ajax_cxd_get_all_product_ids', 'cxd_get_all_product_ids_ajax' );
function cxd_get_all_product_ids_ajax() {
    check_ajax_referer( 'cxd_recalcular_nonce', '_ajax_nonce' );

    $query_args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ];

    $products_query = new WP_Query( $query_args );

    if ( ! $products_query->have_posts() ) {
        wp_send_json_error(['message' => 'No se encontraron productos para procesar.']);
    }

    wp_send_json_success($products_query->posts);
}


add_action( 'wp_ajax_cxd_procesar_lote', 'cxd_procesar_lote_ajax' );
function cxd_procesar_lote_ajax() {
    check_ajax_referer( 'cxd_recalcular_nonce', '_ajax_nonce' );

    if ( ! isset( $_POST['product_ids'] ) || ! is_array( $_POST['product_ids'] ) ) {
        wp_send_json_error( [ 'message' => 'No se proporcionaron IDs de productos.' ] );
    }

    $product_ids = array_map( 'intval', $_POST['product_ids'] );
    $processed_ids = [];

    foreach ( $product_ids as $product_id ) {
        if ( get_post_type( $product_id ) === 'product' ) {
            cxd_calcular_y_guardar_planes( $product_id );
            $processed_ids[] = $product_id;
        }
    }

    wp_send_json_success( [
        'message'       => 'Lote procesado.',
        'processed_count' => count( $processed_ids ),
    ] );
}

add_action('admin_init', 'cxd_registrar_ajustes');
function cxd_registrar_ajustes() {
    register_setting('cxd_opciones_grupo', 'cxd_opciones', 'cxd_sanitizar_opciones');
    add_settings_section('cxd_seccion_principal', 'Ajustes Generales', null, 'costoxd-config');
    add_settings_field('cxd_descuento', 'Porcentaje Descuento (%)', 'cxd_campo_descuento_cb', 'costoxd-config', 'cxd_seccion_principal');
    add_settings_field('cxd_monto_minimo', 'Monto Mínimo de Cuota', 'cxd_campo_monto_minimo_cb', 'costoxd-config', 'cxd_seccion_principal');
    add_settings_field('cxd_redondeo', 'Redondeo (ej: 100 para redondear a la centena)', 'cxd_campo_redondeo_cb', 'costoxd-config', 'cxd_seccion_principal');
    add_settings_field('cxd_descuento_transferencia', 'Porcentaje Descuento Transferencia (%)', 'cxd_campo_descuento_transferencia_cb', 'costoxd-config', 'cxd_seccion_principal');
    add_settings_section('cxd_seccion_coeficientes', 'Coeficientes de Planes', null, 'costoxd-config');
    $planes = [30, 52, 78, 104, 156, 208];
    foreach ($planes as $dias) {
        add_settings_field("cxd_coef_plan_{$dias}", "Coeficiente Plan {$dias} días", 'cxd_campo_coeficiente_cb', 'costoxd-config', 'cxd_seccion_coeficientes', ['dias' => $dias]);
    }
}

function cxd_campo_descuento_cb() { $opciones = get_option('cxd_opciones'); $valor = isset($opciones['descuento']) ? $opciones['descuento'] : '8'; echo "<input type='number' step='0.1' name='cxd_opciones[descuento]' value='{$valor}' />"; }
function cxd_campo_monto_minimo_cb() { $opciones = get_option('cxd_opciones'); $valor = isset($opciones['monto_minimo']) ? $opciones['monto_minimo'] : '100'; echo "<input type='number' step='1' name='cxd_opciones[monto_minimo]' value='{$valor}' />"; }
function cxd_campo_redondeo_cb() { $opciones = get_option('cxd_opciones'); $valor = isset($opciones['redondeo']) ? $opciones['redondeo'] : '100'; echo "<input type='number' min='1' step='1' name='cxd_opciones[redondeo]' value='{$valor}' /><p class='description'>Múltiplo para redondeo hacia arriba (ej: 100 = $123 → $200)</p>"; }
function cxd_campo_descuento_transferencia_cb() { $opciones = get_option('cxd_opciones'); $valor = isset($opciones['descuento_transferencia']) ? $opciones['descuento_transferencia'] : '10'; echo "<input type='number' step='0.1' name='cxd_opciones[descuento_transferencia]' value='{$valor}' /><p class='description'>Descuento que se muestra en el precio por transferencia en el visor de planes.</p>"; }
function cxd_campo_coeficiente_cb($args) { $opciones = get_option('cxd_opciones'); $dias = $args['dias']; $valor = isset($opciones["coef_plan_{$dias}"]) ? $opciones["coef_plan_{$dias}"] : '1.0'; echo "<input type='number' step='0.01' name='cxd_opciones[coef_plan_{$dias}]' value='{$valor}' />"; }
function cxd_sanitizar_opciones($input) { $output = []; foreach ($input as $key => $value) { if (is_numeric($value)) { $output[$key] = $value; } } return $output; }