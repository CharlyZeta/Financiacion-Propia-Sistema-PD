<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function cxd_calcular_y_guardar_planes($product_id, $args = []) {
    $default_args = [
        'new_price' => null,
        'do_save'   => true,
    ];
    $args = wp_parse_args($args, $default_args);

    $producto = wc_get_product($product_id);
    if (!$producto) return [];

    $precio_producto = ($args['new_price'] !== null && is_numeric($args['new_price'])) ? floatval($args['new_price']) : $producto->get_price();
    
    $planes_dias = [30, 52, 78, 104, 156, 208];

    if (empty($precio_producto) || !is_numeric($precio_producto)) {
        if ($args['do_save']) {
            delete_post_meta($product_id, '_costoxd_planes');
            delete_post_meta($product_id, '_costoxd_base');
            // Al borrar los planes, también limpiamos la fecha de modificación.
            delete_post_meta($product_id, 'fecha_ultima_modificacion');
            foreach ($planes_dias as $dias) {
                delete_post_meta($product_id, '_cxd_p' . $dias);
            }
        }
        return [];
    }

    $opciones = get_option('cxd_opciones');
    $porcentaje_descuento = isset($opciones['descuento']) ? floatval($opciones['descuento']) : 8.0;
    $monto_minimo = isset($opciones['monto_minimo']) ? floatval($opciones['monto_minimo']) : 100.0;
    $base_redondeo = isset($opciones['redondeo']) ? max(1, floatval($opciones['redondeo'])) : 1;
    $costo_base = $precio_producto * (1 - ($porcentaje_descuento / 100));
    
    if ($args['do_save']) {
        update_post_meta($product_id, '_costoxd_base', $costo_base);
        // Guardar la fecha y hora de la modificación del precio.
        update_post_meta($product_id, 'fecha_ultima_modificacion', current_time('mysql'));
    }
    
    $planes_calculados = [];

    foreach ($planes_dias as $dias) {
        $coeficiente = isset($opciones["coef_plan_{$dias}"]) ? floatval($opciones["coef_plan_{$dias}"]) : 1.0;
        $cuota_diaria_redondeada = ceil(($costo_base * $coeficiente / $dias) / $base_redondeo) * $base_redondeo;

        if ($cuota_diaria_redondeada >= $monto_minimo) {
            $planes_calculados[$dias] = [
                'cuota_diaria' => number_format($cuota_diaria_redondeada, 2, '.', ''), 
                'monto_total' => $cuota_diaria_redondeada * $dias
            ];
            if ($args['do_save']) {
                update_post_meta($product_id, '_cxd_p' . $dias, number_format($cuota_diaria_redondeada, 2, '.', ''));
            }
        } else {
            if ($args['do_save']) {
                delete_post_meta($product_id, '_cxd_p' . $dias);
            }
        }
    }

    if ($args['do_save']) {
        if (!empty($planes_calculados)) { 
            update_post_meta($product_id, '_costoxd_planes', $planes_calculados); 
        } else { 
            delete_post_meta($product_id, '_costoxd_planes'); 
        }
    }

    return $planes_calculados;
}

/**
 * Devuelve un array con las provincias de Argentina.
 *
 * @return array Lista de provincias argentinas.
 */
function cxd_get_provincias_argentinas() {
    return [
        'Buenos Aires',
        'Catamarca',
        'Chaco',
        'Chubut',
        'Ciudad Autónoma de Buenos Aires',
        'Córdoba',
        'Corrientes',
        'Entre Ríos',
        'Formosa',
        'Jujuy',
        'La Pampa',
        'La Rioja',
        'Mendoza',
        'Misiones',
        'Neuquén',
        'Río Negro',
        'Salta',
        'San Juan',
        'San Luis',
        'Santa Cruz',
        'Santa Fe',
        'Santiago del Estero',
        'Tierra del Fuego',
        'Tucumán',
    ];
}

/**
 * Valida y formatea un número de teléfono argentino para WhatsApp.
 *
 * @param string $phone El número de teléfono a validar.
 * @param string $message El mensaje a enviar.
 * @return string|false El enlace de WhatsApp formateado o false si el número no es válido.
 */
function cxd_get_whatsapp_link($phone, $message = '') {
    // 1. Eliminar todos los caracteres que no sean dígitos.
    $cleaned = preg_replace('/\D/', '', $phone);

    // 2. Extraer los últimos 10 dígitos, que corresponden al código de área + número.
    // Esto elimina prefijos como 0, 15, 54, +54, 549, etc.
    if (strlen($cleaned) > 10) {
        $ten_digits = substr($cleaned, -10);
    } elseif (strlen($cleaned) === 10) {
        $ten_digits = $cleaned;
    } else {
        // Si no tenemos al menos 10 dígitos, no podemos construir un número válido.
        return false;
    }

    // 3. Construir el número final en el formato internacional de WhatsApp para Argentina: 549 + 10 dígitos.
    $final_number = '549' . $ten_digits;
    
    // 4. Construir y devolver el enlace final.
    $url = 'whatsapp://send?phone=' . $final_number;
    if (!empty($message)) {
        $url .= '&text=' . rawurlencode($message);
    }
    return $url;
}


/**
 * Añade la columna 'Últ. Mod. Precio' a la lista de productos de WooCommerce.
 */
add_filter( 'manage_edit-product_columns', 'cxd_agregar_columna_modificacion_precio' );
function cxd_agregar_columna_modificacion_precio( $columns ) {
    // Insertar la nueva columna después de la columna 'price'
    $new_columns = [];
    foreach ( $columns as $key => $value ) {
        $new_columns[$key] = $value;
        if ( $key === 'price' ) {
            $new_columns['fecha_ultima_modificacion'] = 'Últ. Mod. Precio';
        }
    }
    return $new_columns;
}

/**
 * Muestra el contenido de la columna 'Últ. Mod. Precio'.
 */
add_action( 'manage_product_posts_custom_column', 'cxd_mostrar_columna_modificacion_precio', 10, 2 );
function cxd_mostrar_columna_modificacion_precio( $column, $post_id ) {
    if ( $column === 'fecha_ultima_modificacion' ) {
        $fecha = get_post_meta( $post_id, 'fecha_ultima_modificacion', true );
        if ( ! empty( $fecha ) ) {
            // Formatear la fecha para que sea más legible
            $fecha_formateada = date_i18n( 'd/m/Y H:i', strtotime( $fecha ) );
            echo esc_html( $fecha_formateada );
        } else {
            echo '—';
        }
    }
}