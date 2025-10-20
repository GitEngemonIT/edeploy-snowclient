<?php
include ('../../../inc/includes.php');

// Verificar permissões
Session::checkLoginUser();

// Carregar template
$template = file_get_contents(GLPI_ROOT . '/plugins/snowclient/templates/solution_modal.html.twig');

// Enviar template
header('Content-Type: text/html');
echo $template;