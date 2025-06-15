<?php
/* 5.6 Página Formularios: Generador de Formularios */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function leadmahu_form_generator_page()
{   
    // Solo verificar nonce cuando estamos procesando datos POST del formulario
    if (isset($_POST['submit'])) {
        if (!isset($_POST['leadmahu_form_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_form_nonce_field'])), 'leadmahu_form_nonce')) {
            wp_die('Error de seguridad: El formulario ha expirado. Por favor, vuelve atrás y recarga la página.');
        }
    }
    
    // Verificar nonce solo cuando se muestra un mensaje GET y tiene un nonce
    if (isset($_GET['message']) && isset($_GET['leadmahu_nonce'])) {
        check_admin_referer('leadmahu_form_message', 'leadmahu_nonce');
    }
    
    // Si no estamos en la página correcta, salir
    if (!isset($_GET['page']) || $_GET['page'] !== 'leadmahu_form_generator') {
        return;
    }
    $available_fields = array(
        'name' => 'Nombre',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'message' => 'Mensaje',
        'company' => 'Empresa',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'state' => 'Estado/Provincia',
        'postal' => 'Código Postal'
    );    
    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Generador de Formularios Embebidos</h1>
        <h2>Configuración y Creación</h2>
        <?php if (isset($_GET['message']) && $_GET['message'] == 'form_saved'): ?>
            <div class="notice notice-success is-dismissible">
                <p>Formulario guardado exitosamente.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">            <?php wp_nonce_field('leadmahu_save_form_nonce', 'leadmahu_form_nonce_field'); ?>
            <input type="hidden" name="action" value="leadmahu_save_form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="form_title">Título del Formulario</label></th>
                    <td><input name="form_title" type="text" id="form_title" value="" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row">Selecciona los Campos a Incluir</th>
                    <td>
                        <div class="available-fields-container">
                            <?php foreach ($available_fields as $field_key => $field_label): ?>
                                <label><input type="checkbox" name="form_fields[]" value="<?php echo esc_attr($field_key); ?>">
                                    <?php echo esc_html($field_label); ?></label>
                                <label><input type="checkbox" name="form_required[<?php echo esc_attr($field_key); ?>]" value="1">
                                    Requerido</label><br>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Campos Personalizados</th>
                    <td>
                        <div id="custom-fields-container">
                            <div class="custom-field">
                                <label>Nombre del Campo:</label>
                                <input type="text" name="custom_field_names[]" required>
                                <label>Tipo de Campo:</label>
                                <select name="custom_field_types[]">
                                    <option value="text">Texto</option>
                                    <option value="email">Email</option>
                                    <option value="textarea">Area de Texto</option>
                                    <option value="number">Número</option>
                                </select>
                                <label><input type="checkbox" name="custom_field_required[]" value="1">
                                    Requerido</label>
                                <div class="number-limits" style="display: none;">
                                    <label>Mínimo:</label>
                                    <input type="number" name="custom_field_min[]" min="0">
                                    <label>Máximo:</label>
                                    <input type="number" name="custom_field_max[]" min="0">
                                </div>
                                <button type="button" class="remove-custom-field">Eliminar</button>
                            </div>
                        </div>
                        <button type="button" id="add-custom-field">Añadir Campo Personalizado</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="recipient_email">Email de Destino</label></th>
                    <td><input name="recipient_email" type="email" id="recipient_email"
                            value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="subject_client">Asunto para el Cliente</label></th>
                    <td><input name="subject_client" type="text" id="subject_client" value="Confirmación de envío"
                            class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="client_default_text">Texto por Defecto para el Cliente</label></th>
                    <td><textarea name="client_default_text" id="client_default_text" rows="5" class="large-text"
                            required>Gracias por contactarnos. Nos pondremos en contacto contigo pronto.</textarea></td>
                </tr>
            </table>
            <?php submit_button('Guardar Formulario'); ?>
        </form>
        <h2>Shortcode del Formulario Embebido</h2>
        <p>Utiliza el siguiente shortcode para embeber el formulario en tus páginas o entradas:</p>        <code>[leadmahu_embedded_form id="ID_DEL_FORMULARIO"]</code>
    </div>
    <?php
}

/* 5.7 Procesar Guardado de Nuevo Formulario */
function leadmahu_save_form()
{
    if (!isset($_POST['leadmahu_form_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_form_nonce_field'])), 'leadmahu_save_form_nonce')) {
        wp_die('Nonce inválido.');
    }
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    $form_title = isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '';
    $form_fields = isset($_POST['form_fields']) ? array_map('sanitize_text_field', wp_unslash($_POST['form_fields'])) : array();
    $recipient_email = isset($_POST['recipient_email']) ? sanitize_email(wp_unslash($_POST['recipient_email'])) : '';
    $subject_client = isset($_POST['subject_client']) ? sanitize_text_field(wp_unslash($_POST['subject_client'])) : '';
    $client_default_text = isset($_POST['client_default_text']) ? sanitize_textarea_field(wp_unslash($_POST['client_default_text'])) : '';

    // Guardar campos personalizados
    $custom_field_names = isset($_POST['custom_field_names']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_names'])) : array();
    $custom_field_types = isset($_POST['custom_field_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_types'])) : array();


    $custom_fields = array();
    foreach ($custom_field_names as $key => $name) {
        $custom_fields[] = array(
            'name' => $name,
            'type' => $custom_field_types[$key],
            'required' => isset($_POST['custom_field_required'][$key]) ? 1 : 0,
            'min' => isset($_POST['custom_field_min'][$key]) ? sanitize_text_field(wp_unslash($_POST['custom_field_min'][$key])) : '',
            'max' => isset($_POST['custom_field_max'][$key]) ? sanitize_text_field(wp_unslash($_POST['custom_field_max'][$key])) : ''
        );
    }

    $form_post = array(
        'post_title' => $form_title,
        'post_status' => 'publish',
        'post_type' => 'leadmahu_form'
    );
    $form_id = wp_insert_post($form_post);

    if ($form_id && !is_wp_error($form_id)) {
        update_post_meta($form_id, '_form_fields', $form_fields);
        update_post_meta($form_id, '_recipient_email', $recipient_email);
        update_post_meta($form_id, '_subject_client', $subject_client);
        update_post_meta($form_id, '_client_default_text', $client_default_text);
        update_post_meta($form_id, '_custom_fields', $custom_fields); // Guardar campos personalizados

        $available_fields = array(
            'name' => 'Nombre',
            'email' => 'Email',
            'phone' => 'Teléfono',
            'message' => 'Mensaje',
            'company' => 'Empresa',
            'address' => 'Dirección',
            'city' => 'Ciudad',
            'state' => 'Estado/Provincia',
            'postal' => 'Código Postal'
        );

        // Guardar la configuración de campos requeridos
        $form_required = isset($_POST['form_required']) ? array_map('sanitize_text_field', wp_unslash($_POST['form_required'])) : array();
        foreach ($available_fields as $field_key => $field_label) {
            $required = isset($form_required[$field_key]) ? 1 : 0;
            update_post_meta($form_id, '_form_required_' . $field_key, $required);
        }

        wp_redirect(admin_url('admin.php?page=leadmahu_form_generator&message=form_saved'));
        exit;
    } else {
        wp_die('Error al guardar el formulario.');
    }
}
add_action('admin_post_leadmahu_save_form', 'leadmahu_save_form');

/* 5.8 Listado de Formularios */
function leadmahu_list_forms_page()
{
    $forms = get_posts(array(
        'post_type' => 'leadmahu_form',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));
    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Listado de Formularios Generados</h1>
        <?php if (empty($forms)): ?>
            <p>No se han generado formularios.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Shortcode</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td><?php echo esc_html($form->ID); ?></td>
                            <td><?php echo esc_html($form->post_title); ?></td>
                            <td><code>[leadmahu_embedded_form id="<?php echo esc_attr($form->ID); ?>"]</code></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_edit_form&form_id=' . $form->ID)); ?>">Editar</a>
                                     | 
                                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=leadmahu_delete_form&form_id=' . $form->ID . '&_wpnonce=' . wp_create_nonce('delete_form_nonce'))); ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este formulario?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/* 5.9 Página de Edición de Formularios */
function leadmahu_edit_form_page()
{
    // Verificar que el usuario tiene permisos para gestionar opciones
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Lo sentimos, no tienes permisos para acceder a esta página.', 'lead-management-hub'));
    }
    
    // Para edición de formularios, el nonce es opcional en el acceso inicial
    // ya que esto es un acceso legítimo desde la lista de formularios
    if (isset($_GET['form_id'])) {
        $form_id = intval($_GET['form_id']);
        
        // Solo verificamos el nonce si está presente
        if (isset($_GET['_wpnonce'])) {
            // La verificación se hace pero no se genera ningún log de depuración
            wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'edit_form_' . $form_id);
        }
    } else {
        $form_id = 0;
    }
    
    
    if (!$form_id) {
        echo '<div class="notice notice-error"><p>Formulario no encontrado.</p></div>';
        return;
    }
    
    $form = get_post($form_id);
    if (!$form || $form->post_type != 'leadmahu_form') {
        echo '<div class="notice notice-error"><p>Formulario no encontrado.</p></div>';
        return;
    }
    $form_fields = get_post_meta($form_id, '_form_fields', true);
    $recipient_email = get_post_meta($form_id, '_recipient_email', true);
    $subject_client = get_post_meta($form_id, '_subject_client', true);
    $client_default_text = get_post_meta($form_id, '_client_default_text', true);
    $custom_fields = get_post_meta($form_id, '_custom_fields', true); // Recuperar campos personalizados

    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Editar Formulario: <?php echo esc_html($form->post_title); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">            <?php wp_nonce_field('leadmahu_edit_form_nonce', 'leadmahu_edit_form_nonce_field'); ?>
            <input type="hidden" name="action" value="leadmahu_save_form_edit">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="form_title">Título del Formulario</label></th>
                    <td><input name="form_title" type="text" id="form_title"
                            value="<?php echo esc_attr($form->post_title); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row">Campos a Incluir</th>
                    <td>
                        <?php
                        $available_fields = array(
                            'name' => 'Nombre',
                            'email' => 'Email',
                            'phone' => 'Teléfono',
                            'message' => 'Mensaje',
                            'company' => 'Empresa',
                            'address' => 'Dirección',
                            'city' => 'Ciudad',
                            'state' => 'Estado/Provincia',
                            'postal' => 'Código Postal'
                        );
                        foreach ($available_fields as $field_key => $field_label): ?>
                            <label>
                                <input type="checkbox" name="form_fields[]" value="<?php echo esc_attr($field_key); ?>" <?php checked(is_array($form_fields) && in_array($field_key, $form_fields)); ?>>
                                <?php echo esc_html($field_label); ?>
                            </label>
                            <?php 
                            $required = get_post_meta($form_id, '_form_required_' . $field_key, true);
                            ?>
                            <label><input type="checkbox" name="form_required[<?php echo esc_attr($field_key); ?>]" value="1" <?php checked($required, 1); ?>>
                                Requerido</label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Campos Personalizados</th>
                    <td>
                        <div id="custom-fields-container">
                            <?php
                            if ($custom_fields) {
                                foreach ($custom_fields as $custom_field) {
                                    echo '<div class="custom-field">';
                                    echo '<label>Nombre del Campo:</label><input type="text" name="custom_field_names[]" value="' . esc_attr($custom_field['name']) . '" required>';
                                    echo '<label>Tipo de Campo:</label><select name="custom_field_types[]">';
                                    echo '<option value="text" ' . selected($custom_field['type'], 'text') . '>Texto</option>';
                                    echo '<option value="email" ' . selected($custom_field['type'], 'email') . '>Email</option>';
                                    echo '<option value="textarea" ' . selected($custom_field['type'], 'textarea') . '>Area de Texto</option>';
                                    echo '<option value="number" ' . selected($custom_field['type'], 'number') . '>Número</option>';
                                    echo '</select>';
                                    $custom_field_required = isset($custom_field['required']) && $custom_field['required'] == 1 ? 'checked' : '';
                                    echo '<label><input type="checkbox" name="custom_field_required[]" value="1" ' . esc_attr($custom_field_required) . '>Requerido</label>';
                                    echo '<div class="number-limits" style="display: none;">';
                                    echo '<label>Mínimo:</label><input type="number" name="custom_field_min[]" value="' . esc_attr(isset($custom_field['min']) ? $custom_field['min'] : '') . '" min="0">';
                                    echo '<label>Máximo:</label><input type="number" name="custom_field_max[]"  value="' . esc_attr(isset($custom_field['max']) ? $custom_field['max'] : '') . '" min="0">';
                                    echo '</div>';
                                    echo '<button type="button" class="remove-custom-field">Eliminar</button>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                        <button type="button" id="add-custom-field">Añadir Campo Personalizado</button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="recipient_email">Email de Destino</label></th>
                    <td><input name="recipient_email" type="email" id="recipient_email"
                            value="<?php echo esc_attr($recipient_email); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="subject_client">Asunto para el Cliente</label></th>
                    <td><input name="subject_client" type="text" id="subject_client"
                            value="<?php echo esc_attr($subject_client); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="client_default_text">Texto por Defecto para el Cliente</label></th>
                    <td><textarea name="client_default_text" id="client_default_text" rows="5" class="large-text"
                            required><?php echo esc_textarea($client_default_text); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button('Actualizar Formulario'); ?>        </form>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_form_list')); ?>">Volver al Listado de Formularios</a></p>
    </div>
    <?php
}

/* 5.10 Procesar Edición de Formulario */
function leadmahu_save_form_edit()
{
    if (!isset($_POST['leadmahu_edit_form_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_edit_form_nonce_field'])), 'leadmahu_edit_form_nonce')) {
        wp_die('Nonce inválido.');
    }
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    if (!$form_id) {
        wp_die('Formulario no encontrado.');
    }
    $form_title = isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '';
    $form_fields = isset($_POST['form_fields']) ? array_map('sanitize_text_field', wp_unslash($_POST['form_fields'])) : array();
    $recipient_email = isset($_POST['recipient_email']) ? sanitize_email(wp_unslash($_POST['recipient_email'])) : '';
    $subject_client = isset($_POST['subject_client']) ? sanitize_text_field(wp_unslash($_POST['subject_client'])) : '';
    $client_default_text = isset($_POST['client_default_text']) ? sanitize_textarea_field(wp_unslash($_POST['client_default_text'])) : '';

    // Guardar campos personalizados
    $custom_field_names = isset($_POST['custom_field_names']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_names'])) : array();
    $custom_field_types = isset($_POST['custom_field_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_types'])) : array();
    $custom_field_required = isset($_POST['custom_field_required']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_required'])) : array();
    $custom_field_min = isset($_POST['custom_field_min']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_min'])) : array();
    $custom_field_max = isset($_POST['custom_field_max']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_field_max'])) : array();

    $custom_fields = array();
    foreach ($custom_field_names as $key => $name) {
        $custom_fields[] = array(
            'name' => $name,
            'type' => $custom_field_types[$key],
            'required' => isset($_POST['custom_field_required'][$key]) ? 1 : 0,
            'min' => isset($_POST['custom_field_min'][$key]) ? sanitize_text_field(wp_unslash($_POST['custom_field_min'][$key])) : '',
            'max' => isset($_POST['custom_field_max'][$key]) ? sanitize_text_field(wp_unslash($_POST['custom_field_max'][$key])) : ''
        );
    }

    $post_data = array(
        'ID' => $form_id,
        'post_title' => $form_title,
    );
    wp_update_post($post_data);
    update_post_meta($form_id, '_form_fields', $form_fields);
    update_post_meta($form_id, '_recipient_email', $recipient_email);
    update_post_meta($form_id, '_subject_client', $subject_client);
    update_post_meta($form_id, '_client_default_text', $client_default_text);
    update_post_meta($form_id, '_custom_fields', $custom_fields); // Guardar campos personalizados

    // Guardar la configuración de campos requeridos
    $form_required = isset($_POST['form_required']) ? array_map('sanitize_text_field', wp_unslash($_POST['form_required'])) : array();
    
    // Definir los campos disponibles
    $available_fields = array(
        'name' => 'Nombre',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'message' => 'Mensaje',
        'company' => 'Empresa',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'state' => 'Estado/Provincia',
        'postal' => 'Código Postal'
    );
    
    foreach ($available_fields as $field_key => $field_label) {
        $required = isset($form_required[$field_key]) ? 1 : 0;
        update_post_meta($form_id, '_form_required_' . $field_key, $required);
    }

    wp_redirect(admin_url('admin.php?page=leadmahu_edit_form&form_id=' . $form_id . '&message=form_updated'));
    exit;
}
add_action('admin_post_leadmahu_save_form_edit', 'leadmahu_save_form_edit');