<?php

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Plantilla HTML para generar el PDF de la Nota de Venta.
 *
 * @var array $data Los datos del formulario.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Venta</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; }
        .container { width: 100%; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 0; font-size: 13px; }
        .section { margin-bottom: 18px; }
        .section h2 { font-size: 15px; background-color: #f2f2f2; padding: 8px; margin: 0 0 10px 0; border-bottom: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #f9f9f9; }
        
        /* FIX: Simplificar el grid para asegurar saltos de línea */
        .grid-item {
            display: block; /* Asegura que cada item sea un bloque */
            width: 100%;    /* Ocupa todo el ancho */
            margin-bottom: 8px; /* Espacio entre items */
        }

        .signature-block { margin-top: 35px; padding-top: 15px; border-top: 1px solid #ccc; }
        .signature-block img { max-width: 220px; max-height: 90px; }
        .footer { text-align: center; font-size: 9px; color: #777; position: fixed; bottom: 0; width: 100%; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DUAL EQUIPAMIENTOS</h1>
            <p>PARA COMERCIO Y HOGAR</p>
            <h2>Nota de Venta</h2>
        </div>

        <div class="section">
            <div class="grid clearfix">
                <div class="grid-item"><strong>Fecha:</strong> <?php echo esc_html( date('d/m/Y', strtotime($data['fecha'])) ); ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Datos del Cliente</h2>
            <div class="grid clearfix">
                <div class="grid-item"><strong>Apellido y Nombres:</strong> <?php echo esc_html( $data['sr_a'] ); ?></div>
                <div class="grid-item"><strong>DNI:</strong> <?php echo esc_html( $data['dni'] ); ?></div>
                <div class="grid-item"><strong>Teléfono:</strong> <?php echo esc_html( $data['telefono'] ); ?></div>
                <div class="grid-item"><strong>Email:</strong> <?php echo esc_html( $data['email'] ); ?></div>
                <div class="grid-item"><strong>Domicilio:</strong> <?php echo esc_html( $data['domicilio'] ); ?></div>
                <div class="grid-item"><strong>Localidad:</strong> <?php echo esc_html( $data['localidad'] ); ?></div>
                <div class="grid-item"><strong>Código Postal:</strong> <?php echo esc_html( $data['postcode'] ); ?></div>
                <div class="grid-item"><strong>Provincia:</strong> <?php echo esc_html( $data['provincia'] ); ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Productos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cant.</th>
                        <th>Producto</th>
                        <th>Clase</th>
                        <th>Forma de Pago</th>
                        <th>Rec./Desc.</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $data['productos'] as $producto ) : ?>
                    <tr>
                        <td><?php echo esc_html( $producto['cantidad'] ); ?></td>
                        <td><?php echo esc_html( $producto['nombre'] ); ?></td>
                        <td><?php echo esc_html( $producto['clase'] ); ?></td>
                        <td><?php echo esc_html( $producto['forma_pago'] ); ?></td>
                        <td>
                            <?php 
                                if (isset($producto['surcharge']) && $producto['surcharge'] != 0) {
                                    echo esc_html( $producto['surcharge'] ) . '%';
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($producto['forma_pago'] === 'Diario' && !empty($producto['plan_id'])) {
                                    echo esc_html($producto['plan_id']) . " cuotas de " . wc_price($producto['new_daily_rate']);
                                } else {
                                    echo wc_price( $producto['final_price'] / $producto['cantidad'] );
                                }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ( ! empty( $data['notas'] ) ) : ?>
        <div class="section">
            <h2>Notas Adicionales</h2>
            <p><?php echo nl2br( esc_html( $data['notas'] ) ); ?></p>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>Entrega y Conformidad</h2>
            <p><strong>Entrega:</strong> <?php echo $data['entrega_inmediata'] ? 'Inmediata' : 'Programada para el ' . date('d/m/Y', strtotime($data['fecha_entrega'])); ?></p>
            <div class="signature-block">
                <p><strong>Firma y aclaración del comprador:</strong></p>
                <?php if ( ! empty( $data['firma_base64'] ) ) : ?>
                    <img src="<?php echo esc_attr( $data['firma_base64'] ); ?>" alt="Firma del Cliente">
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            Documento no válido como factura.
        </div>
    </div>
</body>
</html>
