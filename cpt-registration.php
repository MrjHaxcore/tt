<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
function leadmahu_register_cpts()
{
    // Registro de Leads
    $labels_lead = array(
        'name' => 'Leads',
        'singular_name' => 'Lead',
        'add_new' => 'Añadir Nuevo',
        'add_new_item' => 'Añadir Nuevo Lead',
        'edit_item' => 'Editar Lead',
        'new_item' => 'Nuevo Lead',
        'all_items' => 'Todos los Leads',
        'view_item' => 'Ver Lead',
        'search_items' => 'Buscar Leads',
        'not_found' => 'No se encontraron Leads',
        'not_found_in_trash' => 'No se encontraron Leads en la papelera',
        'menu_name' => 'Leads'
    );
    $args_lead = array(
        'labels' => $labels_lead,
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-businessman',
        'has_archive' => true,
    );
    register_post_type('leadmahu_lead', $args_lead);    /* Registro de Formularios */
    $labels_form = array(
        'name' => 'Formularios leadmahu',
        'singular_name' => 'Formulario leadmahu',
        'add_new_item' => 'Añadir Nuevo Formulario',
        'edit_item' => 'Editar Formulario',
        'new_item' => 'Nuevo Formulario',
        // Removed 'all_items' entry
        'menu_name' => '' // Empty string to hide from menu
    );
    $args_form = array(
        'labels' => $labels_form,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false, // This hides it from the admin menu
        'supports' => array('title'),
        'has_archive' => false,
    );
    register_post_type('leadmahu_form', $args_form);
}
add_action('init', 'leadmahu_register_cpts');
