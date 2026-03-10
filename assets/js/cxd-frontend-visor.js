jQuery(document).ready(function ($) {
    const stockFilter = document.querySelector('.cxd-visor-filters input[name="stock_pxd"]');
    if (stockFilter) {
        stockFilter.addEventListener('change', function () {
            this.form.submit();
        });
    }

    // Delegación de eventos para el botón de WhatsApp.
    // El data-text llega con \n como secuencia literal (para sobrevivir atributos HTML).
    // Se restaura a saltos de línea reales antes de aplicar encodeURIComponent.
    $('.cxd-product-grid').on('click', '.cxd-action-whatsapp', function (e) {
        e.preventDefault();
        const text = $(this).data('text').replace(/\\n/g, '\n');
        const whatsappUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
        window.open(whatsappUrl, '_blank');
    });

    // --- Lógica para la nueva ventana modal de detalles ---

    // 1. Abrir la modal
    $('.cxd-product-grid').on('click', '.cxd-action-detalles', function () {
        const card = $(this).closest('.cxd-product-card');
        const title = card.find('.cxd-card-title').text();
        const descriptionHtml = card.find('.cxd-product-description-hidden').html();

        // Llenar la modal con los datos del producto
        $('#cxd-modal-title').text('Detalles de: ' + title);
        $('#cxd-modal-content').html(descriptionHtml);

        // Mostrar la modal
        $('#cxd-modal-detalles').fadeIn(200);
    });

    // 2. Cerrar la modal
    function closeModal() {
        $('#cxd-modal-detalles').fadeOut(200);
    }

    // Evento para el botón de cierre
    $('#cxd-modal-close').on('click', closeModal);

    // Evento para cerrar al hacer clic fuera del contenido
    $('.cxd-modal-overlay').on('click', function (event) {
        if ($(event.target).is('.cxd-modal-overlay')) {
            closeModal();
        }
    });

    // Evento para cerrar con la tecla 'Escape'
    $(document).on('keydown', function (event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });

    // Inicializar los carruseles de Swiper
    function initSwiper() {
        $('.cxd-product-slider').each(function () {
            new Swiper(this, {
                loop: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        });
    }

    // Inicializamos al cargar la página.
    initSwiper();
});