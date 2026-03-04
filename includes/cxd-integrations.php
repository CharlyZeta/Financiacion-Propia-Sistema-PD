<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Compatibilidad con el editor masivo BEAR (antes WOOBE).
 *
 * Esta función se engancha al hook 'woobe_after_update_page_field', que se ejecuta
 * después de que BEAR actualiza un campo específico de un producto.
 *
 * @param int    $product_id  ID del producto que se está actualizando.
 * @param object $product     El objeto del producto.
 * @param string $field_key   La clave del campo que se ha modificado (ej: '_regular_price').
 * @param mixed  $value       El nuevo valor para el campo.
 * @param object $field_type  El tipo de campo.
 */
add_action( 'woobe_after_update_page_field', 'cxd_handle_woobe_field_update', 10, 5 );

function cxd_handle_woobe_field_update( $product_id, $product, $field_key, $value, $field_type ) {
    // Verificamos si el campo actualizado es uno de los campos de precio.
    if ( $field_key === 'regular_price' || $field_key === 'sale_price' ) {

        // El parámetro $value ya contiene el nuevo precio. No necesitamos leerlo de nuevo.
        // Simplemente llamamos a la función de cálculo principal pasándole el nuevo precio.
        // Esto es más eficiente y evita problemas de sincronización donde el precio en la BD
        // podría no estar actualizado todavía.
        if ( is_numeric( $value ) ) {
            cxd_calcular_y_guardar_planes( $product_id, [ 'new_price' => $value ] );
        }
    }
}

// Integración para campo DNI en WooCommerce

// Agregar campo DNI al checkout
add_action( 'woocommerce_checkout_fields', 'cxd_add_dni_checkout_field' );
function cxd_add_dni_checkout_field( $fields ) {
    $fields['billing']['billing_dni'] = array(
        'type'        => 'text',
        'label'       => __('DNI', 'woocommerce'),
        'placeholder' => __('Ingrese su DNI', 'woocommerce'),
        'required'    => false,
        'class'       => array('form-row-wide'),
        'clear'       => true,
        'priority'    => 1,
    );
    return $fields;
}

// Guardar DNI en user meta
add_action( 'woocommerce_checkout_update_order_meta', 'cxd_save_dni_checkout_field' );
function cxd_save_dni_checkout_field( $order_id ) {
    if ( ! empty( $_POST['billing_dni'] ) ) {
        $dni = sanitize_text_field( $_POST['billing_dni'] );
        update_post_meta( $order_id, '_billing_dni', $dni );

        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        if ( $user_id ) {
            update_user_meta( $user_id, 'billing_dni', $dni );
        }
    }
}

// Mostrar campo DNI en perfil de usuario
add_action( 'woocommerce_edit_account_form', 'cxd_add_dni_to_account_form' );
function cxd_add_dni_to_account_form() {
    $user_id = get_current_user_id();
    $dni = get_user_meta( $user_id, 'billing_dni', true );

    woocommerce_form_field( 'billing_dni', array(
        'type'        => 'text',
        'label'       => __('DNI', 'woocommerce'),
        'placeholder' => __('Ingrese su DNI', 'woocommerce'),
        'required'    => false,
        'class'       => array('form-row-wide'),
        'clear'       => true,
    ), $dni );
}

// Guardar DNI desde perfil de usuario
add_action( 'woocommerce_save_account_details', 'cxd_save_dni_account_details' );
function cxd_save_dni_account_details( $user_id ) {
    if ( isset( $_POST['billing_dni'] ) ) {
        $dni = sanitize_text_field( $_POST['billing_dni'] );
        update_user_meta( $user_id, 'billing_dni', $dni );
    }
}

// Migración de DNI existentes (asumiendo que están en '_dni')
function cxd_migrate_existing_dni() {
    $users = get_users( array( 'role' => 'customer', 'number' => -1 ) );
    foreach ( $users as $user ) {
        $existing_dni = get_user_meta( $user->ID, '_dni', true ); // Cambiar '_dni' si es otro meta
        if ( ! empty( $existing_dni ) && empty( get_user_meta( $user->ID, 'billing_dni', true ) ) ) {
            update_user_meta( $user->ID, 'billing_dni', $existing_dni );
        }
    }
}

// Mostrar campo DNI en admin de edición de usuarios
add_action( 'show_user_profile', 'cxd_add_dni_admin_field' );
add_action( 'edit_user_profile', 'cxd_add_dni_admin_field' );

function cxd_add_dni_admin_field( $user ) {
    $dni = get_user_meta( $user->ID, 'billing_dni', true );
    ?>
    <h3>DNI</h3>
    <table class="form-table">
        <tr>
            <th><label for="billing_dni">DNI</label></th>
            <td>
                <input type="text" name="billing_dni" id="billing_dni" value="<?php echo esc_attr( $dni ); ?>" class="regular-text" />
            </td>
        </tr>
    </table>
    <?php
}

// Guardar campo DNI desde admin
add_action( 'personal_options_update', 'cxd_save_dni_admin_field' );
add_action( 'edit_user_profile_update', 'cxd_save_dni_admin_field' );

function cxd_save_dni_admin_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    if ( isset( $_POST['billing_dni'] ) ) {
        $dni = sanitize_text_field( $_POST['billing_dni'] );
        update_user_meta( $user_id, 'billing_dni', $dni );
    }
}

// Ejecutar migración en activación
register_activation_hook( __FILE__, 'cxd_migrate_existing_dni' );
