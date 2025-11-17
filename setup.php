<?php

// O autoload do GLPI carregará as classes em src/ automaticamente
// desde que usem o namespace GlpiPlugin\Equipmentexit

define('PLUGIN_EQUIPMENTEXIT_VERSION', '3.8.1');

function plugin_init_equipmentexit() {
    global $PLUGIN_HOOKS;

    $plugin_name = 'equipmentexit';

    $PLUGIN_HOOKS['csrf_compliant'][$plugin_name] = true;

    // 1. Menu do Solicitante (Helpdesk)
    // Aponta para a classe GlpiPlugin\Equipmentexit\Menu
    $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['knowbase'] = 'GlpiPlugin\Equipmentexit\Menu';

    // 2. Menus do Aprovador (Plugins)
    $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['plugins']['approvals'] = 'GlpiPlugin\Equipmentexit\MenuApprovals';
    $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['plugins']['requests']  = 'GlpiPlugin\Equipmentexit\MenuRequests';

    // 3. Menu de Configuração
    if (Session::haveRight('config', UPDATE)) {
         $PLUGIN_HOOKS['menu_toadd'][$plugin_name]['setup'] = 'GlpiPlugin\Equipmentexit\ConfigMenu';
    }

    $PLUGIN_HOOKS['add_css'][$plugin_name] = 'css/equipmentexit.css';
}

function plugin_version_equipmentexit() {
    return [
        'name'           => 'Saída de Equipamentos',
        'version'        => PLUGIN_EQUIPMENTEXIT_VERSION,
        'author'         => 'Kawan Costa',
        'license'        => 'GPLv3',
        'minGlpiVersion' => '10.0.0' // A verificação real é feita pelo plugin.xml agora
    ];
}

function plugin_equipmentexit_check_prerequisites() {
    // A verificação de versão agora é feita pelo plugin.xml
    return true;
}

function plugin_equipmentexit_check_config($verbose = false) {
    return true;
}