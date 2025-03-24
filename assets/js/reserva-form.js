/**
 * JavaScript para el formulario de reserva de Scolari.
 * 
 * Este script maneja toda la funcionalidad del formulario de reserva,
 * incluyendo la actualización dinámica de precios, subtotales,
 * validación y envío del formulario.
 * 
 * Ajustado para trabajar con la estructura normalizada de la base de datos.
 */
jQuery(document).ready(function($) {
    
    // Variables globales
    var productos = {}; // Almacena información de productos
    var productosCounter = 0; // Contador para IDs dinámicos
    var formInitialized = false; // Flag para evitar inicialización múltiple
    
    // Inicializar todo
    if (!window.reservaFormInitialized) {
        window.reservaFormInitialized = true;
        
        // Cargar configuración
        loadConfiguration();
        
        // Inicializar el formulario
        initializeForm();
        
        console.log('Formulario de reserva inicializado');
    } else {
        console.warn('El formulario ya está inicializado, ignorando reinicialización');
    }

    /**
     * Inicializar el formulario completo
     */
    function initializeForm() {
        if (formInitialized) {
            console.warn('El formulario ya está inicializado, ignorando reinicialización');
            return;
        }
        
        // Agregar la primera línea de producto
        agregarLinea();
        
        // Configurar el campo de fecha
        setupDateField();
        
        // Configurar evento de envío del formulario
        $('#reservaForm').on('submit', validarFormulario);
        
        // Agregar el modal al DOM
        agregarModalAlDOM();
        
        // Inicializar event listeners
        setupEventListeners();
        
        // Marcar como inicializado
        formInitialized = true;
    }
    
    /**
     * Cargar configuración de productos y precios
     */
    function loadConfiguration() {
        var config = typeof window.reserva_form_config !== 'undefined' ? window.reserva_form_config : {};
        
        // Debug - mostrar configuración completa en la consola
        console.log('Configuración completa:', config);
        
        // Cargar productos
        if (typeof config.productos !== 'undefined') {
            productos = config.productos;
            
            // Verificar si productos está vacío
            if (Object.keys(productos).length === 0) {
                console.error('ERROR: No se encontraron productos en la configuración');
                
                // Mostrar mensaje de error en el formulario
                $('#productos-container').html(
                    '<div class="error-message">' +
                    '<p>No se encontraron productos disponibles. Por favor, contacte al administrador.</p>' +
                    '</div>'
                );
                
                // Deshabilitar el botón de agregar producto
                $('#btn-agregar-producto').prop('disabled', true).addClass('disabled');
            } else {
                console.log('Productos cargados correctamente. Cantidad:', Object.keys(productos).length);
                // Mostrar primer producto para depuración
                var primerProductoSlug = Object.keys(productos)[0];
                console.log('Primer producto:', primerProductoSlug, productos[primerProductoSlug]);
            }
        } else {
            console.error('ERROR: La configuración no contiene productos');
            
            // Mostrar mensaje de error en el formulario
            $('#productos-container').html(
                '<div class="error-message">' +
                '<p>Error en la configuración de productos. Por favor, contacte al administrador.</p>' +
                '</div>'
            );
            
            // Deshabilitar el botón de agregar producto
            $('#btn-agregar-producto').prop('disabled', true).addClass('disabled');
        }
        
        console.log('Productos cargados:', productos);
    }
    
    /**
     * Agregar modal al DOM - Versión re-corregida
     */
    function agregarModalAlDOM() {
        // Verificar primero si el modal ya existe para evitar duplicados
        if ($('#imagenModal').length) {
            return;
        }
        
        var modalHTML = '<div id="imagenModal" class="modal-overlay">' +
                        '<div class="modal-content">' +
                        '<button type="button" class="modal-close">&times;</button>' +
                        '<img id="modalImage" class="modal-image" src="" alt="Imagen ampliada">' +
                        '</div>' +
                        '</div>';
        
        $('body').append(modalHTML);
        
        // Manejar el cierre del modal
        $('#imagenModal .modal-close').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#imagenModal').removeClass('active').css('display', 'none');
            console.log("Cerrando modal con X");
        });
        
        // Cerrar al hacer clic fuera de la imagen
        $('#imagenModal').on('click', function(e) {
            if (e.target === this) {
                $(this).removeClass('active').css('display', 'none');
                console.log("Cerrando modal con click fuera");
            }
        });
        
        // Evitar que el click en la imagen del modal cierre el modal
        $('#modalImage').on('click', function(e) {
            e.stopPropagation();
        });
    }
    
    /**
     * Configurar event listeners - Versión corregida
     */
    function setupEventListeners() {
        // Botón agregar producto
        $('#btn-agregar-producto').off('click').on('click', function() {
            agregarLinea();
        });
        
        // Botones guía de tallas - ajustados para usar URLs directas
        $('.boton-guia').off('click').on('click', function() {
            var targetId = $(this).data('target');
            var imgSrc;
            
            // Obtener URL de la imagen según el botón
            if (targetId === 'guiaPantalon') {
                imgSrc = 'https://www.scolari.cl/wp-content/uploads/2025/01/3.png';
            } else if (targetId === 'guiaPolera') {
                imgSrc = 'https://www.scolari.cl/wp-content/uploads/2025/01/1.png';
            } else if (targetId === 'guiaPoleron') {
                imgSrc = 'https://www.scolari.cl/wp-content/uploads/2025/01/4.png';
            }
            
            if (imgSrc) {
                mostrarImagenEnModal(imgSrc, 'Guía de tallas');
            }
        });
        
        // Hacer que las imágenes de producto sean clickeables para ampliar
        $(document).on('click', '.product-image', function() {
            var imgSrc = $(this).attr('src');
            var altText = $(this).attr('alt');
            
            if (imgSrc) {
                mostrarImagenEnModal(imgSrc, altText);
            }
        });
    }
    
    /**
     * Configurar el campo de fecha
     */
    function setupDateField() {
        // Obtener fecha mínima (hoy)
        var today = new Date();
        var minDate = today.toISOString().split('T')[0];
        
        // Configurar restricciones de fecha
        $('#fecha_necesidad').attr('min', minDate);
    }
    
    /**
     * Agregar una nueva línea de producto al formulario
     */
    function agregarLinea() {
        productosCounter++;
        var lineId = productosCounter;
        console.log("Agregando línea de producto #" + lineId);
        
        var productLine = '<div id="producto_line_' + lineId + '" class="producto-line" data-line-id="' + lineId + '">' +
                         '<div class="producto-header">' +
                         '<div class="producto-numero">' + lineId + '</div>' +
                         '<div class="producto-title">Producto ' + lineId + '</div>' +
                         '<button type="button" class="boton-eliminar" data-line="' + lineId + '">×</button>' +
                         '</div>' +
                         '<div class="producto-content">' +
                         '<div class="producto-detalles">' +
                         '<div class="column-image">' +
                         '<img id="product_img_' + lineId + '" class="product-image" src="" alt="" style="display:none;">' +
                         '</div>' +
                         '<div class="column-fields">' +
                         '<div class="form-group">' +
                         '<label for="producto_' + lineId + '">Seleccionar producto:</label>' +
                         '<select id="producto_' + lineId + '" name="producto_' + lineId + '" class="producto-select" required>' +
                         '<option value="">Selecciona un producto</option>';
                     
        // Agregar opciones de productos
        $.each(productos, function(slug, producto) {
            productLine += '<option value="' + slug + '">' + producto.nombre + '</option>';
        });
        
        productLine += '</select>' +
                      '</div>' +
                      '<div class="form-group">' +
                      '<label for="talla_' + lineId + '">Talla:</label>' +
                      '<select id="talla_' + lineId + '" name="talla_' + lineId + '" class="talla-select" required disabled>' +
                      '<option value="">Selecciona talla</option>' +
                      '</select>' +
                      '</div>' +
                      '<div class="form-group">' +
                      '<label for="cantidad_' + lineId + '">Cantidad:</label>' +
                      '<input type="number" id="cantidad_' + lineId + '" name="cantidad_' + lineId + '" min="1" value="1" class="cantidad-input" disabled required>' +
                      '</div>' +
                      '</div>' +
                      '</div>' +
                      '<div class="producto-precio">' +
                      '<div class="precio-unitario">Precio unitario: <span id="precio_' + lineId + '">$0</span></div>' +
                      '<div class="subtotal">Subtotal: <span id="subtotal_' + lineId + '">$0</span></div>' +
                      '</div>' +
                      '</div>' +
                      '</div>' +
                      '</div>';
        
        // Agregar la nueva línea al formulario
        $('#productos-container').append(productLine);
        
        // Agregar event listeners para esta línea
        $('#producto_' + lineId).on('change', function() {
            actualizarProducto(lineId);
            // Actualizar título del producto
            var productoNombre = $(this).find('option:selected').text();
            if(productoNombre && productoNombre !== 'Selecciona un producto') {
                $('#producto_line_' + lineId + ' .producto-title').text(productoNombre);
            } else {
                $('#producto_line_' + lineId + ' .producto-title').text('Producto ' + lineId);
            }
        });
        
        $('#talla_' + lineId).on('change', function() {
            actualizarPrecio(lineId);
        });
        
        $('#cantidad_' + lineId).on('change', function() {
            actualizarPrecio(lineId);
        });
        
        // Botón eliminar
        $('#producto_line_' + lineId + ' .boton-eliminar').on('click', function() {
            eliminarLinea($(this).data('line'));
        });
        
        // Actualizar numeración de líneas
        actualizarNumeracionLineas();
    }
    
    /**
     * Actualizar producto y opciones disponibles al cambiar selección
     */
    function actualizarProducto(lineId) {
        console.log("Actualizando producto para línea #" + lineId);
        var select = $('#producto_' + lineId);
        var tallaSelect = $('#talla_' + lineId);
        var cantidadInput = $('#cantidad_' + lineId);
        var img = $('#product_img_' + lineId);
        
        // Resetear valores
        tallaSelect.html('<option value="">Selecciona talla</option>').prop('disabled', true);
        cantidadInput.prop('disabled', true).val(1);
        $('#precio_' + lineId).text('$0');
        $('#subtotal_' + lineId).text('$0');
        
        if (select.val()) {
            var productoSlug = select.val();
            console.log("Producto seleccionado:", productoSlug, "para línea #" + lineId);
            
            // Actualizar imagen - forzar actualización
            if (productos[productoSlug] && productos[productoSlug].img) {
                var timestamp = new Date().getTime(); // Añadir timestamp para evitar caché
                var imgUrl = productos[productoSlug].img + "?t=" + timestamp;
                img.attr('src', imgUrl)
                   .attr('alt', productos[productoSlug].nombre)
                   .show();
                console.log("Imagen actualizada para línea #" + lineId + ": " + imgUrl);
            } else {
                img.hide();
                console.log("Ocultando imagen para línea #" + lineId);
            }
            
            // Habilitar y llenar select de tallas
            tallaSelect.prop('disabled', false);
            
            // Agregar opciones de tallas
            if (productos[productoSlug] && productos[productoSlug].precios) {
                $.each(productos[productoSlug].precios, function(talla, precio) {
                    if (precio > 0) { // Solo agregar tallas con precio
                        tallaSelect.append('<option value="' + talla + '">' + talla + '</option>');
                    }
                });
            }
            
            // Seleccionar primera talla por defecto
            setTimeout(function() {
                if (tallaSelect.find('option').length > 1) {
                    tallaSelect.find('option').eq(1).prop('selected', true);
                    tallaSelect.trigger('change');
                }
            }, 50);
        } else {
            img.attr('src', '').attr('alt', '').hide();
        }
        
        // Actualizar resumen
        actualizarResumen();
    }
    
    /**
     * Actualizar precio según la talla y cantidad
     */
    function actualizarPrecio(lineId) {
        console.log("Actualizando precio para línea #" + lineId);
        var productoSelect = $('#producto_' + lineId);
        var tallaSelect = $('#talla_' + lineId);
        var cantidadInput = $('#cantidad_' + lineId);
        
        if (productoSelect.val() && tallaSelect.val()) {
            var productoSlug = productoSelect.val();
            var talla = tallaSelect.val();
            var cantidad = parseInt(cantidadInput.val()) || 1;
            
            // Activar input de cantidad
            cantidadInput.prop('disabled', false);
            
            // Calcular precios
            if (productos[productoSlug] && productos[productoSlug].precios && productos[productoSlug].precios[talla]) {
                var precioUnitario = productos[productoSlug].precios[talla];
                var subtotal = precioUnitario * cantidad;
                
                // Formatear precios para mostrar
                $('#precio_' + lineId).text('$' + formatearNumero(precioUnitario));
                $('#subtotal_' + lineId).text('$' + formatearNumero(subtotal));
                
                console.log("Precio actualizado para línea #" + lineId + ": $" + precioUnitario + " x " + cantidad + " = $" + subtotal);
            } else {
                $('#precio_' + lineId).text('$0');
                $('#subtotal_' + lineId).text('$0');
                console.log("No se encontró precio para " + productoSlug + " talla " + talla);
            }
        }
        
        // Actualizar totales
        actualizarResumen();
    }
    
    /**
     * Eliminar una línea de producto
     */
    function eliminarLinea(lineId) {
        $('#producto_line_' + lineId).fadeOut(300, function() {
            $(this).remove();
            actualizarNumeracionLineas();
            actualizarResumen();
        });
    }
    
    /**
     * Actualiza el resumen de la reserva
     */
    function actualizarResumen() {
        var cantidadTotal = 0;
        var precioTotal = 0;
        var productos_data = [];
        var listaHTML = '';
        
        console.log('Actualizando resumen');
        
        // Recorrer todas las líneas de productos
        $('.producto-line').each(function() {
            var lineId = $(this).data('line-id');
            var productoSelect = $('#producto_' + lineId);
            var tallaSelect = $('#talla_' + lineId);
            var cantidadInput = $('#cantidad_' + lineId);
            
            if (productoSelect.val() && tallaSelect.val()) {
                var productoSlug = productoSelect.val();
                var productoNombre = productoSelect.find('option:selected').text();
                var talla = tallaSelect.val();
                var cantidad = parseInt(cantidadInput.val()) || 0;
                
                console.log('Línea #' + lineId + ': ' + productoNombre + ' (slug: ' + productoSlug + '), talla: ' + talla + ', cantidad: ' + cantidad);
                
                cantidadTotal += cantidad;
                
                // Obtener precio de la talla seleccionada
                if (productos[productoSlug] && productos[productoSlug].precios && productos[productoSlug].precios[talla]) {
                    var precioUnitario = productos[productoSlug].precios[talla];
                    var subtotal = precioUnitario * cantidad;
                    precioTotal += subtotal;
                    
                    console.log('Precio unitario: ' + precioUnitario + ', subtotal: ' + subtotal);
                    
                    // Añadir a la lista HTML
                    listaHTML += '<div class="resumen-producto-item">' +
                                '<div class="resumen-producto-info">' +
                                '<div class="resumen-producto-nombre">' + productoNombre + '</div>' +
                                '<div class="resumen-producto-detalle">Talla: ' + talla + ' | Cantidad: ' + cantidad + '</div>' +
                                '</div>' +
                                '<div class="resumen-producto-precio">$' + formatearNumero(subtotal) + '</div>' +
                                '</div>';
                    
                    // Preparar datos para enviar
                    productos_data.push({
                        slug: productoSlug,
                        producto: productoNombre,
                        talla: talla,
                        cantidad: cantidad,
                        unitPrice: precioUnitario,
                        subtotal: subtotal,
                        img: productos[productoSlug].img
                    });
                } else {
                    console.error('No se encontró precio para producto: ' + productoSlug + ', talla: ' + talla);
                    if (!productos[productoSlug]) console.error('El producto no existe en la configuración');
                    else if (!productos[productoSlug].precios) console.error('El producto no tiene precios configurados');
                    else console.error('La talla seleccionada no tiene precio configurado');
                }
            }
        });
        
        // Actualizar resumen visual
        if (cantidadTotal > 0) {
            $('.resumen-confirmacion').html(
                '<div class="resumen-titulo">Resumen de tu reserva</div>' +
                '<div class="resumen-detalle">Has seleccionado <strong>' + cantidadTotal + '</strong> producto(s)</div>' +
                '<div class="resumen-productos-lista">' + listaHTML + '</div>' +
                '<div class="resumen-total">Total: <strong>$' + formatearNumero(precioTotal) + '</strong></div>'
            ).show();
        } else {
            $('.resumen-confirmacion').html(
                '<div class="resumen-titulo">Resumen de tu reserva</div>' +
                '<div class="resumen-vacio">No has seleccionado productos aún</div>'
            ).show();
        }
        
        // Guardar datos para enviar
        var productosJSON = JSON.stringify(productos_data);
        console.log('Datos JSON a enviar:', productosJSON);
        $('#producto_data').val(productosJSON);
        
        return { cantidadTotal: cantidadTotal, precioTotal: precioTotal };
    }
    
    /**
     * Actualiza la numeración de las líneas de productos
     */
    function actualizarNumeracionLineas() {
        $('.producto-line').each(function(index) {
            $(this).find('.producto-numero').text(index + 1);
        });
    }
    
    /**
     * Muestra una imagen ampliada en un modal
     */
    function mostrarImagenEnModal(imgSrc, altText) {
        $('#modalImage').attr('src', imgSrc).attr('alt', altText || '');
        
        var modal = $('#imagenModal');
        modal.addClass('active').css('display', 'flex');
        
        console.log("Mostrando modal con imagen: " + imgSrc);
        
        // Precarga de imagen para asegurar dimensiones correctas
        var img = new Image();
        img.onload = function() {
            // Ajustar tamaño si la imagen es muy grande
            var maxWidth = window.innerWidth * 0.9;
            var maxHeight = window.innerHeight * 0.9;
            
            if (this.width > maxWidth || this.height > maxHeight) {
                var ratio = Math.min(maxWidth / this.width, maxHeight / this.height);
                $('#modalImage').css({
                    'max-width': Math.floor(this.width * ratio) + 'px',
                    'max-height': Math.floor(this.height * ratio) + 'px'
                });
            }
        };
        img.src = imgSrc;
    }
    
    /**
     * Valida el formulario antes de enviarlo
     */
    function validarFormulario(e) {
        try {
            var resumen = actualizarResumen();
            
            // Verificar que hay productos seleccionados
            if (resumen.cantidadTotal === 0) {
                alert('Debe seleccionar al menos un producto.');
                e.preventDefault();
                return false;
            }
            
            // Validar que los campos requeridos estén completos
            var nombre = $('#nombre').val().trim();
            var email = $('#email').val().trim();
            var telefono = $('#telefono').val().trim();
            var fecha = $('#fecha_necesidad').val().trim();
            
            if (!nombre || !email || !telefono || !fecha) {
                alert('Por favor complete todos los campos obligatorios marcados con *');
                e.preventDefault();
                return false;
            }
            
            // Si el formulario tiene datos de dirección opcionales, continuamos normalmente
            
            // Debug - verificar datos JSON antes de enviar
            console.log('Datos de productos a enviar:', $('#producto_data').val());
            
            return true;
        } catch (error) {
            console.error('Error en validación del formulario:', error);
            alert('Ocurrió un error al procesar el formulario. Por favor, inténtelo de nuevo.');
            e.preventDefault();
            return false;
        }
    }
    
    /**
     * Formatea un número con separadores de miles
     */
    function formatearNumero(numero) {
        return numero.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
});