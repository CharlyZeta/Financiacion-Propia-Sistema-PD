<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_enqueue_scripts', 'cxd_enqueue_product_edit_scripts' );
function cxd_enqueue_product_edit_scripts($hook) {
    global $post;
    if ( $hook == 'post.php' && isset($post->post_type) && $post->post_type == 'product' ) {
        $nonce = wp_create_nonce('cxd_recalc_preview_nonce');
        $js_path = plugin_dir_path( __FILE__ ) . '../../cxd-recalc-preview.js';
        wp_enqueue_script('cxd-recalc-preview-script', plugin_dir_url(__FILE__) . '../../cxd-recalc-preview.js', ['jquery'], file_exists($js_path) ? filemtime($js_path) : CXD_VERSION, true);
        wp_localize_script('cxd-recalc-preview-script', 'cxd_preview_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $nonce,
            'product_id' => $post->ID,
        ]);
    }
}

add_action('wp_ajax_cxd_get_recalculated_preview', 'cxd_get_recalculated_preview_handler');
function cxd_get_recalculated_preview_handler() {
    check_ajax_referer('cxd_recalc_preview_nonce', 'nonce');

    if ( !isset($_POST['price']) || !is_numeric($_POST['price']) || !isset($_POST['product_id']) || !is_numeric($_POST['product_id']) ) {
        wp_send_json_error(['html' => '<p>Precio o producto inválido.</p>']);
        return;
    }
    
    $price = floatval($_POST['price']);
    $product_id = intval($_POST['product_id']);

    $planes_calculados = cxd_calcular_y_guardar_planes($product_id, [
        'new_price' => $price,
        'do_save'   => false,
    ]);

    $html = '<p>Vista previa de los planes con el nuevo precio. Los cambios se guardarán al actualizar el producto.</p>';
    if (empty($planes_calculados)) {
        $html .= '<p>Con este precio, ningún plan cumple el monto mínimo.</p>';
    } else {
        $html .= '<table class="widefat striped"><thead><tr><th><strong>Plan (días)</strong></th><th><strong>Cuota Diaria</strong></th><th><strong>Monto Total</strong></th></tr></thead><tbody>';
        foreach ($planes_calculados as $dias => $datos) {
            $html .= '<tr><td><strong>' . $dias . ' días</strong></td><td>' . wc_price($datos['cuota_diaria']) . '</td><td>' . wc_price($datos['monto_total']) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    wp_send_json_success(['html' => $html]);
}

add_action('add_meta_boxes', 'cxd_registrar_meta_boxes');
function cxd_registrar_meta_boxes() {
    add_meta_box('costoxd_planes_visualizador', 'Planes de Pago Diario Calculados', 'cxd_renderizar_contenido_meta_box', 'product', 'normal', 'high');
    add_meta_box('cxd_clase_producto_meta_box', 'Clase de Producto', 'cxd_renderizar_clase_producto_meta_box', 'product', 'side', 'default');
}

function cxd_renderizar_clase_producto_meta_box($post) {
    wp_nonce_field('cxd_guardar_clase_producto', 'cxd_clase_producto_nonce');
    $clase_guardada = get_post_meta($post->ID, '_cxd_product_class', true);
    $clases = ['A', 'B', 'C', 'D', 'E', 'F'];

    echo '<label for="cxd_clase_producto_field">Seleccione la clase del producto:</label>';
    echo '<select name="cxd_clase_producto_field" id="cxd_clase_producto_field" class="widefat">';
    echo '<option value="">Ninguna</option>';
    foreach ($clases as $clase) {
        echo '<option value="' . esc_attr($clase) . '"' . selected($clase_guardada, $clase, false) . '>' . esc_html($clase) . '</option>';
    }
    echo '</select>';
}

add_action('woocommerce_process_product_meta', 'cxd_guardar_clase_producto_meta_data');
function cxd_guardar_clase_producto_meta_data($post_id) {
    if (!isset($_POST['cxd_clase_producto_nonce']) || !wp_verify_nonce($_POST['cxd_clase_producto_nonce'], 'cxd_guardar_clase_producto')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['cxd_clase_producto_field'])) {
        $clase_seleccionada = sanitize_text_field($_POST['cxd_clase_producto_field']);
        update_post_meta($post_id, '_cxd_product_class', $clase_seleccionada);
    }
}


function cxd_renderizar_contenido_meta_box($post) {
    echo '<div id="cxd-meta-box-content">';
    $planes = get_post_meta($post->ID, '_costoxd_planes', true);
    if (empty($planes)) { echo '<p>Aún no se han calculado los planes para este producto. Guarda o actualiza el producto para generarlos.</p>'; }
    else {
        echo '<p>Los siguientes planes están guardados actualmente para este producto.</p>';
        echo '<table class="widefat striped"><thead><tr><th><strong>Plan (días)</strong></th><th><strong>Cuota Diaria</strong></th><th><strong>Monto Total</strong></th></tr></thead><tbody>';
        foreach ($planes as $dias => $datos) { echo '<tr><td><strong>' . $dias . ' días</strong></td><td>' . wc_price($datos['cuota_diaria']) . '</td><td>' . wc_price($datos['monto_total']) . '</td></tr>'; }
        echo '</tbody></table>';
    }
    echo '</div>';
}