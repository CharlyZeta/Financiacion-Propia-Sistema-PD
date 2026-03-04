jQuery(document).ready(function($) {
    var allProductIDs = [];
    var totalProducts = 0;
    var processedProducts = 0;
    var batchSize = 25;

    var $status = $('#cxd-recalcular-status');
    var $button = $('#cxd-recalcular-todos');
    var $progressBarContainer = $('<div style="background:#eee; border:1px solid #ccc; margin-top:10px; border-radius: 3px; overflow: hidden;"><div id="cxd-progress-bar" style="background:#0073aa; height:24px; width:0%; text-align:center; color:white; line-height:24px; transition: width 0.3s ease-in-out;">0%</div></div>');
    var $logContainer = $('<div style="height: 200px; overflow-y: scroll; background: #f7f7f7; border: 1px solid #ccc; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px; line-height: 1.5;"></div>');

    $button.on('click', function() {
        if (!confirm('Este proceso puede tardar varios minutos y no se debe cerrar esta ventana hasta que finalice. ¿Estás seguro de que deseas continuar?')) {
            return;
        }

        // 1. Preparar la interfaz de usuario
        $button.prop('disabled', true);
        $status.show().html('Iniciando proceso...');
        if ($('#cxd-progress-bar').length === 0) {
            $status.append($progressBarContainer);
            $status.append($logContainer);
        }
        $logContainer.html(''); // Limpiar el log
        $('#cxd-progress-bar').css('width', '0%').text('0%');
        processedProducts = 0;

        // 2. Obtener todos los IDs de productos
        iniciarObtencionIDs();
    });

    function iniciarObtencionIDs() {
        $logContainer.append('Obteniendo la lista de todos los productos... ');
        $.post(cxd_ajax_obj.ajax_url, {
            action: 'cxd_get_all_product_ids',
            _ajax_nonce: cxd_ajax_obj.nonce
        }).done(function(response) {
            if (!response.success || !response.data || response.data.length === 0) {
                var error_msg = response.data && response.data.message ? response.data.message : 'No se encontraron productos.';
                $logContainer.append('<span style="color: red;">ERROR: ' + error_msg + '</span><br>');
                $button.prop('disabled', false);
                return;
            }

            allProductIDs = response.data;
            totalProducts = allProductIDs.length;
            $logContainer.append('<span style="color: green;">OK</span> (Encontrados ' + totalProducts + ' productos).<br>');
            
            // Iniciar el procesamiento del primer lote
            procesarLote(0);

        }).fail(function() {
            $logContainer.append('<span style="color: red;">ERROR DE RED al obtener la lista de productos. Abortando.</span><br>');
            $button.prop('disabled', false);
        });
    }

    function procesarLote(startIndex) {
        var batchIDs = allProductIDs.slice(startIndex, startIndex + batchSize);
        if (batchIDs.length === 0) {
            $logContainer.append('<strong>¡Proceso completado! Se procesaron ' + processedProducts + ' productos en total.</strong><br>');
            $('#cxd-progress-bar').css('width', '100%').text('100%');
            $button.prop('disabled', false);
            return;
        }

        var loteNum = (startIndex / batchSize) + 1;
        $logContainer.append('Procesando lote ' + loteNum + '... ');

        $.post(cxd_ajax_obj.ajax_url, {
            action: 'cxd_procesar_lote',
            _ajax_nonce: cxd_ajax_obj.nonce,
            product_ids: batchIDs
        }).done(function(response) {
            if (!response.success) {
                var error_msg = response.data && response.data.message ? response.data.message : 'Respuesta no exitosa del servidor.';
                $logContainer.append('<span style="color: red;">ERROR: ' + error_msg + '</span><br>');
                $button.prop('disabled', false);
                return; // Detener el proceso
            }

            var count = response.data.processed_count || 0;
            processedProducts += count;
            $logContainer.append('<span style="color: green;">OK</span> (' + count + ' productos en este lote).<br>');
            
            // Actualizar barra de progreso
            var percentage = Math.round((processedProducts / totalProducts) * 100);
            $('#cxd-progress-bar').css('width', percentage + '%').text(percentage + '%');

            // Procesar el siguiente lote
            var nextIndex = startIndex + batchSize;
            if (nextIndex < totalProducts) {
                setTimeout(function() { procesarLote(nextIndex); }, 200); // Pequeña pausa
            }
             else {
                $logContainer.append('<strong>¡Proceso completado! Se procesaron ' + processedProducts + ' productos en total.</strong><br>');
                $('#cxd-progress-bar').css('width', '100%').text('100%');
                $button.prop('disabled', false);
            }

        }).fail(function() {
            $logContainer.append('<span style="color: red;">ERROR DE RED al procesar el lote. Abortando.</span><br>');
            $button.prop('disabled', false);
        });
    }
});