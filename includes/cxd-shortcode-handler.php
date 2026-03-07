<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Encola los assets del visor de planes condicionalmente.
 */
add_action( 'wp_enqueue_scripts', 'cxd_enqueue_visor_assets_conditionally' );
function cxd_enqueue_visor_assets_conditionally() {
    global $post;

    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'visor_planes_pxd' ) ) {
        // FIX: Usar filemtime para un cache busting infalible.
        $css_path = plugin_dir_path( __FILE__ ) . '../assets/css/cxd-frontend-visor.css';
        $js_path = plugin_dir_path( __FILE__ ) . '../assets/js/cxd-frontend-visor.js';

        wp_enqueue_style(
            'cxd-visor-style',
            plugin_dir_url( __FILE__ ) . '../assets/css/cxd-frontend-visor.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : CXD_VERSION
        );

        wp_enqueue_script(
            'cxd-visor-script',
            plugin_dir_url( __FILE__ ) . '../assets/js/cxd-frontend-visor.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : CXD_VERSION,
            true
        );

        wp_enqueue_style('swiper-css', 'https://unpkg.com/swiper/swiper-bundle.min.css');
        wp_enqueue_script('swiper-js', 'https://unpkg.com/swiper/swiper-bundle.min.js', [], null, true);
    }
}

/**
 * Manejador del shortcode [visor_planes_pxd].
 */
add_shortcode( 'visor_planes_pxd', 'cxd_shortcode_visor_planes_handler' );
function cxd_shortcode_visor_planes_handler( $atts ) {
    if ( is_admin() || (! current_user_can( 'manage_options' ) && post_password_required()) ) {
        return '';
    }

    ob_start();

    $shortcode_page_url = get_permalink( get_the_ID() );

    // Forzar la actualización de la caché de opciones para depuración.
    wp_cache_delete( 'cxd_nota_venta_options', 'options' );
    $nota_venta_options = get_option('cxd_nota_venta_options');
    $url_nota_venta = isset($nota_venta_options['url_nota_venta']) ? $nota_venta_options['url_nota_venta'] : '';

    $search_term = isset( $_GET['s_pxd'] ) ? sanitize_text_field( $_GET['s_pxd'] ) : '';
    $category_slug = isset( $_GET['cat_pxd'] ) ? sanitize_text_field( $_GET['cat_pxd'] ) : '';
    $paged = isset( $_GET['paged_pxd'] ) ? max( 1, intval( $_GET['paged_pxd'] ) ) : 1;
    $stock_status = isset( $_GET['stock_pxd'] ) ? 1 : 0;
    $orderby_val = isset( $_GET['orderby_pxd'] ) ? sanitize_text_field( $_GET['orderby_pxd'] ) : 'date_desc';

    if (!isset($_GET['stock_pxd']) && !isset($_GET['s_pxd']) && !isset($_GET['cat_pxd'])) {
        $stock_status = 1;
    }

    $cxd_opciones = get_option('cxd_opciones');
    $descuento_transferencia = isset($cxd_opciones['descuento_transferencia']) ? floatval($cxd_opciones['descuento_transferencia']) : 10;

    $posts_per_page = 12;
    $offset = ( $paged - 1 ) * $posts_per_page;

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => $posts_per_page,
        'offset'         => $offset,
        'post_status'    => 'publish',
        's'              => $search_term,
        'tax_query'      => [],
        'meta_query'     => [],
    ];

    switch ( $orderby_val ) {
        case 'price_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'ASC';
            break;
        case 'price_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'DESC';
            break;
        case 'name_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'name_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        case 'date_desc':
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    if ( ! empty( $category_slug ) ) {
        $args['tax_query'][] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $category_slug,
        ];
    }

    if ( $stock_status ) {
        $args['meta_query'][] = [
            'key'   => '_stock_status',
            'value' => 'instock',
        ];
    }

    $products_query = new WP_Query( $args );

    $total_results = $products_query->found_posts;
    $results_on_page = $products_query->post_count;
    ?>
    <div class="cxd-visor-frontend">
        <div class="cxd-filters-wrapper">
             <form method="get" action="" class="cxd-visor-filters">
                <input type="text" name="s_pxd" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Buscar productos...">
                <?php wp_dropdown_categories( [ 'show_option_all' => 'Todas las categorías', 'taxonomy' => 'product_cat', 'name' => 'cat_pxd', 'class' => '', 'selected' => $category_slug, 'value_field' => 'slug', 'hierarchical' => true ] ); ?>
                <select name="orderby_pxd" class="cxd-orderby-filter">
                    <option value="date_desc" <?php selected( $orderby_val, 'date_desc' ); ?>>Más recientes</option>
                    <option value="price_asc" <?php selected( $orderby_val, 'price_asc' ); ?>>Precio: de menor a mayor</option>
                    <option value="price_desc" <?php selected( $orderby_val, 'price_desc' ); ?>>Precio: de mayor a menor</option>
                    <option value="name_asc" <?php selected( $orderby_val, 'name_asc' ); ?>>Nombre: de A a Z</option>
                    <option value="name_desc" <?php selected( $orderby_val, 'name_desc' ); ?>>Nombre: de Z a A</option>
                </select>
                <label class="cxd-stock-filter">
                    <input type="checkbox" name="stock_pxd" value="1" <?php checked( $stock_status, 1 ); ?>>
                    Productos con stock
                </label>
                <input type="submit" value="Filtrar" class="button">
                <a href="<?php echo esc_url( $shortcode_page_url ); ?>" class="button button-secondary">Limpiar</a>
            </form>
            <div class="cxd-product-count">
                <?php printf( 'Mostrando %d de %d productos', $results_on_page, $total_results ); ?>
            </div>
        </div>

        <?php if ( $products_query->have_posts() ) : ?>
            <div class="cxd-product-grid">
                <?php while ( $products_query->have_posts() ) : $products_query->the_post(); ?>
                    <?php
                        $product = wc_get_product( get_the_ID() );
                        if( ! $product ) continue;
                        $planes = get_post_meta( get_the_ID(), '_costoxd_planes', true );
                        
                        $whatsapp_text = "*" . html_entity_decode(strip_tags($product->get_name())) . "*\n\n";
                        $image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                        if ($image_url) { $whatsapp_text .= "Imagen: " . $image_url . "\n\n"; }
                        $precio_base = floatval( $product->get_price() );
                        $precio_venta_formateado = wc_price( $precio_base, ['decimals' => 0] );
                        $precio_venta_limpio = html_entity_decode(strip_tags($precio_venta_formateado));
                        $whatsapp_text .= "*Precio:* " . $precio_venta_limpio . "\n";
                        
                        $precio_transferencia = 0;
                        if ($descuento_transferencia > 0) {
                            $precio_transferencia = $precio_base * (1 - (floatval($descuento_transferencia) / 100));
                            $precio_transferencia_formateado = wc_price($precio_transferencia, ['decimals' => 0]);
                            $precio_transferencia_limpio = html_entity_decode(strip_tags($precio_transferencia_formateado));
                            $whatsapp_text .= "*Transferencia:* " . $precio_transferencia_limpio . "\n";
                        }
                        if (!empty($planes)) { 
                            $whatsapp_text .= "\n*Planes de Pago por Día:*\n"; 
                            foreach ($planes as $dias => $datos) { 
                                $cuota_formateada = wc_price($datos['cuota_diaria'], ['decimals' => 0]);
                                $cuota_limpia = html_entity_decode(strip_tags($cuota_formateada));
                                $whatsapp_text .= "• " . $dias . " días: " . $cuota_limpia . "\n"; 
                            } 
                        }
                        
                        $whatsapp_text_encoded = rawurlencode($whatsapp_text);
                        
                        $stock_status_product = $product->get_stock_status(); $stock_quantity = $product->get_stock_quantity();
                        if ($stock_status_product === 'instock') { $stock_html = '<span style="color:green;">En stock' . ($stock_quantity ? " ({$stock_quantity})" : '') . '</span>'; } else { $stock_html = '<span style="color:red;">Agotado</span>'; }

                        $main_image_id = $product->get_image_id();
                        $gallery_image_ids = $product->get_gallery_image_ids();
                        $all_image_ids = array_merge( [ $main_image_id ], $gallery_image_ids );
                        $all_image_ids = array_filter( array_unique( $all_image_ids ) );

                    ?>
                    <div class="cxd-product-card">
                        <div class="cxd-card-image">
                            <div class="swiper-container cxd-product-slider">
                                <div class="swiper-wrapper">
                                    <?php if ( ! empty( $all_image_ids ) ) : ?>
                                        <?php foreach ( $all_image_ids as $image_id ) : ?>
                                            <div class="swiper-slide">
                                                <?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail' ); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <div class="swiper-slide">
                                            <?php echo wc_placeholder_img( 'woocommerce_thumbnail' ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="swiper-pagination"></div>
                                <div class="swiper-button-next"></div>
                                <div class="swiper-button-prev"></div>
                            </div>
                        </div>
                        <div class="cxd-card-content">
                            <h3 class="cxd-card-title"><?php echo $product->get_name(); ?></h3>
                            <div class="cxd-card-details">
                                <div class="cxd-card-price">
                                    Precio: <span><?php echo wc_price($precio_base, ['decimals' => 0]); ?></span>
                                    <?php if ($descuento_transferencia > 0 && $precio_transferencia > 0) : ?>
                                        <span class="cxd-transfer-price">Transferencia: <span><?php echo wc_price($precio_transferencia, ['decimals' => 0]); ?></span></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cxd-card-stock"><?php echo $stock_html; ?></div>
                            </div>
                            <div class="cxd-card-plans">
                                <h4>Planes de Pago por Día</h4>
                                <?php if ( ! empty( $planes ) ) : ?>
                                    <ul><?php foreach ( $planes as $dias => $datos ) : ?><li><strong><?php echo esc_html( $dias ); ?> días:</strong> <span><?php echo wc_price( $datos['cuota_diaria'], ['decimals' => 0] ); ?></span></li><?php endforeach; ?></ul>
                                <?php else:
                                    ?><p style="font-size: 0.9em; color: #777;">No hay planes disponibles.</p>
                                <?php endif; ?>
                            </div>
                            <div class="cxd-card-actions">
                                <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="cxd-action-ver" title="Ver ficha del producto" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 7.61 17 4.5 12 4.5zm0 10c-2.48 0-4.5-2.02-4.5-4.5S9.52 5.5 12 5.5s4.5 2.02 4.5 4.5-2.02 4.5-4.5 4.5zm0-7c-1.38 0-2.5 1.12-2.5 2.5S10.62 12 12 12s2.5-1.12 2.5-2.5S13.38 7.5 12 7.5z"/></svg>
                                    Ver
                                </a>
                                <button type="button" class="cxd-action-detalles" title="Ver detalles del producto">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/></svg>
                                    Detalles
                                </button>
                                <a class="cxd-action-whatsapp" title="Compartir por WhatsApp" data-text="<?php echo esc_attr($whatsapp_text_encoded); ?>" onclick="this.href='https://api.whatsapp.com/send?text=' + this.getAttribute('data-text');" target="_blank">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="white"><path d="M35.2,12.8c-3-3-6.9-4.6-11.2-4.6C15.3,8.2,8.2,15.3,8.2,24c0,3,0.8,5.9,2.4,8.4L11,33l-1.6,5.8 l6-1.6l0.6,0.3c2.4,1.4,5.2,2.2,8,2.2h0c8.7,0,15.8-7.1,15.8-15.8C39.8,19.8,38.2,15.8,35.2,12.8z M24,38.8L24,38.8 c-2.7,0-5.3-0.7-7.6-2.1l-0.5-0.3l-5.6,1.5l1.5-5.5l-0.3-0.6C10,29.2,9.2,26.7,9.2,24c0-7.6,6.2-13.8,13.8-13.8 c3.8,0,7.3,1.5,9.8,4.1c2.5,2.5,4.1,6,4.1,9.8C37.8,32.6,31.6,38.8,24,38.8z"/><path fill="white" d="M19.3,16c-0.4-0.8-0.7-0.8-1.1-0.8c-0.3,0-0.6,0-0.9,0 s-0.8,0.1-1.3,0.6c-0.4,0.5-1.7,1.6-1.7,4s1.7,4.6,1.9,4.9s3.3,5.3,8.1,7.2c4,1.6,4.8,1.3,5.7,1.2c0.9-0.1,2.8-1.1,3.2-2.3 c0.4-1.1,0.4-2.1,0.3-2.3c-0.1-0.2-0.4-0.3-0.9-0.6s-2.8-1.4-3.2-1.5c-0.4-0.2-0.8-0.2-1.1,0.2c-0.3,0.5-1.2,1.5-1.5,1.9 c-0.3,0.3-0.6,0.4-1,0.1c-0.5-0.2-2-0.7-3.8-2.4c-1.4-1.3-2.4-2.8-2.6-3.3c-0.3-0.5,0-0.7,0.2-1c0.2-0.2,0.5-0.6,0.7-0.8 c0.2-0.3,0.3-0.5,0.5-0.8c0.2-0.3,0.1-0.6,0-0.8C20.6,19.3,19.7,17,19.3,16z"/></svg>
                                    Compartir
                                </a>
                                <?php if ( ! empty( $url_nota_venta ) ) : ?>
                                    <a href="<?php echo esc_url( add_query_arg( 'agregar-producto', $product->get_id(), $url_nota_venta ) ); ?>" class="cxd-action-add-to-note" title="Añadir a Nota de Venta" target="_blank">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                                        Añadir a Nota
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="cxd-product-description-hidden" style="display: none;">
                                <?php
                                    $description = $product->get_description();
                                    if ( empty( $description ) ) {
                                        $description = $product->get_short_description();
                                    }
                                    echo apply_filters( 'the_content', $description );
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php
            $total_pages = ceil( $total_results / $posts_per_page );

            $pagination_args = [
                'base'      => add_query_arg( 'paged_pxd', '%#%', $shortcode_page_url ),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '«',
                'next_text' => '»',
                'add_args'  => array_filter( [ 
                    's_pxd'       => $search_term,
                    'cat_pxd'     => $category_slug,
                    'stock_pxd'   => $stock_status ? '1' : null,
                    'orderby_pxd' => $orderby_val,
                ] ),
            ];
            echo '<div class="cxd-pagination">' . paginate_links( $pagination_args ) . '</div>';
            ?>
        <?php else : ?>
            <p>No se encontraron productos que coincidan con tus criterios.</p>
        <?php endif; ?>

        <div id="cxd-modal-detalles" class="cxd-modal-overlay" style="display: none;">
            <div class="cxd-modal-container">
                <div class="cxd-modal-header">
                    <h3 id="cxd-modal-title">Detalles del Producto</h3>
                    <button id="cxd-modal-close" class="cxd-modal-close-btn">&times;</button>
                </div>
                <div id="cxd-modal-content" class="cxd-modal-body">
                </div>
            </div>
        </div>

        <div class="cxd-admin-footer" style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #777;">
            Calculadora PxD v<?php echo esc_html( CXD_VERSION ); ?>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
