<?php

/**
 * Add necessary CSS and JavaScript files for solution modal
 */
function plugin_snowclient_add_solution_resources($hook_params = [])
{
    if (!isset($_SESSION['glpiactiveprofile'])) {
        return;
    }

    // Adicionar recursos apenas se estiver na página de ticket
    if (strpos($_SERVER['REQUEST_URI'], '/front/ticket.form.php') === false) {
        return;
    }

    // Usar o hook post_item_form para injetar os recursos
    if (isset($hook_params['item']) && $hook_params['item'] instanceof Ticket) {
        echo "<link rel='stylesheet' type='text/css' href='" . Plugin::getWebDir('snowclient') . "/css/solution_modal.css'>";
        echo "<script type='text/javascript' src='" . Plugin::getWebDir('snowclient') . "/js/solution_modal.js'></script>";
    }
}