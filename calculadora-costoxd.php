<?php
/**
 * Plugin Name:       Calculadora de Costos y Planes por Día
 * Plugin URI:        https://github.com/gmaidana/
 * Description:       Calcula planes de pago por día para productos de WooCommerce y permite generar notas de venta.
 * Version:           2.9.9
 * Author:            Gerardo Maidana
 * Author URI:        https://github.com/gmaidana/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       calculadora-costoxd
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CXD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CXD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CXD_VERSION', '2.9.9' );

require_once CXD_PLUGIN_PATH . 'includes/cxd-core-functions.php';
require_once CXD_PLUGIN_PATH . 'includes/cxd-shortcode-handler.php';
require_once CXD_PLUGIN_PATH . 'includes/cxd-integrations.php';

// Crear rol Vendedor de Pago Diario si no existe
if ( ! get_role( 'vendedor_pago_diario' ) ) {
    add_role(
        'vendedor_pago_diario',
        'Vendedor de Pago Diario',
        array(
            'read' => true,
        )
    );
}

if ( is_admin() ) {
    require_once CXD_PLUGIN_PATH . 'includes/admin/class-cxd-planes-list-table.php';
    require_once CXD_PLUGIN_PATH . 'includes/admin/cxd-admin-product-meta.php';
    require_once CXD_PLUGIN_PATH . 'includes/admin/cxd-admin-settings.php';
    require_once CXD_PLUGIN_PATH . 'includes/admin/cxd-admin-nota-venta-settings.php';
}

require_once CXD_PLUGIN_PATH . 'includes/nota-venta/class-cxd-nota-venta-shortcode.php';

new CXD_Nota_Venta_Shortcode();

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Limpia todas las cachés conocidas.
 * Esta función es llamada en la activación y, más importante, en la actualización del plugin.
 */
function cxd_purge_all_caches() {
    // WP Rocket
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    }

    // W3 Total Cache
    if ( function_exists( 'w3tc_pgcache_flush' ) ) {
        w3tc_pgcache_flush();
    }

    // LiteSpeed Cache
    if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
        LiteSpeed_Cache_API::purge_all();
    }

    // WP Super Cache
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        wp_cache_clear_cache();
    }
    
    // SG Optimizer (SiteGround)
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
        sg_cachepress_purge_cache();
    }

    // WordPress Object Cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
}

/**
 * Hook que se ejecuta al completar una actualización.
 */
add_action( 'upgrader_process_complete', 'cxd_on_plugin_update', 10, 2 );
function cxd_on_plugin_update( $upgrader_object, $options ) {
    $current_plugin_path = plugin_basename( __FILE__ );

    if ( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins' ] ) ) {
        foreach ( $options['plugins'] as $plugin_path ) {
            if ( $plugin_path == $current_plugin_path ) {
                cxd_purge_all_caches();
            }
        }
    }
}

// También lo ejecutamos en la activación por si es la primera vez.
register_activation_hook( __FILE__, 'cxd_purge_all_caches' );

function cxd_plugin_activate() {
    // Crear rol Vendedor de Pago Diario
    add_role(
        'vendedor_pago_diario',
        'Vendedor de Pago Diario',
        array(
            'read' => true,
        )
    );
}
register_activation_hook( __FILE__, 'cxd_plugin_activate' );

function cxd_plugin_deactivate() {}
register_deactivation_hook( __FILE__, 'cxd_plugin_deactivate' );

/**
 * Agrega un enlace de "Configuración" en la página de listado de plugins.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cxd_add_plugin_action_links' );
function cxd_add_plugin_action_links( $links ) {
    $settings_link = '<a href="admin.php?page=costoxd-config">' . __( 'Configuración', 'calculadora-costoxd' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

function cxd_handle_normal_save( $post_id ) {
    $price_to_use = null;
    if ( isset( $_POST['_sale_price'] ) && $_POST['_sale_price'] !== '' ) {
        $price_to_use = floatval( wc_clean( wp_unslash( $_POST['_sale_price'] ) ) );
    } elseif ( isset( $_POST['_regular_price'] ) && $_POST['_regular_price'] !== '' ) {
        $price_to_use = floatval( wc_clean( wp_unslash( $_POST['_regular_price'] ) ) );
    }
    cxd_calcular_y_guardar_planes( $post_id, ['new_price' => $price_to_use] );
}
add_action( 'woocommerce_process_product_meta', 'cxd_handle_normal_save', 10, 1 );

function cxd_handle_quick_edit_save( $product ) {
    if ( $product && is_a( $product, 'WC_Product' ) ) {
        cxd_calcular_y_guardar_planes( $product->get_id() );
    }
}
add_action( 'woocommerce_product_quick_edit_save', 'cxd_handle_quick_edit_save', 10, 1 );
