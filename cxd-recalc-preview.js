jQuery(document).ready(function($) {
    // Selectores de ID directos y más fiables para los campos de precio
    const regularPriceInput = $('#_regular_price');
    const salePriceInput = $('#_sale_price');
    let debounceTimer;

    // Función que se ejecutará cuando cambie un precio
    function handlePriceChange() {
        // Limpiar el temporizador anterior para reiniciar la cuenta atrás
        clearTimeout(debounceTimer);

        // Determinar qué precio usar: el de oferta tiene prioridad
        let priceValue = salePriceInput.val();
        if (priceValue === '' || parseFloat(priceValue) <= 0) {
            priceValue = regularPriceInput.val();
        }

        const targetContainer = $('#cxd-meta-box-content');

        // Iniciar un nuevo temporizador
        debounceTimer = setTimeout(function() {
            // Si el campo está vacío o no es un número válido, no hacer nada
            if (priceValue === '' || isNaN(parseFloat(priceValue)) || parseFloat(priceValue) < 0) {
                // Opcional: podrías poner un mensaje de "esperando precio válido"
                // targetContainer.html('<p>Introduce un precio válido para ver la vista previa.</p>');
                return;
            }

            // Mostrar un mensaje de carga
            targetContainer.html('<p><em>Calculando vista previa...</em></p>');

            // Realizar la llamada AJAX
            $.ajax({
                url: cxd_preview_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'cxd_get_recalculated_preview',
                    nonce: cxd_preview_obj.nonce,
                    product_id: cxd_preview_obj.product_id,
                    price: priceValue
                },
                success: function(response) {
                    if (response.success) {
                        // Reemplazar el contenido del meta box con la nueva tabla
                        targetContainer.html(response.data.html);
                    } else {
                        // Mostrar un mensaje de error si algo falla
                        targetContainer.html('<p style="color:red;">Error al calcular la vista previa.</p>');
                    }
                },
                error: function() {
                    targetContainer.html('<p style="color:red;">Error de conexión. No se pudo calcular la vista previa.</p>');
                }
            });

        }, 500); // 500ms de espera antes de enviar la petición
    }

    // Escuchar eventos 'keyup' (al escribir) y 'change' en ambos campos de precio.
    // .add() combina los dos selectores de jQuery.
    regularPriceInput.add(salePriceInput).on('keyup change', handlePriceChange);
});
