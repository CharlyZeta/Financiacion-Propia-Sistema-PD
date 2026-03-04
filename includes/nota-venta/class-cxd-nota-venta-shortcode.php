<?php

if ( ! defined( 'ABSPATH' ) ) exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class CXD_Nota_Venta_Shortcode {

    public function __construct() {
        add_shortcode( 'cxd_nota_venta', [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_cxd_procesar_nota_venta', [ $this, 'procesar_nota_venta_ajax' ] );
        add_action( 'wp_ajax_cxd_buscar_clientes', [ $this, 'buscar_clientes_ajax' ] );
    }

    private function get_products_for_nota_venta() {
        $products_query = new WC_Product_Query( [
            'limit' => -1,
            'status' => 'publish',
            'stock_status' => 'instock',
            'orderby' => 'name',
            'order' => 'ASC',
        ] );
        $all_products = $products_query->get_products();
        $products_data = [];

        foreach ( $all_products as $product ) {
            if ($product->get_stock_quantity() > 0) {
                $planes = get_post_meta( $product->get_id(), '_costoxd_planes', true );
                $products_data[] = [
                    'id'     => $product->get_id(),
                    'name'   => $product->get_name(),
                    'stock'  => $product->get_stock_quantity(),
                    'price'  => $product->get_price(),
                    'class'  => get_post_meta( $product->get_id(), '_cxd_product_class', true ),
                    'planes' => !empty($planes) ? $planes : []
                ];
            }
        }
        return $products_data;
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cxd_nota_venta' ) ) {
            
            wp_enqueue_script( 'tailwind-css', 'https://cdn.tailwindcss.com', [], null, true );
            wp_enqueue_script( 'signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', [], null, true );
            wp_enqueue_style( 'google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null );

            // FIX: Usar filemtime para un cache busting infalible.
            $css_path = plugin_dir_path( __FILE__ ) . '../../assets/css/cxd-nota-venta.css';
            $js_path = plugin_dir_path( __FILE__ ) . '../../assets/js/cxd-nota-venta.js';

            wp_enqueue_style('cxd-nota-venta-style', plugin_dir_url( __FILE__ ) . '../../assets/css/cxd-nota-venta.css', [], file_exists($css_path) ? filemtime($css_path) : CXD_VERSION);
            wp_enqueue_script('cxd-nota-venta-script', plugin_dir_url( __FILE__ ) . '../../assets/js/cxd-nota-venta.js', [ 'jquery', 'signature-pad' ], file_exists($js_path) ? filemtime($js_path) : CXD_VERSION, true);

            $products_for_nota_venta = $this->get_products_for_nota_venta();

            // Forzar la actualización de la caché de opciones para depuración.
            wp_cache_delete( 'cxd_nota_venta_options', 'options' );
            $options = get_option( 'cxd_nota_venta_options' );
            $recargos_str = isset( $options['recargos_porcentuales'] ) ? $options['recargos_porcentuales'] : '';
            $recargos = !empty($recargos_str) ? array_map( 'floatval', explode( ',', $recargos_str ) ) : [];

            $preloaded_product_id = isset($_GET['agregar-producto']) ? absint($_GET['agregar-producto']) : 0;

            wp_localize_script('cxd-nota-venta-script', 'cxd_nota_venta_data', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'cxd_nota_venta_nonce' ),
                'products' => $products_for_nota_venta,
                'surcharges' => $recargos,
                'preloaded_product_id' => $preloaded_product_id,
            ]);
        }
    }

    public function procesar_nota_venta_ajax() {
        check_ajax_referer('cxd_nota_venta_nonce', 'nonce');

        $errors = [];
        $form_data = [];

        $form_data['sr_a'] = isset($_POST['sr_a']) ? sanitize_text_field($_POST['sr_a']) : '';
        if (empty(trim($form_data['sr_a']))) { $errors[] = 'El Apellido y nombres del cliente es obligatorio.'; } 
        
        $form_data['dni'] = isset($_POST['dni']) ? sanitize_text_field($_POST['dni']) : '';
        if (empty(trim($form_data['dni']))) { $errors[] = 'El DNI es obligatorio.'; }

        $form_data['domicilio'] = isset($_POST['domicilio']) ? sanitize_text_field($_POST['domicilio']) : '';
        $form_data['localidad'] = isset($_POST['localidad']) ? sanitize_text_field($_POST['localidad']) : '';
        $form_data['provincia'] = isset($_POST['provincia']) ? sanitize_text_field($_POST['provincia']) : '';
        $form_data['postcode'] = isset($_POST['postcode']) ? sanitize_text_field($_POST['postcode']) : '';
        $form_data['entrega_inmediata'] = isset($_POST['entrega_inmediata']) && $_POST['entrega_inmediata'] === 'on';
        $form_data['fecha_entrega'] = isset($_POST['fecha_entrega']) ? sanitize_text_field($_POST['fecha_entrega']) : '';
        $form_data['telefono'] = isset($_POST['telefono']) ? sanitize_text_field($_POST['telefono']) : '';
        $form_data['email'] = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $form_data['notas'] = isset($_POST['notas']) ? sanitize_textarea_field($_POST['notas']) : '';
        $form_data['fecha'] = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : date('Y-m-d');
        $form_data['whatsapp_check'] = isset($_POST['whatsapp_check']) && $_POST['whatsapp_check'] === 'on';

        $form_data['firma_base64'] = isset($_POST['firma_base64']) ? $_POST['firma_base64'] : '';
        if (empty($form_data['firma_base64'])) { $errors[] = 'La firma del cliente es obligatoria.'; }

        $form_data['productos'] = [];
        $productos_ids = isset($_POST['producto_id']) ? (array)$_POST['producto_id'] : [];
        if (empty($productos_ids) || !isset($productos_ids[0]) || empty(trim($productos_ids[0]))) {
            $errors[] = 'Debe añadir al menos un producto.';
        } else {
            foreach ($productos_ids as $index => $product_id) {
                $product_id = absint($product_id);
                $product = wc_get_product($product_id);
                if ($product) {
                    $cantidad = isset($_POST['producto_cantidad'][$index]) ? absint($_POST['producto_cantidad'][$index]) : 1;
                    $stock = $product->get_stock_quantity();

                    if ($stock !== null && $cantidad > $stock) {
                        $errors[] = 'La cantidad para el producto "' . $product->get_name() . '" excede el stock disponible (' . $stock . ').';
                    }

                    $clase_producto = isset($_POST['producto_clase'][$index]) ? sanitize_text_field($_POST['producto_clase'][$index]) : '';
                    if (empty($clase_producto)) {
                        $errors[] = 'Debe seleccionar una Clase para el producto "' . $product->get_name() . '".';
                    }

                    $forma_pago = isset($_POST['forma_pago'][$index]) ? sanitize_text_field($_POST['forma_pago'][$index]) : 'contado';
                    $plan_id = '';
                    $surcharge = 0;
                    $final_price = floatval($product->get_price()) * $cantidad;
                    $new_daily_rate = 0;

                    if ($forma_pago === 'diario') {
                        $plan_id = isset($_POST['producto_plan'][$index]) ? sanitize_text_field($_POST['producto_plan'][$index]) : '';
                        if (empty($plan_id)) {
                            $errors[] = 'Debe seleccionar un Plan de Pago para el producto "' . $product->get_name() . '" si elige pago diario.';
                        }
                        $surcharge = isset($_POST['producto_surcharge_percent'][$index]) ? floatval($_POST['producto_surcharge_percent'][$index]) : 0;
                        $final_price = isset($_POST['producto_final_price'][$index]) ? floatval($_POST['producto_final_price'][$index]) : 0;
                        $new_daily_rate = isset($_POST['producto_new_daily_rate'][$index]) ? floatval($_POST['producto_new_daily_rate'][$index]) : 0;
                    }
                    
                    $form_data['productos'][] = [
                        'cantidad' => $cantidad, 
                        'nombre' => $product->get_name(), 
                        'clase' => $clase_producto,
                        'forma_pago' => ucfirst($forma_pago),
                        'plan_id' => $plan_id, 
                        'surcharge' => $surcharge,
                        'final_price' => $final_price,
                        'new_daily_rate' => $new_daily_rate
                    ];
                }
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(['message' => implode("\n", $errors)]);
        }

        $pdf_path = '';
        try {
            require_once CXD_PLUGIN_PATH . 'lib/autoload.inc.php';
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);

            ob_start();
            $data = $form_data;
            include CXD_PLUGIN_PATH . 'includes/nota-venta/template-pdf-nota-venta.php';
            $html = ob_get_clean();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $upload_dir = wp_upload_dir();
            $pdf_filename = 'nota-venta-' . time() . '-' . wp_generate_password(5, false) . '.pdf';
            $pdf_path = $upload_dir['path'] . '/' . $pdf_filename;
            
            file_put_contents($pdf_path, $dompdf->output());

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error crítico al generar el documento PDF: ' . $e->getMessage()]);
        }

        if (file_exists($pdf_path)) {
            $options = get_option('cxd_nota_venta_options');
            $company_email = !empty($options['email_empresa']) ? $options['email_empresa'] : get_option('admin_email');
            $client_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

            $recipients = [];
            if (is_email($company_email)) { $recipients[] = $company_email; }
            if (is_email($client_email)) { $recipients[] = $client_email; }
            $recipients = array_unique($recipients);

            $subject = 'Nueva Nota de Venta - ' . $form_data['sr_a'];
            $body = '<p>Se ha generado una nueva nota de venta. El documento PDF se encuentra adjunto.</p>';
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $attachments = [$pdf_path];

            $whatsapp_link = null;
            if ($form_data['whatsapp_check']) {
                $nombre_cliente = $form_data['sr_a'];
                $productos_comprados = [];
                $planes_pago = [];
                $fecha_finalizacion = null;

                foreach($form_data['productos'] as $p) {
                    $productos_comprados[] = $p['cantidad'] . 'x ' . $p['nombre'];
                    
                    $plan_texto = '';
                    if ($p['forma_pago'] === 'Diario' && !empty($p['plan_id'])) {
                        $plan_texto = 'Pago Diario a ' . $p['plan_id'] . ' días';
                        try {
                            $dias_plan = intval($p['plan_id']);
                            $fecha_calculada = new DateTime();
                            $dias_pagados = 0;

                            while ($dias_pagados < $dias_plan) {
                                $fecha_calculada->add(new DateInterval('P1D'));
                                if ($fecha_calculada->format('N') != 7) { // 7 = Domingo
                                    $dias_pagados++;
                                }
                            }

                            if ($fecha_finalizacion === null || $fecha_calculada > $fecha_finalizacion) {
                                $fecha_finalizacion = $fecha_calculada;
                            }
                        } catch (Exception $e) {}
                    } else {
                        $plan_texto = $p['forma_pago'];
                    }
                    $planes_pago[] = $plan_texto;
                }

                $productos_str = implode(', ', $productos_comprados);
                $planes_str = implode(', ', array_unique($planes_pago));

                $whatsapp_message = "Hola {$nombre_cliente}, usted acaba de adquirir el producto {$productos_str} con el plan de pago {$planes_str}";

                if ($fecha_finalizacion !== null) {
                    $whatsapp_message .= " y se estima que debería terminar los pagos para el " . date_i18n('d \d\e F \d\e Y', $fecha_finalizacion->getTimestamp());
                }

                $whatsapp_message .= ".\n\nAgradecemos su confianza.\nDual Equipamientos.";
                
                $whatsapp_link = cxd_get_whatsapp_link($form_data['telefono'], $whatsapp_message);
            }

            $email_sent = false;
            if (!empty($recipients)) {
                if(wp_mail($recipients, $subject, $body, $headers, $attachments)) {
                    $email_sent = true;
                }
            }

            unlink($pdf_path);

            $response_data = [
                'message' => $email_sent ? 'Nota de venta procesada y enviada por correo con éxito.' : 'Nota de venta procesada, pero hubo un error al enviar el correo.',
                'whatsapp_link' => $whatsapp_link
            ];

            if (empty($recipients)) {
                $response_data['message'] = 'Nota de venta procesada con éxito (no se configuraron correos para el envío).';
            }

            wp_send_json_success($response_data);

        } else {
            wp_send_json_error(['message' => 'No se pudo crear el archivo PDF en el servidor.']);
        }
    }

    public function buscar_clientes_ajax() {
        check_ajax_referer('cxd_nota_venta_nonce', 'nonce');

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if (empty($query)) {
            wp_send_json_success([]);
        }

        $args = [
            'role' => 'customer',
            'number' => 10,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        if (is_numeric($query)) {
            // Buscar por DNI
            $args['meta_query'] = [
                [
                    'key' => 'billing_dni',
                    'value' => $query,
                    'compare' => 'LIKE',
                ],
            ];
        } else {
            // Buscar por nombre
            $args['search'] = '*' . $query . '*';
            $args['search_columns'] = ['display_name'];
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        $clientes = [];
        foreach ($users as $user) {
            $dni = get_user_meta($user->ID, 'billing_dni', true);
            $domicilio = get_user_meta($user->ID, 'billing_address_1', true);
            $localidad = get_user_meta($user->ID, 'billing_city', true);
            $provincia = get_user_meta($user->ID, 'billing_state', true);
            $postcode = get_user_meta($user->ID, 'billing_postcode', true);
            $telefono = get_user_meta($user->ID, 'billing_phone', true);
            $email = $user->user_email;

            $label_parts = [];
            if (!empty($dni)) $label_parts[] = $dni;
            $label_parts[] = $user->display_name;
            if (!empty($domicilio)) $label_parts[] = $domicilio;

            $clientes[] = [
                'id' => $user->ID,
                'label' => implode(' - ', $label_parts),
                'sr_a' => $user->display_name,
                'dni' => $dni,
                'domicilio' => $domicilio,
                'localidad' => $localidad,
                'provincia' => $provincia,
                'postcode' => $postcode,
                'telefono' => $telefono,
                'email' => $email,
            ];
        }

        wp_send_json_success($clientes);
    }

    public function render_shortcode() {
        if ( post_password_required() ) {
            return get_the_password_form();
        }

        // Verificación de acceso
        if ( ! is_user_logged_in() ) {
            return '<p>Debes iniciar sesión para acceder a esta funcionalidad.</p>';
        }

        $current_user = wp_get_current_user();
        $has_access = false;

        // Administradores y gerentes de tienda siempre tienen acceso
        if ( current_user_can( 'administrator' ) || current_user_can( 'shop_manager' ) ) {
            $has_access = true;
        }

        // Usuarios con rol 'vendedor_pago_diario'
        if ( in_array( 'vendedor_pago_diario', $current_user->roles ) ) {
            $has_access = true;
        }

        // Emails autorizados
        $options = get_option( 'cxd_nota_venta_options' );
        if ( ! $has_access && isset( $options['emails_autorizados'] ) && ! empty( $options['emails_autorizados'] ) ) {
            $emails_autorizados = array_map( 'trim', explode( ',', $options['emails_autorizados'] ) );
            if ( in_array( $current_user->user_email, $emails_autorizados ) ) {
                $has_access = true;
            }
        }

        if ( ! $has_access ) {
            return '<p>No tienes permisos para acceder a esta funcionalidad.</p>';
        }

        $products_for_dropdown = $this->get_products_for_nota_venta();
        $provincias = function_exists('cxd_get_provincias_argentinas') ? cxd_get_provincias_argentinas() : [];
        $provincia_default = 'Santa Fe';

        ob_start();
        ?>
        <div class="cxd-nota-venta-container bg-gray-50 p-4 sm:p-6">
            <div id="cxd-success-message" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">¡Éxito!</strong>
                <span class="block sm:inline">La nota de venta ha sido generada y enviada.</span>
                <div id="cxd-whatsapp-button-container" class="mt-4"></div>
                <button type="button" id="cxd-create-new-note-btn" class="btn btn-primary mt-4">Crear Nueva Nota de Venta</button>
            </div>

            <form id="nota-venta-form" novalidate>
                <div class="card">
                    <div class="card-header text-center">
                        <div class="flex justify-center items-center mb-2">
                            <div class="bg-blue-600 text-white font-bold text-4xl w-16 h-16 flex items-center justify-center rounded-lg">D</div>
                            <div class="ml-3 text-left">
                                <h1 class="text-2xl font-bold text-gray-800">DUAL EQUIPAMIENTOS</h1>
                                <p class="text-sm text-gray-500">PARA COMERCIO Y HOGAR</p>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mt-4">Nota de Venta</h2>
                    </div>

                    <div class="grid grid-cols-2 gap-x-8 gap-y-6 mt-8">
                     <div>
                         <label for="fecha" class="form-label">Fecha</label>
                         <input type="date" name="fecha" id="fecha" class="form-input" required>
                     </div>
                 </div>

                    <fieldset class="mt-8">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Datos del Cliente</legend>
                        <div class="mb-6">
                            <label for="cliente-search" class="form-label">Buscar Cliente (opcional)</label>
                            <input type="text" id="cliente-search" class="form-input" placeholder="Buscar por DNI o nombre...">
                            <div id="cliente-results" class="mt-2 hidden bg-white border border-gray-300 rounded-md shadow-lg max-h-40 overflow-y-auto"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                            <div>
                                <label for="sr_a" class="form-label">Apellido y Nombres <span class="text-red-500">*</span></label>
                                <input type="text" name="sr_a" id="sr_a" class="form-input" required>
                            </div>
                            <div>
                                <label for="dni" class="form-label">DNI <span class="text-red-500">*</span></label>
                                <input type="text" name="dni" id="dni" class="form-input" required>
                            </div>
                            <div>
                                <label for="domicilio" class="form-label">Domicilio</label>
                                <input type="text" name="domicilio" id="domicilio" class="form-input">
                            </div>
                            <div class="grid grid-cols-2 gap-x-8">
                                <div>
                                    <label for="localidad" class="form-label">Localidad</label>
                                    <input type="text" name="localidad" id="localidad" class="form-input">
                                </div>
                                <div>
                                    <label for="postcode" class="form-label">Código Postal</label>
                                    <input type="text" name="postcode" id="postcode" class="form-input">
                                </div>
                            </div>
                            <div>
                                <label for="provincia" class="form-label">Provincia</label>
                                <select id="provincia" name="provincia" class="form-input">
                                    <?php foreach ($provincias as $prov) : ?>
                                        <option value="<?php echo esc_attr($prov); ?>" <?php selected($prov, $provincia_default); ?>><?php echo esc_html($prov); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" name="telefono" id="telefono" class="form-input">
                            </div>
                            <div>
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" name="email" id="email" class="form-input">
                            </div>
                            <div class="flex items-center">
                                <input id="whatsapp_check" name="whatsapp_check" type="checkbox" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="whatsapp_check" class="ml-2 block text-sm text-gray-900">Enviar confirmación por WhatsApp</label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mt-8">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Productos</legend>
                        <div id="productos-container" class="space-y-6"></div>
                        <div class="mt-4">
                            <button type="button" id="add-product-btn" class="btn btn-secondary">Añadir Producto</button>
                        </div>
                    </fieldset>

                    <fieldset class="mt-8">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Entrega</legend>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="flex items-center">
                                <input id="entrega_inmediata" name="entrega_inmediata" type="checkbox" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" checked>
                                <label for="entrega_inmediata" class="ml-2 block text-sm text-gray-900">Entrega inmediata</label>
                            </div>
                            <div id="fecha-entrega-container" class="hidden flex items-center gap-2">
                                <label for="fecha_entrega" class="text-sm font-medium text-gray-700">Fecha de entrega:</label>
                                <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-input w-auto" min="">
                            </div>
                        </div>
                    </fieldset>

                    <div class="mt-8">
                        <button type="button" id="add-note-btn" class="text-blue-600 hover:text-blue-800 text-sm font-medium">+ Añadir nota</button>
                        <div id="notas-content" class="hidden mt-2">
                            <label for="notas" class="form-label sr-only">Notas Adicionales</label>
                            <textarea id="notas" name="notas" rows="3" class="form-input"></textarea>
                        </div>
                    </div>

                    <fieldset class="mt-8">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Firma y Conformidad <span class="text-red-500">*</span></legend>
                        <div class="bg-gray-100 border border-gray-300 rounded-lg">
                            <canvas id="signature-canvas" class="w-full h-48"></canvas>
                        </div>
                        <div class="mt-2 text-right">
                            <button type="button" id="clear-signature-btn" class="text-sm font-medium text-gray-600 hover:text-gray-800">Limpiar Firma</button>
                        </div>
                    </fieldset>

                    <div class="mt-10 pt-6 border-t border-gray-200 text-right">
                        <button type="submit" class="btn btn-primary btn-lg" disabled>Generar Nota de Venta</button>
                    </div>
                </div>
            </form>
            <div class="cxd-admin-footer" style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #777;">
                Calculadora PxD v<?php echo esc_html( CXD_VERSION ); ?>
            </div>
        </div>

        <div id="producto-template" class="p-4 border border-gray-200 rounded-lg relative hidden bg-white shadow-sm space-y-4">
            <button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 remove-product-btn">✖</button>
            
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-6 space-y-2">
                    <label class="text-sm font-medium text-gray-700">Producto</label>
                    <input type="text" class="form-input w-full product-search mb-2" placeholder="Buscar producto...">
                    <div class="flex items-center space-x-2">
                        <input type="number" name="producto_cantidad[]" class="form-input w-16 text-center product-quantity" min="1" value="1">
                        <select name="producto_id[]" class="form-input product-select w-full">
                            <option value="">Seleccione un producto</option>
                            <?php
                            if ( ! empty( $products_for_dropdown ) ) {
                                foreach ( $products_for_dropdown as $product_data ) {
                                    $stock_text = $product_data['stock'] !== null ? ' (Stock: ' . $product_data['stock'] . ')' : '';
                                    echo '<option value="' . esc_attr( $product_data['id'] ) . '" ' . 
                                         'data-stock="' . esc_attr( $product_data['stock'] ) . '" ' . 
                                         'data-price="' . esc_attr( $product_data['price'] ) . '" ' . 
                                         'data-class="' . esc_attr( $product_data['class'] ) . '"' . 
                                         '>' . esc_html( $product_data['name'] . $stock_text ) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="md:col-span-6 space-y-2">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Forma de Pago</label>
                            <select name="forma_pago[]" class="form-input forma-pago-select w-full">
                                <option value="contado">Contado</option>
                                <option value="debito">Débito</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="diario">Pago Diario</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Clase <span class="text-red-500">*</span></label>
                            <select name="producto_clase[]" class="form-input product-class-select w-full">
                                <option value="">Seleccionar</option>
                                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $clase) : ?>
                                    <option value="<?php echo $clase; ?>"><?php echo $clase; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pago-diario-fields" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start pt-4 mt-4 border-t">
                    <div class="md:col-span-6 space-y-2">
                        <label class="text-sm font-medium text-gray-700">Plan de Pago</label>
                        <select name="producto_plan[]" class="form-input plan-select w-full" disabled>
                            <option value="">Seleccione un plan</option>
                        </select>
                    </div>
                    <div class="md:col-span-6 surcharge-container space-y-2">
                        <label class="text-sm font-medium text-gray-700">Recargo/Descuento</label>
                        <div class="surcharge-buttons flex-grow flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="new-daily-rate-container mt-4" style="display: none;">
                    <span class="text-sm font-medium text-gray-700">Valor cuota actualizado:</span>
                    <span class="new-daily-rate font-bold text-gray-900 ml-2"></span>
                </div>
            </div>

            <div class="final-price-container text-right pt-4 mt-4 border-t">
                 <span class="text-sm font-medium text-gray-700">Precio Final:</span>
                 <span class="final-price font-bold text-lg text-gray-900 ml-2">$0.00</span>
            </div>

            <input type="hidden" name="producto_surcharge_percent[]" class="surcharge-percent-input" value="0">
            <input type="hidden" name="producto_final_price[]" class="final-price-input" value="0">
            <input type="hidden" name="producto_new_daily_rate[]" class="new-daily-rate-input" value="0">
        </div>
        <?php
        return ob_get_clean();
    }
}