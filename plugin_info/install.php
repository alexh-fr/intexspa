<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function intexspa_install() {
    // Actions à effectuer lors de l'installation
    log::add('intexspa', 'info', 'Installation du plugin Intex Spa');
}

function intexspa_update() {
    // Actions à effectuer lors de la mise à jour
    log::add('intexspa', 'info', 'Mise à jour du plugin Intex Spa');
}

function intexspa_remove() {
    // Actions à effectuer lors de la suppression  
    log::add('intexspa', 'info', 'Suppression du plugin Intex Spa');
}
?>