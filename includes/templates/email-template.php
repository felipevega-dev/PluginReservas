<?php
/**
 * Genera una plantilla HTML para los correos de reserva
 */
function reserva_form_get_email_template($reserva, $detalles_productos) {
    $site_url = get_site_url();
    // Usar el logo con ruta absoluta para asegurar que funcione en correos
    $logo_url = $site_url . '/wp-content/plugins/reserva-form/assets/images/logo.jpg';
    
    $total = 0;
    $productos_html = '';
    $productos_mobile_html = '';
    
    // Al inicio de la función, después de las variables iniciales
    $email_styles = '
    <style type="text/css">
        @media only screen and (max-width: 480px) {
            .mobile-font {
                font-size: 10px !important;
            }
            .mobile-button {
                width: 100% !important;
                text-align: center !important;
                padding: 12px !important;
            }
            .mobile-padding {
                padding: 10px !important;
            }
        }
    </style>
    ';
    
    // Asegurar que $reserva es un objeto
    if (!is_object($reserva)) {
        $reserva = (object) $reserva;
    }
    
    foreach ($detalles_productos as $detalle) {
        // Calcular total
        $total += isset($detalle['subtotal']) ? $detalle['subtotal'] : 0;
        
        // Asegurar que todas las claves necesarias existen
        $producto = isset($detalle['producto']) ? $detalle['producto'] : '';
        $talla = isset($detalle['talla']) ? $detalle['talla'] : '';
        $cantidad = isset($detalle['cantidad']) ? $detalle['cantidad'] : 0;
        $precio_unitario = isset($detalle['precio_unitario']) ? $detalle['precio_unitario'] : 0;
        $subtotal = isset($detalle['subtotal']) ? $detalle['subtotal'] : 0;
        $imagen_url = isset($detalle['imagen_url']) ? $detalle['imagen_url'] : '';
        
        // Versión para escritorio
        $productos_html .= '
        <tr>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle;">' . esc_html($producto) . '</td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle;">' . esc_html($talla) . '</td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle;">' . esc_html($cantidad) . '</td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle;">$' . number_format($precio_unitario, 0, ',', '.') . '</td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; vertical-align: middle;">$' . number_format($subtotal, 0, ',', '.') . '</td>
            <td style="padding: 12px 15px; border-bottom: 1px solid #e0e0e0; text-align: center; vertical-align: middle;">
                <img src="' . esc_url($imagen_url) . '" alt="' . esc_attr($producto) . '" style="max-width: 60px; height: auto; display: block; margin: 0 auto;">
            </td>
        </tr>';
        
        // Versión para móvil
        $productos_mobile_html .= '
        <div style="border: 1px solid #e0e0e0; margin-bottom: 15px; border-radius: 5px; overflow: hidden;">
            <div style="text-align: center; background-color: #f8f8f8; padding: 8px;">
                <img src="' . esc_url($imagen_url) . '" alt="' . esc_attr($producto) . '" style="max-width: 80px; height: auto; margin: 0 auto;">
            </div>
            <div style="padding: 10px;">
                <p style="margin: 0 0 5px 0;"><strong>Producto:</strong> ' . esc_html($producto) . '</p>
                <p style="margin: 0 0 5px 0;"><strong>Talla:</strong> ' . esc_html($talla) . '</p>
                <p style="margin: 0 0 5px 0;"><strong>Cantidad:</strong> ' . esc_html($cantidad) . '</p>
                <p style="margin: 0 0 5px 0;"><strong>Precio:</strong> $' . number_format($precio_unitario, 0, ',', '.') . '</p>
                <p style="margin: 0 0 5px 0; font-weight: bold;"><strong>Subtotal:</strong> $' . number_format($subtotal, 0, ',', '.') . '</p>
            </div>
        </div>';
    }
    
    // Si no hay un total calculado, usar el de la reserva si existe
    if ($total == 0 && isset($reserva->total)) {
        $total = $reserva->total;
    }
    
    // Asegurar que tenemos valores para todos los campos requeridos
    $direccion = isset($reserva->direccion) ? $reserva->direccion : 'No especificada';
    $comuna = isset($reserva->comuna) ? $reserva->comuna : '';
    $direccion_completa = $direccion;
    if (!empty($comuna)) {
        $direccion_completa .= ', ' . $comuna;
    }
    
    $fecha_formateada = isset($reserva->fecha) ? date('d/m/Y', strtotime($reserva->fecha)) : date('d/m/Y');
    
    // HTML para la plantilla completa
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="supported-color-schemes" content="light">
        <title>Reserva Confirmada</title>
        ' . $email_styles . '
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f9f9f9;
            }
            .header {
                text-align: center;
                padding: 20px 0;
                background-color: #fff;
                border-bottom: 2px solid #f0f0f0;
            }
            .logo {
                max-width: 180px;
                height: auto;
            }
            .content {
                background-color: #ffffff;
                padding: 30px;
                border-radius: 8px;
                margin: 20px 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
            h1 {
                color: #c52f2e;
                font-size: 24px;
                margin-bottom: 20px;
                text-align: center;
            }
            h2 {
                color: #003366;
                font-size: 18px;
                margin-top: 25px;
                margin-bottom: 15px;
            }
            p {
                margin-bottom: 15px;
            }
            .detail {
                background-color: #f5f5f5;
                padding: 10px 15px;
                border-radius: 4px;
                margin-bottom: 10px;
                border: 1px solid #e0e0e0;
                display: block !important;
            }
            .detail p {
                margin: 5px 0;
                display: block !important;
                visibility: visible !important;
            }
            .detail span,
            .detail strong,
            .mobile-view * {
                visibility: visible !important;
                display: inline !important;
            }
            .mobile-view div {
                display: block !important;
                margin-bottom: 15px !important;
            }
            .mobile-view p {
                display: block !important;
                margin: 5px 0 !important;
            }
            .table-container {
                width: 100%;
                overflow-x: auto;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 12px 15px;
                text-align: left;
            }
            .table tr:last-child td {
                border-bottom: none;
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .footer {
                text-align: center;
                padding: 20px;
                font-size: 12px;
                color: #777;
                border-top: 1px solid #e0e0e0;
            }
            .button {
                display: inline-block;
                background-color: #c52f2e;
                color: white;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 4px;
                font-weight: bold;
                margin: 15px 0;
            }
            .info-box {
                background-color: #e8f4f8;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
                border-left: 4px solid #0073aa;
            }
            .mobile-view {
                display: none;
            }
            .desktop-view {
                display: block;
            }
            .total-row {
                background-color: #f0f0f0;
                padding: 10px 15px;
                border-radius: 4px;
                margin-top: 15px;
                text-align: right;
                font-weight: bold;
            }
            
            /* Estilos para dispositivos móviles */
            @media only screen and (max-width: 600px) {
                .container {
                    width: 100% !important;
                    padding: 10px !important;
                }
                .content {
                    padding: 15px !important;
                }
                .desktop-view {
                    display: none !important;
                }
                .mobile-view {
                    display: block !important;
                }
                h1 {
                    font-size: 20px !important;
                }
                h2 {
                    font-size: 16px !important;
                }
                p, .detail, .info-box {
                    font-size: 14px !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . esc_url($logo_url) . '" alt="Scolari" class="logo">
            </div>
            
            <div class="content">
                <h1>¡Reserva Confirmada!</h1>
                
                <p>Estimado/a <strong>' . esc_html($reserva->nombre) . '</strong>,</p>
                
                <p>Hemos recibido correctamente tu reserva. A continuación te dejamos los detalles:</p>
                
                <div class="detail" style="background-color: #f5f5f5 !important; padding: 10px 15px !important; border-radius: 4px !important; margin-bottom: 10px !important; border: 1px solid #e0e0e0 !important; display: block !important;">
                    <p style="margin: 5px 0 !important; font-size: 16px !important; display: block !important; visibility: visible !important; color: #333 !important;"><strong style="color: #333 !important; font-weight: bold !important; display: inline !important;">Número de reserva:</strong> <span style="display: inline !important;">' . esc_html($reserva->id) . '</span></p>
                    <p style="margin: 5px 0 !important; font-size: 16px !important; display: block !important; visibility: visible !important; color: #333 !important;"><strong style="color: #333 !important; font-weight: bold !important; display: inline !important;">Fecha de necesidad:</strong> <span style="display: inline !important;">' . $fecha_formateada . '</span></p>
                    <p style="margin: 5px 0 !important; font-size: 16px !important; display: block !important; visibility: visible !important; color: #333 !important;"><strong style="color: #333 !important; font-weight: bold !important; display: inline !important;">Dirección:</strong> <span style="display: inline !important;">' . esc_html($direccion_completa) . '</span></p>
                    <p style="margin: 5px 0 !important; font-size: 16px !important; display: block !important; visibility: visible !important; color: #333 !important;"><strong style="color: #333 !important; font-weight: bold !important; display: inline !important;">Teléfono:</strong> <span style="display: inline !important;">' . esc_html($reserva->telefono) . '</span></p>
                </div>
                
                <h2>Productos reservados</h2>
                
                <!-- Vista para escritorio -->
                <div class="desktop-view">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Talla</th>
                                    <th>Cantidad</th>
                                    <th>Precio</th>
                                    <th>Subtotal</th>
                                    <th>Imagen</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $productos_html . '
                                <tr>
                                    <td colspan="4" style="padding: 14px 15px; text-align: right;"><strong>Total:</strong></td>
                                    <td style="padding: 14px 15px; background-color: #f0f0f0; color: #0073aa; font-weight: bold;">$' . number_format($total, 0, ',', '.') . '</td>
                                    <td style="padding: 14px 15px; background-color: #f0f0f0;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Vista para móvil -->
                <div class="mobile-view" style="display: none; max-width: 100% !important;">
                    ' . $productos_mobile_html . '
                    <div class="total-row" style="background-color: #f0f0f0 !important; padding: 15px !important; border-radius: 8px !important; margin-top: 15px !important; text-align: right !important; font-weight: bold !important; display: block !important; border-left: 4px solid #0073aa !important; box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;">
                        <strong style="color: #0073aa !important; font-weight: bold !important;">Total: $' . number_format($total, 0, ',', '.') . '</strong>
                    </div>
                </div>
                
                <div class="info-box" style="background-color: #e8f4f8 !important; padding: 18px !important; border-radius: 8px !important; margin: 20px 0 !important; border-left: 4px solid #0073aa !important; box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;">
                    <p style="margin: 5px 0 !important;">Pronto nos comunicaremos contigo para coordinar la entrega. Si tienes alguna consulta, no dudes en contactarnos.</p>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <p style="margin-bottom: 5px; color: #666;">¿Tienes alguna pregunta?</p>
                    <a href="mailto:contacto@scolari.cl" style="display: inline-block; background-color: #c52f2e; color: white; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; margin: 10px 0; text-align: center; box-shadow: 0 2px 5px rgba(197,47,46,0.2);">Contáctanos</a>
                </div>
            </div>
            
            <div class="footer" style="text-align: center; padding: 20px; font-size: 12px; color: #777; border-top: 1px solid #e0e0e0; background-color: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <p style="margin: 5px 0;">Este correo fue enviado desde el sistema de reservas de Scolari.</p>
                <p style="margin: 5px 0;">© ' . date('Y') . ' Scolari - Todos los derechos reservados</p>
                <p style="margin-top: 10px; font-size: 11px;" class="mobile-font">Desarrollado por <a href="https://github.com/felipevega-dev" target="_blank" style="color: #0073aa; text-decoration: none;"><span style="vertical-align: middle;">Felipe Vega</span> <img src="https://github.githubassets.com/favicons/favicon.svg" alt="GitHub" style="width: 12px; height: 12px; vertical-align: middle; margin-left: 3px;"></a></p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return $html;
} 