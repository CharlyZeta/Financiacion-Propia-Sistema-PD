document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('nota-venta-form');
    if (!form) return;

    const submitButton = form.querySelector('button[type="submit"]');
    const productsContainer = document.getElementById('productos-container');
    const productTemplate = document.getElementById('producto-template');
    const addProductBtn = document.getElementById('add-product-btn');
    const successMessageContainer = document.getElementById('cxd-success-message');
    const whatsappButtonContainer = document.getElementById('cxd-whatsapp-button-container');
    const createNewNoteBtn = document.getElementById('cxd-create-new-note-btn');

    let signaturePad = null;
    const canvas = document.getElementById('signature-canvas');
    if (canvas) {
        signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(249, 250, 251)' });
        signaturePad.addEventListener("endStroke", () => {
            if (!signaturePad.isEmpty()) submitButton.disabled = false;
        });
        document.getElementById('clear-signature-btn')?.addEventListener('click', () => {
            signaturePad.clear();
            submitButton.disabled = true;
        });
        window.addEventListener("resize", () => {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
            submitButton.disabled = true;
        });
        window.dispatchEvent(new Event('resize'));
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Validación de entrega
        const entregaInmediata = document.getElementById('entrega_inmediata');
        const fechaEntrega = document.getElementById('fecha_entrega');
        if (entregaInmediata && !entregaInmediata.checked) {
            const selectedDate = new Date(fechaEntrega.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate < today) {
                alert('La fecha de entrega debe ser hoy o posterior.');
                return;
            }
        }

        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = 'Enviando...';

        const formData = new FormData(form);
        if (signaturePad && !signaturePad.isEmpty()) {
            formData.set('firma_base64', signaturePad.toDataURL('image/png'));
        } else {
            formData.set('firma_base64', '');
        }
        formData.append('action', 'cxd_procesar_nota_venta');
        formData.append('nonce', cxd_nota_venta_data.nonce);

        fetch(cxd_nota_venta_data.ajax_url, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.classList.add('hidden');
                    successMessageContainer.querySelector('span').textContent = data.data.message;
                    successMessageContainer.classList.remove('hidden');

                    whatsappButtonContainer.innerHTML = ''; // Limpiar por si acaso
                    if (data.data.whatsapp_link) {
                        const whatsappButton = document.createElement('a');
                        whatsappButton.href = data.data.whatsapp_link;
                        whatsappButton.target = '_blank';
                        whatsappButton.className = 'inline-block bg-green-500 text-white font-bold py-2 px-4 rounded mt-2';
                        whatsappButton.textContent = 'Enviar Confirmación por WhatsApp';
                        whatsappButtonContainer.appendChild(whatsappButton);
                    }
                    window.scrollTo(0, 0);
                } else {
                    alert('Error:\n' + (data.data.message || 'El servidor devolvió un error.'));
                    if (signaturePad && !signaturePad.isEmpty()) submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error en la petición AJAX:', error);
                alert('Ha ocurrido un error inesperado. Revisa la consola del navegador.');
                if (signaturePad && !signaturePad.isEmpty()) submitButton.disabled = false;
            })
            .finally(() => { submitButton.innerHTML = originalButtonText; });
    });

    createNewNoteBtn.addEventListener('click', () => {
        form.reset();
        signaturePad?.clear();
        productsContainer.innerHTML = '';
        initializeForm();
        submitButton.disabled = true;
        successMessageContainer.classList.add('hidden');
        form.classList.remove('hidden');
    });

    window.addProductRow = function() {
        const newProductRow = productTemplate.cloneNode(true);
        newProductRow.removeAttribute('id');
        newProductRow.classList.remove('hidden');
        productsContainer.appendChild(newProductRow);
        initializeProductRow(newProductRow);
        return newProductRow;
    }

    function initializeProductRow(row) {
        const productSelect = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.product-quantity');
        const formaPagoSelect = row.querySelector('.forma-pago-select');
        const pagoDiarioFields = row.querySelector('.pago-diario-fields');
        const planSelect = row.querySelector('.plan-select');
        const surchargeButtons = row.querySelector('.surcharge-buttons');
        const finalPriceDisplay = row.querySelector('.final-price');
        const surchargePercentInput = row.querySelector('.surcharge-percent-input');
        const finalPriceInput = row.querySelector('.final-price-input');
        const newDailyRateContainer = row.querySelector('.new-daily-rate-container');
        const newDailyRate = row.querySelector('.new-daily-rate');
        const productClassSelect = row.querySelector('.product-class-select');
        const newDailyRateInput = row.querySelector('.new-daily-rate-input');
        const searchInput = row.querySelector('.product-search');
        const originalOptions = Array.from(productSelect.options);

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const currentSelectedValue = productSelect.value;
            
            const filteredOptions = originalOptions.filter(option => {
                return option.value === "" || option.text.toLowerCase().includes(searchTerm);
            });

            productSelect.innerHTML = '';
            let isSelectedValueInResults = false;
            filteredOptions.forEach(option => {
                productSelect.appendChild(option.cloneNode(true));
                if (option.value === currentSelectedValue) {
                    isSelectedValueInResults = true;
                }
            });

            if (isSelectedValueInResults) {
                productSelect.value = currentSelectedValue;
            } else if (filteredOptions.length > 1) {
                // If the selected item is filtered out, select the first actual product
                productSelect.value = filteredOptions[1].value;
                productSelect.dispatchEvent(new Event('change'));
            } else if (filteredOptions.length === 1 && filteredOptions[0].value === "") {
                // Only "Seleccione un producto" is left
                productSelect.value = "";
                productSelect.dispatchEvent(new Event('change'));
            }
        });

        let currentProduct = null;
        let currentSurcharge = 0;

        function updatePrice() {
            const quantity = parseInt(quantityInput.value, 10) || 1;
            let finalPrice = 0;
            let newDailyInstallment = 0;
            
            if (newDailyRateContainer) newDailyRateContainer.style.display = 'none';

            if (currentProduct) {
                const paymentMethod = formaPagoSelect.value;
                if (paymentMethod === 'diario') {
                    const selectedPlanOption = planSelect.options[planSelect.selectedIndex];
                    const planDays = parseInt(selectedPlanOption?.value, 10);
                    const totalPlanPrice = parseFloat(selectedPlanOption?.dataset.price || 0);

                    if (totalPlanPrice > 0 && planDays > 0) {
                        const totalPlanPriceWithSurcharge = totalPlanPrice * (1 + currentSurcharge / 100);
                        newDailyInstallment = totalPlanPriceWithSurcharge / planDays;
                        finalPrice = totalPlanPriceWithSurcharge * quantity;
                        
                        if (newDailyRate) newDailyRate.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(newDailyInstallment);
                        if (newDailyRateContainer) newDailyRateContainer.style.display = 'block';

                    }
                } else {
                    const basePrice = parseFloat(currentProduct.price || 0);
                    finalPrice = basePrice * quantity;
                }
            }

            finalPriceDisplay.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(finalPrice);
            finalPriceInput.value = finalPrice.toFixed(2);
            surchargePercentInput.value = currentSurcharge;
            newDailyRateInput.value = newDailyInstallment.toFixed(2);
        }

        function generateSurchargeButtons() {
            surchargeButtons.innerHTML = '';
            currentSurcharge = 0;
            const createButton = (percent) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.percent = percent;
                button.className = 'surcharge-btn px-3 py-1.5 text-sm font-medium rounded-md border bg-gray-100 text-gray-700 border-gray-300';
                button.textContent = `${percent > 0 ? '+' : ''}${percent}%`;
                
                button.addEventListener('click', () => {
                    currentSurcharge = percent;
                    surchargeButtons.querySelectorAll('.surcharge-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
                        btn.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-300');
                    });
                    button.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
                    button.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300');
                    updatePrice();
                });
                return button;
            };

            const zeroButton = createButton(0);
            surchargeButtons.appendChild(zeroButton);

            let surcharges = cxd_nota_venta_data.surcharges;
            if (surcharges && Array.isArray(surcharges)) {
                surcharges.forEach(percent => {
                    if (percent !== 0) {
                        surchargeButtons.appendChild(createButton(percent));
                    }
                });
            }
            
            const initialActiveButton = surchargeButtons.querySelector('[data-percent="0"]');
            if(initialActiveButton) {
                initialActiveButton.classList.add('bg-blue-600', 'text-white', 'border-blue-600');
                initialActiveButton.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-300');
            }
        }

        function handlePaymentMethodChange() {
            const paymentMethod = formaPagoSelect.value;
            if (paymentMethod === 'diario') {
                pagoDiarioFields.style.display = 'block';
                const hasPlans = currentProduct && currentProduct.planes && Object.keys(currentProduct.planes).length > 0;
                planSelect.disabled = !hasPlans;
                if (hasPlans) {
                    generateSurchargeButtons();
                } else {
                    surchargeButtons.innerHTML = '';
                }
            } else {
                pagoDiarioFields.style.display = 'none';
                planSelect.disabled = true;
                surchargeButtons.innerHTML = '';
                currentSurcharge = 0;
            }
            updatePrice();
        }

        productSelect.addEventListener('change', (e) => {
            const productId = parseInt(e.target.value, 10);
            currentProduct = cxd_nota_venta_data.products.find(p => p.id === productId);
            
            if (currentProduct) {
                productClassSelect.value = currentProduct.class || '';
            }

            planSelect.innerHTML = '<option value="">Seleccione un plan</option>';
            if (currentProduct && currentProduct.planes) {
                Object.entries(currentProduct.planes).forEach(([dias, plan]) => {
                    const option = document.createElement('option');
                    option.value = dias;
                    option.textContent = `${dias} días de ${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(plan.cuota_diaria)}`;
                    option.dataset.price = plan.monto_total;
                    option.dataset.cuota = plan.cuota_diaria;
                    planSelect.appendChild(option);
                });
            }
            
            validateStock();
            handlePaymentMethodChange();
        });

        formaPagoSelect.addEventListener('change', handlePaymentMethodChange);
        planSelect.addEventListener('change', () => { updatePrice(); });
        quantityInput.addEventListener('change', () => { validateStock(); updatePrice(); });

        function validateStock() {
            if (!currentProduct) return;
            const stock = parseInt(currentProduct.stock, 10);
            let quantity = parseInt(quantityInput.value, 10);
            if (!isNaN(stock) && quantity > stock) {
                alert(`La cantidad no puede superar el stock disponible (${stock}).`);
                quantityInput.value = stock;
            }
        }

        row.querySelector('.remove-product-btn').addEventListener('click', () => row.remove());
        handlePaymentMethodChange();
    }

    addProductBtn?.addEventListener('click', addProductRow);

    function initializeForm() {
        const firstRow = addProductRow();
        const preloadedProductId = cxd_nota_venta_data.preloaded_product_id;

        if (preloadedProductId && preloadedProductId > 0) {
            const productSelect = firstRow.querySelector('.product-select');
            const productOption = productSelect.querySelector(`option[value="${preloadedProductId}"]`);

            if (productOption) {
                productSelect.value = preloadedProductId;
                productSelect.dispatchEvent(new Event('change'));
            } else {
                console.warn(`Producto precargado con ID ${preloadedProductId} no encontrado o sin stock.`);
            }
        }
    }

    initializeForm();

    document.getElementById('add-note-btn')?.addEventListener('click', (e) => {
        const notasContent = document.getElementById('notas-content');
        const isHidden = notasContent.classList.toggle('hidden');
        e.target.textContent = isHidden ? '+ Añadir nota' : '- Ocultar nota';
        if (!isHidden) document.getElementById('notas').focus();
    });

    const fechaInput = document.getElementById('fecha');
    if (fechaInput) fechaInput.value = new Date().toISOString().split('T')[0];

    // Buscador de clientes
    const clienteSearch = document.getElementById('cliente-search');
    const clienteResults = document.getElementById('cliente-results');

    if (clienteSearch && clienteResults) {
        let searchTimeout;

        clienteSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                clienteResults.classList.add('hidden');
                clienteResults.innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(cxd_nota_venta_data.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'cxd_buscar_clientes',
                        nonce: cxd_nota_venta_data.nonce,
                        query: query
                    })
                })
                .then(response => response.json())
                .then(data => {
                    clienteResults.innerHTML = '';
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(cliente => {
                            const item = document.createElement('div');
                            item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0';
                            item.textContent = cliente.label;
                            item.addEventListener('click', () => {
                                document.getElementById('sr_a').value = cliente.sr_a || '';
                                document.getElementById('dni').value = cliente.dni || '';
                                document.getElementById('domicilio').value = cliente.domicilio || '';
                                document.getElementById('localidad').value = cliente.localidad || '';
                                document.getElementById('provincia').value = cliente.provincia || '';
                                document.getElementById('postcode').value = cliente.postcode || '';
                                document.getElementById('telefono').value = cliente.telefono || '';
                                document.getElementById('email').value = cliente.email || '';
                                clienteSearch.value = cliente.label;
                                clienteResults.classList.add('hidden');
                            });
                            clienteResults.appendChild(item);
                        });
                        clienteResults.classList.remove('hidden');
                    } else {
                        clienteResults.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error buscando clientes:', error);
                    clienteResults.classList.add('hidden');
                });
            }, 300);
        });

        // Ocultar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!clienteSearch.contains(e.target) && !clienteResults.contains(e.target)) {
                clienteResults.classList.add('hidden');
            }
        });
    }

    // Entrega
    const entregaInmediata = document.getElementById('entrega_inmediata');
    const fechaEntregaContainer = document.getElementById('fecha-entrega-container');
    const fechaEntrega = document.getElementById('fecha_entrega');

    if (entregaInmediata && fechaEntregaContainer && fechaEntrega) {
        // Setear min a hoy
        const today = new Date().toISOString().split('T')[0];
        fechaEntrega.min = today;

        entregaInmediata.addEventListener('change', function() {
            if (this.checked) {
                fechaEntregaContainer.classList.add('hidden');
                fechaEntrega.value = '';
            } else {
                fechaEntregaContainer.classList.remove('hidden');
            }
        });
    }
});
