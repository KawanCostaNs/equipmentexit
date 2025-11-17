<?php

namespace GlpiPlugin\Equipmentexit;

class ConfigMenu {

    static function getMenuContent() {
        $plugin_name = 'equipmentexit';
        $links = [];

        // O link aponta para a página de configuração
        $links['config'] = [
            'title' => __('Saída de Equipamentos', 'equipmentexit'),
            'page'  => '/plugins/equipmentexit/front/config.php',
            'icon'  => 'fas fa-sign-out-alt'
        ];

        return [
            // Este título é necessário para o GLPI montar o menu
            'title'       => __('Saída de Equipamentos', 'equipmentexit'),
            'page'        => '/plugins/equipmentexit/front/config.php',
            'links'       => $links,
            'icon'        => 'fas fa-sign-out-alt',
            'SUB_MENU'    => 'config' // Isso amarra o menu dentro de uma categoria existente
        ];
    }
    
    static function getMenuName($nb = 0) {
         return __('Saída de Equipamentos', 'equipmentexit');
    }
}