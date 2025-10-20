/**
 * Add necessary CSS and JavaScript files for solution modal
 */
function plugin_snowclient_add_solution_resources()
{
    // Adicionar CSS
    echo '<link rel="stylesheet" type="text/css" href="../plugins/snowclient/css/solution_modal.css">';
    
    // Adicionar JavaScript
    echo '<script type="text/javascript" src="../plugins/snowclient/js/solution_modal.js"></script>';
}

// Adicionar hook para incluir recursos
Plugin::registerClass('PluginSnowclientConfig', [
    'addtoupdate' => ['Ticket']
]);

if (isset($_SESSION['glpiactiveprofile'])) {
    // Adicionar recursos apenas se estiver na página de ticket
    if (strpos($_SERVER['REQUEST_URI'], '/front/ticket.form.php') !== false) {
        plugin_snowclient_add_solution_resources();
    }
}