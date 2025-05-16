/**
 * JavaScript para la vista de detalle de reserva
 */
jQuery(document).ready(function($) {
    // Confirmación para el botón de eliminar en la página de detalle
    $('.reserva-delete-button').on('click', function(e) {
        e.preventDefault();
        var deleteUrl = $(this).attr('href');
        
        if (confirm('¿Estás seguro de que deseas eliminar esta reserva? Esta acción no se puede deshacer.')) {
            window.location.href = deleteUrl;
        }
    });
    
    // Manejar modal de tallas
    var modal = document.getElementById('sizesModal');
    
    // Abrir modal cuando se hace clic en "Ver distribución por tallas"
    $('.view-size-details').on('click', function(e) {
        e.preventDefault();
        
        // Obtener datos del modal
        var producto = $(this).data('producto');
        var detallesTallas = $(this).data('tallas');
        
        // Actualizar título
        $('#tallas-producto-titulo').text('Distribución por Tallas - ' + producto);
        
        if (detallesTallas && typeof detallesTallas === 'object') {
            // Calcular precio unitario y subtotales
            for (var talla in detallesTallas) {
                if (detallesTallas[talla].cantidad > 0) {
                    // Si no hay precio o subtotal, calcularlos
                    if (!detallesTallas[talla].precio_unitario || detallesTallas[talla].precio_unitario === 0) {
                        // Asignar un precio predeterminado (puedes ajustar esto según tus necesidades)
                        detallesTallas[talla].precio_unitario = 15000;
                    }
                    
                    if (!detallesTallas[talla].subtotal || detallesTallas[talla].subtotal === 0) {
                        detallesTallas[talla].subtotal = detallesTallas[talla].precio_unitario * detallesTallas[talla].cantidad;
                    }
                }
            }
            
            // Actualizar la visualización
            actualizarVisualizacionTallas(producto, detallesTallas);
        }
        
        // Mostrar modal
        modal.style.display = "block";
    });
    
    // Cerrar modal al hacer clic en "×"
    $('.close').on('click', function() {
        modal.style.display = "none";
    });
    
    // Cerrar modal al hacer clic fuera de ella
    $(window).on('click', function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
    
    function actualizarVisualizacionTallas(producto, detallesTallas) {
        // Aquí puedes implementar la creación o actualización de gráficos
        // Usando la librería Chart.js si está disponible
        if (typeof Chart !== 'undefined' && $('#tallasChart').length > 0) {
            var ctx = $('#tallasChart')[0].getContext('2d');
            
            // Preparar datos para el gráfico
            var labels = [];
            var data = [];
            
            for (var talla in detallesTallas) {
                if (detallesTallas[talla].cantidad > 0) {
                    labels.push('Talla ' + talla);
                    data.push(detallesTallas[talla].cantidad);
                }
            }
            
            // Crear o actualizar el gráfico
            if (window.tallasChart) {
                window.tallasChart.destroy();
            }
            
            window.tallasChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cantidad por Talla',
                        data: data,
                        backgroundColor: '#36b9cc',
                        borderColor: '#2c9faf',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var talla = context.label.replace('Talla ', '');
                                    var cantidad = context.raw;
                                    var precio = detallesTallas[talla].precio_unitario;
                                    var subtotal = detallesTallas[talla].subtotal;
                                    
                                    return [
                                        'Cantidad: ' + cantidad,
                                        'Precio unitario: $' + precio.toLocaleString('es-CL'),
                                        'Subtotal: $' + subtotal.toLocaleString('es-CL')
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Actualizar tabla si existe
        if ($('.size-details-table').length > 0) {
            var $tabla = $('.size-details-table');
            var html = '<thead><tr><th>Talla</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody>';
            
            var totalGeneral = 0;
            
            // Primero tallas numéricas
            ['4', '6', '8', '10', '12', '14'].forEach(function(talla) {
                if (detallesTallas[talla] && detallesTallas[talla].cantidad > 0) {
                    var cantidad = detallesTallas[talla].cantidad;
                    var precio = detallesTallas[talla].precio_unitario;
                    var subtotal = detallesTallas[talla].subtotal;
                    
                    totalGeneral += subtotal;
                    
                    html += '<tr>';
                    html += '<td>' + talla + '</td>';
                    html += '<td>' + cantidad + '</td>';
                    html += '<td>$' + precio.toLocaleString('es-CL') + '</td>';
                    html += '<td>$' + subtotal.toLocaleString('es-CL') + '</td>';
                    html += '</tr>';
                }
            });
            
            // Luego tallas de letras
            ['XS', 'S', 'M', 'L', 'XL'].forEach(function(talla) {
                if (detallesTallas[talla] && detallesTallas[talla].cantidad > 0) {
                    var cantidad = detallesTallas[talla].cantidad;
                    var precio = detallesTallas[talla].precio_unitario;
                    var subtotal = detallesTallas[talla].subtotal;
                    
                    totalGeneral += subtotal;
                    
                    html += '<tr>';
                    html += '<td>' + talla + '</td>';
                    html += '<td>' + cantidad + '</td>';
                    html += '<td>$' + precio.toLocaleString('es-CL') + '</td>';
                    html += '<td>$' + subtotal.toLocaleString('es-CL') + '</td>';
                    html += '</tr>';
                }
            });
            
            html += '</tbody>';
            html += '<tfoot><tr><td colspan="3">Total General:</td><td>$' + totalGeneral.toLocaleString('es-CL') + '</td></tr></tfoot>';
            
            $tabla.html(html);
        }
    }
}); 