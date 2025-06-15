<?php
// Agregar esta función para registrar el menú y submenús en el admin


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function leadmahu_register_menu()
{
    // Página principal del plugin
    add_menu_page(
        'Lead Hub', // Título de la página (cuando se abre)
        'Lead Hub', // Título del menú (en la barra lateral)
        'manage_options',
        'leadmahu_dashboard', // Cambiado a dashboard con nuevo prefijo
        'leadmahu_render_dashboard', // Cambiado a la función del dashboard con nuevo prefijo
        'dashicons-businessman',
	    6 // Position of the menu
    );

    // Añadir submenús
    add_submenu_page(
        'leadmahu_dashboard', // Parent slug
        'Gestión de Leads', // Page title
        'Gestión de Leads', // Menu title
        'manage_options', // Capability
        'leadmahu_dashboard', // Menu slug (mismo que el parent para que sea la página principal)
        'leadmahu_render_dashboard' // Function callback
    );    add_submenu_page(
        'leadmahu_dashboard',
        'Gestión de Estados y Exportación CSV',
        'Gestión de Estados y Exportación CSV',
        'manage_options',
        'leadmahu_general',
        'leadmahu_render_general_page'
    );    
    
    add_submenu_page(
        'leadmahu_dashboard',
        'Añadir/Editar Lead',
        'Añadir Lead',
        'manage_options',
        'leadmahu_edit_lead',
        'leadmahu_render_add_edit_lead_page'
    );
    
    // Registramos Ver Lead con la misma capacidad que las otras páginas para que sea accesible
    // pero luego lo ocultaremos del menú manualmente
    add_submenu_page(
        'leadmahu_dashboard',
        'Ver Lead', // Título de la página
        'Ver Lead', // Texto del menú (se ocultará después)
        'manage_options', // Misma capacidad que otras páginas para permitir acceso
        'leadmahu_view_lead', // Slug de la página
        'leadmahu_render_view_leads_page'
    );

    add_submenu_page(
        'leadmahu_dashboard',
        'Generar Formulario',
        'Generar Formulario',
        'manage_options',
        'leadmahu_form_generator',
        'leadmahu_form_generator_page'
    );

    add_submenu_page(
        'leadmahu_dashboard',
        'Listado de Formularios',
        'Listado de Formularios',
        'manage_options',
        'leadmahu_form_list',
        'leadmahu_list_forms_page'
    );    // Registramos Editar Formulario con permisos reales, pero lo ocultaremos después visualmente
    add_submenu_page(
        'leadmahu_dashboard',
        'Editar Formulario', // Título de la página
        'Editar Formulario', // Texto del menú (se ocultará después)
        'manage_options', // Capacidad real que tienen los administradores
        'leadmahu_edit_form', // Slug de la página
        'leadmahu_edit_form_page'
    );
      add_submenu_page(
        'leadmahu_dashboard',
        'Premium',
        'Premium',
        'manage_options',
        'leadmahu_premium',
        'leadmahu_render_premium_page'
    );
    
    // Ocultamos elementos del menú después de que se hayan registrado correctamente
    // Esto es crucial: Primero registramos con los permisos correctos, luego ocultamos visualmente
    global $submenu;
    if (isset($submenu['leadmahu_dashboard'])) {
        // Recorremos el menú buscando las páginas que queremos ocultar
        foreach ($submenu['leadmahu_dashboard'] as $key => $item) {
            // Si el slug coincide con los que queremos ocultar, establecemos una marca CSS
            if ($item[2] === 'leadmahu_view_lead' || $item[2] === 'leadmahu_edit_form' || $item[2] === 'leadmahu_edit_lead') {
                // Agregamos CSS para ocultar este item específico
                $submenu['leadmahu_dashboard'][$key][4] = 'leadmahu-hidden-menu-item';
            }
        }
    }
}
add_action('admin_menu', 'leadmahu_register_menu');

// Añadimos CSS para ocultar los elementos del menú marcados
function leadmahu_hide_menu_items_css() {
    // Registrar el estilo inline con una versión
    wp_register_style('leadmahu-admin-menu-fixes', false, array(), LEADMAHU_VERSION);
    // Encolar el estilo en el admin
    wp_enqueue_style('leadmahu-admin-menu-fixes');
    // Agregar el CSS inline al estilo encolado
    wp_add_inline_style('leadmahu-admin-menu-fixes', '
        .leadmahu-hidden-menu-item {
            display: none !important;
        }
    ');
}
add_action('admin_enqueue_scripts', 'leadmahu_hide_menu_items_css');

// Callback para la página principal del plugin
function leadmahu_main_page_callback()
{
    echo '<div class="wrap"><h1>Lead Management Hub</h1><p>Página principal del leadmahu.</p></div>';
}

function leadmahu_general_callback()
{
    // Si se solicita exportar CSV, procesarlo y salir inmediatamente sin renderizar nada más
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // Limpiar buffers para evitar contenido irrelevante
        if (ob_get_length()) {
            ob_end_clean();
        }
        $estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';
        $fecha_inicio = isset($_GET['fecha_inicio']) ? sanitize_text_field(wp_unslash($_GET['fecha_inicio'])) : '';
        $fecha_fin = isset($_GET['fecha_fin']) ? sanitize_text_field(wp_unslash($_GET['fecha_fin'])) : '';

        $args = array(
            'post_type' => 'leadmahu_lead',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        if (!empty($estado)) {
            $args['meta_query'][] = array(
                'key' => '_leadmahu_status',
                'value' => $estado,
                'compare' => '='
            );
        }

        if (!empty($fecha_inicio) || !empty($fecha_fin)) {
            $date_query = array();
            if (!empty($fecha_inicio)) {
                $date_query['after'] = $fecha_inicio;
            }
            if (!empty($fecha_fin)) {
                $date_query['before'] = $fecha_fin;
            }
            $args['date_query'] = array($date_query);
        }

        $leads = get_posts($args);
        $csv_data = '';
        $csv_data .= implode(',', array('ID', 'Título', 'Email', 'Teléfono', 'Empresa', 'Estado', 'Observaciones', 'Fecha')) . "\n";
        foreach ($leads as $lead) {
            $csv_data .= implode(',', array(
                $lead->ID,
                $lead->post_title,
                get_post_meta($lead->ID, '_leadmahu_email', true),
                get_post_meta($lead->ID, '_leadmahu_phone', true),
                get_post_meta($lead->ID, '_leadmahu_company', true),
                get_post_meta($lead->ID, '_leadmahu_status', true),
                get_post_meta($lead->ID, '_leadmahu_observations', true),
                $lead->post_date
            )) . "\n";
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads_export.csv');
        echo esc_textarea($csv_data);
        exit;
    }

    // Si accedemos a la página sin exportar, mostrar el formulario de filtros
    echo '<div class="wrap">';
    echo '<h1>Exportar Leads en CSV</h1>';
      // Procesar actualización de estados
    if (isset($_POST['action']) && $_POST['action'] == 'leadmahu_save_states') {
        // Verificar nonce para seguridad
        if (!isset($_POST['leadmahu_states_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_states_nonce'])), 'leadmahu_save_states_nonce')) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error de verificación de seguridad.', 'lead-management-hub') . '</p></div>';
        } 
        else if (isset($_POST['states'])) {
            // Sanitizar array de estados
            $states_sanitized = array_map('sanitize_text_field', wp_unslash($_POST['states']));
            update_option('leadmahu_states', $states_sanitized);
            
            if (isset($_POST['new_state']) && !empty($_POST['new_state'])) {
                $new_state = sanitize_text_field(wp_unslash($_POST['new_state']));
                $states = get_option('leadmahu_states', array());
                if (!in_array($new_state, $states)) {
                    $states[] = $new_state;
                    update_option('leadmahu_states', $states);
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Estados actualizados correctamente.', 'lead-management-hub') . '</p></div>';
        }
    }

    // Mostrar el resto de la página
    $states = get_option('leadmahu_states', array('nuevo', 'contactado', 'convertido', 'perdido'));
    ?>    <div class="card">
        <h2>Gestión de Estados</h2>
        <form method="post">
            <input type="hidden" name="action" value="leadmahu_save_states">
            <?php wp_nonce_field('leadmahu_save_states_nonce', 'leadmahu_states_nonce'); ?>
            <?php 
            foreach ($states as $state) {
                echo '<div>';
                echo '<input type="text" name="states[]" value="' . esc_attr($state) . '">';
                echo '</div>';
            }
            ?>
            <p><label>Nuevo Estado: <input type="text" name="new_state"></label></p>
            <p><input type="submit" class="button button-primary" value="Guardar Estados"></p>
        </form>
    </div>
      <div class="card">
        <h2>Exportar en CSV</h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="leadmahu_general">
            <input type="hidden" name="export" value="csv">
            <?php wp_nonce_field('leadmahu_export_csv_nonce', 'leadmahu_export_nonce'); ?>
            
            <p>
                <label><strong>Estado:</strong>
                    <select name="estado">
                        <option value="">Todos</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo esc_attr($state); ?>"><?php echo esc_html($state); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            
            <p>
                <label><strong>Fecha Inicio:</strong> <input type="date" name="fecha_inicio"></label>
            </p>
            
            <p>
                <label><strong>Fecha Fin:</strong> <input type="date" name="fecha_fin"></label>
            </p>
            
            <p>
                <input type="submit" class="button button-primary" value="Exportar a CSV">
            </p>
        </form>
    </div>
    <?php
    echo '</div>';
}

// Crear página premium
function leadmahu_render_premium_page() {
    // Verificar permisos del usuario
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'lead-management-hub'));
    }
    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Mejora a Premium - Funcionalidades Avanzadas</h1>
        <div class="card">
            <h2>¡Actualiza a la versión Premium!</h2>
            <p>Desbloquea todas las funcionalidades avanzadas para gestionar tus leads y clientes:</p>
            <ul style="list-style-type: disc; padding-left: 20px; margin-bottom: 20px;">
                <li>Integración con Odoo ERP</li>
                <li>Automatizaciones y flujos de trabajo</li>
                <li>Reportes avanzados</li>
                <li>Dashboard personalizado</li>
                <li>Campos personalizados ilimitados</li>
                <li>Soporte prioritario</li>
            </ul>
            <p><a href="https://synsighthub.com/lead-management-hub-wordpress-odoo/" class="button button-primary" target="_blank">Ver Planes Premium</a></p>
        </div>
    </div>
    <?php
}